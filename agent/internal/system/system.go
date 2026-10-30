// Package system reports basic host facts for the panel's status screen.
package system

import (
	"os"
	"os/exec"
	"syscall"
)

const dockerSocket = "/var/run/docker.sock"

// Status is what the panel needs to know about the host.
type Status struct {
	NginxAvailable  bool   `json:"nginx_available"`
	DockerAvailable bool   `json:"docker_available"`
	DiskFreeBytes   uint64 `json:"disk_free_bytes"`
}

// Collect gathers the status. dataDir is where free disk space is measured.
// Docker availability is a cheap socket check until the Docker actions land.
func Collect(dataDir string) Status {
	_, nginxErr := exec.LookPath("nginx")
	_, dockerErr := os.Stat(dockerSocket)

	return Status{
		NginxAvailable:  nginxErr == nil,
		DockerAvailable: dockerErr == nil,
		DiskFreeBytes:   diskFree(dataDir),
	}
}

func diskFree(dir string) uint64 {
	for _, path := range []string{dir, "/"} {
		var st syscall.Statfs_t
		if err := syscall.Statfs(path, &st); err == nil {
			return uint64(st.Bavail) * uint64(st.Bsize)
		}
	}
	return 0
}
