# SentraGuard Agent

Lightweight Go agent that runs on Windows Server, reports metrics + service
state to the SentraGuard dashboard, and executes allow-listed service commands
(start / stop / restart) pulled from the dashboard's command queue.

## Build

Requires Go 1.22+. On this server Go 1.22 lives at `/usr/local/go-1.22`
(the system Go 1.18 is left untouched).

```bash
cd agent
VERSION=1.0.0 ./build.sh
```

Outputs to `agent/build/`:
- `sentraguard-agent.exe` — PE32+ x86-64, the production Windows artifact
- `sentraguard-agent` — Linux build for local dev/testing

Binaries are gitignored; build them from source or via the installer.

## Architecture

| Package | Responsibility |
|---|---|
| `internal/config` | YAML config load/save, ProgramData paths |
| `internal/api` | HTTPS client for the 7 agent endpoints + `/up` health |
| `internal/metrics` | gopsutil CPU/RAM/disk (build-tagged drive: `C:\` vs `/`) |
| `internal/services` | Windows SCM enumeration/control via `svc/mgr`; Linux stub |
| `internal/executor` | action → PowerShell mapping with allowlist guard; Linux stub |
| `internal/token` | runtime token at rest: DPAPI on Windows, base64 file fallback dev |
| `internal/logging` | leveled logger → stdout + `agent.log` (rotated) |
| `internal/agent` | runtime: 4 concurrent loops (heartbeat 30s, metrics 15s, poll 5s, monitor 30s) |
| `cmd/sentraguard-agent` | CLI + Windows service entrypoint |

Windows-only APIs (DPAPI, SCM) are isolated behind `_windows.go` build tags so
the agent still compiles on Linux for development. The matching `_unix.go`
files provide dev stubs.

## CLI

```
sentraguard-agent.exe <command> [flags]

install   --server <url> --token <AGT_xxx>   Install + register as Windows Service
register  --server <url> --token <AGT_xxx>   Register only (write config + store token)
run       [--config <path>]                  Run the agent loops in foreground
uninstall                                     Remove the Windows Service
start | stop | restart | status              Service control
test-connection [--server <url>]             Verify dashboard reachability
sync-services [--config <path>]              Push current service list once
version                                       Print version
```

## Typical install (on the Windows host)

```powershell
.\sentraguard-agent.exe install --server https://sentraguard.example.com --token AGT_xxxxxxxx
```

This registers with the dashboard (exchanging the one-time `AGT_` enrollment
token for a long-lived runtime token stored via DPAPI), writes config to
`%ProgramData%\SentraGuard\config.yaml`, and installs + starts the
`SentraGuardAgent` Windows Service.

## Security notes

- The enrollment token (`AGT_`) is single-use; the dashboard rejects reuse.
- The runtime token never touches the config file on Windows — it is sealed
  with DPAPI under the LocalSystem account.
- The executor only runs commands whose target service is allow-listed by the
  dashboard; anything else is refused before a PowerShell process is spawned.
