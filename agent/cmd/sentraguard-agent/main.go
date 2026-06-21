package main

import (
	"context"
	"fmt"
	"io"
	"net/http"
	"os"
	"os/signal"
	"runtime"
	"syscall"

	"github.com/syahrullrmdhn/sentraguard/agent/internal/agent"
	"github.com/syahrullrmdhn/sentraguard/agent/internal/api"
	"github.com/syahrullrmdhn/sentraguard/agent/internal/config"
	"github.com/syahrullrmdhn/sentraguard/agent/internal/logging"
	"github.com/syahrullrmdhn/sentraguard/agent/internal/token"
)

// Version is overridden at build time via -ldflags.
var Version = "dev"

func main() {
	agent.Version = Version

	// If launched by the Windows SCM, run as a service and exit.
	maybeRunAsService()

	if len(os.Args) < 2 {
		usage()
		os.Exit(1)
	}

	cmd := os.Args[1]
	args := os.Args[2:]

	switch cmd {
	case "version":
		fmt.Printf("SentraGuard Agent %s (%s/%s)\n", Version, runtime.GOOS, runtime.GOARCH)
	case "install":
		mustRun(cmdInstall(args))
	case "register":
		mustRun(cmdRegister(args))
	case "run":
		mustRun(cmdRun(args))
	case "update":
		mustRun(cmdUpdate(args))
	case "test-connection":
		mustRun(cmdTestConnection(args))
	case "sync-services":
		mustRun(cmdSyncServices(args))
	case "uninstall", "start", "stop", "restart", "status":
		mustRun(cmdService(cmd))
	default:
		fmt.Fprintf(os.Stderr, "unknown command: %s\n\n", cmd)
		usage()
		os.Exit(1)
	}
}

func usage() {
	fmt.Print(`SentraGuard Agent

Usage:
  sentraguard-agent.exe <command> [flags]

Commands:
  install --server <url> --token <AGT_xxx>   Install + register as Windows Service
  register --server <url> --token <AGT_xxx>  Register only (write config + store token)
  run [--config <path>]                      Run the agent loops in foreground
  update [--server <url>]                    Check for updates and self-update if available
  uninstall                                  Remove the Windows Service
  start | stop | restart | status            Service control
  test-connection [--server <url>]           Verify dashboard reachability
  sync-services [--config <path>]            Push current service list once
  version                                    Print version
`)
}

func mustRun(err error) {
	if err != nil {
		fmt.Fprintf(os.Stderr, "error: %v\n", err)
		os.Exit(1)
	}
}

// parseFlags is a tiny --key value parser (avoids pulling in a flag lib for
// the simple agent CLI surface).
func parseFlags(args []string) map[string]string {
	out := map[string]string{}
	for i := 0; i < len(args); i++ {
		a := args[i]
		if len(a) > 2 && a[:2] == "--" {
			key := a[2:]
			if i+1 < len(args) && len(args[i+1]) >= 2 && args[i+1][:2] != "--" {
				out[key] = args[i+1]
				i++
			} else {
				out[key] = "true"
			}
		}
	}
	return out
}

// register performs registration and persists config + runtime token.
func register(serverURL, regToken string) (*config.Config, error) {
	if serverURL == "" || regToken == "" {
		return nil, fmt.Errorf("--server and --token are required")
	}

	client := api.New(serverURL)
	host, _ := os.Hostname()

	resp, err := client.Register(api.RegisterRequest{
		Token:        regToken,
		Hostname:     host,
		MachineID:    machineID(),
		OSName:       osName(),
		OSVersion:    osVersion(),
		AgentVersion: Version,
		PrivateIP:    privateIP(),
		PublicIP:     publicIP(),
	})
	if err != nil {
		return nil, fmt.Errorf("registration failed: %w", err)
	}

	// Persist runtime token securely (DPAPI on Windows).
	if err := token.NewStore().Save(resp.RuntimeToken); err != nil {
		return nil, fmt.Errorf("store runtime token: %w", err)
	}

	cfg := config.Defaults()
	cfg.ServerURL = serverURL
	cfg.AgentUID = resp.AgentUID
	if err := config.Save(config.ConfigPath(), cfg); err != nil {
		return nil, fmt.Errorf("save config: %w", err)
	}

	return cfg, nil
}

func cmdRegister(args []string) error {
	f := parseFlags(args)
	cfg, err := register(f["server"], f["token"])
	if err != nil {
		return err
	}
	fmt.Printf("✅ Registered. agent_uid=%s config=%s\n", cfg.AgentUID, config.ConfigPath())
	return nil
}

