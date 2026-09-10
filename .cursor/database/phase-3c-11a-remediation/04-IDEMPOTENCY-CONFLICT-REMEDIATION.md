# 04 — IDEMPOTENCY CONFLICT REMEDIATION (F-11A-003)

**No new idempotency table. Reuse `audit.idempotency_keys` only.**

---

## LIVE store evidence

| Item | Evidence |
|------|----------|
| Table | `audit.idempotency_keys` |
| PK | `(key, command_name)` |
| Columns | `key`, `command_name`, `response_payload` JSON, `created_at`, `expires_at` |
| Store API | `IdempotencyStore::find` / `store` — **no request fingerprint column** |
| LIVE handler pattern (e.g. EnrollStudent) | On hit: **return cached response without comparing request body** |

```text
LIVE GAP (observation): same key + same command + different payload currently returns first result.
Graduation MUST NOT inherit that silent behavior.
```

Do not invent a second table. Close conflict via **application binding** using JSON already stored in `response_payload`.

---

## Canonical request identity (Graduation binding)

```text
idempotency_key          → column `key`
operation/command        → column `command_name` (e.g. Graduation.IssueAward)
tenant context           → school_id inside fingerprint material
canonical payload hash   → SHA-256 over canonicalized business fields (sorted keys, no volatile timestamps)
```

Stored `response_payload` MUST include:

```json
{
  "request_fingerprint": "<hex>",
  "school_id": <int>,
  "...result fields...": "..."
}
```

Fingerprint material (example — exact field set per command in impl tickets):

```text
command_name | school_id | enrollment_id | stable business args…
```

No invented business thresholds inside fingerprint.

---

## Case matrix

### Case A — same key + same operation + same canonical payload

```text
Expected: return/reuse original operation result (fromIdempotencyCache)
No second business write
```

### Case B — same key + same operation + different canonical payload

```text
Expected: CONFLICT (HTTP 409 / domain IdempotencyPayloadConflict)
No second business operation may execute
Do NOT overwrite response_payload with the new operation’s result
```

---

## Extended scenarios

| Scenario | Expected |
|----------|----------|
| same key + same payload | Reuse result |
| same key + different payload | **CONFLICT** |
| same key + different tenant (school_id in fingerprint) | **CONFLICT** (fingerprint differs) |
| same key + different command_name | Separate PK row — independent ops (LIVE PK). Prefer distinct client keys per command |
| retry after timeout (never committed) | No row → execute once |
| retry after successful commit, lost response | find hit + matching fingerprint → reuse result |
| find hit + mismatched fingerprint | **CONFLICT** — never execute second write |

---

## Handler algorithm (Graduation — future)

```text
1. If no idempotency key → proceed without store
2. cached = find(key, command_name)
3. If cached:
     if cached.request_fingerprint != current_fingerprint → CONFLICT
     else return Result::fromIdempotency(cached)
4. Else in same UnitOfWork as business write:
     perform operation
     store(key, command_name, { request_fingerprint, school_id, ...result })
```

Platform-wide fix of Enrollment/Exams handlers is **out of scope** unless separately authorized; Graduation handlers are bound to this stricter rule.

---

## F-11A-003 status

```text
F-11A-003: CLOSED
```

Closed as **Graduation implementation binding**. LIVE store schema unchanged. Not executed.
