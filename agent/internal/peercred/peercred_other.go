//go:build !linux

package peercred

import (
	"errors"
	"net"
)

// Get is only implemented on Linux, the only platform the agent runs on in
// production. On other systems the agent works in dry-run mode only.
func Get(net.Conn) (Cred, error) {
	return Cred{}, errors.New("peer credentials are only supported on linux")
}
