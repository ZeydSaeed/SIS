# PHASE 7.6 — U08 HUMAN IMPLEMENTATION AUTHORIZATION

---

```text
Date: 2026-09-12
Human: «استمر»
Selected: Results Rebuild* staff JSON HTTP (Term/Annual/GPA)
Rejected for now: Schedule-exception list · Student portal · Phase 8
Status: GRANTED
```

## Why this path

```text
- Completes deferred Results writer surface after Calculate/Finalize HTTP
- Application Rebuild handlers already exist (7.4–7.5)
- Separate permission results.rebuild (ops-sensitive, includes official mode)
```

## Scope (IN)

```text
Permission results.rebuild + results_manager
ResultsPolicy::rebuild
POST /api/v1/results/{term|annual|gpa}/rebuild
mode ∈ {operational, official}
X-Idempotency-Key required
PG feature tests + AuthZ deny
```

## Scope (OUT)

```text
Bulk rebuild · queue fan-out
Student portal · Phase 8
Silent mutate beyond Application rebuild rules
```
