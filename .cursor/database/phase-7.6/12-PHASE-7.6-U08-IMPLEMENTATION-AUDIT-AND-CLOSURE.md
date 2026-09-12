# PHASE 7.6 — U08
# IMPLEMENTATION AUDIT + CLOSURE

---

```text
Unit: 7.6-U08 — Results Rebuild* staff JSON HTTP
AuthZ: 11 GRANTED («استمر»)
Audit: PASS
Closure: CLOSED / ACCEPTED
Date: 2026-09-12
```

## Delivered

```text
Permission: results.rebuild (on results_manager)
ResultsPolicy::rebuild
POST /api/v1/results/term/rebuild
POST /api/v1/results/annual/rebuild
POST /api/v1/results/gpa/rebuild
mode ∈ {operational, official} — validated at FormRequest
X-Idempotency-Key required
Audit: SEC_RESULTS_DATA_MODIFIED
```

## Validation

```text
architecture:validate --fitness → PASS
security:validate → PASS
Phase76ResultsRebuildHttpApiPostgreSqlTest → 4 passed / 10 assertions
```

## Out of scope (still deferred)

```text
Bulk rebuild / queue fan-out
Schedule-exception list HTTP
Student/guardian portal · Phase 8
```

```text
7.6-U08: CLOSED / ACCEPTED
Staff Results HTTP now covers Calculate/Finalize/Ranking/Issue/Rebuild.
```
