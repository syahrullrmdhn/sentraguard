//go:build windows

package services

import (
	"fmt"

	"golang.org/x/sys/windows/svc"
	"golang.org/x/sys/windows/svc/mgr"

	"github.com/syahrullrmdhn/sentraguard/agent/internal/api"
	"github.com/syahrullrmdhn/sentraguard/agent/internal/logging"
)

type windowsManager struct {
	log *logging.Logger
}

// NewManager returns the production Windows SCM-backed service manager.
func NewManager(log *logging.Logger) Manager {
	return &windowsManager{log: log}
}

func stateString(s svc.State) string {
	switch s {
	case svc.Running:
		return "Running"
	case svc.Stopped:
		return "Stopped"
	case svc.StartPending:
		return "StartPending"
	case svc.StopPending:
		return "StopPending"
	case svc.Paused:
		return "Paused"
	default:
		return "Unknown"
	}
}

func startupString(startType uint32) string {
	switch startType {
	case mgr.StartAutomatic:
		return "Automatic"
	case mgr.StartManual:
		return "Manual"
	case mgr.StartDisabled:
		return "Disabled"
	default:
		return "Unknown"
	}
}

func (m *windowsManager) open(name string) (*mgr.Mgr, *mgr.Service, error) {
	manager, err := mgr.Connect()
	if err != nil {
		return nil, nil, fmt.Errorf("connect SCM: %w", err)
	}
	s, err := manager.OpenService(name)
	if err != nil {
		manager.Disconnect()
		return nil, nil, fmt.Errorf("open service %s: %w", name, err)
	}
	return manager, s, nil
}

func (m *windowsManager) Status(name string) (string, error) {
	manager, s, err := m.open(name)
	if err != nil {
		return "", err
	}
	defer manager.Disconnect()
	defer s.Close()

	status, err := s.Query()
	if err != nil {
		return "", fmt.Errorf("query %s: %w", name, err)
	}
	return stateString(status.State), nil
}

func (m *windowsManager) Info(name string) (api.ServiceInfo, error) {
	manager, s, err := m.open(name)
	if err != nil {
		return api.ServiceInfo{}, err
	}
	defer manager.Disconnect()
	defer s.Close()

	status, err := s.Query()
	if err != nil {
		return api.ServiceInfo{}, fmt.Errorf("query %s: %w", name, err)
	}
	cfg, err := s.Config()
	if err != nil {
		return api.ServiceInfo{}, fmt.Errorf("config %s: %w", name, err)
	}

	return api.ServiceInfo{
		ServiceName: name,
		DisplayName: cfg.DisplayName,
		Status:      stateString(status.State),
		StartupType: startupString(cfg.StartType),
	}, nil
}

func (m *windowsManager) List(names []string) []api.ServiceInfo {
	out := make([]api.ServiceInfo, 0, len(names))
	for _, n := range names {
		info, err := m.Info(n)
		if err != nil {
			continue // skip services not present on this host
		}
		out = append(out, info)
	}
	return out
}

// ListAll enumerates every service registered on the host so the dashboard can
// present the full list for the operator to choose from.
func (m *windowsManager) ListAll() []api.ServiceInfo {
	manager, err := mgr.Connect()
	if err != nil {
		m.log.Error("service ListAll: SCM connect failed: %v", err)
		return nil
	}
	defer manager.Disconnect()

	names, err := manager.ListServices()
	if err != nil {
		m.log.Error("service ListAll: enumerate services failed: %v", err)
		return nil
	}

	m.log.Debug("service ListAll: enumerating %d services", len(names))
	out := make([]api.ServiceInfo, 0, len(names))
	for _, n := range names {
		info, err := m.Info(n)
		if err != nil {
			continue // service may have been removed mid-enumeration
		}
		out = append(out, info)
	}
	m.log.Info("service ListAll: collected %d services", len(out))
	return out
}
