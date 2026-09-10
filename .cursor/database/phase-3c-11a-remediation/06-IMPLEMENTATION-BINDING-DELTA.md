# 06 — IMPLEMENTATION BINDING DELTA

**Supersedes conflicting 3C.11 migration security ordering. Does not rewrite 3C.10A physical design.**

---

## Delta summary

| Topic | 3C.11 original | Binding after 3C.11A remediation |
|-------|----------------|----------------------------------|
| RLS timing | Separate M17 after all creates | **Option A: same migration as each table** |
| Security window proof | Feature flag / deploy order | **Transactional CREATE+ENABLE+FORCE+POLICY** |
| M17 | Create all policies | **Optional verify-only (M17′)** |
| Denorm | Generic “add trigger” | **Explicit matrix + named trigger specs** |
| Idempotency conflict | Underspecified | **Fingerprint in response_payload; CONFLICT on mismatch** |
| App vs owner | Noted | **Checklist; FORCE mandatory; prefer role split** |

---

## Unchanged (reconfirmed)

| Item | Status |
|------|--------|
| Schema `graduation` | Unchanged |
| 14 tables / physical model 3C.10A | Unchanged |
| NO PARTITION AT LAUNCH | Unchanged |
| Outbox = `audit.outbox_messages` | Unchanged |
| Idempotency store = `audit.idempotency_keys` | Unchanged (no new table) |
| Event names | **PROPOSED** |
| StudentStatus multi-enrollment | **HUMAN DECISION REQUIRED** — not decided |
| No new projection table | Unchanged |
| No business policy content | Unchanged |
| Official records non-deletable | Unchanged |

---

## Mandatory implementation checklist (human auth record)

```text
[ ] Each tenant-scoped graduation table migration embeds Option A
[ ] Fail-closed policy USING + WITH CHECK
[ ] CI/schema test asserts relrowsecurity + relforcerowsecurity
[ ] Denorm: composite FKs + triggers per 03-*
[ ] Graduation handlers: idempotency fingerprint CONFLICT rule
[ ] No Graduation routes before migrations applied
[ ] App role privilege preflight (05-*)
[ ] No partition at launch
[ ] Event names not treated as final contracts
[ ] StudentStatus auto-sync not enabled without HD
```

---

## Authority note

```text
3C.10A physical > this delta (security/idempotency binding only)
3C.11 planning remains valid except RLS ordering superseded here
3C.11A gate (pre-remediation) historical; delta gate is current safety status
```
