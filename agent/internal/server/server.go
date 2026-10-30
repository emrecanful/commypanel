// Package server exposes the agent's typed actions over HTTP on a Unix socket.
package server

import (
	"context"
	"encoding/json"
	"errors"
	"log/slog"
	"net"
	"net/http"
	"strconv"

	"commypanel/agent/internal/certs"
	"commypanel/agent/internal/docker"
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

// Docker is the part of the Docker manager the server needs.
type Docker interface {
	Run(ctx context.Context, s docker.ContainerSpec) error
	Stop(ctx context.Context, name string) error
	Remove(ctx context.Context, name string) error
	List(ctx context.Context) ([]docker.ContainerInfo, error)
	Logs(ctx context.Context, name string, tail int) (string, error)
	ValidateBuild(s docker.BuildSpec) error
	Build(ctx context.Context, s docker.BuildSpec, onLine func(string)) error
	PruneImages(ctx context.Context) (uint64, error)
	RemoveImage(ctx context.Context, tag string) error
}

// Certificates is the part of the certificate manager the server needs.
type Certificates interface {
	Issue(ctx context.Context, r certs.Request) (certs.Info, error)
	Info(name string) (*certs.Info, error)
	Remove(name string) error
}

// Authorizer decides whether the caller of a request may use the agent.
type Authorizer func(r *http.Request) bool

type Options struct {
	Nginx     Nginx
	Docker    Docker
	Certs     Certificates
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
	mux.HandleFunc("POST /v1/certificates/issue", s.issueCertificate)
	mux.HandleFunc("GET /v1/certificates/{name}", s.certificateInfo)
	mux.HandleFunc("DELETE /v1/certificates/{name}", s.removeCertificate)
	mux.HandleFunc("POST /v1/docker/containers", s.runContainer)
	mux.HandleFunc("GET /v1/docker/containers", s.listContainers)
	mux.HandleFunc("POST /v1/docker/containers/{name}/stop", s.stopContainer)
	mux.HandleFunc("DELETE /v1/docker/containers/{name}", s.removeContainer)
	mux.HandleFunc("GET /v1/docker/containers/{name}/logs", s.containerLogs)
	mux.HandleFunc("POST /v1/docker/images/build", s.buildImage)
	mux.HandleFunc("POST /v1/docker/images/prune", s.pruneImages)
	mux.HandleFunc("POST /v1/docker/images/remove", s.removeImage)

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

func (s *Server) issueCertificate(w http.ResponseWriter, r *http.Request) {
	var req certs.Request
	if !decode(w, r, &req) {
		return
	}
	info, err := s.opts.Certs.Issue(r.Context(), req)
	if err != nil {
		s.respond(w, err)
		return
	}
	writeJSON(w, http.StatusOK, info)
}

func (s *Server) certificateInfo(w http.ResponseWriter, r *http.Request) {
	info, err := s.opts.Certs.Info(r.PathValue("name"))
	if err != nil {
		s.respond(w, err)
		return
	}
	if info == nil {
		writeJSON(w, http.StatusNotFound, map[string]string{"error": "no certificate with that name"})
		return
	}
	writeJSON(w, http.StatusOK, info)
}

func (s *Server) removeCertificate(w http.ResponseWriter, r *http.Request) {
	s.respond(w, s.opts.Certs.Remove(r.PathValue("name")))
}

func (s *Server) runContainer(w http.ResponseWriter, r *http.Request) {
	var spec docker.ContainerSpec
	if !decode(w, r, &spec) {
		return
	}
	s.respond(w, s.opts.Docker.Run(r.Context(), spec))
}

func (s *Server) stopContainer(w http.ResponseWriter, r *http.Request) {
	s.respond(w, s.opts.Docker.Stop(r.Context(), r.PathValue("name")))
}

func (s *Server) removeContainer(w http.ResponseWriter, r *http.Request) {
	s.respond(w, s.opts.Docker.Remove(r.Context(), r.PathValue("name")))
}

func (s *Server) listContainers(w http.ResponseWriter, r *http.Request) {
	list, err := s.opts.Docker.List(r.Context())
	if err != nil {
		s.respond(w, err)
		return
	}
	writeJSON(w, http.StatusOK, map[string]any{"containers": list})
}

func (s *Server) containerLogs(w http.ResponseWriter, r *http.Request) {
	tail := 200
	if v := r.URL.Query().Get("tail"); v != "" {
		n, err := strconv.Atoi(v)
		if err != nil {
			writeJSON(w, http.StatusBadRequest, map[string]string{"error": "invalid tail"})
			return
		}
		tail = n
	}
	out, err := s.opts.Docker.Logs(r.Context(), r.PathValue("name"), tail)
	if err != nil {
		s.respond(w, err)
		return
	}
	writeJSON(w, http.StatusOK, map[string]string{"logs": out})
}

func (s *Server) removeImage(w http.ResponseWriter, r *http.Request) {
	var body struct {
		Tag string `json:"tag"`
	}
	if !decode(w, r, &body) {
		return
	}
	s.respond(w, s.opts.Docker.RemoveImage(r.Context(), body.Tag))
}

func (s *Server) pruneImages(w http.ResponseWriter, r *http.Request) {
	reclaimed, err := s.opts.Docker.PruneImages(r.Context())
	if err != nil {
		s.respond(w, err)
		return
	}
	writeJSON(w, http.StatusOK, map[string]any{"ok": true, "space_reclaimed": reclaimed})
}

// buildImage validates first (plain JSON error), then streams build output as
// newline-delimited JSON: {"log":"..."} lines and a final {"ok":true} or
// {"error":"..."}.
func (s *Server) buildImage(w http.ResponseWriter, r *http.Request) {
	var spec docker.BuildSpec
	if !decode(w, r, &spec) {
		return
	}
	if err := s.opts.Docker.ValidateBuild(spec); err != nil {
		s.respond(w, err)
		return
	}

	w.Header().Set("Content-Type", "application/x-ndjson")
	w.WriteHeader(http.StatusOK)
	enc := json.NewEncoder(w)
	flusher, _ := w.(http.Flusher)
	send := func(v any) {
		_ = enc.Encode(v)
		if flusher != nil {
			flusher.Flush()
		}
	}

	err := s.opts.Docker.Build(r.Context(), spec, func(line string) { send(map[string]string{"log": line}) })
	if err != nil {
		slog.Error("build failed", "tag", spec.Tag, "err", err)
		send(map[string]string{"error": err.Error()})
		return
	}
	send(map[string]bool{"ok": true})
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
	case errors.Is(err, docker.ErrUnavailable):
		writeJSON(w, http.StatusServiceUnavailable, map[string]string{"error": "docker is not available"})
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
