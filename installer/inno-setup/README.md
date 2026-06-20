# SentraGuard Agent Installer (Inno Setup)

Packages `sentraguard-agent.exe` into a single `SentraGuardAgentSetup.exe` that
registers the agent with the dashboard and installs it as a Windows Service.

## Prerequisites

1. **Build the agent first** (produces the `.exe` the installer bundles):
   ```bash
   cd ../../agent
   VERSION=1.0.0 ./build.sh
   # -> agent/build/sentraguard-agent.exe
   ```
2. **Inno Setup 6+** on a Windows machine (or Wine):
   - Download: https://jrsoftware.org/isdl.php
   - The compiler CLI is `ISCC.exe`.

## Compile the installer

On Windows:
```cmd
"C:\Program Files (x86)\Inno Setup 6\ISCC.exe" SentraGuardAgent.iss
```

Output: `dist\SentraGuardAgentSetup.exe` (relative to repo root).

## Usage

### Interactive
Double-click `SentraGuardAgentSetup.exe`. The wizard asks for:
- **Dashboard URL** — e.g. `https://sentraguard.example.com`
- **Enrollment Token** — the single-use `AGT_...` token shown when you add the
  server in the dashboard.

### Silent / unattended (GPO, RMM, scripted)
```cmd
SentraGuardAgentSetup.exe /SILENT /server="https://sentraguard.example.com" /token="AGT_xxxxxxxx"

:: fully quiet, no message boxes
SentraGuardAgentSetup.exe /VERYSILENT /SUPPRESSMSGBOXES /server="https://sentraguard.example.com" /token="AGT_xxxxxxxx"
```

If `/server=` and `/token=` are supplied, the wizard prompt page is skipped
automatically. In silent mode, missing values abort the install with a clear
error rather than installing a non-functional service.

## What the installer does

1. Copies `sentraguard-agent.exe` to `%ProgramFiles%\SentraGuard\Agent`.
2. Runs `sentraguard-agent.exe install --server <url> --token <token>`, which:
   - exchanges the one-time `AGT_` token for a long-lived runtime token,
   - seals the runtime token with DPAPI (LocalSystem),
   - writes `%ProgramData%\SentraGuard\config.yaml`,
   - installs + starts the **`SentraGuard Agent Service`** Windows Service.
3. Adds Start Menu shortcuts (status + uninstall).

## Uninstall

Via "Apps & features" or `unins000.exe`. The uninstaller stops and removes the
Windows Service before deleting files.

## WiX alternative

`../wix/` is reserved for a future MSI build (GPO software-deployment via
`msiexec /i ... /qn SERVER=... TOKEN=...`). The Inno Setup installer above is
the current supported package.
