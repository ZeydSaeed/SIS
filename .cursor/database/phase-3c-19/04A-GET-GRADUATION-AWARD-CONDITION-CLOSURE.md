# PHASE 3C.19.4A
# GET GRADUATION AWARD — CONDITION CLOSURE
# (Fallback / pointer vs is_current_issued)

**Date:** 2026-09-11  
**Mode:** AUDIT ONLY  
**Parent:** `.cursor/database/phase-3c-19/04-GET-GRADUATION-AWARD-AUDIT.md`  

```text
IMPLEMENTATION: NOT AUTHORIZED
DATABASE MUTATION: NOT AUTHORIZED
HTTP: NOT AUTHORIZED
WRITE PATH: NOT AUTHORIZED
CODE CHANGES: NONE
```

---

## Answers

### 1. Is `current_issued_version_id` guaranteed non-null whenever an issued version exists?

**NO.**

Evidence:

- Column is nullable (`BIGINT` without `NOT NULL`) in `2026_09_10_170500_…`.
- No CHECK / trigger requires `current_issued_version_id` to equal any version with `is_current_issued = true`.
- `IssueAward` write path inserts the award row **first** (pointer unset), then inserts version, then `UPDATE`s the pointer — schema permits NULL.
- Nothing in schema forbids an issued version (`is_current_issued = true`, `lifecycle_status = 1`) while pointer remains NULL.

---

### 2. Can a valid award exist with `current_issued_version_id = NULL`?

**YES (schema-allowed).**

Evidence:

- Nullable FK column.
- Transiently true during `insertAwardWithVersion` before the pointer UPDATE.
- Persistently possible if a future/alternate write omits the pointer UPDATE (no DB enforcement).
- Award identity UNIQUE `(school_id, enrollment_id)` does not require the pointer.

---

### 3. If yes, what exact persisted rule identifies the preferred award version in that state?

```text
FALLBACK:
NOT AUTHORITATIVELY DEFINED
```

No locked rule states: “when pointer is NULL, select by `is_current_issued` / `version_no` / `awarded_at` / `MAX(id)`.”

The LATERAL fallback proposed in `04-GET-GRADUATION-AWARD-AUDIT.md` is **not** established by schema or write-path invariants — it must not be treated as locked.

---

### 4. Does `is_current_issued=true` have independent meaning, or only sync with the pointer?

**Independent (integrity flag), not a guaranteed sync of the pointer.**

Evidence:

| Event | Pointer `current_issued_version_id` | `is_current_issued` |
|-------|-------------------------------------|---------------------|
| Issue (current app) | Set to new version id | Set `true` on that version |
| Revoke (current app) | **Not cleared / not updated** | Set `false`; `lifecycle_status = 3` |

- Partial UNIQUE: at most one row per award where `is_current_issued AND lifecycle_status = 1`.
- No trigger keeps pointer ↔ flag aligned after revoke.
- Therefore `is_current_issued` is the DB’s definition of “current issued” cardinality; the pointer is a separate nullable denormalized reference maintained only by application issue logic.

---

### 5. Does the partial unique prove selection semantics, or only cardinality/integrity?

**Cardinality / integrity only.**

It proves: ≤1 current-issued version per award under `(is_current_issued AND lifecycle_status = 1)`.

It does **not** define GetGraduationAward’s preferred-version selection algorithm, especially when the pointer is NULL or stale.

---

### 6. If pointer and `is_current_issued` disagree, which persisted fact is authoritative?

**No single “winner” is defined for a synthetic preferred-version query.**

Persisted facts (both real):

| Fact | Meaning |
|------|---------|
| `current_issued_version_id` | Stored pointer value (may reference a revoked version after `RevokeAward`) |
| `is_current_issued` + `lifecycle_status` | Whether that version is in the partial-unique “current issued” set |

After revoke (current write path): pointer can still point at a version with `is_current_issued = false` — **disagreement is reachable**.

Choosing one as “the preferred version” without a locked rule = invention.

---

### 7. Can a revoked award have no current issued version while still requiring the award identity to be returned?

**YES.**

Evidence:

- Award row is not deleted on revoke (reject-delete triggers; soft revoke).
- Revoke clears current-issued on the version; partial unique then allows **zero** current issued versions.
- Award identity `(school_id, enrollment_id)` remains.

GetGraduationAward must be able to return **award identity** with **no current issued version snapshot** (or with factual pointer/version fields that are not “current issued”).

---

## Condition closure recommendation

```text
FALLBACK:
NOT AUTHORITATIVELY DEFINED

RECOMMENDED SELECTION (for future implementation ratification):
POINTER-ONLY
```

**Pointer-only** means:

1. If `current_issued_version_id IS NOT NULL` → load that version row as the pointed snapshot (expose raw `is_current_issued` / `lifecycle_status`; do not relabel).
2. If pointer IS NULL → **no version snapshot** (do not invent `ORDER BY is_current_issued / version_no`).
3. Always return award identity when the award row exists, including fully revoked (no current issued).

Optional later (separate decision): expose “current issued version” as a **second** optional field resolved only by `is_current_issued = true AND lifecycle_status = 1` — that is a different contract from pointer-only, and must be explicitly locked, not smuggled in as fallback.

---

## Correction to parent audit (04)

Parent § “Preferred Version Rule” step 2 (LATERAL `is_current_issued DESC, version_no DESC`) is **withdrawn as authoritative**.

Treat it as **non-locked / do not implement** unless humans explicitly approve a new Design Lock.

---

## Mutation check

```text
CODE MUTATION: NONE
DATABASE MUTATION: NONE
HTTP: NONE
WRITE PATH: NONE
```

Deliverable only:

```text
.cursor/database/phase-3c-19/04A-GET-GRADUATION-AWARD-CONDITION-CLOSURE.md
```

---

```text
PHASE 3C.19.4 CONDITION CLOSURE
IMPLEMENTATION: NOT AUTHORIZED
DATABASE MUTATION: NOT AUTHORIZED
HTTP: NOT AUTHORIZED
WRITE PATH: NOT AUTHORIZED
STOP
```
