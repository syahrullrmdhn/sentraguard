//go:build !windows

package firewall

import "fmt"

// Manager is a stub for non-Windows platforms
type Manager struct{}

func NewManager() *Manager {
	return &Manager{}
}

func (m *Manager) AddRule(ruleName, direction, protocol, port, action string) error {
	return fmt.Errorf("firewall management not supported on this platform")
}

func (m *Manager) EnableRule(ruleName string) error {
	return fmt.Errorf("firewall management not supported on this platform")
}

func (m *Manager) DisableRule(ruleName string) error {
	return fmt.Errorf("firewall management not supported on this platform")
}

func (m *Manager) DeleteRule(ruleName string) error {
	return fmt.Errorf("firewall management not supported on this platform")
}
