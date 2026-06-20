package services

import "github.com/syahrullrmdhn/sentraguard/agent/internal/api"

// Manager queries Windows service state. Implementations are platform-specific.
type Manager interface {
	// Status returns the current status string (Running/Stopped/etc.) for a service.
	Status(name string) (string, error)
	// Info returns full service info (display name, status, startup type).
	Info(name string) (api.ServiceInfo, error)
	// List returns info for the provided service names, skipping any not found.
	List(names []string) []api.ServiceInfo
}
