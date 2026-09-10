# 01 — RLS SECURITY WINDOW REMEDIATION (F-11A-001)

**Mode:** Design binding only — NO DDL  
**Selected option:** **OPTION A** (exclusive)

```text
ENABLE + FORCE RLS IN THE SAME MIGRATION AS EACH TENANT-SCOPED TABLE
```

Option B/C not selected. No repository contradiction found that makes Option A impossible (PostgreSQL transactional DDL + Laravel per-migration transactions support it; Phase 3B already runs ENABLE/FORCE/POLICY via SQL statements in migrations).

---

## Binding sequence (per tenant-scoped table)

```text
CREATE TABLE …
    ↓
PK / UNIQUE / FK / CHECK / partial UNIQUE (as designed)
    ↓
ENABLE ROW LEVEL SECURITY
    ↓
FORCE ROW LEVEL SECURITY
    ↓
CREATE POLICY … fail-closed on app.current_school_id
    ↓
ONLY THEN is the table a committed, application-visible object
```

All of the above MUST live in the **same migration class `up()`** for that table (same transactional deployment unit).

---

## Supersedes Phase 3C.11 ordering

| 3C.11 plan | Remediation binding |
|------------|---------------------|
| M02–M15 create tables without RLS | **INVALID** |
| M17 enable RLS for all tables | **SUPERSEDED** — RLS moves into each table migration; M17 becomes optional **verification-only** migration (assert policies exist) or is deleted at implementation time |
| “App after M17 + feature flag” as window proof | **INSUFFICIENT alone** — retained as defense-in-depth, not primary control |

---

## Fail-closed policy (binding text)

Mirror LIVE `student_grades_school_isolation`:

```sql
USING (
  NULLIF(current_setting('app.current_school_id', true), '') IS NOT NULL
  AND school_id = NULLIF(current_setting('app.current_school_id', true), '')::BIGINT
)
WITH CHECK ( /* identical */ )
```

| Context | Access |
|---------|--------|
| GUC missing / '' / NULL | **NO ROW ACCESS** |
| GUC set to other school | **NO ROW ACCESS** |
| GUC matches row.school_id | Allowed per command |

Never: missing context → all rows.

---

## FORCE RLS

| Actor | Effect |
|-------|--------|
| Table owner | Subject to RLS when FORCE set (unlike ENABLE-only) |
| Application role | Must pass policy; no bypass via ownership if not owner |
| Migration role | Typically bypasses as superuser/owner during migration session — acceptable only inside migration transaction that also installs FORCE before commit |
| PostgreSQL **superuser** | Can bypass RLS — **do not claim otherwise**; operational control is process/privilege separation |

---

## Prove no security window

**Primary proof (technical):** PostgreSQL commits the migration transaction only after CREATE + ENABLE + FORCE + POLICY succeed together. An unprotected table is never a durable committed state.

**Secondary:** No Graduation routes/models until after authorized app wave (defense-in-depth).

**Not relied on as sole proof:** feature flags, “next migration runs soon,” manual order, developer discipline.

---

## Transactional deployment analysis

| # | Topic | Finding |
|---|--------|---------|
| 1 | PostgreSQL DDL | CREATE TABLE, ALTER ENABLE/FORCE RLS, CREATE POLICY are transactional; abort rolls back all |
| 2 | Laravel migrations | Each migration `up()` runs in a transaction on pgsql by default (`withinTransaction` true unless disabled) |
| 3 | Per-migration security ops | **Binding:** each table migration contains full Option A sequence |
| 4 | Non-transactional ops | Avoid `CREATE INDEX CONCURRENTLY` in these migrations; standard CREATE INDEX is transactional |
| 5 | Partial sequence across files | If M_n commits and M_n+1 fails, committed tables still have RLS (Option A). Residual risk = incomplete feature set, not unprotected tenants |
| 6 | Fail halfway inside one `up()` | Transaction abort → table not left without RLS |
| 7 | App traffic before migrate complete | Graduation endpoints must not exist until migrations applied; binding in FILE-CHANGE / deploy: no Graduation routes in release that lacks completed Option A migrations. **Uncertainty if** someone manually points ad-hoc SQL at DB during migrate — mitigated by short transaction + FORCE before commit |

Zero-downtime: empty-schema expand with Option A is **compatible**; no SLA claimed.

---

## F-11A-001 status

```text
F-11A-001: CLOSED
```

Closed at **implementation-binding / design** level. Not executed.
