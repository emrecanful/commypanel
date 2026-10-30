// Package validate holds the allow-list checks every agent action runs on its
// inputs. The agent never trusts the caller, even though the caller is the panel.
package validate

import (
	"fmt"
	"regexp"
	"strings"
)

// InputError marks a problem with caller-supplied input (HTTP 400), as opposed
// to a failure of the host operation itself.
type InputError struct{ Msg string }

func (e *InputError) Error() string { return e.Msg }

func invalid(format string, args ...any) error {
	return &InputError{Msg: fmt.Sprintf(format, args...)}
}

var (
	nameRe  = regexp.MustCompile(`^[a-z0-9][a-z0-9-]{0,61}[a-z0-9]$`)
	labelRe = regexp.MustCompile(`^[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?$`)
)

// Name checks a resource name (site, container, certificate).
func Name(s string) error {
	if !nameRe.MatchString(s) {
		return invalid("invalid name %q", s)
	}
	return nil
}

// Domain checks a lowercase DNS hostname with at least two labels.
func Domain(s string) error {
	if len(s) == 0 || len(s) > 253 {
		return invalid("invalid domain length")
	}
	labels := strings.Split(s, ".")
	if len(labels) < 2 {
		return invalid("invalid domain %q", s)
	}
	for _, l := range labels {
		if !labelRe.MatchString(l) {
			return invalid("invalid domain %q", s)
		}
	}
	return nil
}

// Port checks an unprivileged TCP port.
func Port(p int) error {
	if p < 1024 || p > 65535 {
		return invalid("invalid port %d", p)
	}
	return nil
}
