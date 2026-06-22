# SentraGuard Agent Installer

Native Windows GUI installer for SentraGuard monitoring agent.

## Features

- ✅ Native Windows GUI application
- ✅ Simple token-based installation
- ✅ Automatic agent download
- ✅ Service installation & verification
- ✅ Administrator privilege check
- ✅ Progress feedback

## Usage

### Option 1: GUI Installer (Recommended)

1. **Download** `SentraGuardInstaller.exe` from your SentraGuard console
2. **Right-click** → **Run as Administrator**
3. **Enter** your registration token from the console
4. **Click Install**

### Option 2: Command Line

```powershell
# Download and run agent directly
iwr -Uri https://sentraguard.mastolongin.web.id/download/agent -OutFile agent.exe
.\agent.exe install --server https://sentraguard.mastolongin.web.id --token YOUR_TOKEN
```

## Requirements

- Windows Server 2016+ or Windows 10+
- Administrator privileges
- Internet connection to download agent

## Troubleshooting

**"Administrator privileges required"**
→ Right-click the installer and select "Run as administrator"

**"Failed to download agent"**
→ Check internet connection and firewall settings

**"Service not found after installation"**
→ Check Windows Event Viewer for agent installation errors

## Build from Source

Built with Go + Fyne (pure Go GUI framework):

```bash
go build -ldflags="-s -w -H windowsgui" -o SentraGuardInstaller.exe .
```

## License

Proprietary - SentraGuard
