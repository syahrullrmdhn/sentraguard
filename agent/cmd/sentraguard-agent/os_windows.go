//go:build windows

package main

import (
	"fmt"
	"os/exec"
	"strings"
)

// osVersion returns the Windows version string via cmd `ver`.
func osVersion() string {
	out, err := exec.Command("cmd", "/c", "ver").Output()
	if err != nil {
		return ""
	}
	return strings.TrimSpace(string(out))
}

// cmdInstall registers then installs the agent as a Windows Service.
func cmdInstall(args []string) error {
	f := parseFlags(args)
	cfg, err := register(f["server"], f["token"])
	if err != nil {
		return err
	}
	fmt.Printf("✅ Registered (uid=%s). Installing Windows Service...\n", cfg.AgentUID)
	if err := installService(); err != nil {
		return fmt.Errorf("install service: %w", err)
	}
	fmt.Println("✅ SentraGuard Agent Service installed and started.")
	return nil
}

// cmdService handles uninstall/start/stop/restart/status via the SCM helper.
func cmdService(action string) error {
	return controlService(action)
}
