# Phase 3.6 — Workload Validation

**Phase:** 3.6  
**Date:** 2026-09-07  
**Previous:** Phase 3.5 Intelligence Integration  
**Status:** Complete — `student_search` validated against PERFORMANCE-BUDGET.md

---

## Executive Summary

Phase 3.6 validates the **Students API `student_search` workload** against documented performance budgets using **real HTTP telemetry**. A validation runner executes sustained traffic (search, list, show), aggregates metrics via `HttpRequestTelemetryMonitor`, and checks results against `PERFORMANCE-BUDGET.md` targets (PB-2026.09).

When within budget, the full pipeline produces **zero Intelligence detections** — confirming observability + intelligence integration without false positives on healthy traffic.

---

## Architecture

```text
sis:validate-workload student_search [--seed]
    ↓
WorkloadValidationSeeder (50 students)
    ↓
WorkloadValidationRunner::runProfile()
    → warmup + sample HTTP requests (kernel handle + terminate)
    → RequestTelemetryMiddleware captures telemetry
    ↓
HttpRequestTelemetryMonitor::flush()
    ↓
WorkloadBudgetValidator::validate()
    → compare p95/p99/queries/error_rate vs budget
    ↓
[optional] DatabaseGuardian::runPerformanceCycle()
    → 0 detections when budget met
```

---

## Deliverables

| Item | Location | Purpose |
|------|----------|---------|
| Budget validator | `WorkloadBudgetValidator.php` | Compare metrics vs PERFORMANCE-BUDGET |
| Validation runner | `WorkloadValidationRunner.php` | Sustained HTTP profile execution |
| Report DTO | `WorkloadValidationReport.php` | Structured pass/fail output |
| Artisan command | `sis:validate-workload` | CLI validation entry point |
| Seed data | `WorkloadValidationSeeder.php` | 50 students for realistic search |
| Budget config | `config/intelligence.php` | p95, p99, max_db_queries per workload |
| Profile config | `config/sis.php` | warmup/sample counts, search terms |
| Unit tests | `WorkloadBudgetValidatorTest.php` | Budget logic |
| Feature tests | `StudentSearchWorkloadValidationTest.php` | E2E validation + zero detections |

---

## Performance Budget — `student_search`

Aligned with `.cursor/architecture/PERFORMANCE-BUDGET.md` (PB-2026.09):

| Metric | Target | Config Key |
|--------|--------|------------|
| P95 latency | ≤ 200 ms | `performance_budgets.student_search.p95_ms` |
| P99 latency | ≤ 500 ms | `performance_budgets.student_search.p99_ms` |
| DB queries/request | ≤ 3 | `performance_budgets.student_search.max_db_queries_per_request` |
| Error rate (5xx) | ≤ 0.5% | Hardcoded in validator |
| Budget exceeded | `false` | Computed by telemetry monitor |

---

## Validation Profile

```php
// config/sis.php → workload_validation.profiles.student_search
'seed_students' => 50,
'warmup_requests' => 5,
'sample_requests' => 30,
'search_terms' => ['Ali', 'Sara', 'Omar', 'Noor', 'Hassan'],
```

Traffic mix (rotating): **search** → **list** → **show** per iteration.

---

## Usage

```bash
# Full validation with seed data
php artisan sis:validate-workload student_search --seed

# Include intelligence performance cycle check
php artisan sis:validate-workload student_search --seed --performance-cycle

# Custom student count
php artisan sis:validate-workload student_search --seed --students=100
```

---

## Verification

```bash
php artisan test --filter=WorkloadBudgetValidator
php artisan test --filter=StudentSearchWorkloadValidation
php artisan test --filter="HttpWorkloadDetection|HttpRequestTelemetry|StudentApi"
php artisan architecture:validate --fitness
```

**Gate (2026-09-07):** Workload validation tests pass · zero detections on healthy traffic · architecture 9/9 PASS

---

## Evidence Template (Before/After)

```yaml
workload: student_search
performance_budget_version: PB-2026.09
environment: testing (sqlite) | local (pgsql)
sample_count: 35
before:
  p95_ms: <measured>
  p99_ms: <measured>
  mean_db_queries_per_request: <measured>
  budget_exceeded: false
decision: pass | fail
intelligence_detections: 0
```

Run on PostgreSQL with foundation seed for production-like evidence before Phase 3.8 gate.

---

## What Is NOT in Phase 3.6

- 45K student load test (Phase 3.8 / capacity planning)
- k6/Artillery external load tools
- Automatic budget version bump on breach
- Production canary authorization

---

## Next: Phase 3.7 — Integration + Architecture Tests ✅

See `PHASE-3.7-INTEGRATION-TESTS.md` and `PHASE-3.7-TEST-MATRIX.md` — completed 2026-09-07.

## Next: Phase 3.8 — Gate Report
