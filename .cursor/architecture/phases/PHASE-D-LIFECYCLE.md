# Phase D — Lifecycle

> **الحالة:** دليل مرجع — لا migration  
> **المتطلب:** Phase C مكتمل  
> **التالي:** [PHASE-E-SUPPORTING.md](./PHASE-E-SUPPORTING.md)

## الهدف

قبول، امتحانات، نتائج، ترقية، نقل، تخرج، شهادات — دورة حياة الطالب الكاملة.

## الجداول (20)

| Schema | الجداول | العدد |
|--------|---------|-------|
| admission | application_periods, applications, application_documents | 3 |
| exams | exam_types, exams, exam_sessions, exam_enrollments, student_grades | 5 |
| results | term_results, annual_results, transcripts | 3 |
| promotion | rules, records | 2 |
| transfers | transfer_requests, transfer_records | 2 |
| graduation | eligibility_rules, records | 2 |
| certificates | templates, issued_certificates, generation_jobs | 3 |

## نقاط حرجة للحجم 45K

### exams.student_grades — Partitioned

```
45K × 15 subjects × 4 = 2.7M rows/year
Partition by academic_year_id — P0
```

### Certificate generation — Async فقط

```
500+ certificates → Queue job
Never HTTP sync — batch-write-patterns.md
Idempotency key required
```

### Promotion — End of year

```
45,000 enrollment records evaluated
Background job + progress UI
Results in promotion.records (never overwrite enrollment)
```

### Transfers between 20 schools

```
transfer_requests → approval workflow
Old enrollment: status=TRANSFERRED, effective_to=date
New enrollment: new school, same academic_year
History preserved — never update student.school_id alone
```

## Indexes إلزامية

| الجدول | Index |
|--------|-------|
| student_grades | `(student_id, academic_year_id)`, `(subject_id, academic_year_id)` |
| annual_results | `UNIQUE(enrollment_id)` |
| issued_certificates | `UNIQUE(verification_code)` |

## Checklist قبل Phase E

- [ ] student_grades partitioned
- [ ] Grade CHECK (0–100) in DB
- [ ] Certificate jobs async with idempotency
- [ ] Transfer preserves history (enrollment effective_to)
- [ ] Promotion never hard-deletes records
- [ ] database-blueprint.md synced

## مراجع

- `student-lifecycle.md`
- `batch-write-patterns.md`
- `database-blueprint.md` → exams, results, promotion, transfers, graduation, certificates