func cmdRun(args []string) error {
	f := parseFlags(args)
	cfg, err := config.Load(f["config"])
	if err != nil {
		return err
	}

	runtimeToken, err := token.NewStore().Load()
	if err != nil {
		// Dev fallback: allow runtime_token from config.
		if cfg.RuntimeToken != "" {
			runtimeToken = cfg.RuntimeToken
		} else {
			return fmt.Errorf("load runtime token: %w", err)
		}
	}

	client := api.New(cfg.ServerURL)
	client.SetRuntimeToken(runtimeToken)

	log := logging.New(cfg.LogLevel, config.LogDir())
	rt := agent.New(cfg, client, log)

	ctx, stop := signal.NotifyContext(context.Background(), os.Interrupt, syscall.SIGTERM)
	defer stop()

	rt.Run(ctx)
	return nil
}

func cmdTestConnection(args []string) error {
	f := parseFlags(args)
	serverURL := f["server"]
	if serverURL == "" {
		if cfg, err := config.Load(f["config"]); err == nil {
			serverURL = cfg.ServerURL
		}
	}
	if serverURL == "" {
		return fmt.Errorf("--server required (or a valid config)")
	}
	if err := api.New(serverURL).TestConnection(); err != nil {
		return err
	}
	fmt.Printf("✅ Connection OK: %s\n", serverURL)
	return nil
}

func cmdSyncServices(args []string) error {
	f := parseFlags(args)
	cfg, err := config.Load(f["config"])
	if err != nil {
		return err
	}
	runtimeToken, err := token.NewStore().Load()
	if err != nil && cfg.RuntimeToken != "" {
		runtimeToken = cfg.RuntimeToken
	} else if err != nil {
		return fmt.Errorf("load runtime token: %w", err)
	}

	client := api.New(cfg.ServerURL)
	client.SetRuntimeToken(runtimeToken)
	log := logging.New(cfg.LogLevel, config.LogDir())
	agent.New(cfg, client, log) // constructs service manager
	fmt.Println("Use 'run' for continuous sync; one-shot sync handled at startup.")
	return nil
}

func cmdUpdate(args []string) error {
	f := parseFlags(args)
	serverURL := f["server"]
	
	// Try to get server URL from config if not provided
	if serverURL == "" {
		if cfg, err := config.Load(""); err == nil {
			serverURL = cfg.ServerURL
		}
	}
	
	if serverURL == "" {
		return fmt.Errorf("--server required (or a valid config)")
	}
	
	// Check latest version from dashboard
	client := api.New(serverURL)
	latestVersion, downloadURL, err := client.CheckVersion()
	if err != nil {
		return fmt.Errorf("check version failed: %w", err)
	}
	
	// Compare versions
	if latestVersion == Version {
		fmt.Printf("✅ Already up to date (v%s)\n", Version)
		return nil
	}
	
	fmt.Printf("🔄 Update available: v%s → v%s\n", Version, latestVersion)
	fmt.Printf("📥 Downloading from %s...\n", downloadURL)
	
	// Download new binary to temp
	newExePath, err := downloadUpdate(downloadURL)
	if err != nil {
		return fmt.Errorf("download failed: %w", err)
	}
	
	fmt.Println("⚙️  Stopping service...")
	if err := cmdService("stop"); err != nil {
		fmt.Printf("⚠️  Stop service warning: %v\n", err)
	}
	
	// Replace current executable
	currentExe, err := os.Executable()
	if err != nil {
		return fmt.Errorf("get current exe path: %w", err)
	}
	
	backupPath := currentExe + ".backup"
	fmt.Println("💾 Backing up current binary...")
	if err := os.Rename(currentExe, backupPath); err != nil {
		return fmt.Errorf("backup current exe: %w", err)
	}
	
	fmt.Println("🔄 Replacing binary...")
	if err := os.Rename(newExePath, currentExe); err != nil {
		// Rollback on failure
		_ = os.Rename(backupPath, currentExe)
		return fmt.Errorf("replace exe: %w", err)
	}
	
	// Cleanup backup on success
	_ = os.Remove(backupPath)
	
	fmt.Println("🚀 Starting service...")
	if err := cmdService("start"); err != nil {
		return fmt.Errorf("start service: %w", err)
	}
	
	fmt.Printf("✅ Successfully updated to v%s\n", latestVersion)
	return nil
}

func downloadUpdate(url string) (string, error) {
	resp, err := http.Get(url)
	if err != nil {
		return "", err
	}
	defer resp.Body.Close()
	
	if resp.StatusCode != 200 {
		return "", fmt.Errorf("download returned HTTP %d", resp.StatusCode)
	}
	
	tmpFile, err := os.CreateTemp("", "sentraguard-agent-*.exe")
	if err != nil {
		return "", err
	}
	defer tmpFile.Close()
	
	if _, err := io.Copy(tmpFile, resp.Body); err != nil {
		os.Remove(tmpFile.Name())
		return "", err
	}
	
	return tmpFile.Name(), nil
}
