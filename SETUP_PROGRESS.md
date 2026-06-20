# SentraGuard AgentOps - Setup Progress Report

**Date:** 2026-06-20  
**Status:** Phase 1 Backend Foundation - COMPLETED ✅

---

## ✅ Completed Tasks

### 1. Project Initialization
- ✅ Repository cloned to `/var/www/sentraguard`
- ✅ Laravel 13.16.1 installed in `dashboard/` directory
- ✅ PHP 8.4.21 + Composer 2.9.8 verified
- ✅ Node.js 22.22.3 + NPM 10.9.8 verified
- ✅ Livewire 3.8.1 installed

### 2. Environment Configuration
- ✅ `.env` configured for SentraGuard
  - App Name: "SentraGuard AgentOps"
  - App URL: http://localhost:8001
  - Timezone: Asia/Jakarta
  - Database: syahrul1_sentraguard (MySQL on 127.0.0.1)
  - Agent configuration defaults set

### 3. Database Schema (7 Migrations Created)
- ✅ `create_servers_table.php` - Server inventory management
- ✅ `create_agents_table.php` - Agent lifecycle and status
- ✅ `create_server_services_table.php` - Windows Services inventory
- ✅ `create_service_commands_table.php` - Command queue with atomic pickup
- ✅ `create_server_metrics_table.php` - CPU/RAM/Disk metrics storage
- ✅ `create_audit_logs_table.php` - Immutable audit trail
- ✅ `create_roles_and_permissions_tables.php` - RBAC implementation

### 4. Eloquent Models (9 Models Created)
- ✅ `Server.php` - with relationships (agent, services, commands, metrics, auditLogs)
- ✅ `Agent.php` - with heartbeat(), revoke(), isOnline() methods
- ✅ `ServerService.php` - with allow(), disallow() methods
- ✅ `ServiceCommand.php` - with status transition methods (markPicked, markSuccess, etc)
- ✅ `ServerMetric.php` - with computed attributes (ramPercent, diskPercent)
- ✅ `AuditLog.php` - with static log() helper method
- ✅ `Role.php` - with hasPermission(), givePermissionTo() methods
- ✅ `Permission.php` - simple permission model
- ✅ `User.php` - extended with RBAC methods (hasPermission, hasRole, isSuperAdmin, isAdmin)

### 5. Database Seeders (2 Seeders Created)
- ✅ `RolePermissionSeeder.php`
  - 26 permissions across 9 groups (dashboard, servers, services, commands, metrics, audit, users, roles, settings)
  - 4 roles: Super Admin, Admin, Operator, Viewer
  - Permission assignments per role (RBAC matrix implemented)
- ✅ `UserSeeder.php`
  - Default Super Admin user: admin@sentraguard.local / password
- ✅ `DatabaseSeeder.php` - updated to call both seeders

### 6. Configuration Files
- ✅ `config/agent.php` - Agent configuration defaults and retention policies

---

## ⏳ Pending Tasks (Requires Manual Action)

### Database Creation
**ACTION REQUIRED:** Create MySQL database manually via cPanel/DirectAdmin
- Database name: `syahrul1_sentraguard`
- Database user: `syahrul1_sentraguard` (same as MejaHR user if possible)
- Grant all privileges on `syahrul1_sentraguard.*`

After database is created, run:
```bash
cd /var/www/sentraguard/dashboard
php artisan migrate --seed
```

---

## 📋 Next Phase - Backend APIs & Services

### Phase 2: API Endpoints for Agent Communication
- [ ] `POST /api/agent/register` - Agent self-registration
- [ ] `POST /api/agent/heartbeat` - Periodic liveness signal
- [ ] `GET /api/agent/commands/poll` - Atomic command pickup with `lockForUpdate()`
- [ ] `POST /api/agent/commands/{id}/result` - Submit command result
- [ ] `POST /api/agent/services/sync` - Sync Windows Services list
- [ ] `POST /api/agent/metrics` - Post CPU/RAM/Disk metrics
- [ ] `POST /api/agent/service-events` - Proactive service state change

### Phase 3: Dashboard Web APIs
- [ ] Server CRUD endpoints
- [ ] Service allowlist management
- [ ] Command creation (start/stop/restart/enable/disable)
- [ ] Metrics retrieval (latest + historical)
- [ ] Audit log viewer

### Phase 4: Services & Business Logic
- [ ] `AgentTokenService` - Generate/validate registration tokens (bcrypt)
- [ ] `CommandService` - Queue commands, atomic pickup, timeout handling
- [ ] `AuditService` - Centralized audit logging helper
- [ ] `MetricsService` - Data aggregation and retention pruning
- [ ] Scheduled command: `php artisan metrics:prune` (daily cleanup)

