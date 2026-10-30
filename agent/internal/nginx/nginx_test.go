package nginx

import (
	"context"
	"errors"
	"os"
	"path/filepath"
	"strings"
	"testing"

	"commypanel/agent/internal/validate"
)

type fakeRunner struct {
	calls   []string
	failOn  string // "test" or "reload"
	version string // output of `nginx -v`
}

func (f *fakeRunner) Run(_ context.Context, name string, args ...string) ([]byte, error) {
	call := name + " " + strings.Join(args, " ")
	f.calls = append(f.calls, call)
	if call == "nginx -v" {
		return []byte(f.version), nil
	}
	if f.failOn == "test" && strings.HasSuffix(call, "-t") {
		return []byte("bad config"), errors.New("exit 1")
	}
	if f.failOn == "reload" && strings.HasSuffix(call, "reload") {
		return []byte("reload failed"), errors.New("exit 1")
	}
	return nil, nil
}

func newManager(t *testing.T, run Runner, dry bool) (*Manager, string) {
	t.Helper()
	dir := t.TempDir()
	return NewManager(Config{SitesDir: filepath.Join(dir, "sites"), DataDir: filepath.Join(dir, "data"), DryRun: dry}, run), dir
}

var site = Site{Name: "demo", Domains: []string{"demo.example.com", "www.example.com"}, UpstreamPort: 20001}

func TestRenderHTTPOnly(t *testing.T) {
	m, _ := newManager(t, nil, true)
	conf, err := m.Render(site)
	if err != nil {
		t.Fatal(err)
	}
	for _, want := range []string{
		"server_name demo.example.com www.example.com;",
		"proxy_pass http://127.0.0.1:20001;",
		"location /.well-known/acme-challenge/",
	} {
		if !strings.Contains(conf, want) {
			t.Errorf("config missing %q:\n%s", want, conf)
		}
	}
	if strings.Contains(conf, "listen 443") || strings.Contains(conf, "return 301") {
		t.Errorf("http-only site must not have TLS or redirect:\n%s", conf)
	}
}

func TestRenderWithCertificate(t *testing.T) {
	m, dir := newManager(t, nil, true)
	s := site
	s.Certificate = "demo"
	conf, err := m.Render(s)
	if err != nil {
		t.Fatal(err)
	}
	for _, want := range []string{
		"listen 443 ssl;",
		"return 301 https://$host$request_uri;",
		"ssl_certificate " + filepath.Join(dir, "data", "certs", "demo") + "/fullchain.pem;",
		"ssl_certificate_key " + filepath.Join(dir, "data", "certs", "demo") + "/privkey.pem;",
	} {
		if !strings.Contains(conf, want) {
			t.Errorf("config missing %q:\n%s", want, conf)
		}
	}
}

func TestRejectsInjection(t *testing.T) {
	m, _ := newManager(t, nil, true)
	bad := []Site{
		{Name: "../evil", Domains: []string{"a.example.com"}, UpstreamPort: 20001},
		{Name: "ok", Domains: []string{"a.example.com; }\nserver { listen 1;"}, UpstreamPort: 20001},
		{Name: "ok", Domains: []string{"a.example.com\n"}, UpstreamPort: 20001},
		{Name: "ok", Domains: nil, UpstreamPort: 20001},
		{Name: "ok", Domains: []string{"a.example.com"}, UpstreamPort: 80},
		{Name: "ok", Domains: []string{"a.example.com"}, UpstreamPort: 20001, Certificate: "../../etc"},
	}
	for i, s := range bad {
		_, err := m.Render(s)
		var ie *validate.InputError
		if !errors.As(err, &ie) {
			t.Errorf("case %d: want *InputError, got %v", i, err)
		}
	}
}

func TestApplyRunsTestThenReload(t *testing.T) {
	run := &fakeRunner{}
	m, _ := newManager(t, run, false)
	if err := m.Apply(context.Background(), site); err != nil {
		t.Fatal(err)
	}
	if got := strings.Join(run.calls, "|"); got != "nginx -t|nginx -s reload" {
		t.Errorf("calls = %q", got)
	}
	if _, err := os.Stat(m.path("demo")); err != nil {
		t.Errorf("config not written: %v", err)
	}
}

func TestApplyRollsBackNewSiteWhenTestFails(t *testing.T) {
	run := &fakeRunner{failOn: "test"}
	m, _ := newManager(t, run, false)
	if err := m.Apply(context.Background(), site); err == nil {
		t.Fatal("want error")
	}
	if _, err := os.Stat(m.path("demo")); !os.IsNotExist(err) {
		t.Errorf("rejected config must be removed, stat err = %v", err)
	}
	for _, c := range run.calls {
		if strings.HasSuffix(c, "reload") {
			t.Error("must not reload after a failed test")
		}
	}
}

func TestApplyRestoresPreviousConfigWhenTestFails(t *testing.T) {
	m, _ := newManager(t, &fakeRunner{}, false)
	if err := m.Apply(context.Background(), site); err != nil {
		t.Fatal(err)
	}
	before, _ := os.ReadFile(m.path("demo"))

	m.run = &fakeRunner{failOn: "test"}
	changed := site
	changed.UpstreamPort = 20002
	if err := m.Apply(context.Background(), changed); err == nil {
		t.Fatal("want error")
	}
	after, _ := os.ReadFile(m.path("demo"))
	if string(before) != string(after) {
		t.Error("previous config was not restored")
	}
}

