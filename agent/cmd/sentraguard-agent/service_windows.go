//go:build windows

package main

import (
	"context"
	"fmt"
	"os"
	"time"

	"golang.org/x/sys/windows/svc"
	"golang.org/x/sys/windows/svc/mgr"

	"github.com/syahrullrmdhn/sentraguard/agent/internal/agent"
	"github.com/syahrullrmdhn/sentraguard/agent/internal/api"
	"github.com/syahrullrmdhn/sentraguard/agent/internal/config"
	"github.com/syahrullrmdhn/sentraguard/agent/internal/logging"
	"github.com/syahrullrmdhn/sentraguard/agent/internal/token"
)

const serviceName = "SentraGuard Agent Service"

// installService registers the running binary as a Windows Service set to
// auto-start, then starts it.
func installService() error {
	exePath, err := os.Executable()
	if err != nil {
		return err
	}

	m, err := mgr.Connect()
	if err != nil {
		return err
	}
	defer m.Disconnect()

	if s, err := m.OpenService(serviceName); err == nil {
		s.Close()
		return fmt.Errorf("service %q already exists", serviceName)
	}

	s, err := m.CreateService(serviceName, exePath, mgr.Config{
		DisplayName: serviceName,
		Description: "SentraGuard pull-based Windows service control & monitoring agent.",
		StartType:   mgr.StartAutomatic,
	}, "run")
	if err != nil {
		return err
	}
	defer s.Close()

	return s.Start()
}

// controlService performs uninstall/start/stop/restart/status on the service.
func controlService(action string) error {
	m, err := mgr.Connect()
	if err != nil {
		return err
	}
	defer m.Disconnect()

	s, err := m.OpenService(serviceName)
	if err != nil {
		return fmt.Errorf("service %q not installed", serviceName)
	}
	defer s.Close()

	switch action {
	case "uninstall":
		_, _ = s.Control(svc.Stop)
		time.Sleep(2 * time.Second)
		if err := s.Delete(); err != nil {
			return err
		}
		// Wipe the DPAPI-protected runtime token from Credential Manager.
		if err := token.NewStore().Delete(); err != nil {
			fmt.Printf("⚠️  service deleted, but token cleanup failed: %v\n", err)
		}
		// Remove config + logs directory so a fresh install starts clean.
		if err := os.RemoveAll(config.DataDir()); err != nil {
			fmt.Printf("⚠️  service deleted, but data dir cleanup failed: %v\n", err)
		}
		fmt.Println("✅ Service uninstalled, runtime token and config removed.")
	case "start":
		if err := s.Start(); err != nil {
			return err
		}
		fmt.Println("✅ Service started.")
	case "stop":
		if _, err := s.Control(svc.Stop); err != nil {
			return err
		}
		fmt.Println("✅ Service stopped.")
	case "restart":
		_, _ = s.Control(svc.Stop)
		time.Sleep(2 * time.Second)
		if err := s.Start(); err != nil {
			return err
		}
		fmt.Println("✅ Service restarted.")
	case "status":
		st, err := s.Query()
		if err != nil {
			return err
		}
		fmt.Printf("Service state: %d\n", st.State)
	}
	return nil
}

// runAsService is the SCM entrypoint used when Windows starts the service.
type serviceHandler struct{}

func (h *serviceHandler) Execute(args []string, r <-chan svc.ChangeRequest, changes chan<- svc.Status) (bool, uint32) {
	const accepted = svc.AcceptStop | svc.AcceptShutdown
	changes <- svc.Status{State: svc.StartPending}

	ctx, cancel := context.WithCancel(context.Background())
	defer cancel()

	go func() {
		log := logging.New("info", config.LogDir())
		defer func() {
			if rec := recover(); rec != nil {
				log.Error("service worker PANIC: %v", rec)
				changes <- svc.Status{State: svc.Stopped, Win32ExitCode: 1}
			}
		}()
		log.Info("service worker goroutine entered")
		cfg, err := config.Load("")
		if err != nil {
			log.Error("service worker: load config failed: %v", err)
			changes <- svc.Status{State: svc.Stopped, Win32ExitCode: 1}
			return
		}
		runtimeToken, err := token.NewStore().Load()
		if err != nil {
			log.Error("service worker: load runtime token failed (DPAPI scope mismatch?): %v", err)
			changes <- svc.Status{State: svc.Stopped, Win32ExitCode: 1}
			return
		}
		client := api.New(cfg.ServerURL)
		client.SetRuntimeToken(runtimeToken)
		log = logging.New(cfg.LogLevel, config.LogDir())
		log.Info("service worker started, entering run loops")
		agent.New(cfg, client, log).Run(ctx)
	}()

	changes <- svc.Status{State: svc.Running, Accepts: accepted}
	for c := range r {
		switch c.Cmd {
		case svc.Interrogate:
			changes <- c.CurrentStatus
		case svc.Stop, svc.Shutdown:
			cancel()
			changes <- svc.Status{State: svc.StopPending}
			return false, 0
		}
	}
	return false, 0
}

// maybeRunAsService runs the SCM dispatcher if launched by Windows as a service.
func maybeRunAsService() {
	isService, err := svc.IsWindowsService()
	if err != nil || !isService {
		return
	}
	_ = svc.Run(serviceName, &serviceHandler{})
	os.Exit(0)
}
