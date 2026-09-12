# MASTER PHASE 7 — PHASE 7.4
# HUMAN IMPLEMENTATION AUTHORIZATION — 7.4-U01

---

```text
Document Type:
HUMAN IMPLEMENTATION AUTHORIZATION

Unit:
7.4-U01 — results.term_result_versions schema + FORCE RLS

Date:
2026-09-12

Design Lock:
03 LOCKED

Authority:
Absolute continuation — GRANT 7.4-U01 ONLY

Status:
GRANTED
```

---

## 1. Authorized work

```text
AUTHORIZED:
  - versioned migration creating results.term_result_versions
  - CHECK / UNIQUE partial indexes for current ops/official
  - ENABLE + FORCE ROW LEVEL SECURITY + school isolation policy
  - blueprint / docs catalog updates required by database-change skill
  - PostgreSQL feature tests for table + RLS presence
  - architecture:validate --fitness (expect no app layer yet)

NOT AUTHORIZED in U01:
  - Calculate / Finalize / Rebuild handlers
  - HTTP routes
  - Annual tables
  - GPA / Ranking / Transcript
  - seeds that invent official results
  - DISABLE RLS
  - second grade ledger columns that store raw marks as SSOT
```

---

## 2. Acceptance criteria

| # | Criterion |
|---|-----------|
| 1 | Table exists under `results` schema |
| 2 | PK BIGINT IDENTITY |
| 3 | `school_id` + `academic_year_id` present |
| 4 | No letter/GPA columns |
| 5 | RLS enabled + forced |
| 6 | School isolation policy present |
| 7 | Migration reversible within governance (down drops safely) |
| 8 | PG test proves structure + FORCE |
| 9 | `student_grades` DEFAULT still absent |
| 10 | `exam.session.cancel` still absent |

---

## 3. STOP (AuthZ)

```text
7.4-U01: IMPLEMENTATION AUTHORIZED
Continue → database-change skill → implement → audit
```
