//go:build !windows

package services

import (
	"github.com/syahrullrmdhn/sentraguard/agent/internal/api"
	"github.com/syahrullrmdhn/sentraguard/agent/internal/logging"
)

// devManager is a development stub for non-Windows platforms. It reports a
// stable fake state so the agent loops can be exercised without a real SCM.
type devManager struct {
	log *logging.Logger
}

// NewManager returns the dev stub service manager.
func NewManager(log *logging.Logger) Manager {
	return &devManager{log: log}
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

// ListAll returns a couple of fake services so non-Windows dev builds can
// exercise the full-enumeration path.
func (m *devManager) ListAll() []api.ServiceInfo {
	return []api.ServiceInfo{
		{ServiceName: "dev-svc-1", DisplayName: "Dev Service 1 (stub)", Status: "Running", StartupType: "Automatic"},
		{ServiceName: "dev-svc-2", DisplayName: "Dev Service 2 (stub)", Status: "Stopped", StartupType: "Manual"},
	}
}
