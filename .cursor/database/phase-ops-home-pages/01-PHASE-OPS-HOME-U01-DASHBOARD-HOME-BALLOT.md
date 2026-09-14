# OPS-HOME-U01 — Adopt authenticated dashboard as primary home

**Status:** CLOSED  
**Unit:** OPS-HOME-U01

- Fortify `home` remains `/dashboard` (config/fortify.php)
- `HomeController`: authenticated `/` → `dashboard`; guest `/` → hub
- Guest `/hub` unchanged for Window Manager desktop shell
