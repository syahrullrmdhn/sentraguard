# SentraGuard Frontend Deployment

## Masalah yang Sudah Fixed (2026-06-21)

### 1. Backend Issues ✅
- **DELETE 405**: Nginx blocking PUT/PATCH/DELETE → Added to allowed methods
- **API 500**: Controllers owned by root → `chown www-data:www-data`
- **Session not set**: Sanctum stateful domains missing localhost → Added to `.env`

### 2. Frontend Build Issues ✅
- **`useRuntimeConfig()` premature call**: Removed from `useAuth.ts` & `useApi.ts` → hardcoded `apiBase = ""`
- **`window.__NUXT__` missing**: Manual inject in HTML with `app.baseURL`, `buildId`, `public.apiBase`
- **JS error `-Htpy4kj.js`**: Fresh build → chunk `Daqd7LhB.js` without useRuntimeConfig refs

### 3. CSS Not Loading ✅
- **Missing CSS link in HTML**: Added `<link>` tag for `entry.ja59q-bY.css`
- **Inline critical CSS**: Embedded base styles directly in HTML to bypass cache

## Build & Deploy Process

### Build Frontend
```bash
cd /var/www/sentraguard/frontend
rm -rf .nuxt .output
npm run build
```

### Post-Build (Generate HTML)
```bash
bash /var/www/sentraguard/frontend/post-build.sh
```

This script:
- Extracts entry chunk name from build manifest
- Gets build ID from `builds/meta/`
- Generates `index.html` with:
  - `window.__NUXT__` config (app.baseURL, buildId, apiBase)
  - CSS link tag
  - Entry module script tag
- Creates `200.html` (SPA fallback)

### Deploy
```bash
# HTML + assets already in .output/public/
systemctl reload nginx
```

## Nginx Configuration

### Laravel Backend (Port 8001)
- `/etc/nginx/sites-enabled/sentraguard`
- Allows: GET, POST, PUT, PATCH, DELETE, HEAD, OPTIONS
- PHP-FPM 8.4 via unix socket

### Nuxt Frontend (Port 8002)
- `/etc/nginx/sites-enabled/sentraguard-nuxt`
- Serves static files from `/var/www/sentraguard/frontend/.output/public/`
- Proxies `/api`, `/sanctum`, `/download` → `:8001` (Laravel)
- `/_nuxt/` assets: `Cache-Control: public, max-age=31536000, immutable`

### Cloudflare Tunnel
- Points to `localhost:8002` (Nuxt nginx)
- Domain: `sentraguard.mastolongin.web.id`

## Troubleshooting

### Browser Cache Issues
Symptoms: Old chunks loading, CSS not applying, page blank

**Solutions (in order):**
1. Hard refresh: `Ctrl+Shift+R` (desktop) or force close browser (mobile)
2. Incognito/Private window
3. Clear site data: DevTools → Application → Storage → Clear site data
4. Test alternate URL: `/app.html` (bypasses index.html cache)
5. Inline critical CSS in HTML (already done in `index.html`)

### Cloudflare Cache
If changes don't appear after browser refresh:
1. Cloudflare Dashboard → Caching → Purge Cache → Purge Everything
2. Or wait 5-10 minutes for TTL expire

### Debug Steps
```bash
# Verify HTML served
curl -sk https://sentraguard.mastolongin.web.id/ | grep -E 'src=|href='

# Check chunk exists
curl -sk -I https://sentraguard.mastolongin.web.id/_nuxt/Daqd7LhB.js

# Check CSS loads
curl -sk -I https://sentraguard.mastolongin.web.id/_nuxt/app-styles.css

# Test backend health
curl -sk https://sentraguard.mastolongin.web.id/api/health

# Check Laravel logs
tail -50 /var/www/sentraguard/dashboard/storage/logs/laravel.log
```

## Files Modified

### Frontend
- `app/composables/useAuth.ts`: Removed `useRuntimeConfig()`, hardcoded `apiBase = ""`
- `app/composables/useApi.ts`: Removed `useRuntimeConfig()`, hardcoded `apiBase = ""`
- `post-build.sh`: Auto-generates HTML with `window.__NUXT__` + CSS link
- `.output/public/index.html`: Manual generated (not from Nuxt SSR)
- `.output/public/app.html`: Cache-bypass alternate URL

### Backend
- `.env`: `SANCTUM_STATEFUL_DOMAINS=sentraguard.mastolongin.web.id,localhost,127.0.0.1`
- `/etc/nginx/sites-enabled/sentraguard`: Added PUT/PATCH/DELETE to allowed methods
- Ownership: `chown www-data:www-data` on controllers

## Production Checklist

- [ ] Build frontend: `npm run build`
- [ ] Run post-build: `bash post-build.sh`
- [ ] Verify HTML: `cat .output/public/index.html` (check `window.__NUXT__` + CSS link)
- [ ] Check file permissions: `www-data:www-data` on Laravel files
- [ ] Clear Laravel cache: `php artisan optimize:clear`
- [ ] Reload nginx: `systemctl reload nginx`
- [ ] Test in incognito window
- [ ] Purge Cloudflare cache if needed

## Known Issues

### Mobile Browser Cache
Mobile browsers (especially Chrome Android) have **extremely persistent cache**. Users may need to:
- Force stop browser app completely (not just close tab)
- Clear browsing data from Settings
- Use alternate URL (`/app.html`) as workaround

### Service Worker
If a service worker was ever registered, it can intercept requests even after clearing cache. Check DevTools → Application → Service Workers and unregister if present.
