# Phase 3C.13 — Idempotency Contract

**Critical design.** Reuses `audit.idempotency_keys` + `GraduationIdempotencyGuard`.  
No new idempotency table. No silent accept of same key / different payload.

## Storage

| Item | Contract |
|------|----------|
| Table | `audit.idempotency_keys` |
| PK | `(key, command_name)` |
| Payload | JSON including `request_fingerprint`, `status`, result resource ids, `school_id` |
| TTL | Default 86400s (ADR-014); Graduation may use longer for official awards if approved later |

## Cases

### Case A — same key + same fingerprint

```text
find → assertFingerprintMatch → return original Result
NO second business mutation
```

### Case B — same key + different fingerprint

```text
find → assertFingerprintMatch FAILS
→ IdempotencyPayloadConflictException
→ REJECT (do not execute second request)
```

### Case C — different key + same business identity

```text
Idempotency does NOT substitute for UNIQUE(school_id, enrollment_id) etc.
Second command may pass key checks then hit 23505 on business constraint
→ map to BusinessConflict / AlreadyExists
→ at most one official effect remains
```

### Case D — commit then lost response then retry

```text
If idempotency row committed in SAME txn as business:
  retry → Case A replay
If business committed without idempotency row (FORBIDDEN design):
  retry may hit 23505 without replay payload → BAD
Therefore: store idempotency INSIDE UnitOfWork (see 06)
```

---

## Canonical fingerprint

Use existing `GraduationIdempotencyGuard::fingerprint($commandName, $schoolId, $canonicalPayload)`.

### Canonical payload rules

1. Include only **semantic** fields (command intent).  
2. Exclude: raw HTTP headers, timestamps of request arrival, correlation_id (trace ≠ semantics), unstable map key order (ksort already).  
3. Include: enrollment_id / version ids / decision_status / approval_id as applicable.  
4. Include: `schema_version` integer (start at `1`) so future payload evolution is explicit.  
5. Do **not** hash raw JSON body strings; build associative array then fingerprint.

```text
same semantic request  ⇒ same fingerprint
different semantic request ⇒ different fingerprint
```

### Example canonical set — IssueGraduationAward

```text
schema_version, enrollment_id, graduation_approval_id,
completion_outcome_version_id, (honors_code if part of issue semantics)
```

---

## State machine (payload-encoded; no DDL required)

`audit.idempotency_keys` has no status column today. Encode in `response_payload`:

| State | Meaning | Ownership | Retry | Response |
|-------|---------|-----------|-------|----------|
| *(absent)* | No record | — | May start | — |
| `PROCESSING` | Optional reservation row | Handler txn | Peer waits or conflicts on PK | 409/conflict or retry later |
| `SUCCEEDED` | Complete + replayable | Committed txn | Replay only | Original result |
| `FAILED_TERMINAL` | Optional; business rejected permanently for this key | Rare | No auto-retry same key | Error replay |
| Expired | `expires_at` passed | Store find returns null | Treated as new (dangerous for awards — prefer long TTL / no expire for official) | — |

### Recommended MVP transitions (Graduation)

```text
absent
  → (single txn) business+outbox+store(SUCCEEDED)
  → SUCCEEDED / REPLAYABLE

absent
  → conflict 23505 on business
  → rollback; no SUCCEEDED row; map error
```

### Optional `PROCESSING` reservation (concurrency hardening)

If two same-key requests race before either commits:

```text
Txn A: INSERT idempotency PROCESSING (fingerprint F)
Txn B: INSERT same PK → 23505 → find → if PROCESSING wait/retry; if SUCCEEDED replay; if fingerprint mismatch REJECT
```

Requires `IdempotencyStore` extension (`tryReserve` / insert-only) — **design change to contract**, implementation later.  
**Optional DDL** for `status`/`locked_at` columns = **requires human approval** (not this phase).

### Failure paths

| Path | Behavior |
|------|----------|
| Exception before commit | Rollback → no SUCCEEDED → safe retry with same key |
| Domain rejection (already approved) | No SUCCEEDED unless intentionally caching terminal error |
| Timeout unknown commit | Client retries same key → either replay or re-enter safe path |

---

## Replay payload contents (security)

| Include | Exclude |
|---------|---------|
| Resource ids (outcome/version/award/approval) | PII beyond ids |
| `request_fingerprint` | Full evidence blobs |
| `school_id` | Secrets / tokens |
| `status=SUCCEEDED` | Arbitrary request dumps |
| Stable result fields needed by API | Teacher comments free text unless required |

HTTP status codes are Interface-layer mapping of Result, not necessarily stored.

---

## Relationship to DB uniqueness

```text
Application Idempotency
        +
Database Business Uniqueness
        +
Transaction Integrity
```

Application is UX + replay. Database remains final authority.

## Verdict

```text
IDEMPOTENCY CONTRACT: PASS
```

Conditions for implementation phase: extend store for fingerprint-aware find; put store inside txn; decide PROCESSING reservation vs MVP single-shot store.
