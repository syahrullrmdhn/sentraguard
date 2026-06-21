# SentraGuard AgentOps — Setup Progress Report

**Date:** 2026-06-21
**Status:** Phases 1–6 COMPLETE ✅ — dashboard, agent API, UI, Go Windows agent, installer + docs, and Cloudflare Tunnel-style enrollment all done and verified. Only blocker: production MySQL database creation (needs panel/root access).

---

## Phase Status

| Phase | Scope | Status |
|---|---|---|
| 1 | Backend foundation (migrations, models, RBAC seeders) | ✅ Done |
| 2 | Agent API (Sanctum, 7 endpoints, services, middleware) | ✅ Done |
| 3 | Dashboard UI (4-system design fusion, Livewire) | ✅ Done |
| 4 | Go Windows agent (SCM, metrics, executor, DPAPI) | ✅ Done |
| 5 | Inno Setup installer + deployment docs | ✅ Done |
| 6 | Cloudflare Tunnel-style enrollment UX | ✅ Done |

---

## Phase 1 — Backend Foundation ✅
- Laravel 13.16.1 in `dashboard/`, PHP 8.4.21, Composer 2.9.8, Node 22, Livewire 3.8.1
- 11 migrations (servers, agents, server_services, service_commands, server_metrics, audit_logs, roles/permissions pivots + sanctum)
- 9 Eloquent models with relationships + domain methods
- RBAC seeders: 4 roles, 26 permissions, Super Admin (`admin@sentraguard.local` / `password`)

## Phase 2 — Agent API ✅
- Sanctum installed, `routes/api.php` registered
- 7 agent endpoints: register, heartbeat, commands/poll, commands/{id}/result, services/sync, metrics, service-events
- Service layer: `AgentTokenService` (bcrypt), `CommandService` (atomic `lockForUpdate` pickup), agent Bearer-token middleware
- Console commands: stale-command timeout + metric pruning, scheduled in `routes/console.php`
- **Verified:** agent API lifecycle 14/14 PASS (register, token-reuse reject, auth enforce, heartbeat, metrics, sync, atomic pickup no-dup, allowlist enforce, result, events, revoke)

## Phase 3 — Dashboard UI ✅
- Design fusion: Swiss/International foundation + Neo-brutalism character + Glassmorphism (sidebar/topbar) + Neumorphism (inputs/tiles). Fonts: Space Grotesk + Space Mono.
- Custom lightweight auth (no Breeze), DashboardController, web routes with route-model binding
- Livewire components: ServerList, ServerDetail, CommandQueue, AuditLogViewer (+ index views)
- `npm run build` clean (fonts + ~53KB CSS)
- **Verified visually:** login + dashboard render all 4 systems; servers list; detail tabs incl. SERVICES (allowlisted service shows START/STOP/RESTART, others locked); RESTART via UI queued a PENDING command

## Phase 4 — Go Windows Agent ✅
- Go 1.22 installed at `/usr/local/go-1.22` (system Go untouched)
- Packages: config (YAML), api (HTTPS client), metrics (gopsutil), services (Windows SCM via svc/mgr), executor (PowerShell + allowlist guard), token (DPAPI + dev file fallback), logging (rotation), agent runtime (4 loops), cmd (CLI + SCM entrypoint)
- Build-tag isolation (Windows vs Linux dev)
- **Verified:** `go vet` clean; cross-compiles to **Windows amd64 PE32+** (7.1 MB) and Linux dev; CLI runs
- `agent/build.sh` + `agent/README.md` for reproducible builds

## Phase 5 — Installer & Docs ✅
- `installer/inno-setup/SentraGuardAgent.iss` → builds `SentraGuardAgentSetup.exe`
  - Interactive wizard (Dashboard URL + enrollment token) **and** silent install (`/SILENT /server= /token=`) for GPO/RMM
  - Post-install runs `sentraguard-agent.exe install`, registers + starts `SentraGuard Agent Service`
  - Uninstaller stops/removes the service
- `installer/inno-setup/README.md` — how to compile with ISCC
- `docs/DEPLOYMENT.md` — full operator guide (dashboard install, DB, Redis isolation, agent onboarding, allowlist, config reference, troubleshooting, security checklist)

## Phase 6 — Cloudflare Tunnel-style Enrollment ✅
- Public download endpoint: `GET /download/agent` → serves Windows `.exe` (no auth, 6.8 MB)
- UI: PowerShell one-liner in modal after server creation (copy button + visual feedback)
- Command: `irm .../download/agent -OutFile $env:TEMP\agent.exe; & "$env:TEMP\agent.exe" install --server ... --token AGT_xxx`
- Collapsible manual installation fallback
- **Verified:** download 200 OK (PE header valid), UI modal shows command + SALIN button, token one-time display

---

## ⏳ Remaining (blocked / out of scope)

### Database creation — ONLY blocker for production
`syahrul1_sentraguard` MySQL database must be created via cPanel/DirectAdmin/root
(the MejaHR app user lacks `CREATE DATABASE`; resetting MariaDB root would disrupt
MejaHR production). All code is verified against a throwaway SQLite env instead.

```sql
CREATE DATABASE syahrul1_sentraguard CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'syahrul1_sentraguard'@'localhost' IDENTIFIED BY '***';
GRANT ALL PRIVILEGES ON syahrul1_sentraguard.* TO 'syahrul1_sentraguard'@'localhost';
FLUSH PRIVILEGES;
```
Then: `php artisan migrate --seed --force`

### Nice-to-have (future)
- WiX MSI build (dir reserved at `installer/wix/`)
- Docker Compose for dashboard
- Realtime via WebSocket/SSE, signed/notarized agent binary

---

## Isolation from MejaHR (verified)
- DB: `syahrul1_sentraguard` vs `syahrul1_mejahr`
- Redis: db3/db4 + unique prefix vs MejaHR db0/db1 (isolation verified — MejaHR keys untouched)
- Directory: `/var/www/sentraguard` vs `/var/www/mejahr`

## Default credentials (CHANGE IN PRODUCTION)
`admin@sentraguard.local` / `password`

---

**Repository:** https://github.com/syahrullrmdhn/sentraguard
**Owner:** Syahrul Ramadhan (syahrulrmdhn.0911@gmail.com)
**License:** MIT
