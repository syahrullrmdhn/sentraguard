# SentraGuard Agent Installer

Modern web-based GUI installer for SentraGuard monitoring agent.

## Features

- ✅ **Web-based UI** — runs local web server, opens in browser
- ✅ **No external dependencies** — pure Go stdlib, 6.5MB
- ✅ **Modern design** — gradient UI, responsive, animated
- ✅ **Token-based installation** — paste token from console
- ✅ **Automatic agent download** — fetches latest agent
- ✅ **Service verification** — confirms installation success
- ✅ **Cross-platform build** — works on any Go-supported platform

## Usage

1. **Download** `SentraGuardInstaller.exe` from your SentraGuard console
2. **Right-click** → **Run as Administrator**
3. **Browser opens automatically** at `http://localhost:8765`
4. **Enter your registration token** from the console
5. **Click "Install Agent"**
6. **Done!** ✓

## How It Works

The installer:
1. Starts a local web server on port 8765
2. Opens your default browser to the installer UI
3. Downloads the agent from your SentraGuard server
4. Installs and registers the agent service
5. Verifies the service is running

## Requirements

- Windows Server 2016+ or Windows 10+
- Administrator privileges
- Internet connection to download agent
- Available port 8765

## Architecture

```
┌─────────────────┐
│  .exe (Go app)  │
│  - HTTP server  │
│  - Embedded     │
│    HTML/CSS     │
└────────┬────────┘
         │ Opens browser
         ▼
┌─────────────────┐
│  Browser        │
│  localhost:8765 │
│  - Token form   │
│  - Install UI   │
└─────────────────┘
```

## Build from Source

Pure Go stdlib, no external dependencies:

```bash
GOOS=windows GOARCH=amd64 go build -ldflags="-s -w" -o SentraGuardInstaller.exe .
```

## Troubleshooting

**"Administrator privileges required"**
→ Right-click the installer and select "Run as administrator"

**"Failed to download agent"**
→ Check internet connection and firewall settings

**"Port 8765 already in use"**
→ Close other applications using port 8765, or change PORT in code

**Browser doesn't open automatically**
→ Manually open `http://localhost:8765` in your browser

## Technical Details

- **Language:** Go 1.18+
- **Dependencies:** None (pure stdlib)
- **UI:** Embedded HTML template
- **Size:** 6.5MB
- **Port:** 8765 (configurable in code)

## License

Proprietary - SentraGuard
