package system

import (
	"context"
	"os"
	"path/filepath"
	"strings"
	"testing"
)

func fixture(t *testing.T) *Reader {
	t.Helper()
	dir := t.TempDir()
	write := func(rel, content string) {
		path := filepath.Join(dir, rel)
		if err := os.MkdirAll(filepath.Dir(path), 0o755); err != nil {
			t.Fatal(err)
		}
		if err := os.WriteFile(path, []byte(content), 0o644); err != nil {
			t.Fatal(err)
		}
	}
	write("proc/uptime", "93784.12 12345.67\n")
	write("proc/loadavg", "0.52 0.40 0.31 1/234 5678\n")
	write("proc/meminfo", "MemTotal:        2048000 kB\nMemFree:          100000 kB\nMemAvailable:    1024000 kB\n")
	write("proc/sys/kernel/osrelease", "6.8.0-test\n")
	write("os-release", "NAME=\"Debian\"\nPRETTY_NAME=\"Debian GNU/Linux 13 (trixie)\"\n")
	write("units/php8.4-fpm.service", "")
	write("units/php-fpm.service", "")
	write("units/phpmyadmin.service", "")

	return &Reader{
		ProcDir:   filepath.Join(dir, "proc"),
		OSRelease: filepath.Join(dir, "os-release"),
		DataDir:   dir,
		UnitDirs:  []string{filepath.Join(dir, "units")},
		Run: func(_ context.Context, name string, args ...string) ([]byte, error) {
			if name != "systemctl" || len(args) != 2 || args[0] != "is-active" {
				t.Errorf("unexpected command %s %v", name, args)
			}
			switch args[1] {
			case "nginx", "docker", "php8.4-fpm":
				return []byte("active\n"), nil
			case "commypanel-queue@2":
				return []byte("failed\n"), os.ErrInvalid
			}
			return []byte("inactive\n"), os.ErrInvalid
		},
	}
}

func TestInfoReadsHostFacts(t *testing.T) {
	info := fixture(t).Info(context.Background())

	if info.OS != "Debian GNU/Linux 13 (trixie)" || info.Kernel != "6.8.0-test" {
		t.Errorf("os/kernel = %q / %q", info.OS, info.Kernel)
	}
	if info.UptimeSeconds != 93784 {
		t.Errorf("uptime = %d", info.UptimeSeconds)
	}
	if info.Load != [3]float64{0.52, 0.40, 0.31} {
		t.Errorf("load = %v", info.Load)
	}
	if info.MemTotalBytes != 2048000*1024 || info.MemAvailBytes != 1024000*1024 {
		t.Errorf("memory = %d / %d", info.MemTotalBytes, info.MemAvailBytes)
	}
	if info.DiskTotal == 0 || info.DiskFree == 0 || info.DiskFree > info.DiskTotal {
		t.Errorf("disk = %d free of %d", info.DiskFree, info.DiskTotal)
	}
}

func TestServicesAreAnAllowListPlusPhpFpm(t *testing.T) {
	info := fixture(t).Info(context.Background())

	got := map[string]string{}
	for _, s := range info.Services {
		got[s.Name] = s.State
	}
	for name, want := range map[string]string{
		"nginx": "active", "docker": "active", "php8.4-fpm": "active",
		"commypanel-queue@2": "failed", "commypanel-agent": "inactive",
	} {
		if got[name] != want {
			t.Errorf("%s = %q, want %q", name, got[name], want)
		}
	}
	for name := range got {
		if strings.Contains(name, "phpmyadmin") || name == "php-fpm" {
			t.Errorf("unexpected unit %q", name)
		}
	}
}

func TestMissingFilesLeaveZeroValues(t *testing.T) {
	r := &Reader{ProcDir: "/nonexistent", OSRelease: "/nonexistent", DataDir: t.TempDir()}
	info := r.Info(context.Background())

	if info.UptimeSeconds != 0 || info.MemTotalBytes != 0 || info.OS != "" {
		t.Errorf("expected zero values, got %+v", info)
	}
	for _, s := range info.Services {
		if s.State != "unknown" {
			t.Errorf("%s = %q without a runner, want unknown", s.Name, s.State)
		}
	}
}
