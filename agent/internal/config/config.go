package config

import (
	"fmt"
	"os"
	"path/filepath"
	"runtime"

	"gopkg.in/yaml.v3"
)

// Config holds the agent runtime configuration loaded from config.yaml.
type Config struct {
	ServerURL string `yaml:"server_url"`
	AgentUID  string `yaml:"agent_uid"`

	// RuntimeToken is only persisted here in development mode. In production
	// it is stored in the Windows Credential Manager via DPAPI.
	RuntimeToken string `yaml:"runtime_token,omitempty"`

	PollIntervalSeconds           int `yaml:"poll_interval_seconds"`
	HeartbeatIntervalSeconds      int `yaml:"heartbeat_interval_seconds"`
	MetricsIntervalSeconds        int `yaml:"metrics_interval_seconds"`
	ServiceMonitorIntervalSeconds int `yaml:"service_monitor_interval_seconds"`
	CommandTimeoutSeconds         int `yaml:"command_timeout_seconds"`

	LogLevel      string `yaml:"log_level"`
	LogMaxSizeMB  int    `yaml:"log_max_size_mb"`
	LogMaxBackups int    `yaml:"log_max_backups"`

	AllowedServices []string `yaml:"allowed_services"`
}

// Defaults returns a Config populated with the spec's default intervals.
func Defaults() *Config {
	return &Config{
		PollIntervalSeconds:           5,
		HeartbeatIntervalSeconds:      30,
		MetricsIntervalSeconds:        15,
		ServiceMonitorIntervalSeconds: 30,
		CommandTimeoutSeconds:         60,
		LogLevel:                      "info",
		LogMaxSizeMB:                  50,
		LogMaxBackups:                 7,
		AllowedServices:               []string{},
	}
}

// DataDir returns the platform-appropriate ProgramData directory for the agent.
func DataDir() string {
	if runtime.GOOS == "windows" {
		base := os.Getenv("ProgramData")
		if base == "" {
			base = `C:\ProgramData`
		}
		return filepath.Join(base, "SentraGuard Agent")
	}
	// Dev/Linux fallback
	return filepath.Join(os.TempDir(), "sentraguard-agent")
}

// ConfigPath returns the full path to config.yaml.
func ConfigPath() string {
	return filepath.Join(DataDir(), "config.yaml")
}

// LogDir returns the directory where agent logs are written.
func LogDir() string {
	return filepath.Join(DataDir(), "logs")
}

// Load reads and parses config.yaml, applying defaults for unset fields.
func Load(path string) (*Config, error) {
	if path == "" {
		path = ConfigPath()
	}

	data, err := os.ReadFile(path)
	if err != nil {
		return nil, fmt.Errorf("read config %s: %w", path, err)
	}

	cfg := Defaults()
	if err := yaml.Unmarshal(data, cfg); err != nil {
		return nil, fmt.Errorf("parse config: %w", err)
	}

	if cfg.ServerURL == "" {
		return nil, fmt.Errorf("server_url is required in config")
	}

	return cfg, nil
}

// Save writes the config to disk as YAML, creating parent directories.
func Save(path string, cfg *Config) error {
	if path == "" {
		path = ConfigPath()
	}
	if err := os.MkdirAll(filepath.Dir(path), 0o755); err != nil {
		return fmt.Errorf("create config dir: %w", err)
	}

	data, err := yaml.Marshal(cfg)
	if err != nil {
		return fmt.Errorf("marshal config: %w", err)
	}

	if err := os.WriteFile(path, data, 0o600); err != nil {
		return fmt.Errorf("write config: %w", err)
	}
	return nil
}
