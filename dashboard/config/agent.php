<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Agent Configuration Defaults
    |--------------------------------------------------------------------------
    |
    | These values are returned to the agent during registration.
    | The agent uses these as default intervals for polling, heartbeat, etc.
    |
    */

    'default_poll_interval' => env('AGENT_DEFAULT_POLL_INTERVAL', 5),
    'default_heartbeat_interval' => env('AGENT_DEFAULT_HEARTBEAT_INTERVAL', 30),
    'default_metrics_interval' => env('AGENT_DEFAULT_METRICS_INTERVAL', 15),
    'default_service_monitor_interval' => env('AGENT_DEFAULT_SERVICE_MONITOR_INTERVAL', 30),

    /*
    |--------------------------------------------------------------------------
    | Command Timeout
    |--------------------------------------------------------------------------
    |
    | Maximum seconds a command can run before being marked as timeout.
    |
    */

    'command_timeout' => env('AGENT_COMMAND_TIMEOUT', 60),

    /*
    |--------------------------------------------------------------------------
    | Offline Threshold
    |--------------------------------------------------------------------------
    |
    | Seconds of no heartbeat before marking an agent as offline.
    |
    */

    'offline_threshold_seconds' => env('AGENT_OFFLINE_THRESHOLD_SECONDS', 120),

    /*
    |--------------------------------------------------------------------------
    | Data Retention Policy
    |--------------------------------------------------------------------------
    |
    | How long to keep metrics and audit logs before pruning.
    |
    */

    'metrics_retention_days' => env('METRICS_RETENTION_DAYS', 30),
    'audit_log_retention_days' => env('AUDIT_LOG_RETENTION_DAYS', 90),

    /*
    |--------------------------------------------------------------------------
    | Latest Agent Version
    |--------------------------------------------------------------------------
    |
    | The latest stable agent version. Agents query /api/agent/version to
    | check for updates. Bump this after rebuilding the agent binary.
    |
    */

    'latest_version' => env('AGENT_LATEST_VERSION', '1.0.7'),

];
