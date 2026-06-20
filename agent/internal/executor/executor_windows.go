//go:build windows

package executor

import (
	"bytes"
	"context"
	"fmt"
	"os/exec"
	"strings"
	"time"
)

// psCommand maps a safe action to its PowerShell command. %s is the service name.
var psCommand = map[string]string{
	"start_service":      `Start-Service -Name "%s"`,
	"stop_service":       `Stop-Service -Name "%s" -Force`,
	"restart_service":    `Restart-Service -Name "%s" -Force`,
	"enable_service":     `Set-Service -Name "%s" -StartupType Automatic`,
	"disable_service":    `Stop-Service -Name "%s" -Force; Set-Service -Name "%s" -StartupType Disabled`,
	"get_service_status": `Get-Service -Name "%s" | Select-Object Status,StartType | Format-List`,
	"sync_services":      `Get-Service | Select-Object Name,DisplayName,Status,StartType | ConvertTo-Json`,
}

func execute(action, service string, timeoutSeconds int) (Result, error) {
	tmpl, ok := psCommand[action]
	if !ok {
		return Result{Status: "failed", ExitCode: -1}, fmt.Errorf("no command mapping for %q", action)
	}

	// disable_service uses the service name twice.
	var script string
	if strings.Count(tmpl, "%s") == 2 {
		script = fmt.Sprintf(tmpl, service, service)
	} else if strings.Contains(tmpl, "%s") {
		script = fmt.Sprintf(tmpl, service)
	} else {
		script = tmpl
	}

	ctx, cancel := context.WithTimeout(context.Background(), time.Duration(timeoutSeconds)*time.Second)
	defer cancel()

	// -NoProfile + -NonInteractive for predictable, fast, non-blocking execution.
	cmd := exec.CommandContext(ctx, "powershell.exe",
		"-NoProfile", "-NonInteractive", "-ExecutionPolicy", "Bypass",
		"-Command", script)

	var stdout, stderr bytes.Buffer
	cmd.Stdout = &stdout
	cmd.Stderr = &stderr

	err := cmd.Run()

	res := Result{
		Stdout: strings.TrimSpace(stdout.String()),
		Stderr: strings.TrimSpace(stderr.String()),
	}

	if ctx.Err() == context.DeadlineExceeded {
		res.Status = "failed"
		res.ExitCode = -1
		return res, fmt.Errorf("command timed out after %ds", timeoutSeconds)
	}

	if err != nil {
		res.Status = "failed"
		if exitErr, ok := err.(*exec.ExitError); ok {
			res.ExitCode = exitErr.ExitCode()
		} else {
			res.ExitCode = -1
		}
		return res, err
	}

	res.Status = "success"
	res.ExitCode = 0
	return res, nil
}
