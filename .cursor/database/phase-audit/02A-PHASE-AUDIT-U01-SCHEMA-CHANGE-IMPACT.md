# PHASE AUDIT — U01 SCHEMA CHANGE IMPACT

---

```text
Change: CREATE audit.audit_logs
Type: CREATE + RLS + triggers
Partition: NONE (HOLD)
```

## Checklist

```text
[x] Blueprint defined (enriched school_id)
[x] PK BIGINT IDENTITY
[x] school_id FK → organization.schools
[x] user_id FK → public.users SET NULL
[x] No hard-delete / no update (triggers)
[x] FORCE RLS school isolation
[x] Indexes: school+created, entity, user+created, correlation
[ ] Partition — deferred (adaptive governance)
```

## Blast radius

New empty table. No rewrite of `security.security_audit_logs` or outbox.
