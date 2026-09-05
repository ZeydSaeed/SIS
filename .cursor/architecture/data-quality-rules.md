# Data Quality Rules

> Enforce at **database** (constraints) + **application** (validation) + **process** (governance)

## Identity & Uniqueness

| Rule | Enforcement |
|------|-------------|
| One active enrollment per student per year | `UNIQUE (student_id, academic_year_id) WHERE status = 1` |
| student_code unique province-wide | `UNIQUE (student_code)` |
| national_id unique when present | `UNIQUE (national_id) WHERE national_id IS NOT NULL` |
| One grade per student per exam session | `UNIQUE (exam_session_id, student_id)` |
| One attendance per student per session | `UNIQUE (session_id, student_id)` |

---

## Value Bounds

| Field | Rule | DB Constraint |
|-------|------|---------------|
| grade | 0–100 (or exam max) | `CHECK (grade >= 0 AND grade <= max_grade)` |
| payment amount | > 0 | `CHECK (amount > 0)` |
| GPA | 0–4 or 0–100 scale | Document scale in blueprint |
| attendance status | Enum 1–5 | `smallInteger` + app enum |
| day_of_week | 1–7 | `CHECK (day_of_week BETWEEN 1 AND 7)` |

---

## Temporal Integrity

| Rule | Implementation |
|------|----------------|
| effective_from ≤ effective_to | App validation + CHECK where both set |
| enrollment effective_to set on transfer/withdraw | Service layer |
| academic_year start < end | `CHECK (start_date < end_date)` |
| Never overwrite historical school assignment | enrollment history, not student.school_id |

---

## Referential Integrity

- All FK defined with explicit onDelete
- Academic tables: `restrictOnDelete()` — never CASCADE on history
- Orphan detection in CI post-seed (see testing-strategy.md)

---

## PII & Sensitive Data

| Field | Classification | Rules |
|-------|---------------|-------|
| national_id | PII | Mask in logs, encrypt in transit |
| student names | PII | No PII in audit old_values unless required |
| passwords | Secret | Never log, never audit |
| file_hash | Integrity | SHA-256 for certificates |

---

## Official Records — Never

| Action | Alternative |
|--------|-------------|
| Hard-delete enrollment | status + effective_to |
| Hard-delete grades | Correction record + audit |
| Hard-delete attendance | Correction with audit trail |
| UPDATE grade without audit | Audit log old/new values |

---

## Data Completeness (Seed & Production)

| Check | Query |
|-------|-------|
| Student without enrollment (active year) | Flag for registrar |
| Section over capacity | Warn at enrollment |
| Teacher without subject assignment | Block timetable generation |
| Missing daily_summary for school day | Alert after 10:00 AM |

---

## Duplicate Prevention

| Operation | Mechanism |
|-----------|-----------|
| Payment | idempotency_key UNIQUE |
| Bulk import | idempotency_key on job |
| Certificate generation | idempotency_key on generation_jobs |
| Notification send | idempotency_key on messages |

---

## Retention

| Data | Retention |
|------|-----------|
| Academic records | 10+ years minimum (legal policy) |
| Audit logs | Append-only, partition, archive |
| Session tokens | Delete on expiry |
| Failed queue jobs | 30 days then archive |

---

## Related

- [DATABASE-GOVERNANCE.md](./DATABASE-GOVERNANCE.md)
- [database-dictionary.md](./database-dictionary.md)
- [01-principles-and-layers.md](./01-principles-and-layers.md)
