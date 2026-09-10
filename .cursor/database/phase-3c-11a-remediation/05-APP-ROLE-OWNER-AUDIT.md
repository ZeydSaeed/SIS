# 05 — APP ROLE / OWNER AUDIT (F-11A-004)

**No GRANT/REVOKE executed. Design audit only.**

---

## Current project model (evidence)

| Item | Finding |
|------|---------|
| Connection | `config/database.php` → `pgsql` uses `DB_USERNAME` / `DB_PASSWORD` |
| Typical Herd/local | Single DB user often owns objects **and** runs the app (common) |
| Distinct app role vs migration role | **Not separately provisioned in-repo** as code — environment-dependent |
| RLS | FORCE RLS used on grades so **table owner is still subject to policies** for non-superuser |
| Superuser | Can bypass RLS — operational reality |

```text
UNCERTAINTY (explicit): Production may or may not already separate migration owner from app role.
Do not invent that it does.
```

---

## Target binding (compatible with architecture)

```text
TABLE OWNER  ≠  APPLICATION ROLE
```

where the deployment environment can support it.

| Role | Privileges (intended) | Must NOT |
|------|----------------------|----------|
| Migration / DDL role | CREATE/ALTER on `graduation`, run migrations | Be used by HTTP app pool |
| Application role | SELECT/INSERT/UPDATE on graduation tables as required by handlers; USAGE on schema | CREATE/DROP/ALTER TABLE; BYPASSRLS; superuser |
| Break-glass DBA | Controlled ops | Routine app traffic |

### FORCE RLS interaction

With **FORCE ROW LEVEL SECURITY**, the table owner (if not superuser/BYPASSRLS) cannot skip tenant policies — closes the classic “owner bypass ENABLE-only” hole for the app-as-owner misconfig.

Still required:

- App role should not be superuser  
- Prefer app role ≠ table owner when ops can provision it  
- Preflight checklist item before production Graduation go-live  

### Unnecessary privileges

| Privilege | App role |
|-----------|----------|
| DROP TABLE | NO |
| ALTER TABLE | NO |
| CREATE POLICY | NO |
| BYPASSRLS | NO |
| TRUNCATE | NO |

---

## Preflight checks (future — not executed)

```text
1. Confirm FORCE RLS on all graduation.* tenant tables
2. Confirm app DB user cannot ALTER/DROP graduation tables
3. Confirm app DB user is not superuser
4. Prefer owner ≠ app role; if equal, FORCE RLS is mandatory compensating control (already Option A)
```

---

## F-11A-004 status

```text
F-11A-004: CLOSED
```

Closed as **documented model + binding checklist**. Roles not modified. Residual env uncertainty acknowledged without invention.
