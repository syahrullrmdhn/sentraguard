package api

import (
	"bytes"
	"encoding/json"
	"fmt"
	"io"
	"net/http"
	"strings"
	"time"
)

// Client is a thin HTTPS client for the SentraGuard agent API.
type Client struct {
	baseURL      string
	runtimeToken string
	http         *http.Client
}

// New creates an API client for the given dashboard base URL.
func New(baseURL string) *Client {
	return &Client{
		baseURL: strings.TrimRight(baseURL, "/"),
		http: &http.Client{
			Timeout: 30 * time.Second,
		},
	}
}

// SetRuntimeToken sets the Bearer token used for authenticated agent calls.
func (c *Client) SetRuntimeToken(token string) {
	c.runtimeToken = token
}

// ---- Request/response payloads ----

type RegisterRequest struct {
	Token        string `json:"token"`
	Hostname     string `json:"hostname"`
	MachineID    string `json:"machine_id"`
	OSName       string `json:"os_name"`
	OSVersion    string `json:"os_version"`
	AgentVersion string `json:"agent_version"`
	PrivateIP    string `json:"private_ip"`
	PublicIP     string `json:"public_ip"`
}

type RegisterResponse struct {
	AgentUID     string `json:"agent_uid"`
	RuntimeToken string `json:"runtime_token"`
	ServerID     int    `json:"server_id"`
}

type HeartbeatRequest struct {
	Status       string `json:"status"`
	AgentVersion string `json:"agent_version"`
}

type MetricsRequest struct {
	CPUPercent  float64 `json:"cpu_percent"`
	RAMUsedMB   uint64  `json:"ram_used_mb"`
	RAMTotalMB  uint64  `json:"ram_total_mb"`
	DiskUsedGB  float64 `json:"disk_used_gb"`
	DiskTotalGB float64 `json:"disk_total_gb"`
}

type ServiceInfo struct {
	ServiceName string `json:"service_name"`
	DisplayName string `json:"display_name"`
	Status      string `json:"status"`
	StartupType string `json:"startup_type"`
}

type SyncServicesRequest struct {
	Services []ServiceInfo `json:"services"`
}

type ServiceEventRequest struct {
	ServiceName    string `json:"service_name"`
	PreviousStatus string `json:"previous_status"`
	CurrentStatus  string `json:"current_status"`
}

// Command is a pending command returned from the poll endpoint.
type Command struct {
	ID          int    `json:"id"`
	ServiceName string `json:"service_name"`
	Action      string `json:"action"`
}

type PollResponse struct {
	HasCommand bool     `json:"has_command"`
	Command    *Command `json:"command"`
}

type CommandResultRequest struct {
	Status        string `json:"status"`
	ExitCode      int    `json:"exit_code"`
	Stdout        string `json:"stdout"`
	Stderr        string `json:"stderr"`
	ServiceStatus string `json:"service_status,omitempty"`
	StartupType   string `json:"startup_type,omitempty"`
}

// ---- API methods ----

// Register performs one-time agent registration with a registration token.
func (c *Client) Register(req RegisterRequest) (*RegisterResponse, error) {
	var resp RegisterResponse
	if err := c.do(http.MethodPost, "/api/agent/register", req, &resp, false); err != nil {
		return nil, err
	}
	return &resp, nil
}

// Heartbeat sends a liveness signal.
func (c *Client) Heartbeat(req HeartbeatRequest) error {
	return c.do(http.MethodPost, "/api/agent/heartbeat", req, nil, true)
}

// PostMetrics submits a metrics sample.
func (c *Client) PostMetrics(req MetricsRequest) error {
	return c.do(http.MethodPost, "/api/agent/metrics", req, nil, true)
}

// SyncServices uploads the full monitored-service list.
func (c *Client) SyncServices(req SyncServicesRequest) error {
	return c.do(http.MethodPost, "/api/agent/services/sync", req, nil, true)
}

// PostServiceEvent reports a proactive service state change.
func (c *Client) PostServiceEvent(req ServiceEventRequest) error {
	return c.do(http.MethodPost, "/api/agent/service-events", req, nil, true)
}

// PollCommand fetches the next pending command (atomic pickup server-side).
func (c *Client) PollCommand() (*PollResponse, error) {
	var resp PollResponse
	if err := c.do(http.MethodGet, "/api/agent/commands/poll", nil, &resp, true); err != nil {
		return nil, err
	}
	return &resp, nil
}

// SubmitResult reports the outcome of an executed command.
func (c *Client) SubmitResult(commandID int, req CommandResultRequest) error {
	path := fmt.Sprintf("/api/agent/commands/%d/result", commandID)
	return c.do(http.MethodPost, path, req, nil, true)
}

// TestConnection verifies the dashboard is reachable (uses /up health route).
func (c *Client) TestConnection() error {
	httpReq, err := http.NewRequest(http.MethodGet, c.baseURL+"/up", nil)
	if err != nil {
		return err
	}
	resp, err := c.http.Do(httpReq)
	if err != nil {
		return err
	}
	defer resp.Body.Close()
	if resp.StatusCode >= 400 {
		return fmt.Errorf("health check failed: HTTP %d", resp.StatusCode)
	}
	return nil
}

// do performs an HTTP request, optionally attaching the runtime bearer token,
// and decodes a JSON response body into out when provided.
func (c *Client) do(method, path string, body any, out any, auth bool) error {
	var reader io.Reader
	if body != nil {
		b, err := json.Marshal(body)
		if err != nil {
			return fmt.Errorf("marshal request: %w", err)
		}
		reader = bytes.NewReader(b)
	}

	req, err := http.NewRequest(method, c.baseURL+path, reader)
	if err != nil {
		return fmt.Errorf("build request: %w", err)
	}
	req.Header.Set("Accept", "application/json")
	if body != nil {
		req.Header.Set("Content-Type", "application/json")
	}
	if auth {
		if c.runtimeToken == "" {
			return fmt.Errorf("runtime token not set for authenticated call %s", path)
		}
		req.Header.Set("Authorization", "Bearer "+c.runtimeToken)
	}

	resp, err := c.http.Do(req)
	if err != nil {
		return fmt.Errorf("%s %s: %w", method, path, err)
	}
	defer resp.Body.Close()

	data, _ := io.ReadAll(resp.Body)

	if resp.StatusCode >= 400 {
		return fmt.Errorf("%s %s: HTTP %d: %s", method, path, resp.StatusCode, strings.TrimSpace(string(data)))
	}

	if out != nil && len(data) > 0 {
		if err := json.Unmarshal(data, out); err != nil {
			return fmt.Errorf("decode response: %w", err)
		}
	}
	return nil
}
