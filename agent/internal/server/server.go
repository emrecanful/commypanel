// Package server exposes the agent's typed actions over HTTP on a Unix socket.
package server

import (
	"context"
	"encoding/json"
	"errors"
	"log/slog"
	"net"
	"net/http"

	"commypanel/agent/internal/nginx"
	"commypanel/agent/internal/peercred"
	"commypanel/agent/internal/system"
	"commypanel/agent/internal/validate"
)

const maxBody = 64 << 10

// Nginx is the part of the nginx manager the server needs.
type Nginx interface {
	Apply(ctx context.Context, s nginx.Site) error
	Remove(ctx context.Context, name string) error
}

// Authorizer decides whether the caller of a request may use the agent.
type Authorizer func(r *http.Request) bool

type Options struct {
	Nginx     Nginx
	Authorize Authorizer
	Version   string
	DryRun    bool
	System    func() system.Status
}

type Server struct{ opts Options }

func New(opts Options) *Server { return &Server{opts: opts} }

// Handler returns the HTTP handler with authorization applied to every route.
func (s *Server) Handler() http.Handler {
	mux := http.NewServeMux()
	mux.HandleFunc("GET /v1/status", s.status)
	mux.HandleFunc("PUT /v1/nginx/sites", s.applySite)
	mux.HandleFunc("DELETE /v1/nginx/sites/{name}", s.removeSite)

	return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		if s.opts.Authorize == nil || !s.opts.Authorize(r) {
			writeJSON(w, http.StatusForbidden, map[string]string{"error": "forbidden"})
			return
		}
		mux.ServeHTTP(w, r)
	})
}

type credKey struct{}

// ConnContext stores the peer credentials of each new connection so the
// authorizer can read them from the request context.
func ConnContext(ctx context.Context, c net.Conn) context.Context {
	cred, err := peercred.Get(c)
	if err != nil {
		return ctx
	}
	return context.WithValue(ctx, credKey{}, cred)
}

// AllowUIDs authorizes callers whose peer UID is in the allow-list.
func AllowUIDs(uids ...uint32) Authorizer {
	allowed := make(map[uint32]bool, len(uids))
	for _, u := range uids {
		allowed[u] = true
	}
	return func(r *http.Request) bool {
		cred, ok := r.Context().Value(credKey{}).(peercred.Cred)
		return ok && allowed[cred.UID]
	}
}

// AllowAll is only for dry-run development on platforms without peer credentials.
func AllowAll() Authorizer { return func(*http.Request) bool { return true } }

func (s *Server) status(w http.ResponseWriter, _ *http.Request) {
	var sys system.Status
	if s.opts.System != nil {
		sys = s.opts.System()
	}
	writeJSON(w, http.StatusOK, map[string]any{
		"version":          s.opts.Version,
		"dry_run":          s.opts.DryRun,
		"nginx_available":  sys.NginxAvailable,
		"docker_available": sys.DockerAvailable,
		"disk_free_bytes":  sys.DiskFreeBytes,
	})
}

func (s *Server) applySite(w http.ResponseWriter, r *http.Request) {
	var site nginx.Site
	if !decode(w, r, &site) {
		return
	}
	s.respond(w, s.opts.Nginx.Apply(r.Context(), site))
}

func (s *Server) removeSite(w http.ResponseWriter, r *http.Request) {
	s.respond(w, s.opts.Nginx.Remove(r.Context(), r.PathValue("name")))
}

func decode(w http.ResponseWriter, r *http.Request, v any) bool {
	dec := json.NewDecoder(http.MaxBytesReader(w, r.Body, maxBody))
	dec.DisallowUnknownFields()
	if err := dec.Decode(v); err != nil {
		writeJSON(w, http.StatusBadRequest, map[string]string{"error": "invalid request body"})
		return false
	}
	return true
}

func (s *Server) respond(w http.ResponseWriter, err error) {
	var input *validate.InputError
	switch {
	case err == nil:
		writeJSON(w, http.StatusOK, map[string]bool{"ok": true})
	case errors.As(err, &input):
		writeJSON(w, http.StatusBadRequest, map[string]string{"error": input.Msg})
	default:
		slog.Error("action failed", "err", err)
		writeJSON(w, http.StatusInternalServerError, map[string]string{"error": err.Error()})
	}
}

func writeJSON(w http.ResponseWriter, status int, v any) {
	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(status)
	_ = json.NewEncoder(w).Encode(v)
}
