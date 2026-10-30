package validate

import (
	"errors"
	"strings"
	"testing"
)

func TestName(t *testing.T) {
	good := []string{"ab", "my-app", "app1", strings.Repeat("a", 63)}
	bad := []string{"", "a", "-ab", "ab-", "My-App", "a_b", "a b", "a;b", "../etc", "a/b", "a\nb", strings.Repeat("a", 64)}
	for _, s := range good {
		if err := Name(s); err != nil {
			t.Errorf("Name(%q) = %v, want nil", s, err)
		}
	}
	for _, s := range bad {
		if err := Name(s); err == nil {
			t.Errorf("Name(%q) = nil, want error", s)
		}
	}
}

func TestDomain(t *testing.T) {
	good := []string{"example.com", "a.b.example.co.uk", "my-app.example.com", "xn--bcher-kva.example"}
	bad := []string{"", "localhost", "example", ".com", "example..com", "-a.com", "a-.com", "Example.com",
		"exa mple.com", "example.com;", "example.com\nserver_name evil", "a.com/../", "*.example.com",
		strings.Repeat("a", 64) + ".com", strings.Repeat("a.", 130) + "com"}
	for _, s := range good {
		if err := Domain(s); err != nil {
			t.Errorf("Domain(%q) = %v, want nil", s, err)
		}
	}
	for _, s := range bad {
		if err := Domain(s); err == nil {
			t.Errorf("Domain(%q) = nil, want error", s)
		}
	}
}

func TestPort(t *testing.T) {
	for _, p := range []int{1024, 20001, 65535} {
		if err := Port(p); err != nil {
			t.Errorf("Port(%d) = %v", p, err)
		}
	}
	for _, p := range []int{-1, 0, 80, 1023, 65536} {
		if err := Port(p); err == nil {
			t.Errorf("Port(%d) = nil, want error", p)
		}
	}
}

func TestErrorsAreInputErrors(t *testing.T) {
	var ie *InputError
	if !errors.As(Name("BAD"), &ie) {
		t.Fatal("expected *InputError")
	}
}
