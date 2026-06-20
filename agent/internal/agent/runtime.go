package agent

import (
	"context"
	"sync"
	"time"

	"github.com/syahrullrmdhn/sentraguard/agent/internal/api"
	"github.com/syahrullrmdhn/sentraguard/agent/internal/config"
	"github.com/syahrullrmdhn/sentraguard/agent/internal/executor"
	"github.com/syahrullrmdhn/sentraguard/agent/internal/logging"
	"github.com/syahrullrmdhn/sentraguard/agent/internal/metrics"
	"github.com/syahrullrmdhn/sentraguard/agent/internal/services"
)

// Version is set at build time via -ldflags "-X ...agent.Version=x.y.z".
var Version = "dev"

// Runtime wires together the agent's background loops.
type Runtime struct {
	cfg    *config.Config
	client *api.Client
	svcs   services.Manager
	log    *logging.Logger

	prevStates map[string]string
	mu         sync.Mutex
}

// New builds a Runtime from config + an authenticated API client.
func New(cfg *config.Config, client *api.Client, log *logging.Logger) *Runtime {
	return &Runtime{
		cfg:        cfg,
		client:     client,
		svcs:       services.NewManager(),
		log:        log,
		prevStates: map[string]string{},
	}
}

// Run starts all loops and blocks until ctx is cancelled.
func (r *Runtime) Run(ctx context.Context) {
	r.log.Info("SentraGuard Agent %s starting (uid=%s)", Version, r.cfg.AgentUID)

	// Initial service sync so the dashboard has a baseline.
	r.syncServices()

	var wg sync.WaitGroup
	loops := []struct {
		name     string
		interval time.Duration
		fn       func()
	}{
		{"heartbeat", time.Duration(r.cfg.HeartbeatIntervalSeconds) * time.Second, r.heartbeat},
		{"metrics", time.Duration(r.cfg.MetricsIntervalSeconds) * time.Second, r.postMetrics},
		{"poll", time.Duration(r.cfg.PollIntervalSeconds) * time.Second, r.pollAndExecute},
		{"monitor", time.Duration(r.cfg.ServiceMonitorIntervalSeconds) * time.Second, r.monitorServices},
	}

	for _, loop := range loops {
		wg.Add(1)
		go func(name string, interval time.Duration, fn func()) {
			defer wg.Done()
			r.runLoop(ctx, name, interval, fn)
		}(loop.name, loop.interval, loop.fn)
	}

	<-ctx.Done()
	r.log.Info("shutdown signal received, stopping loops")
	wg.Wait()
	r.log.Info("agent stopped")
}

// runLoop ticks fn at interval until ctx is cancelled, running fn once immediately.
func (r *Runtime) runLoop(ctx context.Context, name string, interval time.Duration, fn func()) {
	fn() // run once at startup
	ticker := time.NewTicker(interval)
	defer ticker.Stop()
	for {
		select {
		case <-ctx.Done():
			return
		case <-ticker.C:
			fn()
		}
	}
}

func (r *Runtime) heartbeat() {
	if err := r.client.Heartbeat(api.HeartbeatRequest{
		Status:       "online",
		AgentVersion: Version,
	}); err != nil {
		r.log.Warn("heartbeat failed: %v", err)
		return
	}
	r.log.Debug("heartbeat ok")
}

func (r *Runtime) postMetrics() {
	m, err := metrics.Collect()
	if err != nil {
		r.log.Warn("metrics collect failed: %v", err)
		return
	}
	if err := r.client.PostMetrics(m); err != nil {
		r.log.Warn("metrics post failed: %v", err)
		return
	}
	r.log.Debug("metrics posted: cpu=%.1f%% ram=%d/%dMB", m.CPUPercent, m.RAMUsedMB, m.RAMTotalMB)
}

func (r *Runtime) syncServices() {
	list := r.svcs.List(r.cfg.AllowedServices)
	if err := r.client.SyncServices(api.SyncServicesRequest{Services: list}); err != nil {
		r.log.Warn("service sync failed: %v", err)
		return
	}
	r.log.Info("synced %d services", len(list))
}

func (r *Runtime) pollAndExecute() {
	resp, err := r.client.PollCommand()
	if err != nil {
		r.log.Warn("poll failed: %v", err)
		return
	}
	if !resp.HasCommand || resp.Command == nil {
		return
	}

	cmd := resp.Command
	r.log.Info("picked command #%d: %s on %s", cmd.ID, cmd.Action, cmd.ServiceName)

	result, execErr := executor.Execute(cmd.Action, cmd.ServiceName, r.cfg.CommandTimeoutSeconds)
	if execErr != nil {
		r.log.Warn("command #%d failed: %v", cmd.ID, execErr)
	}

	// Capture post-execution service status for the dashboard.
	var svcStatus, startup string
	if info, err := r.svcs.Info(cmd.ServiceName); err == nil {
		svcStatus = info.Status
		startup = info.StartupType
	}

	if err := r.client.SubmitResult(cmd.ID, api.CommandResultRequest{
		Status:        result.Status,
		ExitCode:      result.ExitCode,
		Stdout:        result.Stdout,
		Stderr:        result.Stderr,
		ServiceStatus: svcStatus,
		StartupType:   startup,
	}); err != nil {
		r.log.Warn("submit result for #%d failed: %v", cmd.ID, err)
		return
	}
	r.log.Info("command #%d reported: %s (exit=%d)", cmd.ID, result.Status, result.ExitCode)
}

func (r *Runtime) monitorServices() {
	r.mu.Lock()
	defer r.mu.Unlock()

	for _, svc := range r.cfg.AllowedServices {
		current, err := r.svcs.Status(svc)
		if err != nil {
			continue // service may not exist on this host
		}
		prev, known := r.prevStates[svc]
		if known && current != prev {
			r.log.Info("service %s changed: %s -> %s", svc, prev, current)
			if err := r.client.PostServiceEvent(api.ServiceEventRequest{
				ServiceName:    svc,
				PreviousStatus: prev,
				CurrentStatus:  current,
			}); err != nil {
				r.log.Warn("service-event post failed for %s: %v", svc, err)
			}
		}
		r.prevStates[svc] = current
	}
}
