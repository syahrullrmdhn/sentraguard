//go:build !windows

package main

import (
	"fmt"
	"os/exec"
	"strings"
)

// osVersion returns the kernel/OS version via `uname -r` (dev fallback).
func osVersion() string {
	out, err := exec.Command("uname", "-r").Output()
	if err != nil {
		return ""
	}
	return strings.TrimSpace(string(out))
}

// cmdInstall on non-Windows just registers (no SCM available in dev).
func cmdInstall(args []string) error {
	f := parseFlags(args)
	cfg, err := register(f["server"], f["token"])
	if err != nil {
		return err
	}
	fmt.Printf("✅ Registered (uid=%s). Service install is Windows-only; use 'run' in dev.\n", cfg.AgentUID)
	return nil
}

// cmdService is a no-op on non-Windows dev platforms.
func cmdService(action string) error {
	return fmt.Errorf("service control (%s) is only available on Windows", action)
}

// maybeRunAsService is a no-op on non-Windows platforms.
func maybeRunAsService() {}
