# PHASE-AR-RTL-OPS-UI — AR-U01 Locale / dir shared props

**Unit:** AR-U01  
**Status:** CLOSED  
**Branch:** `feature/phase-ar-rtl-ops-ui`

## Intent

Default app locale Arabic; share `locale` + `dir` on every Inertia page; HTML `lang`/`dir` already derived in `app.blade.php`.

## Changes

- `config/app.php` — default `locale` / `fallback_locale` → `ar`
- `HandleInertiaRequests` — share `locale`, `dir` (`rtl` for ar/he/fa/ur)
- `.env` already `APP_LOCALE=ar`

## Security

No guest access to school PII; locale only.
