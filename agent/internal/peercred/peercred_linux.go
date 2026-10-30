//go:build linux

package peercred

import (
	"errors"
	"net"
	"syscall"
)

// Get returns the credentials of the peer of a Unix socket connection.
func Get(conn net.Conn) (Cred, error) {
	uc, ok := conn.(*net.UnixConn)
	if !ok {
		return Cred{}, errors.New("not a unix connection")
	}
	raw, err := uc.SyscallConn()
	if err != nil {
		return Cred{}, err
	}
	var ucred *syscall.Ucred
	var sockErr error
	if err := raw.Control(func(fd uintptr) {
		ucred, sockErr = syscall.GetsockoptUcred(int(fd), syscall.SOL_SOCKET, syscall.SO_PEERCRED)
	}); err != nil {
		return Cred{}, err
	}
	if sockErr != nil {
		return Cred{}, sockErr
	}
	return Cred{UID: ucred.Uid, GID: ucred.Gid, PID: ucred.Pid}, nil
}
