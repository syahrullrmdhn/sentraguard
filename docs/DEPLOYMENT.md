# SentraGuard — Deployment Guide

End-to-end deployment of the SentraGuard dashboard (Laravel) and the Windows
agent. This guide assumes the dashboard runs on a Linux host and agents run on
Windows Servers you want to monitor and control.

> **Co-hosting note:** On this server SentraGuard runs alongside MejaHR with full
> isolation — separate MySQL database (`syahrul1_sentraguard`), separate Redis
> DB indexes (db3 cache-default / db4 cache) with a unique key prefix, and a
> separate directory (`/var/www/sentraguard`). Nothing here touches MejaHR.

---

## 1. Dashboard (Laravel 13 + PHP 8.4)

### 1.1 Requirements
- PHP 8.4 (with `pdo_mysql`, `redis` or `phpredis`, `mbstring`, `openssl`, `bcmath`)
- Composer 2
- MySQL 8.x / MariaDB 10.6+
- Redis 7.x
- Node.js 20+ (for the Vite build)

### 1.2 Install
```bash
cd /var/www/sentraguard/dashboard
composer install --no-dev --optimize-autoloader
cp .env.example .env          # then edit values (see 1.3)
php artisan key:generate
npm ci && npm run build
```

### 1.3 Environment (`.env`)
Key values for an isolated co-hosted deploy:
```ini
APP_NAME=SentraGuard
APP_URL=https://sentraguard.example.com

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_DATABASE=syahrul1_sentraguard
DB_USERNAME=syahrul1_sentraguard
DB_PASSWORD='use-single-quotes-if-it-contains-$'

# Redis isolation from any co-hosted app (e.g. MejaHR on db0/db1)
REDIS_HOST=127.0.0.1
REDIS_PASSWORD='...'
REDIS_DB=3
REDIS_CACHE_DB=4
REDIS_PREFIX=sentraguard_database_

CACHE_STORE=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis
```
> Passwords containing `$` **must** use single quotes — phpdotenv does not
> unescape `\$`.

### 1.4 Create the database
The app user needs its own database. Create it once (root / cPanel / DirectAdmin):
```sql
CREATE DATABASE syahrul1_sentraguard CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'syahrul1_sentraguard'@'localhost' IDENTIFIED BY '***';
GRANT ALL PRIVILEGES ON syahrul1_sentraguard.* TO 'syahrul1_sentraguard'@'localhost';
FLUSH PRIVILEGES;
```

### 1.5 Migrate + seed
```bash
php artisan migrate --force
php artisan db:seed --force      # 4 roles, 26 permissions, Super Admin
```
Default Super Admin: `admin@sentraguard.local` / `password` — **change this
immediately in production.**

### 1.6 Scheduler + queue
The agent lifecycle relies on scheduled jobs (stale-command timeout, metric
pruning). Wire the Laravel scheduler and a queue worker:
```cron
* * * * * cd /var/www/sentraguard/dashboard && php artisan schedule:run >> /dev/null 2>&1
```
Queue worker (systemd/supervisor recommended):
```bash
php artisan queue:work redis --sleep=1 --tries=3
```

### 1.7 Web server
Point your vhost docroot at `/var/www/sentraguard/dashboard/public`. Behind
Cloudflare Flexible SSL, ensure `APP_URL` is `https://` and trust the proxy.

---

## 2. Onboarding a Windows Server

### 2.1 Add the server in the dashboard
1. Log in → **Servers** → **Add Server**.
2. Fill name / environment. On save you get a one-time **enrollment token**
   (`AGT_...`). It is single-use and short-lived.

### 2.2 Install the agent
Build the installer once (see `installer/inno-setup/README.md`), then on the
Windows host (as Administrator):

**Interactive**
```cmd
SentraGuardAgentSetup.exe
```
Enter the dashboard URL and the `AGT_` token when prompted.

**Silent (GPO / RMM)**
```cmd
SentraGuardAgentSetup.exe /VERYSILENT /SUPPRESSMSGBOXES ^
  /server="https://sentraguard.example.com" /token="AGT_xxxxxxxx"
```

The installer registers the agent (exchanging `AGT_` for a DPAPI-sealed runtime
token), writes `%ProgramData%\SentraGuard Agent\config.yaml`, and starts the
**`SentraGuard Agent Service`** Windows Service.

### 2.3 Verify
On the agent host:
```cmd
"C:\Program Files\SentraGuard\Agent\sentraguard-agent.exe" status
"C:\Program Files\SentraGuard\Agent\sentraguard-agent.exe" test-connection
```
In the dashboard, the server should flip to **online** within ~30s (first
heartbeat) and its services list populates within ~30s (first monitor cycle).

---

## 3. Allowing service control

By design the agent will only start/stop/restart services that the dashboard
has **allow-listed** for that server. Until a service is allow-listed, its
control buttons in the UI are locked. This is the primary blast-radius guard —
review the allowlist per server before granting operators control.

---

## 4. Agent configuration reference

`%ProgramData%\SentraGuard Agent\config.yaml`:

| Key | Default | Meaning |
|---|---|---|
| `server_url` | — | Dashboard base URL |
| `agent_uid` | — | Assigned at registration |
| `poll_interval_seconds` | 5 | Command queue poll |
| `heartbeat_interval_seconds` | 30 | Liveness ping |
| `metrics_interval_seconds` | 15 | CPU/RAM/disk push |
| `service_monitor_interval_seconds` | 30 | Service-state sync |
| `command_timeout_seconds` | 60 | Per-command execution cap |
| `log_level` | info | trace/debug/info/warn/error |
| `allowed_services` | [] | Local allowlist (dashboard allowlist is authoritative) |

The runtime token is **not** stored in this file on Windows — it is sealed with
DPAPI under LocalSystem.

---

## 5. Troubleshooting

| Symptom | Check |
|---|---|
| Server stays **offline** | `test-connection`; firewall egress 443; `APP_URL` reachable |
| Token rejected | `AGT_` tokens are single-use — generate a fresh one in the dashboard |
| Services not listed | Service runs as LocalSystem? check `logs\agent.log` |
| Command stuck PENDING | queue worker + scheduler running on the dashboard |
| `$` mangled in DB password | wrap the value in single quotes in `.env` |

Agent logs: `%ProgramData%\SentraGuard Agent\logs\agent.log`.
Dashboard logs: `storage/logs/laravel.log`.

---

## 6. Security checklist

- [ ] Change the default Super Admin password.
- [ ] Serve the dashboard over HTTPS only.
- [ ] Restrict the allowlist to the minimum services operators actually need.
- [ ] Rotate enrollment tokens; never reuse or commit them.
- [ ] Keep agent egress limited to the dashboard host.
