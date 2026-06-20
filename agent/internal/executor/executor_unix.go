//go:build !windows

package executor

import "fmt"

// execute is a development stub for non-Windows platforms. It pretends the
// action succeeded so the command loop can be exercised end-to-end in dev.
func execute(action, service string, timeoutSeconds int) (Result, error) {
	return Result{
		Status:   "success",
		ExitCode: 0,
		Stdout:   fmt.Sprintf("[dev-stub] executed %s on %s", action, service),
		Stderr:   "",
	}, nil
}
