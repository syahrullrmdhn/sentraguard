# SentraGuard AgentOps

> Centralized Windows Service Control & Monitoring Platform powered by Laravel 13, PHP 8.4, MySQL, and a lightweight Windows Agent `.exe` built in Go.

[![Laravel](https://img.shields.io/badge/Laravel-13.x-red?style=flat-square&logo=laravel)](https://laravel.com)
[![PHP](https://img.shields.io/badge/PHP-8.4-777BB4?style=flat-square&logo=php)](https://php.net)
[![Go](https://img.shields.io/badge/Go-1.22+-00ADD8?style=flat-square&logo=go)](https://go.dev)
[![MySQL](https://img.shields.io/badge/MySQL-8.x-4479A1?style=flat-square&logo=mysql&logoColor=white)](https://mysql.com)
[![Redis](https://img.shields.io/badge/Redis-7.x-DC382D?style=flat-square&logo=redis&logoColor=white)](https://redis.io)
[![Architecture](https://img.shields.io/badge/Architecture-Pull%20Agent-2E7D32?style=flat-square)](https://github.com/syahrullrmdhn/sentraguard)
[![Status](https://img.shields.io/badge/Status-In%20Development-blue?style=flat-square)](https://github.com/syahrullrmdhn/sentraguard)
[![License](https://img.shields.io/badge/License-MIT-gray?style=flat-square)](LICENSE)

---

## Table of Contents

1. [Project Overview](#1-project-overview)
2. [Product Identity](#2-product-identity)
3. [Background & Problem Statement](#3-background--problem-statement)
4. [Objectives](#4-objectives)
5. [Architecture Overview](#5-architecture-overview)
6. [Why Pull-Based Agent](#6-why-pull-based-agent)
7. [Core Modules](#7-core-modules)
8. [Feature Scope](#8-feature-scope)
9. [Technology Stack](#9-technology-stack)
10. [UML Use Case](#10-uml-use-case)
11. [System Flow](#11-system-flow)
12. [Database Design](#12-database-design)
13. [MySQL Schema](#13-mysql-schema)
14. [API Contract](#14-api-contract)
15. [Windows Agent Specification](#15-windows-agent-specification)
16. [Security Design](#16-security-design)
17. [RBAC Matrix](#17-rbac-matrix)
18. [UI Structure](#18-ui-structure)
19. [Repository Structure](#19-repository-structure)
20. [Environment Configuration](#20-environment-configuration)
21. [Installation Guide](#21-installation-guide)
22. [Deployment Design](#22-deployment-design)
23. [Development Roadmap](#23-development-roadmap)
24. [MVP Acceptance Criteria](#24-mvp-acceptance-criteria)
25. [Risk Register](#25-risk-register)
26. [Contributing](#26-contributing)
27. [References](#27-references)

---

## 1. Project Overview

**SentraGuard AgentOps** is a centralized web platform for managing and monitoring Windows Services across multiple Windows Server machines using a lightweight Windows Agent.

The platform allows authorized users to:

- **Control** Windows Services (start, stop, restart, enable, disable) from a single dashboard
- **Monitor** server health metrics in near-realtime (CPU, RAM, Disk usage)
- **Detect** service state changes proactively without waiting for a manual sync
- **Audit** every administrative action with a full, immutable trail
- **Delegate** service operations safely using role-based access control

The Windows Agent is distributed as a single `.exe` binary and installed as a **Windows Service**. It initiates outbound HTTPS communication to the central dashboard, polls for pending commands, executes approved service operations locally, and reports the result back. No inbound port on the Windows Server is required.

---

## 2. Product Identity

| Item | Value |
|---|---|
| Project Name | `SentraGuard AgentOps` |
| Dashboard Name | `SentraGuard Console` |
| Windows Agent Name | `SentraGuard Agent` |
| Agent Binary | `sentraguard-agent.exe` |
| Installer Name | `SentraGuardAgentSetup.exe` |
| Windows Service Name | `SentraGuard Agent Service` |
| Architecture Pattern | Pull-Based Agent |
| Primary Backend | Laravel 13 |
| Runtime | PHP 8.4 |
| Database | MySQL 8.x |
| Agent Runtime | Go 1.22+ (single binary `.exe`) |

---

## 3. Background & Problem Statement

Managing Windows Services manually is operationally inefficient when an organization runs multiple Windows Servers. Common manual approaches include:

- Direct RDP access and using `services.msc`
- Manual PowerShell execution on each server
- Direct login to each server for routine restarts

This creates several operational problems:

| Problem | Impact |
|---|---|
| Too much RDP dependency | Slow response time; requires VPN or bastion |
| No centralized audit trail | Cannot track who changed what and when |
| No centralized status visibility | Operators must check each server individually |
| Hard to delegate safely | Risk of excessive privilege grants |
| No proactive service failure detection | Services can silently die without anyone noticing |
| No resource usage visibility | Cannot correlate service restarts with CPU/RAM spikes |

**SentraGuard AgentOps** solves these problems by introducing a controlled, auditable, and centralized Windows Service operations platform with built-in resource monitoring.

---

## 4. Objectives

### 4.1 Primary Objective

Build a centralized web dashboard that can safely manage and monitor Windows Services on multiple Windows Servers using a secure outbound Windows Agent.

### 4.2 Operational Objectives

- Reduce direct RDP usage for routine service operations
- Provide centralized, near-realtime visibility of Windows Service status
- Provide near-realtime CPU, RAM, and Disk usage per server
- Proactively detect service state changes (no manual sync required)
- Provide full audit logs for every service and administrative action
- Allow safe role-based delegation to operators
- Prevent arbitrary PowerShell execution from the dashboard
- Support multiple Windows Servers from a single dashboard

### 4.3 Security Objectives

- Do not expose custom inbound agent ports on Windows Servers
- Use HTTPS for all dashboard–agent communication
- Use hashed token storage in the backend database
- Store runtime tokens securely using Windows DPAPI on the agent side
- Restrict actions using a service allowlist and RBAC
- Make every administrative action traceable and immutable

---

## 5. Architecture Overview

SentraGuard AgentOps uses a **pull-based agent model**. The agent on each Windows Server initiates all communication outbound to the dashboard. The dashboard never connects directly to a Windows Server.

```
┌─────────────────────────────────────────────────────────┐
│                   SentraGuard Console                    │
│                                                          │
│   Admin / Operator ──► Laravel 13 API ──► MySQL DB      │
│                                │                         │
│                         Command Queue                    │
│                         Metrics Store                    │
│                         Audit Logs                       │
└────────────────────────────┬────────────────────────────┘
                             │ HTTPS (inbound from agent)
                             │
              ┌──────────────▼──────────────┐
              │     Windows Server           │
              │                             │
              │  SentraGuard Agent (.exe)   │
              │  ├─ Heartbeat loop          │
              │  ├─ Metrics collector       │
              │  ├─ Command poller          │
              │  ├─ Service monitor loop    │
              │  └─ PowerShell executor     │
              │         │                   │
              │  Windows Service Control    │
              │  Manager (SCM)              │
              └─────────────────────────────┘
```

### Main Data Flow

1. Admin issues a command or views metrics on the dashboard.
2. Backend validates user permission via RBAC.
3. Backend stores the command as `pending` in MySQL.
4. Windows Agent polls the backend over HTTPS every N seconds.
5. Agent receives pending command and validates it against the service allowlist.
6. Agent executes a predefined, safe PowerShell action.
7. Agent reports the result (stdout, stderr, exit code) back to the backend.
8. Dashboard updates command status and writes to the audit log.

### Metrics Flow (independent of command flow)

1. Agent collects CPU, RAM, and Disk metrics every 15 seconds using `gopsutil`.
2. Agent `POST`s metrics to `/api/agent/metrics`.
3. Backend stores them in `server_metrics`.
4. Dashboard Livewire component polls the backend every 10 seconds to render charts.

### Proactive Service Monitoring Flow (independent of command flow)

1. Agent monitors whitelisted services every 30 seconds.
2. If a service state changes (e.g., Running → Stopped), agent immediately posts a service event to `/api/agent/service-events`.
3. Backend updates `server_services`, writes an audit log entry, and can trigger a notification.

---

## 6. Why Pull-Based Agent

| Aspect | Pull-Based Agent | Direct Public IP Agent |
|---|---|---|
| Inbound port on Windows | Not required | Required |
| Public internet exposure | Lower | Higher |
| Works behind NAT / private network | Yes | Difficult |
| AWS Security Group complexity | Lower | Higher |
| Risk of internet scanning | Lower | Higher |
| Operational safety | Better | Requires strict hardening |
| Recommended for production | ✅ Yes | ⚠️ Only with strong controls |

**Recommended:**
```
Windows Agent ──outbound HTTPS──► SentraGuard Backend
```

**Not recommended:**
```
SentraGuard Dashboard ──► Public IP:Port ──► Windows Server
```

---

## 7. Core Modules

### 7.1 Dashboard Module

Central hub presenting operational information to human users.

- Total servers, online/offline agent count
- Pending / running / failed command count
- Recent service activity and failed operations
- Per-server CPU, RAM, Disk usage charts
- Audit log viewer with filtering and search

### 7.2 Server Management Module

Manages the inventory of monitored Windows Servers.

- Create and update server records
- Generate and revoke agent registration tokens
- View server detail (hostname, OS, IP, environment, agent version)
- View agent online/offline status and last heartbeat

### 7.3 Agent Management Module

Handles agent identity and lifecycle.

- Agent self-registration
- Periodic heartbeat processing and online/offline detection
- Runtime token lifecycle and revocation
- Agent version tracking
- Offline threshold alerting

### 7.4 Metrics Module *(added to MVP scope)*

Collects and displays server resource usage.

- Near-realtime CPU percentage per server
- RAM used / total per server
- Disk used / total per server
- Historical metrics chart (last 1h / 24h / 7d)
- Data retention and automatic pruning

### 7.5 Windows Service Module

Manages service inventory and operations.

- Sync Windows Service list from agent (on-demand and proactive)
- View service status and startup type
- Mark service as allowed / not allowed (allowlist management)
- Execute: Start, Stop, Restart, Enable startup, Disable startup
- Receive proactive service state change events from agent

### 7.6 Command Queue Module

Controls safe command execution.

- Create and queue pending commands
- Atomic command assignment to agent (prevents duplicate pickup)
- Track command lifecycle: pending → picked → running → success / failed / timeout / rejected / cancelled
- Store stdout / stderr / exit code from agent
- Handle command timeout and retry
- Cancel pending commands before pickup

### 7.7 Audit Log Module

Provides full operational traceability.

- Log user login / logout
- Log every server, agent, and allowlist change
- Log every service command request and result
- Log proactive service state changes
- Immutable records — no UPDATE or DELETE on audit_logs
- Filterable by user, server, action, and date range

---

## 8. Feature Scope

### 8.1 MVP Scope

| Feature | Status |
|---|---|
| User login + session management | MVP |
| Role-based access control (RBAC) | MVP |
| Server inventory (CRUD) | MVP |
| Agent token generation | MVP |
| Agent registration | MVP |
| Agent heartbeat + online/offline detection | MVP |
| Service synchronization (on-demand) | MVP |
| Proactive service state change detection | MVP *(added)* |
| Service allowlist management | MVP |
| Start / Stop / Restart service | MVP |
| Enable / Disable service startup | MVP |
| Command history and status tracking | MVP |
| Atomic command pickup (race-condition safe) | MVP *(added)* |
| CPU / RAM / Disk metrics collection | MVP *(added)* |
| Near-realtime metrics display | MVP *(added)* |
| Audit logs | MVP |
| Metrics data retention / auto-pruning | MVP *(added)* |

### 8.2 Future Scope

- Two-factor authentication (2FA)
- Approval workflow for stop/disable actions
- Telegram / WhatsApp / Email notification on service failure
- Scheduled service restart (cron-style)
- Agent auto-update mechanism
- Service dependency warning
- Multi-tenant organization support
- Realtime dashboard using WebSocket or SSE
- Signed and notarized agent binary
- MSI installer with GPO deployment support
- Long-polling command delivery (sub-second latency)

### 8.3 Explicit Non-Goals

For security reasons, the system will **never** provide:

- Arbitrary PowerShell or CMD execution from the dashboard
- Remote shell or terminal access
- File manager or file transfer
- Full remote desktop replacement
- Unrestricted Windows administration
- Raw script upload or execution

---

## 9. Technology Stack

### 9.1 Application Stack

| Layer | Technology | Notes |
|---|---|---|
| Backend Framework | Laravel 13 | Main application framework |
| PHP Runtime | PHP 8.4 | Standard runtime |
| Frontend | Blade + Livewire 3 + Tailwind CSS | Admin dashboard stack |
| Realtime UI | Livewire polling (`wire:poll`) | Near-realtime metrics, no WebSocket needed for MVP |
| Database | MySQL 8.x | Primary relational database |
| Cache | Redis 7.x | Session, cache, queue backend |
| Queue | Redis Queue | Async jobs and background processing |
| Web Server | Nginx 1.26+ | Reverse proxy and PHP-FPM gateway |
| Process Manager | Supervisor | Queue worker management |
| Agent Language | Go 1.22+ | Single binary `.exe` for Windows |
| Agent Metrics | `gopsutil` v3 | CPU, RAM, Disk collection |
| Agent Config | YAML + Windows DPAPI | Config file + secure token storage |
| Agent Installer | Inno Setup or WiX Toolset | Windows installer packaging; supports silent install |
| Transport | HTTPS REST API | All agent–dashboard communication |
| Containerization | Docker Compose | Dashboard deployment |

### 9.2 Version Standards

| Component | Target Version |
|---|---|
| Laravel | `13.x` |
| PHP | `8.4` |
| Go | `1.22+` |
| MySQL | `8.x` |
| Redis | `7.x` |
| Node.js | `22.x LTS` |
| Nginx | `1.26+` |
| gopsutil | `v3` |

---

## 10. UML Use Case

```
Actors:
  Super Admin  ─── Full system control
  Admin        ─── Server and service management
  Operator     ─── Service operations only
  Viewer       ─── Read-only access
  Agent        ─── Automated system actor

Super Admin:  Manage Users, Manage Roles, all Admin capabilities
Admin:        Manage Servers, Generate/Revoke Token, Manage Allowlist,
              Service Control (start/stop/restart/enable/disable), View Audit Logs
Operator:     Start / Stop / Restart (whitelisted services only), Retry/Cancel Command
Viewer:       View Services, View Metrics, View Servers
Agent:        Register, Heartbeat, Poll Command, Submit Result,
              Sync Services, Post Metrics, Post Service Events
```

---

## 11. System Flow

### 11.1 Agent Registration Flow

```
Admin                Dashboard               MySQL               Agent
  │                      │                     │                   │
  ├── Create server ─────►│                     │                   │
  │                      ├── Store token hash ─►│                   │
  │                      │                     │                   │
  │                      │         (Admin installs agent with URL + token)
  │                      │                     │                   │
  │                      │◄───────── POST /api/agent/register ──────┤
  │                      ├── Validate token ───►│                   │
  │                      ├── Upsert agent ──────►│                   │
  │                      ├── Return agent_uid + runtime_token ──────►│
  │                      │                     │                   │
  │                      │◄────────────────── Heartbeat starts ─────┤
```

### 11.2 Command Execution Flow

```
User             Dashboard               MySQL               Agent                 SCM
  │                 │                      │                   │                    │
  ├── Click ─────►  │                      │                   │                    │
  │                 ├── Check RBAC ──────► │                   │                    │
  │                 ├── Create pending ──► │                   │                    │
  │                 │                      │                   │                    │
  │                 │          ◄─── Poll (every N seconds) ────┤                    │
  │                 ├── lockForUpdate() ──►│                   │                    │
  │                 ├── Mark picked ──────►│                   │                    │
  │                 ├── Return command ──────────────────────► │                    │
  │                 │                      │                   ├── Validate ──────► │
  │                 │                      │                   ├── Exec PS ────────►│
  │                 │                      │                   │◄──── Result ───────┤
  │                 │◄────────── Submit result ────────────────┤                    │
  │                 ├── Mark success/failed►│                   │                    │
  │                 ├── Write audit log ──►│                   │                    │
```

### 11.3 Metrics Collection Flow

```
Agent                              Dashboard               MySQL
  │                                    │                     │
  ├── Collect CPU/RAM/Disk (gopsutil)   │                     │
  ├── POST /api/agent/metrics ─────────►│                     │
  │                                    ├── INSERT server_metrics ►│
  │                                    │                     │
  │          (Livewire polling every 10s)                     │
  │                                    │◄── SELECT latest ───┤
  │                                    ├── Render chart ──────►│ (UI)
```

### 11.4 Proactive Service Monitoring Flow

```
Agent                              Dashboard               MySQL
  │                                    │                     │
  ├── Monitor whitelisted services      │                     │
  │   (every 30 seconds)               │                     │
  │                                    │                     │
  ├── Detect: Running → Stopped         │                     │
  ├── POST /api/agent/service-events ──►│                     │
  │                                    ├── UPDATE server_services►│
  │                                    ├── INSERT audit_log ─►│
  │                                    ├── Trigger notification│
```

### 11.5 Agent Status State Machine

```
                    ┌──────────┐
         ┌──────────│ inactive │◄──────────────┐
         │          └──────────┘               │
         │ register                            │ uninstall
         ▼                                     │
    ┌────────┐  heartbeat timeout  ┌─────────┐ │
    │ online │────────────────────►│ offline │─┤
    │        │◄────────────────────│         │ │
    └────────┘  heartbeat received └─────────┘ │
         │                              │       │
         │ token revoked                │       │
         ▼                              │       │
    ┌─────────┐◄─────────────────────────┘      │
    │ revoked │─────────────────────────────────┘
    └─────────┘
```

### 11.6 Command Status State Machine

```
             ┌─────────┐
             │ pending │◄────── retry
             └────┬────┘
      user cancel │ agent polls (lockForUpdate)
                  ▼
          ┌────────────┐ invalid service
          │   picked   │──────────────────► rejected
          └─────┬──────┘
                │ execution starts
                ▼
          ┌─────────┐
          │ running │
          └────┬────┘
               │
    ┌──────────┼─────────────┐
    ▼          ▼             ▼
 success     failed       timeout
(exit=0)   (exit≠0)   (exceeded limit)
```

---

## 12. Database Design

### 12.1 Entity Relationship Overview

```
users ──────────────── service_commands (requested_by)
users ──────────────── audit_logs (actor)
users ──── role_user ── roles ── permission_role ── permissions

servers ─────────────── agents
servers ─────────────── server_services
servers ─────────────── server_metrics       ← new
servers ─────────────── service_commands
servers ─────────────── audit_logs
```

### 12.2 Entity Summary

| Table | Purpose |
|---|---|
| `users` | Dashboard users |
| `roles` | Role grouping |
| `permissions` | Fine-grained permission keys |
| `role_user` | User ↔ role pivot |
| `permission_role` | Role ↔ permission pivot |
| `servers` | Managed Windows Server inventory |
| `agents` | Installed agent identity and lifecycle |
| `server_services` | Windows Services synced from agent |
| `server_metrics` | CPU / RAM / Disk time-series data |
| `service_commands` | Command queue and execution result |
| `audit_logs` | Immutable operational trace |

---

## 13. MySQL Schema

```sql
-- Users
CREATE TABLE users (
    id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name          VARCHAR(150) NOT NULL,
    email         VARCHAR(190) NOT NULL UNIQUE,
    password      VARCHAR(255) NOT NULL,
    status        ENUM('active','inactive','locked') NOT NULL DEFAULT 'active',
    last_login_at TIMESTAMP NULL,
    created_at    TIMESTAMP NULL,
    updated_at    TIMESTAMP NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Roles
CREATE TABLE roles (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(100) NOT NULL UNIQUE,
    description TEXT NULL,
    created_at  TIMESTAMP NULL,
    updated_at  TIMESTAMP NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Permissions
CREATE TABLE permissions (
    id             BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    permission_key VARCHAR(150) NOT NULL UNIQUE,
    name           VARCHAR(150) NOT NULL,
    description    TEXT NULL,
    created_at     TIMESTAMP NULL,
    updated_at     TIMESTAMP NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Role ↔ User pivot
CREATE TABLE role_user (
    id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id    BIGINT UNSIGNED NOT NULL,
    role_id    BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NULL,
    UNIQUE KEY role_user_unique (user_id, role_id),
    CONSTRAINT fk_role_user_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_role_user_role FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Role ↔ Permission pivot
CREATE TABLE permission_role (
    id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    role_id       BIGINT UNSIGNED NOT NULL,
    permission_id BIGINT UNSIGNED NOT NULL,
    created_at    TIMESTAMP NULL,
    UNIQUE KEY permission_role_unique (role_id, permission_id),
    CONSTRAINT fk_permission_role_role       FOREIGN KEY (role_id)       REFERENCES roles(id)       ON DELETE CASCADE,
    CONSTRAINT fk_permission_role_permission FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Servers
CREATE TABLE servers (
    id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name         VARCHAR(150) NOT NULL,
    hostname     VARCHAR(150) NULL,
    machine_id   VARCHAR(255) NULL UNIQUE,
    os_name      VARCHAR(150) NULL,
    os_version   VARCHAR(100) NULL,
    environment  ENUM('development','staging','production') NOT NULL DEFAULT 'production',
    public_ip    VARCHAR(45) NULL,
    private_ip   VARCHAR(45) NULL,
    status       ENUM('online','offline','inactive','error') NOT NULL DEFAULT 'inactive',
    last_seen_at TIMESTAMP NULL,
    created_at   TIMESTAMP NULL,
    updated_at   TIMESTAMP NULL,
    INDEX idx_servers_status (status),
    INDEX idx_servers_environment (environment),
    INDEX idx_servers_last_seen_at (last_seen_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Agents
CREATE TABLE agents (
    id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    server_id           BIGINT UNSIGNED NOT NULL,
    agent_uid           VARCHAR(100) NOT NULL UNIQUE,
    token_hash          TEXT NOT NULL,
    version             VARCHAR(50) NULL,
    status              ENUM('inactive','online','offline','revoked','error') NOT NULL DEFAULT 'inactive',
    last_heartbeat_at   TIMESTAMP NULL,
    installed_at        TIMESTAMP NULL,
    revoked_at          TIMESTAMP NULL,
    created_at          TIMESTAMP NULL,
    updated_at          TIMESTAMP NULL,
    CONSTRAINT fk_agents_server FOREIGN KEY (server_id) REFERENCES servers(id) ON DELETE CASCADE,
    INDEX idx_agents_status (status),
    INDEX idx_agents_last_heartbeat_at (last_heartbeat_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Windows Services (synced from agent)
CREATE TABLE server_services (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    server_id       BIGINT UNSIGNED NOT NULL,
    service_name    VARCHAR(200) NOT NULL,
    display_name    VARCHAR(255) NULL,
    status          VARCHAR(50) NULL,
    startup_type    VARCHAR(50) NULL,
    is_allowed      BOOLEAN NOT NULL DEFAULT FALSE,
    last_checked_at TIMESTAMP NULL,
    created_at      TIMESTAMP NULL,
    updated_at      TIMESTAMP NULL,
    CONSTRAINT fk_server_services_server FOREIGN KEY (server_id) REFERENCES servers(id) ON DELETE CASCADE,
    UNIQUE KEY server_service_unique (server_id, service_name),
    INDEX idx_server_services_status (status),
    INDEX idx_server_services_allowed (is_allowed)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Server Metrics (CPU / RAM / Disk time-series)
CREATE TABLE server_metrics (
    id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    server_id     BIGINT UNSIGNED NOT NULL,
    cpu_percent   DECIMAL(5,2) NOT NULL DEFAULT 0,
    ram_used_mb   BIGINT UNSIGNED NOT NULL DEFAULT 0,
    ram_total_mb  BIGINT UNSIGNED NOT NULL DEFAULT 0,
    disk_used_gb  DECIMAL(10,2) NOT NULL DEFAULT 0,
    disk_total_gb DECIMAL(10,2) NOT NULL DEFAULT 0,
    collected_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_server_metrics_server FOREIGN KEY (server_id) REFERENCES servers(id) ON DELETE CASCADE,
    INDEX idx_server_metrics_lookup (server_id, collected_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Service Commands (command queue)
CREATE TABLE service_commands (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    server_id       BIGINT UNSIGNED NOT NULL,
    service_name    VARCHAR(200) NOT NULL,
    action          ENUM(
                        'start_service',
                        'stop_service',
                        'restart_service',
                        'enable_service',
                        'disable_service',
                        'get_service_status',
                        'sync_services'
                    ) NOT NULL,
    status          ENUM(
                        'pending',
                        'picked',
                        'running',
                        'success',
                        'failed',
                        'timeout',
                        'rejected',
                        'cancelled'
                    ) NOT NULL DEFAULT 'pending',
    requested_by    BIGINT UNSIGNED NULL,
    picked_at       TIMESTAMP NULL,
    executed_at     TIMESTAMP NULL,
    finished_at     TIMESTAMP NULL,
    timeout_seconds INT UNSIGNED NOT NULL DEFAULT 60,
    exit_code       INT NULL,
    stdout          LONGTEXT NULL,
    stderr          LONGTEXT NULL,
    error_message   LONGTEXT NULL,
    created_at      TIMESTAMP NULL,
    updated_at      TIMESTAMP NULL,
    CONSTRAINT fk_service_commands_server FOREIGN KEY (server_id)    REFERENCES servers(id) ON DELETE CASCADE,
    CONSTRAINT fk_service_commands_user   FOREIGN KEY (requested_by) REFERENCES users(id)   ON DELETE SET NULL,
    INDEX idx_service_commands_status (status),
    INDEX idx_service_commands_server_status (server_id, status),
    INDEX idx_service_commands_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Audit Logs (immutable — no UPDATE or DELETE)
CREATE TABLE audit_logs (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     BIGINT UNSIGNED NULL,
    server_id   BIGINT UNSIGNED NULL,
    action      VARCHAR(100) NOT NULL,
    target_type VARCHAR(100) NULL,
    target_id   VARCHAR(100) NULL,
    ip_address  VARCHAR(45) NULL,
    user_agent  TEXT NULL,
    description TEXT NULL,
    metadata    JSON NULL,
    created_at  TIMESTAMP NULL,
    CONSTRAINT fk_audit_logs_user   FOREIGN KEY (user_id)   REFERENCES users(id)   ON DELETE SET NULL,
    CONSTRAINT fk_audit_logs_server FOREIGN KEY (server_id) REFERENCES servers(id) ON DELETE SET NULL,
    INDEX idx_audit_logs_action (action),
    INDEX idx_audit_logs_created_at (created_at),
    INDEX idx_audit_logs_user_id (user_id),
    INDEX idx_audit_logs_server_id (server_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 14. API Contract

### 14.1 Dashboard API (User-authenticated)

All endpoints require `Authorization: Bearer <USER_TOKEN>`.

#### GET /api/servers

List all managed servers with their agent status.

**Response:**
```json
{
  "data": [
    {
      "id": 1,
      "name": "WIN-AWS-01",
      "hostname": "WIN-AWS-01",
      "environment": "production",
      "public_ip": "13.x.x.x",
      "private_ip": "10.0.1.10",
      "status": "online",
      "agent_version": "1.2.0",
      "last_seen_at": "2026-06-20T14:20:00+07:00"
    }
  ]
}
```

#### POST /api/servers

Create a new server record and generate a registration token.

**Request:**
```json
{
  "name": "WIN-AWS-01",
  "environment": "production"
}
```

**Response:**
```json
{
  "success": true,
  "server_id": 1,
  "agent_token": "AGT_xxxxxxxxxxxxxxxxxxxxxxxxx"
}
```

#### GET /api/servers/{server_id}/services

List all synced Windows Services for a server.

#### GET /api/servers/{server_id}/metrics/latest

Get the most recent metric snapshot for a server.

**Response:**
```json
{
  "server_id": 1,
  "cpu_percent": 23.5,
  "ram_used_mb": 4096,
  "ram_total_mb": 16384,
  "disk_used_gb": 120.4,
  "disk_total_gb": 500.0,
  "collected_at": "2026-06-20T14:20:00+07:00"
}
```

#### GET /api/servers/{server_id}/metrics/history

Get metric history for charting. Supports `?period=1h|24h|7d`.

#### POST /api/servers/{server_id}/services/{service_name}/commands

Issue a command for a specific service.

**Request:**
```json
{
  "action": "restart_service",
  "timeout_seconds": 60
}
```

**Response:**
```json
{
  "success": true,
  "command_id": 1001,
  "status": "pending"
}
```

#### DELETE /api/servers/{server_id}/commands/{command_id}

Cancel a pending command.

#### POST /api/servers/{server_id}/commands/{command_id}/retry

Retry a failed command.

---

### 14.2 Agent API (Agent-authenticated)

All endpoints (except register) require `Authorization: Bearer <RUNTIME_TOKEN>`.

#### POST /api/agent/register

Agent registers itself using the one-time installation token.

**Request:**
```json
{
  "token": "AGT_xxxxxxxxxxxxxxxxxxxxxxxxx",
  "hostname": "WIN-AWS-01",
  "machine_id": "A1B2C3D4E5F6",
  "os_name": "Windows Server 2022",
  "os_version": "10.0.20348",
  "agent_version": "1.2.0",
  "private_ip": "10.0.1.10",
  "public_ip": "13.x.x.x"
}
```

**Response:**
```json
{
  "success": true,
  "agent_uid": "agt_01HXABCDEF",
  "server_id": 1,
  "runtime_token": "RUNTIME_TOKEN_HERE",
  "poll_interval_seconds": 5,
  "heartbeat_interval_seconds": 30,
  "metrics_interval_seconds": 15,
  "service_monitor_interval_seconds": 30
}
```

#### POST /api/agent/heartbeat

Periodic liveness signal from the agent.

**Request:**
```json
{
  "agent_uid": "agt_01HXABCDEF",
  "status": "online",
  "agent_version": "1.2.0",
  "timestamp": "2026-06-20T14:20:00+07:00"
}
```

#### GET /api/agent/commands/poll

Agent polls for the next pending command. Uses `SELECT ... FOR UPDATE` on the backend to prevent duplicate pickup.

**Response (command available):**
```json
{
  "has_command": true,
  "command": {
    "id": 1001,
    "action": "restart_service",
    "service_name": "W3SVC",
    "timeout_seconds": 60
  }
}
```

**Response (no command):**
```json
{
  "has_command": false
}
```

#### POST /api/agent/commands/{command_id}/result

Agent submits the result of an executed command.

**Request:**
```json
{
  "status": "success",
  "exit_code": 0,
  "stdout": "Service 'W3SVC' restarted successfully.",
  "stderr": "",
  "service_status": "Running",
  "startup_type": "Automatic",
  "finished_at": "2026-06-20T14:21:05+07:00"
}
```

#### POST /api/agent/services/sync

Agent pushes the full Windows Service list.

**Request:**
```json
{
  "services": [
    {
      "service_name": "W3SVC",
      "display_name": "World Wide Web Publishing Service",
      "status": "Running",
      "startup_type": "Automatic"
    }
  ]
}
```

#### POST /api/agent/metrics

Agent pushes a resource usage snapshot.

**Request:**
```json
{
  "agent_uid": "agt_01HXABCDEF",
  "cpu_percent": 23.5,
  "ram_used_mb": 4096,
  "ram_total_mb": 16384,
  "disk_used_gb": 120.4,
  "disk_total_gb": 500.0,
  "collected_at": "2026-06-20T14:20:00+07:00"
}
```

#### POST /api/agent/service-events

Agent reports a proactive service state change (without a command being issued).

**Request:**
```json
{
  "agent_uid": "agt_01HXABCDEF",
  "service_name": "W3SVC",
  "previous_status": "Running",
  "current_status": "Stopped",
  "detected_at": "2026-06-20T14:25:00+07:00"
}
```

---

## 15. Windows Agent Specification

### 15.1 Agent Responsibilities

| Responsibility | Description |
|---|---|
| Self-registration | Register with backend using one-time token |
| Heartbeat | Send periodic liveness signal every 30s |
| Metrics collection | Collect CPU / RAM / Disk every 15s via `gopsutil` |
| Command polling | Poll pending commands every 5s with safe atomic pickup |
| Service monitoring | Monitor whitelisted services every 30s, report state changes |
| Command execution | Execute predefined PowerShell actions only |
| Result reporting | Submit stdout / stderr / exit code to backend |
| Local logging | Write structured logs to `C:\ProgramData\SentraGuard Agent\logs\` |
| Secure token storage | Store runtime token in Windows Credential Manager / DPAPI |

### 15.2 Agent CLI

```bash
# Install and register the agent as a Windows Service
sentraguard-agent.exe install --server https://console.example.com --token AGT_xxx

# Uninstall the agent service
sentraguard-agent.exe uninstall

# Service control
sentraguard-agent.exe start
sentraguard-agent.exe stop
sentraguard-agent.exe restart
sentraguard-agent.exe status

# Diagnostics
sentraguard-agent.exe test-connection
sentraguard-agent.exe sync-services
sentraguard-agent.exe version
```

**Silent install for GPO/deployment tool:**
```bash
SentraGuardAgentSetup.exe /SILENT /server="https://console.example.com" /token="AGT_xxx"
```

### 15.3 Agent Directory Layout

```
C:\Program Files\SentraGuard Agent\
    sentraguard-agent.exe

C:\ProgramData\SentraGuard Agent\
    config.yaml
    logs\
        agent.log
        agent-YYYY-MM-DD.log   (rotated daily)
```

### 15.4 Agent Config (`config.yaml`)

```yaml
server_url: "https://console.example.com"
agent_uid: "agt_01HXABCDEF"

# runtime_token is NOT stored here in production.
# It is stored in Windows Credential Manager via DPAPI.
# This field is only used in development mode.
# runtime_token: ""

poll_interval_seconds: 5
heartbeat_interval_seconds: 30
metrics_interval_seconds: 15
service_monitor_interval_seconds: 30
command_timeout_seconds: 60

log_level: "info"
log_max_size_mb: 50
log_max_backups: 7

allowed_services:
  - W3SVC
  - MSSQLSERVER
  - MyAppService
  - Tomcat9
```

### 15.5 Metrics Collection (Go / gopsutil)

```go
import (
    "github.com/shirou/gopsutil/v3/cpu"
    "github.com/shirou/gopsutil/v3/mem"
    "github.com/shirou/gopsutil/v3/disk"
    "time"
)

func collectMetrics() MetricsPayload {
    cpuPct, _   := cpu.Percent(time.Second, false)
    memStat, _  := mem.VirtualMemory()
    diskStat, _ := disk.Usage("C:\\")

    return MetricsPayload{
        CpuPercent:  cpuPct[0],
        RamUsedMB:   memStat.Used / 1024 / 1024,
        RamTotalMB:  memStat.Total / 1024 / 1024,
        DiskUsedGB:  float64(diskStat.Used) / 1024 / 1024 / 1024,
        DiskTotalGB: float64(diskStat.Total) / 1024 / 1024 / 1024,
        CollectedAt: time.Now(),
    }
}
```

### 15.6 Proactive Service Monitoring (Go)

```go
func monitorServices(interval time.Duration, allowedServices []string) {
    ticker := time.NewTicker(interval)
    previousStates := map[string]string{}

    for range ticker.C {
        for _, svc := range allowedServices {
            current := getServiceStatus(svc) // queries SCM
            prev, known := previousStates[svc]

            if known && current != prev {
                // State changed — report immediately
                postServiceEvent(ServiceEvent{
                    ServiceName:    svc,
                    PreviousStatus: prev,
                    CurrentStatus:  current,
                    DetectedAt:     time.Now(),
                })
            }
            previousStates[svc] = current
        }
    }
}
```

### 15.7 Secure Token Storage (Windows DPAPI)

```go
// On registration: encrypt and save to Windows Credential Manager
func saveRuntimeToken(token string) error {
    encrypted := dpapi.Encrypt([]byte(token))
    return wincred.Set("SentraGuardAgent", "runtime_token", encrypted)
}

// On agent start: load and decrypt
func loadRuntimeToken() (string, error) {
    encrypted, err := wincred.Get("SentraGuardAgent", "runtime_token")
    if err != nil { return "", err }
    return string(dpapi.Decrypt(encrypted)), nil
}
```

### 15.8 PowerShell Action Mapping

The dashboard never sends raw PowerShell. The agent maps safe actions to predefined commands:

| Action | PowerShell |
|---|---|
| `start_service` | `Start-Service -Name "<service>"` |
| `stop_service` | `Stop-Service -Name "<service>" -Force` |
| `restart_service` | `Restart-Service -Name "<service>" -Force` |
| `enable_service` | `Set-Service -Name "<service>" -StartupType Automatic` |
| `disable_service` | `Stop-Service -Name "<service>" -Force; Set-Service -Name "<service>" -StartupType Disabled` |
| `get_service_status` | `Get-Service -Name "<service>" \| Select-Object Status,StartType` |
| `sync_services` | `Get-Service \| Select-Object Name,DisplayName,Status,StartType` |

### 15.9 Agent Build Command

```bash
GOOS=windows GOARCH=amd64 go build \
  -ldflags="-s -w -X main.Version=1.2.0" \
  -o sentraguard-agent.exe \
  ./cmd/sentraguard-agent
```

---

## 16. Security Design

### 16.1 Security Principles

- HTTPS-only — no plaintext communication
- No inbound custom agent port on Windows Server
- No arbitrary shell or PowerShell execution from dashboard
- Registration token is one-time use; stored as bcrypt hash in database
- Runtime token stored in Windows Credential Manager (DPAPI-protected), never in plain text
- Service commands restricted to a predefined action enum
- Service name validated against the server-specific allowlist before execution
- All actions are audited with actor, timestamp, and IP address
- `audit_logs` table is append-only — no UPDATE or DELETE ever issued against it
- Atomic command pickup via `SELECT ... FOR UPDATE` prevents duplicate execution

### 16.2 Token Lifecycle

```
1. Admin creates server record
2. Backend generates one-time registration token (stored as bcrypt hash)
3. Admin provides token to agent installer
4. Agent POSTs token to /api/agent/register
5. Backend validates token hash → marks token as used → returns runtime_token
6. Agent encrypts runtime_token via Windows DPAPI → stores in Windows Credential Manager
7. Runtime token used for all subsequent API calls
8. Admin can revoke runtime token from dashboard at any time
9. Revoked agent's API calls return 401 — agent stops polling
```

### 16.3 Command Safety Rules

**Allowed action enum:**
```
start_service, stop_service, restart_service,
enable_service, disable_service,
get_service_status, sync_services
```

**Explicitly forbidden:**
```
POST /api/agent/execute-powershell    ← Never implement
POST /api/agent/run-script            ← Never implement
POST /api/agent/execute-command       ← Never implement
```

### 16.4 Service Allowlist

Only services explicitly allowed by an Admin can be controlled.

**Example allowed services:**
```
W3SVC, MSSQLSERVER, MyAppService, Tomcat9, Redis, nginx
```

**Services that should never be in the allowlist:**
```
WinDefend, EventLog, RpcSs, SamSs, LanmanServer, TermService,
CryptSvc, wuauserv, BFE, MpsSvc, Netlogon
```

The allowlist is enforced at **two layers**:
1. **Backend**: `is_allowed = true` check before creating a command
2. **Agent**: validates service name against local `allowed_services` config before executing

---

## 17. RBAC Matrix

| Action | Super Admin | Admin | Operator | Viewer |
|---|:---:|:---:|:---:|:---:|
| View Dashboard | ✅ | ✅ | ✅ | ✅ |
| View Server List | ✅ | ✅ | ✅ | ✅ |
| View Service List | ✅ | ✅ | ✅ | ✅ |
| View Metrics | ✅ | ✅ | ✅ | ✅ |
| Start Service | ✅ | ✅ | ✅ | ❌ |
| Stop Service | ✅ | ✅ | ✅ | ❌ |
| Restart Service | ✅ | ✅ | ✅ | ❌ |
| Retry / Cancel Command | ✅ | ✅ | ✅ | ❌ |
| Enable Service Startup | ✅ | ✅ | ❌ | ❌ |
| Disable Service Startup | ✅ | ✅ | ❌ | ❌ |
| Manage Service Allowlist | ✅ | ✅ | ❌ | ❌ |
| Add / Edit Server | ✅ | ✅ | ❌ | ❌ |
| Generate Agent Token | ✅ | ✅ | ❌ | ❌ |
| Revoke Agent | ✅ | ✅ | ❌ | ❌ |
| View Audit Logs | ✅ | ✅ | ❌ | ❌ |
| Manage Users | ✅ | ❌ | ❌ | ❌ |
| Manage Roles & Permissions | ✅ | ❌ | ❌ | ❌ |
| System Settings | ✅ | ❌ | ❌ | ❌ |

---

## 18. UI Structure

### 18.1 Sidebar Navigation

```
Dashboard
Servers
  └─ [Server Detail]
       ├─ Overview
       ├─ Services
       ├─ Metrics
       └─ Command History
Commands (global queue)
Audit Logs
Users
Roles & Permissions
Settings
```

### 18.2 Dashboard Page

**Stat cards (top row):**
- Total Servers
- Online Agents
- Offline Agents
- Pending Commands
- Failed Commands (today)
- Service Events (today)

**Charts:**
- CPU usage across all servers (sparklines)
- Command success/failure rate (last 24h)

**Tables:**
- Recent Commands
- Offline Agents
- Recent Service Events (proactive alerts)
- Recent Audit Logs

### 18.3 Server Detail Page

**Tabs:**

**Overview** — hostname, OS, IP, environment, agent version, status, last heartbeat

**Metrics** — realtime charts (Livewire `wire:poll.10000ms`):
- CPU % over time
- RAM used / total
- Disk used / total

**Services** — table columns: Name, Display Name, Status, Startup Type, Allowed, Actions

| Service | Display Name | Status | Startup | Allowed | Actions |
|---|---|---|---|---|---|
| W3SVC | World Wide Web Publishing Service | Running | Automatic | ✅ | Stop / Restart / Disable |
| MSSQLSERVER | SQL Server | Running | Automatic | ✅ | Stop / Restart / Disable |
| Spooler | Print Spooler | Stopped | Manual | ❌ | — |

**Command History** — table: Action, Service, Status, Requested By, Requested At, Finished At, Exit Code

### 18.4 Metrics Component (Livewire)

```php
// app/Livewire/ServerMetrics.php
class ServerMetrics extends Component
{
    public Server $server;
    public array $history = [];

    #[On('timer-refresh')]
    public function refresh(): void
    {
        $this->history = ServerMetric::where('server_id', $this->server->id)
            ->where('collected_at', '>=', now()->subHour())
            ->orderBy('collected_at')
            ->get(['cpu_percent', 'ram_used_mb', 'disk_used_gb', 'collected_at'])
            ->toArray();
    }

    public function render()
    {
        return view('livewire.server-metrics');
    }
}
```

```html
<!-- resources/views/livewire/server-metrics.blade.php -->
<div wire:poll.10000ms="refresh">
    <canvas id="cpu-chart" data-history="{{ json_encode($history) }}"></canvas>
</div>
```

---

## 19. Repository Structure

```
sentraguard-agentops/
├── dashboard/                          # Laravel 13 application
│   ├── app/
│   │   ├── Console/Commands/
│   │   │   └── PruneMetrics.php        # Retention cleanup job
│   │   ├── Http/Controllers/
│   │   │   ├── Api/
│   │   │   │   ├── AgentController.php
│   │   │   │   ├── CommandController.php
│   │   │   │   ├── MetricsController.php
│   │   │   │   ├── ServerController.php
│   │   │   │   └── ServiceController.php
│   │   │   └── Dashboard/
│   │   ├── Livewire/
│   │   │   ├── ServerMetrics.php
│   │   │   ├── ServiceTable.php
│   │   │   └── CommandQueue.php
│   │   ├── Models/
│   │   │   ├── Agent.php
│   │   │   ├── AuditLog.php
│   │   │   ├── Server.php
│   │   │   ├── ServerMetric.php
│   │   │   ├── ServerService.php
│   │   │   ├── ServiceCommand.php
│   │   │   └── User.php
│   │   ├── Policies/
│   │   │   ├── ServiceCommandPolicy.php
│   │   │   └── ServerPolicy.php
│   │   └── Services/
│   │       ├── AgentTokenService.php
│   │       ├── CommandService.php
│   │       └── AuditService.php
│   ├── database/
│   │   ├── migrations/
│   │   └── seeders/
│   ├── routes/
│   │   ├── web.php
│   │   └── api.php
│   ├── docker-compose.yml
│   └── .env.example
│
├── agent/                              # Go Windows Agent
│   ├── cmd/
│   │   └── sentraguard-agent/
│   │       └── main.go
│   ├── internal/
│   │   ├── api/                        # HTTP client to dashboard
│   │   │   ├── client.go
│   │   │   ├── register.go
│   │   │   ├── heartbeat.go
│   │   │   ├── poll.go
│   │   │   ├── metrics.go
│   │   │   └── events.go
│   │   ├── config/
│   │   │   └── config.go
│   │   ├── executor/                   # PowerShell executor
│   │   │   ├── executor.go
│   │   │   └── actions.go
│   │   ├── metrics/                    # gopsutil collector
│   │   │   └── collector.go
│   │   ├── monitor/                    # Proactive service monitor
│   │   │   └── monitor.go
│   │   ├── security/                   # DPAPI / Credential Manager
│   │   │   └── token.go
│   │   ├── service/                    # Windows Service wrapper
│   │   │   └── service.go
│   │   └── logger/
│   │       └── logger.go
│   ├── build/
│   │   └── build.bat
│   ├── config.example.yaml
│   ├── go.mod
│   └── go.sum
│
├── installer/
│   ├── inno-setup/
│   │   └── setup.iss
│   └── wix/
│       └── setup.wxs
│
├── docs/
│   ├── architecture.md
│   ├── api-contract.md
│   ├── security.md
│   ├── deployment.md
│   └── agent-installation.md
│
├── .gitignore
├── LICENSE
└── README.md
```

---

## 20. Environment Configuration

### 20.1 Laravel `.env`

```dotenv
APP_NAME="SentraGuard AgentOps"
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=https://console.example.com
APP_TIMEZONE=Asia/Jakarta

LOG_CHANNEL=stack
LOG_LEVEL=info

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=sentraguard
DB_USERNAME=sentraguard
DB_PASSWORD=change_this_strong_password

CACHE_STORE=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis

REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

# Agent configuration defaults (returned on registration)
AGENT_DEFAULT_POLL_INTERVAL=5
AGENT_DEFAULT_HEARTBEAT_INTERVAL=30
AGENT_DEFAULT_METRICS_INTERVAL=15
AGENT_DEFAULT_SERVICE_MONITOR_INTERVAL=30
AGENT_COMMAND_TIMEOUT=60
AGENT_OFFLINE_THRESHOLD_SECONDS=120

# Retention policy
METRICS_RETENTION_DAYS=30
AUDIT_LOG_RETENTION_DAYS=90
```

### 20.2 Agent `config.yaml`

```yaml
server_url: "https://console.example.com"
agent_uid: "agt_01HXABCDEF"

# runtime_token is stored in Windows Credential Manager in production.
# Uncomment only for local development:
# runtime_token: "dev-token-here"

poll_interval_seconds: 5
heartbeat_interval_seconds: 30
metrics_interval_seconds: 15
service_monitor_interval_seconds: 30
command_timeout_seconds: 60

log_level: "info"
log_max_size_mb: 50
log_max_backups: 7

allowed_services:
  - W3SVC
  - MSSQLSERVER
  - MyAppService
```

### 20.3 Docker Compose

```yaml
services:
  sentraguard-app:
    build: .
    environment:
      - APP_ENV=production
    volumes:
      - .:/var/www/html
    depends_on:
      - sentraguard-mysql
      - sentraguard-redis

  sentraguard-nginx:
    image: nginx:1.26-alpine
    ports:
      - "443:443"
    volumes:
      - ./docker/nginx.conf:/etc/nginx/conf.d/default.conf

  sentraguard-worker:
    build: .
    command: php artisan queue:work redis --sleep=3 --tries=3

  sentraguard-scheduler:
    build: .
    command: php artisan schedule:work

  sentraguard-mysql:
    image: mysql:8.0
    environment:
      MYSQL_DATABASE: sentraguard
      MYSQL_USER: sentraguard
      MYSQL_PASSWORD: change_this_strong_password
      MYSQL_ROOT_PASSWORD: root_change_this_too
    volumes:
      - mysql_data:/var/lib/mysql

  sentraguard-redis:
    image: redis:7-alpine
    volumes:
      - redis_data:/data

volumes:
  mysql_data:
  redis_data:
```

---

## 21. Installation Guide

### 21.1 Dashboard Installation

**Prerequisites:**
- PHP 8.4 + Composer
- MySQL 8.x
- Redis 7.x
- Node.js 22.x LTS
- Nginx 1.26+

```bash
# 1. Clone and install dependencies
git clone https://github.com/syahrullrmdhn/sentraguard.git
cd sentraguard/dashboard
composer install --no-dev --optimize-autoloader
npm ci && npm run build

# 2. Configure environment
cp .env.example .env
php artisan key:generate

# Edit .env with your DB credentials, Redis, and APP_URL
nano .env

# 3. Run database migrations and seeders
php artisan migrate --force
php artisan db:seed --class=RolePermissionSeeder

# 4. Set file permissions
chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache

# 5. Start queue worker (via Supervisor)
php artisan queue:work redis --sleep=3 --tries=3 --daemon

# 6. Start scheduler (via Supervisor or cron)
* * * * * cd /path/to/dashboard && php artisan schedule:run >> /dev/null 2>&1

# OR using Docker Compose
docker compose up -d
```

### 21.2 Windows Agent Installation

**Prerequisites on Windows Server:**
- Windows Server 2016 / 2019 / 2022
- .NET Framework 4.8+ (for Inno Setup installer only)
- Outbound TCP 443 to the SentraGuard Console

**Method 1 — Interactive installer:**
```
1. Download SentraGuardAgentSetup.exe from the Releases page
2. Run as Administrator
3. Enter the Console URL and Agent Token when prompted
4. Click Install
```

**Method 2 — Silent install (for GPO / deployment tools):**
```powershell
SentraGuardAgentSetup.exe /SILENT `
  /server="https://console.example.com" `
  /token="AGT_xxxxxxxxxxxxxxxxxxxxxxxxx"
```

**Method 3 — Manual install:**
```powershell
# 1. Copy binary
mkdir "C:\Program Files\SentraGuard Agent"
copy sentraguard-agent.exe "C:\Program Files\SentraGuard Agent\"

# 2. Register and install as Windows Service
cd "C:\Program Files\SentraGuard Agent"
.\sentraguard-agent.exe install --server https://console.example.com --token AGT_xxx

# 3. Start the service
.\sentraguard-agent.exe start

# 4. Verify
.\sentraguard-agent.exe status
.\sentraguard-agent.exe test-connection
```

### 21.3 Agent Uninstallation

```powershell
cd "C:\Program Files\SentraGuard Agent"
.\sentraguard-agent.exe stop
.\sentraguard-agent.exe uninstall

# Or via Add/Remove Programs if installed using the installer
```

### 21.4 Windows Firewall Requirement

No inbound port needs to be opened. The agent only requires:

```
Protocol: TCP
Direction: Outbound
Destination: <SentraGuard Console IP or domain>
Port: 443
```

---

## 22. Deployment Design

### 22.1 Dashboard Deployment (Production)

**Recommended server stack:**
```
Nginx 1.26+          → Reverse proxy + SSL termination
PHP-FPM 8.4          → PHP process manager
Laravel 13           → Application
MySQL 8.x            → Primary database
Redis 7.x            → Cache, sessions, and queue
Supervisor           → Queue worker + scheduler process manager
Certbot / acme.sh    → TLS certificate management
```

**Supervisor config example:**
```ini
[program:sentraguard-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/sentraguard/artisan queue:work redis --sleep=3 --tries=3
autostart=true
autorestart=true
numprocs=2
user=www-data
redirect_stderr=true
stdout_logfile=/var/log/supervisor/sentraguard-worker.log

[program:sentraguard-scheduler]
command=php /var/www/sentraguard/artisan schedule:work
autostart=true
autorestart=true
numprocs=1
user=www-data
redirect_stderr=true
stdout_logfile=/var/log/supervisor/sentraguard-scheduler.log
```

### 22.2 Metrics Retention (Scheduled Command)

```php
// app/Console/Commands/PruneMetrics.php
class PruneMetrics extends Command
{
    protected $signature = 'metrics:prune';
    protected $description = 'Delete old server_metrics and audit_logs beyond retention period';

    public function handle(): void
    {
        $metricsDays = (int) config('agent.metrics_retention_days', 30);
        $auditDays   = (int) config('agent.audit_log_retention_days', 90);

        ServerMetric::where('collected_at', '<', now()->subDays($metricsDays))->delete();
        AuditLog::where('created_at', '<', now()->subDays($auditDays))->delete();

        $this->info("Pruned metrics older than {$metricsDays} days.");
        $this->info("Pruned audit logs older than {$auditDays} days.");
    }
}

// routes/console.php
Schedule::command('metrics:prune')->dailyAt('02:00');
```

---

## 23. Development Roadmap

### Phase 1 — Foundation

- [ ] Initialize Laravel 13 project with PHP 8.4
- [ ] Configure MySQL and Redis
- [ ] Set up authentication (Laravel Breeze or custom)
- [ ] Implement role and permission structure
- [ ] Create base admin layout (Blade + Livewire + Tailwind)
- [ ] Create server CRUD with token generation
- [ ] Create database migrations including `server_metrics`

### Phase 2 — Agent MVP

- [ ] Initialize Go agent project (`go mod init`)
- [ ] Create config loader (YAML)
- [ ] Create Windows Service wrapper (`golang.org/x/sys/windows/svc`)
- [ ] Create register API client
- [ ] Create heartbeat loop
- [ ] Create metrics collector (`gopsutil`)
- [ ] Build `sentraguard-agent.exe`

### Phase 3 — Command Execution

- [ ] Implement command queue with `lockForUpdate` pickup
- [ ] Implement dashboard service action buttons
- [ ] Implement agent command polling loop
- [ ] Implement predefined PowerShell executor
- [ ] Implement allowlist validation (backend + agent)
- [ ] Implement command result submission
- [ ] Implement command timeout handling

### Phase 4 — Metrics & Service Monitor

- [ ] Agent: post metrics to `/api/agent/metrics` every 15s
- [ ] Backend: store metrics in `server_metrics`
- [ ] Dashboard: Livewire metrics chart with `wire:poll`
- [ ] Agent: proactive service monitor loop every 30s
- [ ] Backend: handle `POST /api/agent/service-events`
- [ ] Dashboard: display proactive service events

### Phase 5 — Security Hardening

- [ ] Implement bcrypt token hashing on registration
- [ ] Implement Windows DPAPI / Credential Manager for agent token
- [ ] Implement runtime token revocation
- [ ] Implement RBAC policy checks on all endpoints
- [ ] Implement full audit logging
- [ ] Implement offline detection scheduler
- [ ] Implement metrics data retention / pruning

### Phase 6 — Installer & Release

- [ ] Create Inno Setup installer with silent install support
- [ ] Implement `sentraguard-agent.exe version` command
- [ ] Add agent log rotation
- [ ] Write deployment and agent installation documentation
- [ ] Tag first release

---

## 24. MVP Acceptance Criteria

The MVP is considered complete when all of the following pass:

- [ ] Admin can log in to the dashboard
- [ ] Admin can create a server record and generate an agent token
- [ ] Agent `.exe` installs as a Windows Service
- [ ] Agent registers to the backend and receives a runtime token
- [ ] Agent sends periodic heartbeat
- [ ] Dashboard correctly shows agent online / offline
- [ ] Agent collects and posts CPU / RAM / Disk metrics every 15s
- [ ] Dashboard displays near-realtime metrics charts
- [ ] Agent syncs Windows Services on request
- [ ] Agent proactively reports service state changes
- [ ] Admin can add a service to the allowlist
- [ ] Admin can start, stop, and restart an allowed service
- [ ] Admin can enable and disable service startup
- [ ] Commands are atomically picked up (no duplicate execution)
- [ ] Agent submits command results with exit code and output
- [ ] Dashboard stores full command history
- [ ] Dashboard stores full audit logs
- [ ] Revoked agent receives 401 and stops polling
- [ ] Metrics older than 30 days are automatically pruned

---

## 25. Risk Register

| Risk | Impact | Mitigation |
|---|---|---|
| Agent token leaked | Unauthorized agent registration | One-time token, bcrypt hash, token expiration, revocation capability |
| Runtime token leaked | Unauthorized polling and command submission | Windows DPAPI storage, runtime token rotation, revocation |
| Stop of critical system service | Server outage | Allowlist enforcement (backend + agent), RBAC, confirmation modal |
| Raw command execution abused | Full system compromise | Never implement raw PowerShell endpoint — action enum only |
| Duplicate command execution | Service instability from double restart | `SELECT ... FOR UPDATE` atomic pickup |
| Agent offline — command stuck | Command never executed | Heartbeat monitoring, offline detection, command timeout, retry |
| MySQL data growth from metrics | Slow dashboard queries | Indexed queries, daily retention pruning, optional partitioning |
| Long-running PowerShell command | Worker hang | Command timeout enforcement with forceful process kill |
| Operator error (stop wrong service) | Service outage | Allowlist, RBAC role separation, confirmation UI, full audit trail |
| Token stored in plain text YAML | Credential theft from disk | Windows DPAPI / Credential Manager for production |

---

## 26. Contributing

1. Fork the repository
2. Create a feature branch: `git checkout -b feature/your-feature-name`
3. Follow the coding standards:
   - Laravel: PSR-12, Laravel conventions
   - Go: `gofmt`, `golint`, standard Go project layout
4. Write tests for new features
5. Submit a pull request against the `main` branch

**Branch naming:**
```
feature/  → new features
fix/      → bug fixes
chore/    → tooling, dependencies, CI
docs/     → documentation only
```

---

## 27. References

- [Laravel 13 Documentation](https://laravel.com/docs/13.x)
- [Laravel Livewire 3](https://livewire.laravel.com)
- [Go Documentation](https://go.dev/doc/)
- [gopsutil — Go system and process utilities](https://github.com/shirou/gopsutil)
- [golang.org/x/sys — Windows Service support](https://pkg.go.dev/golang.org/x/sys/windows/svc)
- [Inno Setup Documentation](https://jrsoftware.org/ishelp/)
- [MySQL 8.x Documentation](https://dev.mysql.com/doc/)
- [Redis Documentation](https://redis.io/docs/)
- [Windows DPAPI](https://docs.microsoft.com/en-us/windows/win32/api/dpapi/)
- [Windows Service Control Manager](https://docs.microsoft.com/en-us/windows/win32/services/service-control-manager)

---

## License

MIT License — see [LICENSE](LICENSE) for details.

---

## Author

Prepared and maintained by **Syahrul Ramadhan**.
