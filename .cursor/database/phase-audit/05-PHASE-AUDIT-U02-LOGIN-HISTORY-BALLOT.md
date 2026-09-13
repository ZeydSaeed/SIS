# Phase AUDIT-U02 — login_history physicalize — BALLOT

**Unit:** AUDIT-U02  
**Parent:** AUDIT-U01 HOLD (`login_history`)  
**Authority:** «استمر» + 3-step push plan  
**Date:** 2026-09-13

## Locked decisions

| # | Decision |
|---|----------|
| A | Create `audit.login_history` unpartitioned |
| B | Enrichment: `school_id` NOT NULL for FORCE RLS |
| C | Append-only: reject DELETE + UPDATE |
| D | FK `user_id` → `public.users` |
| E | `login_status` 1=Success, 2=Failed |
| F | HTTP: `POST/GET /api/v1/audit/login-history` |
| G | Auth: reuse `audit.view` / `audit.manage` |
| H | Auto-hook from auth login — HOLD (manual register v1) |

## Out of scope

Payroll; partition; SMTP; Ranking/PDF.
