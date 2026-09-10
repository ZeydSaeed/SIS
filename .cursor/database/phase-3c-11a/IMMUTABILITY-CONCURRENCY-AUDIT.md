# PHASE 3C.11A — IMMUTABILITY & CONCURRENCY AUDIT

## Immutability distinctions

| State | UPDATE payload | DELETE | Revocation | Supersession |
|-------|----------------|--------|------------|--------------|
| Draft | Allowed | Prefer soft | N/A | N/A |
| Official / issued / decided / published | Denied (flags only where designed) | Denied | Append revoke | New version + edge |
| Historical | Frozen | Denied | Preserved | Preserved |

```text
UPDATE ≠ DELETE ≠ REVOCATION ≠ SUPERSESSION — PASS in plan
DB enforcement authoritative (+ Laravel guard) — PASS
```

No plan says hard-delete official history for rollback. **PASS**

---

## Concurrency scenarios

### Scenario 1 — Two creates version N

| Item | Plan |
|------|------|
| DB invariant | UNIQUE `(parent_id, version_no)` |
| Lock | FOR UPDATE parent |
| Expected failure | unique_violation |
| App timing only? | **NO** |

### Scenario 2 — Dual publish current

| Item | Plan |
|------|------|
| DB invariant | Partial UNIQUE `is_current_official` / `is_current_issued` |
| Lock | FOR UPDATE + clear prior flag |
| Expected failure | unique_violation |
| App timing only? | **NO** |

### Scenario 3 — Publish + supersede race

| Item | Plan |
|------|------|
| DB invariant | Partial UNIQUE + lineage CHECKs |
| Lock | FOR UPDATE parent/version |
| Expected failure | unique / domain reject |
| App timing only? | **NO** |

### Scenario 4 — Publish + revoke race

| Item | Plan |
|------|------|
| DB invariant | Partial UNIQUE current issued; revoke clears flag |
| Lock | FOR UPDATE award version |
| Expected failure | serialization / check |
| App timing only? | **NO** |

### Scenario 5 — Retry after timeout

| Item | Plan |
|------|------|
| DB invariant | Idempotency `(key, command_name)` + unique version |
| Behavior | Replay cached Result if committed; safe retry if not |
| Gap | Same key / different payload — **F-11A-003 MEDIUM** |

```text
Gate H: PASS (DB-enforced)
Gate I: CONDITIONAL (payload conflict underspecified)
```
