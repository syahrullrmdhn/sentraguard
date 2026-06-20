package executor

import "fmt"

// Result holds the outcome of an executed command.
type Result struct {
	Status   string // success | failed
	ExitCode int
	Stdout   string
	Stderr   string
}

// Allowed actions the agent will execute. The dashboard never sends raw
// PowerShell — only these action keys are accepted.
var allowedActions = map[string]bool{
	"start_service":      true,
	"stop_service":       true,
	"restart_service":    true,
	"enable_service":     true,
	"disable_service":    true,
	"get_service_status": true,
	"sync_services":      true,
}

// IsAllowed reports whether an action key is permitted.
func IsAllowed(action string) bool {
	return allowedActions[action]
}

// Execute runs the mapped action for the given service. Platform-specific
// implementations provide the actual command execution.
func Execute(action, service string, timeoutSeconds int) (Result, error) {
	if !IsAllowed(action) {
		return Result{Status: "failed", ExitCode: -1}, fmt.Errorf("action %q not allowed", action)
	}
	return execute(action, service, timeoutSeconds)
}
