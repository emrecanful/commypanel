package server

import (
	"context"
	"errors"
	"fmt"
	"net/http"
	"net/http/httptest"
	"strconv"
	"strings"
	"testing"

	"commypanel/agent/internal/docker"
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

type fakeDocker struct {
	ran       []docker.ContainerSpec
	stopped   []string
	removed   []string
	err       error
	buildErr  error
	buildLogs []string
}

func (f *fakeDocker) Run(_ context.Context, s docker.ContainerSpec) error {
	if f.err != nil {
		return f.err
	}
	if s.Name == "" {
		return &validate.InputError{Msg: "invalid name"}
	}
	f.ran = append(f.ran, s)
	return nil
}
func (f *fakeDocker) Stop(_ context.Context, n string) error {
	f.stopped = append(f.stopped, n)
	return f.err
}
func (f *fakeDocker) Remove(_ context.Context, n string) error {
	f.removed = append(f.removed, n)
	return f.err
}
func (f *fakeDocker) List(context.Context) ([]docker.ContainerInfo, error) {
	return []docker.ContainerInfo{{Name: "demo-1", State: "running"}}, f.err
}
func (f *fakeDocker) Logs(_ context.Context, n string, tail int) (string, error) {
	return "tail=" + strconv.Itoa(tail), f.err
}
func (f *fakeDocker) ValidateBuild(s docker.BuildSpec) error {
	if !strings.HasPrefix(s.Tag, "commypanel/") {
		return &validate.InputError{Msg: "bad tag"}
	}
	return nil
}
func (f *fakeDocker) Build(_ context.Context, _ docker.BuildSpec, onLine func(string)) error {
	for _, l := range f.buildLogs {
		onLine(l)
	}
	return f.buildErr
}
func (f *fakeDocker) PruneImages(context.Context) (uint64, error) { return 99, f.err }

func newDockerServer(f *fakeDocker) *Server {
	return New(Options{Nginx: &fakeNginx{}, Docker: f, Authorize: AllowAll()})
}

func TestRunContainerRoute(t *testing.T) {
	f := &fakeDocker{}
	rec := do(t, newDockerServer(f), "POST", "/v1/docker/containers",
		`{"name":"demo-1","image":"commypanel/demo:r1","host_port":20001,"container_port":8080,"env":{"A":"1"}}`)
	if rec.Code != 200 || len(f.ran) != 1 || f.ran[0].HostPort != 20001 || f.ran[0].Env["A"] != "1" {
		t.Errorf("code=%d ran=%v body=%s", rec.Code, f.ran, rec.Body)
	}
}

func TestContainerRoutesRejectUnknownFields(t *testing.T) {
	// A caller must not be able to smuggle in settings like privileged or binds.
	for _, field := range []string{`"privileged":true`, `"binds":["/:/host"]`, `"cap_add":["SYS_ADMIN"]`, `"network_mode":"host"`} {
		f := &fakeDocker{}
		rec := do(t, newDockerServer(f), "POST", "/v1/docker/containers",
			`{"name":"demo-1","image":"commypanel/demo:r1",`+field+`}`)
		if rec.Code != http.StatusBadRequest || len(f.ran) != 0 {
			t.Errorf("%s: code=%d ran=%v", field, rec.Code, f.ran)
		}
	}
}

func TestDockerUnavailableIs503(t *testing.T) {
	f := &fakeDocker{err: fmt.Errorf("%w: dial", docker.ErrUnavailable)}
	rec := do(t, newDockerServer(f), "POST", "/v1/docker/containers/demo-1/stop", "")
	if rec.Code != http.StatusServiceUnavailable {
		t.Errorf("code = %d, want 503", rec.Code)
	}
}

func TestStopRemoveListLogsPrune(t *testing.T) {
	f := &fakeDocker{}
	s := newDockerServer(f)
	if rec := do(t, s, "POST", "/v1/docker/containers/demo-1/stop", ""); rec.Code != 200 || f.stopped[0] != "demo-1" {
		t.Errorf("stop: %d %v", rec.Code, f.stopped)
	}
	if rec := do(t, s, "DELETE", "/v1/docker/containers/demo-1", ""); rec.Code != 200 || f.removed[0] != "demo-1" {
		t.Errorf("remove: %d %v", rec.Code, f.removed)
	}
	if rec := do(t, s, "GET", "/v1/docker/containers", ""); !strings.Contains(rec.Body.String(), `"demo-1"`) {
		t.Errorf("list: %s", rec.Body)
	}
	if rec := do(t, s, "GET", "/v1/docker/containers/demo-1/logs?tail=50", ""); !strings.Contains(rec.Body.String(), "tail=50") {
		t.Errorf("logs: %s", rec.Body)
	}
	if rec := do(t, s, "GET", "/v1/docker/containers/demo-1/logs?tail=abc", ""); rec.Code != 400 {
		t.Errorf("bad tail: %d", rec.Code)
	}
	if rec := do(t, s, "POST", "/v1/docker/images/prune", ""); !strings.Contains(rec.Body.String(), `"space_reclaimed":99`) {
		t.Errorf("prune: %s", rec.Body)
	}
}

func TestBuildStreamsNDJSON(t *testing.T) {
	f := &fakeDocker{buildLogs: []string{"Step 1", "Step 2"}}
	rec := do(t, newDockerServer(f), "POST", "/v1/docker/images/build", `{"tag":"commypanel/demo:r1","context_path":"/x"}`)
	if rec.Code != 200 || rec.Header().Get("Content-Type") != "application/x-ndjson" {
		t.Fatalf("code=%d type=%s", rec.Code, rec.Header().Get("Content-Type"))
	}
	lines := strings.Split(strings.TrimSpace(rec.Body.String()), "\n")
	want := []string{`{"log":"Step 1"}`, `{"log":"Step 2"}`, `{"ok":true}`}
	if strings.Join(lines, "|") != strings.Join(want, "|") {
		t.Errorf("lines = %v", lines)
	}
}

func TestBuildFailureAndValidation(t *testing.T) {
	f := &fakeDocker{buildErr: errors.New("build failed: boom")}
	rec := do(t, newDockerServer(f), "POST", "/v1/docker/images/build", `{"tag":"commypanel/demo:r1","context_path":"/x"}`)
	if !strings.Contains(rec.Body.String(), `{"error":"build failed: boom"}`) {
		t.Errorf("body = %s", rec.Body)
	}
	rec = do(t, newDockerServer(&fakeDocker{}), "POST", "/v1/docker/images/build", `{"tag":"library/alpine","context_path":"/x"}`)
	if rec.Code != http.StatusBadRequest {
		t.Errorf("invalid build: code = %d, want 400", rec.Code)
	}
}
