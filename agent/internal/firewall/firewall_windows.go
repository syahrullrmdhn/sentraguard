//go:build windows

package firewall

import (
	"fmt"
	"os/exec"
	"strings"
)

// Manager handles Windows Firewall operations via netsh
type Manager struct{}

func NewManager() *Manager {
	return &Manager{}
}

// AddRule creates a new firewall rule
func (m *Manager) AddRule(ruleName, direction, protocol, port, action string) error {
	dir := strings.Title(direction) // Inbound/Outbound
	act := strings.Title(action)    // Allow/Block
	proto := strings.ToUpper(protocol)
	
	args := []string{
		"advfirewall", "firewall", "add", "rule",
		fmt.Sprintf("name=%s", ruleName),
		fmt.Sprintf("dir=%s", dir),
		fmt.Sprintf("action=%s", act),
	}
	
	if proto != "ANY" {
		args = append(args, fmt.Sprintf("protocol=%s", proto))
	}
	
	if port != "" {
		args = append(args, fmt.Sprintf("localport=%s", port))
	}
	
	cmd := exec.Command("netsh", args...)
	output, err := cmd.CombinedOutput()
	if err != nil {
		return fmt.Errorf("netsh add rule failed: %w, output: %s", err, output)
	}
	return nil
}

// EnableRule enables an existing firewall rule
func (m *Manager) EnableRule(ruleName string) error {
	cmd := exec.Command("netsh", "advfirewall", "firewall", "set", "rule",
		fmt.Sprintf("name=%s", ruleName), "new", "enable=yes")
	output, err := cmd.CombinedOutput()
	if err != nil {
		return fmt.Errorf("netsh enable rule failed: %w, output: %s", err, output)
	}
	return nil
}

// DisableRule disables an existing firewall rule
func (m *Manager) DisableRule(ruleName string) error {
	cmd := exec.Command("netsh", "advfirewall", "firewall", "set", "rule",
		fmt.Sprintf("name=%s", ruleName), "new", "enable=no")
	output, err := cmd.CombinedOutput()
	if err != nil {
		return fmt.Errorf("netsh disable rule failed: %w, output: %s", err, output)
	}
	return nil
}

// DeleteRule removes a firewall rule
func (m *Manager) DeleteRule(ruleName string) error {
	cmd := exec.Command("netsh", "advfirewall", "firewall", "delete", "rule",
		fmt.Sprintf("name=%s", ruleName))
	output, err := cmd.CombinedOutput()
	if err != nil {
		return fmt.Errorf("netsh delete rule failed: %w, output: %s", err, output)
	}
	return nil
}
