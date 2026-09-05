# Testing Strategy — SIS Enterprise

> **Maturity target:** Production Proven requires passing tests with measured metrics.  
> **Reference:** [load-test-results.md](./load-test-results.md)

## Test Pyramid

```
        E2E (few)
       /          \
   Integration (moderate)
  /                      \
Feature Tests (many)    Load/Stress (scheduled)
        Unit (many)
```

---

## 1. Unit Tests

**Scope:** Services, value objects, helpers — no DB or mocked DB.

| Area | Examples |
|------|----------|
| AttendanceBatchService | Chunk logic, summary calculation |
| Promotion rules | GPA eligibility |
| Grade validation | 0–100 bounds |
| Idempotency keys | Duplicate detection |

```php
// tests/Unit/Services/AttendanceBatchServiceTest.php
public function test_chunks_records_in_batches_of_500(): void
```

---

## 2. Feature Tests (PHPUnit + Laravel)

**Scope:** HTTP endpoints, policies, DB transactions (SQLite or PostgreSQL test DB).

| Module | Critical Tests |
|--------|---------------|
| Auth | Login, RBAC, school scope |
| Enrollment | One active per year, unique constraint |
| Attendance | Batch submit, upsert idempotency |
| RLS | School A admin cannot see School B (PostgreSQL test) |
| Grades | CHECK constraint, exam session unique |
| Transfers | History preserved, old enrollment closed |

```php
// tests/Feature/Attendance/BatchAttendanceTest.php
public function test_teacher_can_submit_section_attendance_batch(): void
{
    $this->actingAs($teacher)
        ->post(route('attendance.batch', $section), ['records' => [...]])
        ->assertRedirect()
        ->assertSessionHas('success');

    $this->assertDatabaseCount('attendance.records', 50);
}
```

---

## 3. Integration Tests

**Scope:** Multi-module flows with real PostgreSQL (CI service container).

| Flow | Steps |
|------|-------|
| Student lifecycle | Admission → Enroll → Attend → Exam → Promote |
| Transfer | School A → request → approve → School B enrollment |
| Certificate | Graduate → queue job → issued_certificate + hash |

---

## 4. Database Tests

| Test | Pass Criteria |
|------|---------------|
| Migration up/down | All migrations reversible |
| FK integrity | No orphan rows after seed |
| Partition pruning | EXPLAIN shows single partition scan |
| RLS isolation | Cross-school SELECT returns 0 |
| Constraint violation | App + DB reject invalid grade |

```sql
-- CI script after seed
EXPLAIN SELECT * FROM attendance.records WHERE academic_year_id = 1;
-- Must NOT scan all partitions
```

---

## 5. Load & Stress Tests (P0 Pre-Production)

**Tool:** k6, Artillery, or Laravel Octane benchmark.

| # | Scenario | Concurrent | Pass |
|---|----------|-----------|------|
| 1 | Login | 500 | P95 < 500ms |
| 2 | Student search | 200 | P95 < 200ms |
| 3 | Attendance batch (50) | 900 teachers | All < 2s |
| 4 | 45K bulk attendance job | 1 | < 60s |
| 5 | School dashboard | 20 | P95 < 1s |
| 6 | Directorate dashboard | 5 | P95 < 3s |
| 7 | Stress | 2,000 | Document limit |
| 8 | 10-year student history | 50 | P95 < 200ms |

Record in `load-test-results.md`.

---

## 6. Failure & Recovery Tests

| Test | Frequency |
|------|-----------|
| Backup restore | Monthly |
| PITR to timestamp | Quarterly |
| Replica failover drill | Quarterly |
| Queue failure retry | Per release |
| Redis unavailable fallback | Per release |

See [dr-runbook.md](./dr-runbook.md).

---

## 7. Security Tests

| Test | Method |
|------|--------|
| RLS bypass attempt | Feature test + manual |
| Cross-school IDOR | API with wrong school_id |
| Permission escalation | Role boundary tests |
| Audit log completeness | CRUD generates audit row |

---

## 8. Data Integrity Tests

Post-seed validation (Target tier):

```sql
-- No orphan enrollments
SELECT count(*) FROM enrollment.enrollments e
LEFT JOIN students.students s ON s.id = e.student_id WHERE s.id IS NULL; -- 0

-- One active enrollment per student per year
SELECT student_id, academic_year_id, count(*)
FROM enrollment.enrollments WHERE status = 1
GROUP BY 1,2 HAVING count(*) > 1; -- 0 rows
```

---

## CI Pipeline

```yaml
# .github/workflows/tests.yml (extend)
- php artisan test --parallel
- php artisan migrate --force (pgsql service)
- php artisan sis:seed --tier=small
- optional: load test smoke on main branch
```

---

## Coverage Targets

| Layer | Target |
|-------|--------|
| Services (attendance, enrollment) | 80%+ |
| Policies | 90%+ |
| Controllers | Critical paths |
| Frontend | Key forms + permissions |

---

## Related

- [seed-data-45k.md](./seed-data-45k.md)
- [load-test-results.md](./load-test-results.md)
- [production-readiness.md](./production-readiness.md)
- [dr-runbook.md](./dr-runbook.md)
