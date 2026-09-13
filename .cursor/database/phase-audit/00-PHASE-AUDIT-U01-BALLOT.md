# Phase AUDIT-U01 — Domain audit_logs physicalize — BALLOT

**Unit:** AUDIT-U01  
**Parent:** Master audit — `audit.audit_logs` missing  
**Authority:** Continuation «استمر»  
**Date:** 2026-09-13

## Question

Physicalize blueprint `audit.audit_logs` as append-only domain change trail (distinct from `security.security_audit_logs`).

## Options (locked)

| # | Decision |
|---|----------|
| A | Create `audit.audit_logs` unpartitioned (partition HOLD until measured) |
| B | Enrichment: `school_id` NOT NULL for FORCE RLS |
| C | Append-only: reject hard DELETE **and** UPDATE |
| D | FK `user_id` → `public.users` (nullable); not `security.users` |
| E | HTTP: `POST/GET /api/v1/audit/logs` |
| F | Auth: `audit.view` / `audit.manage` + school scope |
| G | Idempotency on register |
| H | Auto-wire writers across domains — HOLD (manual register v1) |
| I | `login_history` — HOLD |

## Out of scope

Payroll; Ranking/PDF; monthly partitions; SMTP; notification_jobs.
