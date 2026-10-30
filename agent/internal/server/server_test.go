package server

import (
	"context"
	"errors"
	"net/http"
	"net/http/httptest"
	"strings"
	"testing"

	"commypanel/agent/internal/nginx"
	"commypanel/agent/internal/system"
	"commypanel/agent/internal/validate"
)

type fakeNginx struct {
	applied []nginx.Site
	removed []string
	err     error
}

func (f *fakeNginx) Apply(_ context.Context, s nginx.Site) error {
	if err := s.Validate(); err != nil {
		return err
	}
	f.applied = append(f.applied, s)
	return f.err
}

func (f *fakeNginx) Remove(_ context.Context, name string) error {
	if err := validate.Name(name); err != nil {
		return err
	}
	f.removed = append(f.removed, name)
	return f.err
}

func do(t *testing.T, s *Server, method, path, body string) *httptest.ResponseRecorder {
	t.Helper()
	req := httptest.NewRequest(method, path, strings.NewReader(body))
	rec := httptest.NewRecorder()
	s.Handler().ServeHTTP(rec, req)
	return rec
}

func newServer(f *fakeNginx) *Server {
	return New(Options{Nginx: f, Authorize: AllowAll(), Version: "test"})
}

const validSite = `{"name":"demo","domains":["demo.example.com"],"upstream_port":20001}`

func TestRefusesWithoutAuthorizer(t *testing.T) {
	s := New(Options{Nginx: &fakeNginx{}})
	if rec := do(t, s, "GET", "/v1/status", ""); rec.Code != http.StatusForbidden {
		t.Errorf("code = %d, want 403", rec.Code)
	}
}

func TestRefusesUnknownCaller(t *testing.T) {
	// No peer credentials in the request context: AllowUIDs must deny.
	s := New(Options{Nginx: &fakeNginx{}, Authorize: AllowUIDs(0, 1000)})
	if rec := do(t, s, "PUT", "/v1/nginx/sites", validSite); rec.Code != http.StatusForbidden {
		t.Errorf("code = %d, want 403", rec.Code)
	}
}

func TestStatus(t *testing.T) {
	s := New(Options{Nginx: &fakeNginx{}, Authorize: AllowAll(), Version: "test",
		System: func() system.Status { return system.Status{NginxAvailable: true, DiskFreeBytes: 42} }})
	rec := do(t, s, "GET", "/v1/status", "")
	body := rec.Body.String()
	if rec.Code != 200 || !strings.Contains(body, `"version":"test"`) ||
		!strings.Contains(body, `"nginx_available":true`) || !strings.Contains(body, `"disk_free_bytes":42`) {
		t.Errorf("code=%d body=%s", rec.Code, rec.Body)
	}
}

func TestApplySite(t *testing.T) {
	f := &fakeNginx{}
	rec := do(t, newServer(f), "PUT", "/v1/nginx/sites", validSite)
	if rec.Code != 200 || len(f.applied) != 1 || f.applied[0].UpstreamPort != 20001 {
		t.Errorf("code=%d applied=%v", rec.Code, f.applied)
	}
}

func TestInvalidInputIs400(t *testing.T) {
	for name, body := range map[string]string{
		"bad domain":    `{"name":"demo","domains":["a.com;"],"upstream_port":20001}`,
		"bad name":      `{"name":"../x","domains":["a.example.com"],"upstream_port":20001}`,
		"unknown field": `{"name":"demo","domains":["a.example.com"],"upstream_port":20001,"raw_config":"x"}`,
		"not json":      `nope`,
	} {
		if rec := do(t, newServer(&fakeNginx{}), "PUT", "/v1/nginx/sites", body); rec.Code != http.StatusBadRequest {
			t.Errorf("%s: code = %d, want 400", name, rec.Code)
		}
	}
}

func TestOversizedBodyRejected(t *testing.T) {
	body := `{"name":"demo","domains":["a.example.com"],"upstream_port":20001,"certificate":"` + strings.Repeat("a", 70<<10) + `"}`
	if rec := do(t, newServer(&fakeNginx{}), "PUT", "/v1/nginx/sites", body); rec.Code != http.StatusBadRequest {
		t.Errorf("code = %d, want 400", rec.Code)
	}
}

func TestHostFailureIs500(t *testing.T) {
	f := &fakeNginx{err: errors.New("nginx -t failed: boom")}
	rec := do(t, newServer(f), "PUT", "/v1/nginx/sites", validSite)
	if rec.Code != 500 || !strings.Contains(rec.Body.String(), "boom") {
		t.Errorf("code=%d body=%s", rec.Code, rec.Body)
	}
}

func TestRemoveSite(t *testing.T) {
	f := &fakeNginx{}
	if rec := do(t, newServer(f), "DELETE", "/v1/nginx/sites/demo", ""); rec.Code != 200 || f.removed[0] != "demo" {
		t.Errorf("code=%d removed=%v", rec.Code, f.removed)
	}
	if rec := do(t, newServer(f), "DELETE", "/v1/nginx/sites/BAD", ""); rec.Code != 400 {
		t.Errorf("code = %d, want 400", rec.Code)
	}
}
