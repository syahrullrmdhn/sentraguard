# Changelog

All notable changes to SentraGuard will be documented in this file.

## [1.0.6] - 2026-06-21

### 🎉 Big Update - Production Ready

**Agent Stability & Cloud Support**

This release fixes critical agent issues and adds cloud VM support. All agents should upgrade to v1.0.6 for stable operation.

### ✨ Added
- **Auto-Update System**: Agents can now self-update from dashboard without manual reinstall
  - Dashboard UI with copy-paste PowerShell script (token auto-included)
  - Agent command `sentraguard-agent.exe update` for automated updates
  - Backend API `/api/agent/version` for version checking
  - Future updates (v1.0.7+) can be done with one click from dashboard
- **Public IP Detection**: Cloud VMs (AWS, Azure, GCP) now correctly detect public IP via ipinfo.io
- **Copy Script Modal**: Dashboard generates PowerShell install script with embedded token (no manual input needed)

### 🐛 Fixed
- **CRITICAL**: Agent metrics/services not syncing due to Credential Manager cross-account issue
  - Root cause: LocalSystem service cannot access Administrator's Credential Manager
  - Solution: Token now stored as DPAPI-encrypted file in `C:\ProgramData\SentraGuard Agent\`
  - Logger fixed: Windows Service console errors no longer block file logging
- **UI**: Progress bars extending beyond viewport on metrics tab (inline style syntax error)
- **Database**: Duplicate server name error after delete (switched from soft delete to hard delete)
- **Database**: SQL enum error when queueing `update` command (added 'update' to allowed actions)
- **UI**: Color mismatch on update buttons (ungu + hitam → lime + hitam with blue hover)

### 🔧 Changed
- Server delete now uses hard delete (`forceDelete()`) instead of soft delete to free unique names immediately
- Agent token storage moved from Windows Credential Manager to encrypted file in ProgramData
- Services sync is now recognized as **CORE FEATURE** (required for remote Windows Service management)

### 📦 Agent Versions Timeline
- **v1.0.1**: DPAPI user-scope (failed in LocalSystem context)
- **v1.0.2**: Logger fix (revealed Credential Manager error)
- **v1.0.3**: Token moved to ProgramData file → **metrics/services working!** 🎉
- **v1.0.4**: Added services enumeration logging
- **v1.0.5**: Public IP detection via ipinfo.io
- **v1.0.6**: Self-update command + dashboard modal

### 🚀 Upgrade Instructions

**For agents v1.0.5 and below (first-time upgrade):**

1. Open server detail page in dashboard
2. Click "📋 Copy Script Install" button
3. Copy PowerShell script
4. Run as Administrator in Windows
5. Script will automatically download v1.0.6 and install with saved token

**For agents v1.0.6+ (future updates):**

1. Click "Update Sekarang" button in dashboard
2. Wait ~60 seconds
3. Agent automatically downloads, replaces binary, and restarts

### 🔐 Security
- Token encryption: DPAPI machine-local scope (accessible by LocalSystem service)
- Token path: `C:\ProgramData\SentraGuard Agent\token.dat`
- Script embedding: Token included in dashboard-generated script (auth required to view)

### 📊 Database Migrations
- `2026_06_21_054155`: Add 'update' action to service_commands enum

### 🎨 UI/UX Polish
- Update card: brutal design with version badges, process steps, color-matched buttons
- Modal: backdrop blur 12px, slide-up animation, one-click copy
- Button colors: lime (`accent-2`) for CTAs, blue (`accent`) for hover
- Instructions: 5-step numbered guide for PowerShell execution

### ⚠️ Breaking Changes
None. v1.0.6 is backward compatible with all v1.0.x configurations.

### 📝 Known Issues
- Agent v1.0.5 cannot execute `update` command from dashboard (command doesn't exist in v1.0.5 code)
  - Workaround: Use copy-paste script from dashboard (one-time manual upgrade)
- First install requires token input (subsequent updates do not)

---

## [1.0.5] - 2026-06-21 (Internal)
- Added public IP detection via ipinfo.io

## [1.0.4] - 2026-06-21 (Internal)
- Added logging to services enumeration

## [1.0.3] - 2026-06-21 (Internal)
- Fixed token storage using ProgramData file

## [1.0.2] - 2026-06-21 (Internal)
- Fixed logger blocking in Windows Service

## [1.0.1] - 2026-06-21 (Internal)
- Fixed DPAPI encryption scope

## [1.0.0] - 2026-06-20
- Initial release