func TestDryRunWritesButNeverRuns(t *testing.T) {
	run := &fakeRunner{}
	m, _ := newManager(t, run, true)
	if err := m.Apply(context.Background(), site); err != nil {
		t.Fatal(err)
	}
	if len(run.calls) != 0 {
		t.Errorf("dry run executed %v", run.calls)
	}
	if _, err := os.Stat(m.path("demo")); err != nil {
		t.Errorf("dry run should still write the file: %v", err)
	}
}

func TestRemove(t *testing.T) {
	run := &fakeRunner{}
	m, _ := newManager(t, run, false)
	if err := m.Apply(context.Background(), site); err != nil {
		t.Fatal(err)
	}
	run.calls = nil
	if err := m.Remove(context.Background(), "demo"); err != nil {
		t.Fatal(err)
	}
	if _, err := os.Stat(m.path("demo")); !os.IsNotExist(err) {
		t.Error("config should be gone")
	}
	if got := strings.Join(run.calls, "|"); got != "nginx -t|nginx -s reload" {
		t.Errorf("calls = %q", got)
	}
	if err := m.Remove(context.Background(), "demo"); err != nil {
		t.Errorf("removing a missing site should succeed, got %v", err)
	}
	if err := m.Remove(context.Background(), "../x"); err == nil {
		t.Error("invalid name must be rejected")
	}
}

func TestEnsureDefaultSite(t *testing.T) {
	run := &fakeRunner{}
	m, _ := newManager(t, run, false)

	if err := m.EnsureDefaultSite(context.Background()); err != nil {
		t.Fatal(err)
	}
	conf, err := os.ReadFile(filepath.Join(m.cfg.SitesDir, "00-commypanel-default.conf"))
	if err != nil {
		t.Fatal(err)
	}
	for _, want := range []string{"listen 80 default_server;", "location /.well-known/acme-challenge/", "return 404;", "acme-webroot"} {
		if !strings.Contains(string(conf), want) {
			t.Errorf("default site missing %q:\n%s", want, conf)
		}
	}
	if got := strings.Join(run.calls, "|"); got != "nginx -t|nginx -s reload" {
		t.Errorf("calls = %q", got)
	}

	// Unchanged content means no second reload.
	run.calls = nil
	if err := m.EnsureDefaultSite(context.Background()); err != nil {
		t.Fatal(err)
	}
	if len(run.calls) != 0 {
		t.Errorf("idempotent call reloaded nginx: %v", run.calls)
	}
}

func TestEnsureDefaultSiteIsRemovedWhenNginxRejectsIt(t *testing.T) {
	m, _ := newManager(t, &fakeRunner{failOn: "test"}, false)

	if err := m.EnsureDefaultSite(context.Background()); err == nil {
		t.Fatal("want error")
	}
	if _, err := os.Stat(filepath.Join(m.cfg.SitesDir, "00-commypanel-default.conf")); !os.IsNotExist(err) {
		t.Error("a rejected default site must be removed")
	}
}

func TestEnsureDefaultSiteInDryRun(t *testing.T) {
	run := &fakeRunner{}
	m, _ := newManager(t, run, true)

	if err := m.EnsureDefaultSite(context.Background()); err != nil {
		t.Fatal(err)
	}
	if len(run.calls) != 0 {
		t.Errorf("dry run executed %v", run.calls)
	}
}

func TestHTTP2SyntaxFollowsTheNginxVersion(t *testing.T) {
	cases := map[string]struct{ modern bool }{
		"nginx version: nginx/1.24.0 (Ubuntu)": {false},
		"nginx version: nginx/1.25.0":          {false},
		"nginx version: nginx/1.25.1":          {true},
		"nginx version: nginx/1.26.3":          {true},
		"nginx version: nginx/1.30.0":          {true},
		"nginx version: nginx/2.0.0":           {true},
		"nginx version: nginx/1.9.15":          {false},
		"something unexpected":                 {false},
		"":                                     {false},
	}
	for output, want := range cases {
		m, _ := newManager(t, &fakeRunner{version: output}, false)
		s := site
		s.Certificate = "demo"
		conf, err := m.Render(s)
		if err != nil {
			t.Fatal(err)
		}
		hasDirective := strings.Contains(conf, "    http2 on;")
		hasListenFlag := strings.Contains(conf, "listen 443 ssl http2;") && strings.Contains(conf, "listen [::]:443 ssl http2;")
		if want.modern && (!hasDirective || hasListenFlag) {
			t.Errorf("%q: want `http2 on;` only:\n%s", output, conf)
		}
		if !want.modern && (hasDirective || !hasListenFlag) {
			t.Errorf("%q: want `ssl http2` listen flags only:\n%s", output, conf)
		}
	}
}

func TestHTTP2IsDetectedOnlyOnce(t *testing.T) {
	run := &fakeRunner{version: "nginx version: nginx/1.26.0"}
	m, _ := newManager(t, run, false)
	s := site
	s.Certificate = "demo"
	for i := 0; i < 3; i++ {
		if _, err := m.Render(s); err != nil {
			t.Fatal(err)
		}
	}
	count := 0
	for _, c := range run.calls {
		if c == "nginx -v" {
			count++
		}
	}
	if count != 1 {
		t.Errorf("nginx -v ran %d times, want 1", count)
	}
}

func TestDryRunNeverAsksNginxForItsVersion(t *testing.T) {
	run := &fakeRunner{}
	m, _ := newManager(t, run, true)
	s := site
	s.Certificate = "demo"
	conf, err := m.Render(s)
	if err != nil {
		t.Fatal(err)
	}
	if len(run.calls) != 0 || !strings.Contains(conf, "http2 on;") {
		t.Errorf("calls=%v conf=%s", run.calls, conf)
	}
}
