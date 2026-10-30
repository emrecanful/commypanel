// Package nginx renders, validates and applies per-site nginx configs.
package nginx

import (
	"bytes"
	"context"
	"errors"
	"fmt"
	"log/slog"
	"os"
	"os/exec"
	"path/filepath"
	"strings"
	"text/template"

	"commypanel/agent/internal/validate"
)

// Site is the only input the panel may send for an nginx site. Raw nginx
// config is never accepted; the config is rendered from this validated data.
type Site struct {
	Name         string   `json:"name"`
	Domains      []string `json:"domains"`
	UpstreamPort int      `json:"upstream_port"`
	Certificate  string   `json:"certificate,omitempty"`
}

func (s Site) Validate() error {
	if err := validate.Name(s.Name); err != nil {
		return err
	}
	if len(s.Domains) == 0 || len(s.Domains) > 50 {
		return &validate.InputError{Msg: "a site needs between 1 and 50 domains"}
	}
	for _, d := range s.Domains {
		if err := validate.Domain(d); err != nil {
			return err
		}
	}
	if err := validate.Port(s.UpstreamPort); err != nil {
		return err
	}
	if s.Certificate != "" {
		return validate.Name(s.Certificate)
	}
	return nil
}

// Runner executes host commands. It exists so tests never run real nginx.
type Runner interface {
	Run(ctx context.Context, name string, args ...string) ([]byte, error)
}

type execRunner struct{}

func (execRunner) Run(ctx context.Context, name string, args ...string) ([]byte, error) {
	return exec.CommandContext(ctx, name, args...).CombinedOutput()
}

// Config locates the files the manager works with.
type Config struct {
	SitesDir string // directory included by the main nginx.conf
	DataDir  string // panel data dir (certs, ACME webroot)
	DryRun   bool   // write files but never run nginx
}

type Manager struct {
	cfg Config
	run Runner
}

func NewManager(cfg Config, run Runner) *Manager {
	if run == nil {
		run = execRunner{}
	}
	return &Manager{cfg: cfg, run: run}
}

var siteTmpl = template.Must(template.New("site").Parse(`# Managed by CommyPanel. Do not edit; changes are overwritten.
server {
    listen 80;
    listen [::]:80;
    server_name {{.ServerNames}};

    location /.well-known/acme-challenge/ {
        root {{.Webroot}};
    }
{{if .CertDir}}
    location / {
        return 301 https://$host$request_uri;
    }
}

server {
    listen 443 ssl;
    listen [::]:443 ssl;
    http2 on;
    server_name {{.ServerNames}};

    ssl_certificate {{.CertDir}}/fullchain.pem;
    ssl_certificate_key {{.CertDir}}/privkey.pem;
    ssl_protocols TLSv1.2 TLSv1.3;
{{end}}
    location / {
        proxy_pass http://127.0.0.1:{{.Port}};
        proxy_http_version 1.1;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
}
`))

var defaultTmpl = template.Must(template.New("default").Parse(`# Managed by CommyPanel. Do not edit; changes are overwritten.
# Catches port-80 requests for hosts no app serves, so Let's Encrypt can
# validate a domain before its app is live. Everything else gets a 404.
server {
    listen 80 default_server;
    listen [::]:80 default_server;
    server_name _;

    location /.well-known/acme-challenge/ {
        root {{.Webroot}};
    }

    location / {
        return 404;
    }
}
`))

// EnsureDefaultSite writes the catch-all port-80 site that serves ACME
// challenges. If nginx rejects it (for example because another default_server
// exists) the file is removed again and the error returned.
func (m *Manager) EnsureDefaultSite(ctx context.Context) error {
	var buf bytes.Buffer
	if err := defaultTmpl.Execute(&buf, struct{ Webroot string }{filepath.Join(m.cfg.DataDir, "acme-webroot")}); err != nil {
		return err
	}
	if err := os.MkdirAll(filepath.Join(m.cfg.DataDir, "acme-webroot"), 0o755); err != nil {
		return err
	}
	path := filepath.Join(m.cfg.SitesDir, "00-commypanel-default.conf")
	previous, readErr := os.ReadFile(path)
	if readErr == nil && bytes.Equal(previous, buf.Bytes()) {
		return nil
	}
	if err := writeAtomic(path, buf.Bytes()); err != nil {
		return err
	}
	if err := m.testAndReload(ctx); err != nil {
		m.restore(path, previous, readErr == nil)
		return err
	}
	return nil
}

