// Package peercred reads the identity of the process on the other end of a
// Unix socket connection, so the agent can refuse unknown callers.
package peercred

// Cred identifies the connecting process.
type Cred struct {
	UID uint32
	GID uint32
	PID int32
}
