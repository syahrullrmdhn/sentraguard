//go:build !windows

package services

import "github.com/syahrullrmdhn/sentraguard/agent/internal/api"

// devManager is a development stub for non-Windows platforms. It reports a
// stable fake state so the agent loops can be exercised without a real SCM.
type devManager struct{}

// NewManager returns the dev stub service manager.
func NewManager() Manager {
	return &devManager{}
}

func (m *devManager) Status(name string) (string, error) {
	return "Running", nil
}

func (m *devManager) Info(name string) (api.ServiceInfo, error) {
	return api.ServiceInfo{
		ServiceName: name,
		DisplayName: name + " (dev stub)",
		Status:      "Running",
		StartupType: "Automatic",
	}, nil
}

func (m *devManager) List(names []string) []api.ServiceInfo {
	out := make([]api.ServiceInfo, 0, len(names))
	for _, n := range names {
		info, _ := m.Info(n)
		out = append(out, info)
	}
	return out
}