// Render returns the nginx config for a validated site.
func (m *Manager) Render(s Site) (string, error) {
	if err := s.Validate(); err != nil {
		return "", err
	}
	data := struct {
		ServerNames, Webroot, CertDir string
		Port                          int
	}{
		ServerNames: strings.Join(s.Domains, " "),
		Webroot:     filepath.Join(m.cfg.DataDir, "acme-webroot"),
		Port:        s.UpstreamPort,
	}
	if s.Certificate != "" {
		data.CertDir = filepath.Join(m.cfg.DataDir, "certs", s.Certificate)
	}
	var buf bytes.Buffer
	if err := siteTmpl.Execute(&buf, data); err != nil {
		return "", err
	}
	return buf.String(), nil
}

func (m *Manager) path(name string) string {
	return filepath.Join(m.cfg.SitesDir, "commypanel-"+name+".conf")
}

// Apply writes the site, validates the whole nginx config and reloads
// gracefully. If validation fails the previous config is restored.
func (m *Manager) Apply(ctx context.Context, s Site) error {
	conf, err := m.Render(s)
	if err != nil {
		return err
	}
	path := m.path(s.Name)
	previous, readErr := os.ReadFile(path)
	hadPrevious := readErr == nil

	if err := writeAtomic(path, []byte(conf)); err != nil {
		return fmt.Errorf("write site: %w", err)
	}
	if err := m.testAndReload(ctx); err != nil {
		m.restore(path, previous, hadPrevious)
		return err
	}
	slog.Info("nginx site applied", "site", s.Name, "dry_run", m.cfg.DryRun)
	return nil
}

// Remove deletes a site config and reloads nginx.
func (m *Manager) Remove(ctx context.Context, name string) error {
	if err := validate.Name(name); err != nil {
		return err
	}
	path := m.path(name)
	previous, readErr := os.ReadFile(path)
	if errors.Is(readErr, os.ErrNotExist) {
		return nil
	}
	if err := os.Remove(path); err != nil {
		return fmt.Errorf("remove site: %w", err)
	}
	if err := m.testAndReload(ctx); err != nil {
		m.restore(path, previous, true)
		return err
	}
	slog.Info("nginx site removed", "site", name, "dry_run", m.cfg.DryRun)
	return nil
}

func (m *Manager) testAndReload(ctx context.Context) error {
	if m.cfg.DryRun {
		return nil
	}
	if out, err := m.run.Run(ctx, "nginx", "-t"); err != nil {
		return fmt.Errorf("nginx -t failed: %s", strings.TrimSpace(string(out)))
	}
	if out, err := m.run.Run(ctx, "nginx", "-s", "reload"); err != nil {
		return fmt.Errorf("nginx reload failed: %s", strings.TrimSpace(string(out)))
	}
	return nil
}

func (m *Manager) restore(path string, previous []byte, had bool) {
	if had {
		if err := writeAtomic(path, previous); err != nil {
			slog.Error("could not restore previous nginx config", "path", path, "err", err)
		}
		return
	}
	if err := os.Remove(path); err != nil && !errors.Is(err, os.ErrNotExist) {
		slog.Error("could not remove rejected nginx config", "path", path, "err", err)
	}
}

func writeAtomic(path string, data []byte) error {
	if err := os.MkdirAll(filepath.Dir(path), 0o755); err != nil {
		return err
	}
	tmp, err := os.CreateTemp(filepath.Dir(path), ".commypanel-*.tmp")
	if err != nil {
		return err
	}
	defer os.Remove(tmp.Name())
	if _, err := tmp.Write(data); err != nil {
		tmp.Close()
		return err
	}
	if err := tmp.Chmod(0o644); err != nil {
		tmp.Close()
		return err
	}
	if err := tmp.Close(); err != nil {
		return err
	}
	return os.Rename(tmp.Name(), path)
}
