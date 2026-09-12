# PHASE 7.6 — U07 HUMAN IMPLEMENTATION AUTHORIZATION

---

```text
Date: 2026-09-12
Human: «استمر»
Selected: Results staff JSON HTTP writers (Calculate/Finalize/Ranking/Issue)
Rejected for now: Rebuild* HTTP · Student portal · Phase 8
Status: GRANTED
```

## Why this path

```text
- Application writers (7.4–7.5) exist; staff product surface still missing
- Complements 7.6-U06 official read HTTP
- Rebuild deferred (ops-only, higher risk)
```

## Scope (IN)

```text
Permissions: results.calculate / results.finalize / results.ranking.build / results.transcript.issue
Role: results_manager (+ results.view)
ResultsPolicy write abilities
Thin Api\ResultsWriteController → existing handlers
POST /api/v1/results/... calculate|finalize|ranking|transcripts
X-School-Id + required X-Idempotency-Key
PG feature tests + AuthZ deny
```

## Scope (OUT)

```text
RebuildTerm/Annual/Gpa HTTP
Student/guardian portal
PDF bytes
Phase 8
Silent mutate of issued/official rows beyond Application rules
```