### Phase 5: Dashboard UI (Livewire Components)
- [ ] Authentication scaffolding (Laravel Breeze or custom)
- [ ] Dashboard overview page (stat cards, charts)
- [ ] Server list & detail pages
- [ ] Service management UI with allowlist toggle
- [ ] Real-time metrics charts (`wire:poll.10000ms`)
- [ ] Command history table
- [ ] Audit log viewer with filters

### Phase 6: Go Windows Agent
- [ ] Initialize Go module in `agent/` directory
- [ ] Registration flow with one-time token
- [ ] Heartbeat loop (every 30s)
- [ ] Metrics collector using `gopsutil` (every 15s)
- [ ] Command poller with atomic pickup (every 5s)
- [ ] Predefined PowerShell executor (safe actions only)
- [ ] Proactive service monitor (every 30s)
- [ ] Windows Service wrapper (`golang.org/x/sys/windows/svc`)
- [ ] DPAPI token storage (Windows Credential Manager)
- [ ] Build `sentraguard-agent.exe`

### Phase 7: Installer & Deployment
- [ ] Inno Setup installer with silent install support
- [ ] Docker Compose for dashboard deployment
- [ ] Nginx configuration with SSL
- [ ] Supervisor config for queue workers
- [ ] Documentation: installation, agent deployment, API reference

---

## 📊 Project Statistics

| Metric | Count |
|--------|-------|
| Migrations | 7 |
| Models | 9 |
| Seeders | 2 |
| Permissions | 26 |
| Roles | 4 |
| Total PHP Files | 119 |

---

## 🔐 Security Implementation Status

- ✅ bcrypt token hashing for registration tokens
- ✅ Runtime token storage (will use Windows DPAPI in agent)
- ✅ RBAC matrix implemented (4 roles, 26 permissions)
- ✅ Audit logging structure ready (immutable logs)
- ✅ Service allowlist enforcement at database level
- ✅ Command timeout configuration
- ⏳ API authentication (pending - will use Bearer tokens)
- ⏳ Atomic command pickup with `lockForUpdate()` (pending implementation)
- ⏳ HTTPS enforcement (pending Nginx config)

---

## 📁 Directory Structure

```
/var/www/sentraguard/
├── dashboard/              ✅ Laravel 13 application
│   ├── app/
│   │   └── Models/        ✅ 9 Eloquent models
│   ├── config/
│   │   └── agent.php      ✅ Agent configuration
│   ├── database/
│   │   ├── migrations/    ✅ 7 schema migrations
│   │   └── seeders/       ✅ 2 seeders
│   ├── .env               ✅ Configured for SentraGuard
│   └── composer.json      ✅ Livewire 3 installed
├── agent/                 ⏳ Pending - Go agent
├── installer/             ⏳ Pending - Inno Setup
├── docs/                  ⏳ Pending - Documentation
└── README.md              ✅ Original project documentation

```

---

## ⚠️ Important Notes

1. **Database Creation Required:** The MySQL database `syahrul1_sentraguard` must be created manually before running migrations.

2. **No Conflict with MejaHR:** 
   - Separate database: `syahrul1_sentraguard` vs `syahrul1_mejahr`
   - Different app URL: `localhost:8001` vs `mejahr.web.id`
   - Isolated Laravel installation in separate directory

3. **Default Credentials (CHANGE IN PRODUCTION):**
   - Email: `admin@sentraguard.local`
   - Password: `password`

4. **Redis Setup:** Not yet configured. Required for:
   - Cache and sessions
   - Queue backend for async jobs
   - Real-time features (optional)

5. **Go Agent Development:** Requires Go 1.22+ and Windows development environment for building `.exe` binary.

---

## 🚀 Quick Start (After Database Creation)

```bash
# 1. Navigate to dashboard
cd /var/www/sentraguard/dashboard

# 2. Run migrations and seeders
php artisan migrate --seed

# 3. Start development server (for testing)
php artisan serve --port=8001

# 4. Access dashboard
# URL: http://localhost:8001
# Login: admin@sentraguard.local / password
```

---

## 📞 Contact & Support

**Project Owner:** Syahrul Ramadhan (syahrulrmdhn.0911@gmail.com)  
**Repository:** https://github.com/syahrullrmdhn/sentraguard  
**License:** MIT

---

**Report Generated:** 2026-06-20 18:51 WIB  
**Next Milestone:** Create MySQL database → Run migrations → Build Agent APIs
