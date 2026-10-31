package system

import (
	"bufio"
	"context"
	"os"
	"os/exec"
	"path/filepath"
	"regexp"
	"runtime"
	"strconv"
	"strings"
	"time"
)

// Info is what the panel's Server page shows about the host.
type Info struct {
	Hostname      string     `json:"hostname"`
	OS            string     `json:"os"`
	Kernel        string     `json:"kernel"`
	Arch          string     `json:"arch"`
	CPUs          int        `json:"cpus"`
	UptimeSeconds uint64     `json:"uptime_seconds"`
	Load          [3]float64 `json:"load"`
	MemTotalBytes uint64     `json:"mem_total_bytes"`
	MemAvailBytes uint64     `json:"mem_available_bytes"`
	DiskTotal     uint64     `json:"disk_total_bytes"`
	DiskFree      uint64     `json:"disk_free_bytes"`
	Services      []Service  `json:"services"`
}

// Service is the state of one systemd unit the panel depends on.
type Service struct {
	Name  string `json:"name"`
	State string `json:"state"` // active, inactive, failed, activating, unknown
}

// Runner runs a host command; tests replace it so no real systemctl is needed.
type Runner func(ctx context.Context, name string, args ...string) ([]byte, error)

// Reader gathers host facts. The directories are fields so tests can point
// them at fixtures; the zero value (via NewReader) reads the real host.
type Reader struct {
	ProcDir   string
	OSRelease string
	DataDir   string
	UnitDirs  []string
	Run       Runner
}

func NewReader(dataDir string) *Reader {
	return &Reader{
		ProcDir:   "/proc",
		OSRelease: "/etc/os-release",
		DataDir:   dataDir,
		UnitDirs:  []string{"/usr/lib/systemd/system", "/lib/systemd/system", "/etc/systemd/system"},
		Run: func(ctx context.Context, name string, args ...string) ([]byte, error) {
			return exec.CommandContext(ctx, name, args...).Output()
		},
	}
}

// baseUnits are always reported; php-fpm units are discovered by version.
var baseUnits = []string{
	"nginx", "docker", "commypanel-agent", "commypanel-queue@1", "commypanel-queue@2", "commypanel-scheduler",
}

var phpUnitRe = regexp.MustCompile(`^php[0-9]+\.[0-9]+-fpm\.service$`)

// Info collects the facts. Anything that cannot be read (for example /proc on
// macOS during development) is left at its zero value.
func (r *Reader) Info(ctx context.Context) Info {
	host, _ := os.Hostname()
	info := Info{
		Hostname: host,
		OS:       r.osName(),
		Kernel:   strings.TrimSpace(readFile(filepath.Join(r.ProcDir, "sys/kernel/osrelease"))),
		Arch:     runtime.GOARCH,
		CPUs:     runtime.NumCPU(),
	}
	info.UptimeSeconds = r.uptime()
	info.Load = r.load()
	info.MemTotalBytes, info.MemAvailBytes = r.memory()
	info.DiskTotal, info.DiskFree = diskUsage(r.DataDir)
	info.Services = r.services(ctx)

	return info
}

func (r *Reader) osName() string {
	f, err := os.Open(r.OSRelease)
	if err != nil {
		return ""
	}
	defer f.Close()

	sc := bufio.NewScanner(f)
	for sc.Scan() {
		if v, ok := strings.CutPrefix(sc.Text(), "PRETTY_NAME="); ok {
			return strings.Trim(v, `"`)
		}
	}
	return ""
}

func (r *Reader) uptime() uint64 {
	fields := strings.Fields(readFile(filepath.Join(r.ProcDir, "uptime")))
	if len(fields) == 0 {
		return 0
	}
	v, err := strconv.ParseFloat(fields[0], 64)
	if err != nil || v < 0 {
		return 0
	}
	return uint64(v)
}

func (r *Reader) load() (out [3]float64) {
	fields := strings.Fields(readFile(filepath.Join(r.ProcDir, "loadavg")))
	for i := 0; i < 3 && i < len(fields); i++ {
		out[i], _ = strconv.ParseFloat(fields[i], 64)
	}
	return out
}

func (r *Reader) memory() (total, available uint64) {
	for _, line := range strings.Split(readFile(filepath.Join(r.ProcDir, "meminfo")), "\n") {
		key, rest, ok := strings.Cut(line, ":")
		if !ok {
			continue
		}
		fields := strings.Fields(rest)
		if len(fields) == 0 {
			continue
		}
		kb, err := strconv.ParseUint(fields[0], 10, 64)
		if err != nil {
			continue
		}
		switch key {
		case "MemTotal":
			total = kb * 1024
		case "MemAvailable":
			available = kb * 1024
		}
	}
	return total, available
}

// serviceNames returns the fixed units plus any installed php-fpm unit.
func (r *Reader) serviceNames() []string {
	names := append([]string{}, baseUnits...)
	seen := map[string]bool{}
	for _, dir := range r.UnitDirs {
		entries, err := os.ReadDir(dir)
		if err != nil {
			continue
		}
		for _, e := range entries {
			if phpUnitRe.MatchString(e.Name()) && !seen[e.Name()] {
				seen[e.Name()] = true
				names = append(names, strings.TrimSuffix(e.Name(), ".service"))
			}
		}
	}
	return names
}

func (r *Reader) services(ctx context.Context) []Service {
	ctx, cancel := context.WithTimeout(ctx, 10*time.Second)
	defer cancel()

	var out []Service
	for _, name := range r.serviceNames() {
		state := "unknown"
		if r.Run != nil {
			// is-active exits non-zero for anything but "active" but still prints the state.
			b, _ := r.Run(ctx, "systemctl", "is-active", name)
			if s := strings.TrimSpace(string(b)); s != "" {
				state = s
			}
		}
		out = append(out, Service{Name: name, State: state})
	}
	return out
}

func readFile(path string) string {
	b, err := os.ReadFile(path)
	if err != nil {
		return ""
	}
	return string(b)
}
