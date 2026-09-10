# PHASE 3C.11 — TRANSACTION & CONCURRENCY PLAN

**Not implemented.**

## Transaction template (authoritative writes)

```text
SAME TRANSACTION:
  idempotency reserve/check
  parent lock FOR UPDATE (outcome/award)
  version insert + flag flips + supersession/revoke row
  outbox stage
  idempotency store response
COMMIT

EVENTUALLY CONSISTENT:
  StudentStatus projection consumer
  downstream certificate/transcript consumers
```

No 2PC. Projection failures → retry/rebuild.

---

## Concurrency matrix

| Scenario | Race | DB invariant | Locking | Retry | Expected failure | Test |
|----------|------|--------------|---------|-------|------------------|------|
| Two version creates | duplicate version_no | UNIQUE(parent, version_no) | FOR UPDATE parent | yes | unique_violation | concurrent insert |
| Two official publish | two currents | partial UNIQUE is_current_official | FOR UPDATE + flag clear | yes | unique_violation | concurrent publish |
| Two award issues | two current issued | partial UNIQUE is_current_issued | FOR UPDATE award | yes | unique_violation | concurrent issue |
| Two approvals same attempt | dup attempt | UNIQUE(version, attempt) | — | idempotent | unique / idempotency hit | |
| Duplicate idempotency key | replay | PK (key, command_name) | — | return cached | fromIdempotencyCache | |
| Concurrent revoke + issue | flag conflict | CHECK + partial UNIQUE | FOR UPDATE version | yes | serialization / check | |
| Supersession cycle | bad lineage | CHECK + domain reject | — | no | domain exception | |

Prefer DB invariants over sleep/timing.

## Isolation

Default READ COMMITTED acceptable with FOR UPDATE on parent + partial UNIQUE. Document if SERIALIZABLE ever required — not assumed.
