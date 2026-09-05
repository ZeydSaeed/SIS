# Seed Data Strategy — 45K Realistic Datasets

> **Purpose:** Test architecture with realistic volume — not production data.  
> **Rule:** Seed runs in staging/dev only via artisan commands or queue jobs.

## Dataset Tiers

| Tier | Students | Schools | Purpose |
|------|----------|---------|---------|
| **Small** | 5,000 | 5 | Dev daily work, unit tests |
| **Medium** | 20,000 | 10 | Integration tests |
| **Target** | 45,000 | 20 | Load test baseline (production mirror) |
| **Stress** | 60,000–75,000 | 20 | Breaking point discovery |

---

## Target Dataset Structure (45K)

```
1 Directorate (province)
  └── 20 Schools
        └── 5 Vocational Specializations each
              └── 3 Grade Levels each
                    └── 3 Sections each
                          └── 50 Students each

= 20 × 5 × 3 × 3 × 50 = 45,000 students
= 900 sections
≈ 1,500 teachers
≈ 45,000 guardians (1 per student minimum)
```

---

## Entities to Seed (Complete Realism)

| Entity | Count (Target) | Notes |
|--------|---------------|-------|
| directorates | 1 | Province |
| schools | 20 | |
| specializations | 100 | 5 × 20 |
| grade_levels | 3 | Shared reference |
| classes | 900 | 45 per school |
| sections | 900 | 1:1 with classes |
| students | 45,000 | Unique student_code |
| guardians | ~45,000 | |
| student_guardians | ~45,000 | |
| teachers | ~1,500 | ~75 per school |
| enrollments | 45,000 | Active, current year |
| enrollment_subjects | ~675,000 | 15 subjects × 45K |
| subjects | ~300 | Shared + per-school |
| curricula | ~900 | Per class/year |
| academic_years | 3 | Current + 2 historical for partition test |
| attendance.records (current year) | ~9M | 45K × 200 days × 1 (or 45M with 5 sessions) |
| attendance.daily_section_summary | ~180K | 900 × 200 days |
| exams + student_grades | ~2.7M | Current year sample |
| audit_logs | ~100K | Sample operations |
| transfers | ~500 | Cross-school |
| withdrawals | ~200 | status=WITHDRAWN |
| graduations | ~1,000 | Previous year alumni |

---

## Seed Order (FK Dependencies)

```
Phase 1: organization + academic + security (roles)
Phase 2: curriculum subjects + grade_levels
Phase 3: schools → specializations → classes → sections
Phase 4: teachers + teacher_schools
Phase 5: students + guardians + student_guardians
Phase 6: enrollments + enrollment_subjects
Phase 7: attendance sessions + records (batch COPY)
Phase 8: daily_section_summary (aggregate from records)
Phase 9: exams + grades (sample)
Phase 10: audit_logs (sample)
```

---

## Generation Strategy

### Small/Medium — Factory + Faker

```php
// database/seeders/MediumDatasetSeeder.php
Student::factory()->count(20000)->create();
// Use chunk(500) for enrollments
```

### Target/Stress — Batch INSERT / COPY

```php
// Queue job: GenerateAttendanceSeedJob
// Generate CSV → COPY into attendance.records
// 500 rows per INSERT for enrollments
```

**Never:** 45,000 individual factory creates in one HTTP/artisan request without chunking.

---

## Realistic Edge Cases to Include

| Case | Count | Why |
|------|-------|-----|
| Transferred students | 500 | History across schools |
| Repeated grade (راسب) | 200 | Multiple enrollments same student |
| Withdrawn mid-year | 200 | status + effective_to |
| Inactive historical students | 5,000 | Partial index testing |
| Students with 2 guardians | 10,000 | student_guardians N:M |
| Absent-heavy students | 1,000 | Report edge cases |

---

## Partition Seed (Multi-Year)

For 10-year query testing, seed 3 academic years minimum:

```
Year 1 (historical): 40,000 enrollments + partition records_y1
Year 2 (historical): 42,000 enrollments + partition records_y2
Year 3 (current):    45,000 enrollments + partition records_y3
```

Verify partition pruning with EXPLAIN on single-student history query.

---

## Commands (When Implemented)

```bash
php artisan sis:seed --tier=small
php artisan sis:seed --tier=target --with-attendance
php artisan sis:seed --tier=stress --queue
```

---

## Post-Seed Validation

```sql
SELECT count(*) FROM students.students;                    -- 45000
SELECT count(DISTINCT school_id) FROM enrollment.enrollments; -- 20
SELECT count(*) FROM enrollment.sections;                  -- 900
SELECT count(*) FROM attendance.records WHERE academic_year_id = 3;
ANALYZE attendance.records;
```

---

## Related

- [capacity-planning-45k.md](./capacity-planning-45k.md)
- [batch-write-patterns.md](./batch-write-patterns.md)
- [testing-strategy.md](./testing-strategy.md)
- [phases/PHASE-B-CORE.md](./phases/PHASE-B-CORE.md)
