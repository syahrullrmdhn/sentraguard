package agent

import (
	"context"
	"strings"
	"sync"
	"time"

	"github.com/syahrullrmdhn/sentraguard/agent/internal/api"
	"github.com/syahrullrmdhn/sentraguard/agent/internal/config"
	"github.com/syahrullrmdhn/sentraguard/agent/internal/executor"
	"github.com/syahrullrmdhn/sentraguard/agent/internal/firewall"
	"github.com/syahrullrmdhn/sentraguard/agent/internal/logging"
	"github.com/syahrullrmdhn/sentraguard/agent/internal/metrics"
	"github.com/syahrullrmdhn/sentraguard/agent/internal/services"
)

// Version is set at build time via -ldflags "-X ...agent.Version=x.y.z".
var Version = "dev"

// Runtime wires together the agent's background loops.
type Runtime struct {
	cfg      *config.Config
	client   *api.Client
	svcs     services.Manager
	firewall *firewall.Manager
	log      *logging.Logger

	prevStates map[string]string
	mu         sync.Mutex
}

// New builds a Runtime from config + an authenticated API client.
func New(cfg *config.Config, client *api.Client, log *logging.Logger) *Runtime {
	return &Runtime{
		cfg:        cfg,
		client:     client,
		log:        log,
		svcs:       services.NewManager(log),
		firewall:   firewall.NewManager(),
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
	// Report ALL services on the host so the dashboard can present the full
	// inventory; the operator picks which ones to monitor from there. Sending
	// only the allowlist meant an unconfigured agent sent an empty list, which
	// the backend rejected (422 "services field is required").
	list := r.svcs.ListAll()
	if len(list) == 0 {
		r.log.Warn("service sync skipped: no services enumerated")
		return
	}
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

	var result executor.Result
	var execErr error

	// Route firewall commands to firewall manager
	if strings.HasPrefix(cmd.Action, "firewall_") {
		result, execErr = r.executeFirewallCommand(cmd)
	} else {
		result, execErr = executor.Execute(cmd.Action, cmd.ServiceName, r.cfg.CommandTimeoutSeconds)
	}

	if execErr != nil {
		r.log.Warn("command #%d failed: %v", cmd.ID, execErr)
	}

	// Capture post-execution service status for the dashboard.
	var svcStatus, startup string
	if cmd.ServiceName != "" {
		if info, err := r.svcs.Info(cmd.ServiceName); err == nil {
			svcStatus = info.Status
			startup = info.StartupType
		}
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

func (r *Runtime) executeFirewallCommand(cmd *api.Command) (executor.Result, error) {
	// Use Payload field directly
	payload := cmd.Payload
	if payload == nil {
		return executor.Result{Status: "failed", ExitCode: -1, Stderr: "Missing payload"}, nil
	}

	ruleName, _ := payload["rule_name"].(string)
	if ruleName == "" {
		return executor.Result{Status: "failed", ExitCode: -1, Stderr: "Missing rule_name"}, nil
	}

	var err error
	switch cmd.Action {
	case "firewall_add_rule":
		direction, _ := payload["direction"].(string)
		protocol, _ := payload["protocol"].(string)
		port, _ := payload["port"].(string)
		action, _ := payload["action"].(string)
		err = r.firewall.AddRule(ruleName, direction, protocol, port, action)
	case "firewall_enable_rule":
		err = r.firewall.EnableRule(ruleName)
	case "firewall_disable_rule":
		err = r.firewall.DisableRule(ruleName)
	case "firewall_delete_rule":
		err = r.firewall.DeleteRule(ruleName)
	default:
		return executor.Result{Status: "failed", ExitCode: -1, Stderr: "Unknown firewall action"}, nil
	}

	if err != nil {
		return executor.Result{Status: "failed", ExitCode: 1, Stderr: err.Error()}, err
	}

	return executor.Result{Status: "success", ExitCode: 0, Stdout: "Firewall command executed"}, nil
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
