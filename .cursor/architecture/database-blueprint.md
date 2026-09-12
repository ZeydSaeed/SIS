# Database Blueprint — Reference Only

> **Status:** Architecture reference. Migrations are created from approved phases — not blindly from this file.  
> **Target:** **90** blueprint objects (tables + reporting MVs) across **24** PostgreSQL schemas.  
> **Not counted here:** `intelligence.*` platform tables (see section at end).  
> **PK convention:** `id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY` (ADR-003, ADR-020 D1)  
> **Timestamps:** All transactional tables include `created_at TIMESTAMPTZ`, `updated_at TIMESTAMPTZ`  
> **SSOT note:** Prior 87 reconciled 2026-09-10; +1 `gpa_results` (7.5-U01); +2 ranking snapshot tables (7.5-U05) → **90**. `results.transcripts` physicalized in 7.5-U07 (was sketch; count unchanged).

---

## Schema: `organization` (6 tables)

### `organization.ministries`

| Column | Type | Constraints |
|--------|------|-------------|
| id | BIGINT | PK |
| code | VARCHAR(20) | UNIQUE NOT NULL |
| name | VARCHAR(255) | NOT NULL |
| name_en | VARCHAR(255) | |
| status | SMALLINT | NOT NULL DEFAULT 1 |
| created_at | TIMESTAMPTZ | NOT NULL |
| updated_at | TIMESTAMPTZ | NOT NULL |

**Indexes:** `UNIQUE(code)`, `BTREE(status)`

### `organization.directorates`

| Column | Type | Constraints |
|--------|------|-------------|
| id | BIGINT | PK |
| ministry_id | BIGINT | FK → ministries |
| code | VARCHAR(20) | UNIQUE NOT NULL |
| name | VARCHAR(255) | NOT NULL |
| region | VARCHAR(100) | |
| status | SMALLINT | NOT NULL DEFAULT 1 |
| created_at | TIMESTAMPTZ | NOT NULL |
| updated_at | TIMESTAMPTZ | NOT NULL |

**Indexes:** `BTREE(ministry_id)`, `UNIQUE(code)`

### `organization.schools`

| Column | Type | Constraints |
|--------|------|-------------|
| id | BIGINT | PK |
| directorate_id | BIGINT | FK → directorates |
| code | VARCHAR(20) | UNIQUE NOT NULL |
| name | VARCHAR(255) | NOT NULL |
| school_type | SMALLINT | NOT NULL |
| address | TEXT | |
| phone | VARCHAR(30) | |
| email | VARCHAR(255) | |
| status | SMALLINT | NOT NULL DEFAULT 1 |
| created_at | TIMESTAMPTZ | NOT NULL |
| updated_at | TIMESTAMPTZ | NOT NULL |

**Indexes:** `BTREE(directorate_id)`, `BTREE(status)`, `UNIQUE(code)`

### `organization.branches`

| Column | Type | Constraints |
|--------|------|-------------|
| id | BIGINT | PK |
| school_id | BIGINT | FK → schools |
| code | VARCHAR(20) | NOT NULL |
| name | VARCHAR(255) | NOT NULL |
| address | TEXT | |
| status | SMALLINT | NOT NULL DEFAULT 1 |
| created_at | TIMESTAMPTZ | NOT NULL |
| updated_at | TIMESTAMPTZ | NOT NULL |

**Indexes:** `BTREE(school_id)`, `UNIQUE(school_id, code)`

### `organization.departments`

| Column | Type | Constraints |
|--------|------|-------------|
| id | BIGINT | PK |
| school_id | BIGINT | FK → schools |
| branch_id | BIGINT | FK → branches, nullable |
| code | VARCHAR(20) | NOT NULL |
| name | VARCHAR(255) | NOT NULL |
| department_type | SMALLINT | NOT NULL |
| status | SMALLINT | NOT NULL DEFAULT 1 |
| created_at | TIMESTAMPTZ | NOT NULL |
| updated_at | TIMESTAMPTZ | NOT NULL |

**Indexes:** `BTREE(school_id)`, `BTREE(branch_id)`

### `organization.rooms`

| Column | Type | Constraints |
|--------|------|-------------|
| id | BIGINT | PK |
| branch_id | BIGINT | FK → branches |
| code | VARCHAR(20) | NOT NULL |
| name | VARCHAR(100) | NOT NULL |
| capacity | SMALLINT | |
| room_type | SMALLINT | NOT NULL |
| status | SMALLINT | NOT NULL DEFAULT 1 |
| created_at | TIMESTAMPTZ | NOT NULL |
| updated_at | TIMESTAMPTZ | NOT NULL |

**Indexes:** `BTREE(branch_id)`, `UNIQUE(branch_id, code)`

---

## Schema: `academic` (5 tables)

### `academic.academic_years`

| Column | Type | Constraints |
|--------|------|-------------|
| id | BIGINT | PK |
| code | VARCHAR(20) | UNIQUE NOT NULL |
| name | VARCHAR(100) | NOT NULL |
| start_date | DATE | NOT NULL |
| end_date | DATE | NOT NULL |
| is_current | BOOLEAN | NOT NULL DEFAULT false |
| status | SMALLINT | NOT NULL DEFAULT 1 |
| created_at | TIMESTAMPTZ | NOT NULL |
| updated_at | TIMESTAMPTZ | NOT NULL |

**Indexes:** `UNIQUE(code)`, `PARTIAL(is_current) WHERE is_current = true`

### `academic.terms`

| Column | Type | Constraints |
|--------|------|-------------|
| id | BIGINT | PK |
| academic_year_id | BIGINT | FK → academic_years |
| code | VARCHAR(20) | NOT NULL |
| name | VARCHAR(100) | NOT NULL |
| start_date | DATE | NOT NULL |
| end_date | DATE | NOT NULL |
| term_order | SMALLINT | NOT NULL |
| status | SMALLINT | NOT NULL DEFAULT 1 |
| created_at | TIMESTAMPTZ | NOT NULL |
| updated_at | TIMESTAMPTZ | NOT NULL |

**Indexes:** `BTREE(academic_year_id)`, `UNIQUE(academic_year_id, code)`

### `academic.grade_levels`

| Column | Type | Constraints |
|--------|------|-------------|
| id | SMALLINT | PK |
| code | VARCHAR(10) | UNIQUE NOT NULL |
| name | VARCHAR(100) | NOT NULL |
| level_order | SMALLINT | NOT NULL |
| education_stage | SMALLINT | NOT NULL |
| status | SMALLINT | NOT NULL DEFAULT 1 |

**Indexes:** `UNIQUE(code)`, `BTREE(level_order)`

### `academic.holidays`

| Column | Type | Constraints |
|--------|------|-------------|
| id | BIGINT | PK |
| academic_year_id | BIGINT | FK → academic_years |
| school_id | BIGINT | FK → schools, nullable |
| name | VARCHAR(255) | NOT NULL |
| start_date | DATE | NOT NULL |
| end_date | DATE | NOT NULL |
| holiday_type | SMALLINT | NOT NULL |
| created_at | TIMESTAMPTZ | NOT NULL |

**Indexes:** `BTREE(academic_year_id)`, `BTREE(school_id, start_date)`

### `academic.system_settings`

| Column | Type | Constraints |
|--------|------|-------------|
| id | BIGINT | PK |
| school_id | BIGINT | FK → schools, nullable |
| setting_key | VARCHAR(100) | NOT NULL |
| setting_value | JSONB | NOT NULL |
| description | TEXT | |
| updated_at | TIMESTAMPTZ | NOT NULL |

**Indexes:** `UNIQUE(school_id, setting_key)`

---

## Schema: `vocational` (3 tables)

> **Phase TV-U05:** FORCE RLS on all three. `specializations` by `school_id`; `tracks` / `specialization_subjects` via EXISTS → specialization.school_id. Hard DELETE rejected.

### `vocational.specializations`

| Column | Type | Constraints |
|--------|------|-------------|
| id | BIGINT | PK |
| school_id | BIGINT | FK → schools |
| code | VARCHAR(20) | NOT NULL |
| name | VARCHAR(255) | NOT NULL |
| description | TEXT | |
| status | SMALLINT | NOT NULL DEFAULT 1 |
| created_at | TIMESTAMPTZ | NOT NULL |
| updated_at | TIMESTAMPTZ | NOT NULL |

**Indexes:** `BTREE(school_id)`, `UNIQUE(school_id, code)`  
**RLS:** ENABLE + FORCE  
**Triggers:** reject hard DELETE

### `vocational.tracks`

| Column | Type | Constraints |
|--------|------|-------------|
| id | BIGINT | PK |
| specialization_id | BIGINT | FK → specializations |
| code | VARCHAR(20) | NOT NULL |
| name | VARCHAR(255) | NOT NULL |
| status | SMALLINT | NOT NULL DEFAULT 1 |
| created_at | TIMESTAMPTZ | NOT NULL |
| updated_at | TIMESTAMPTZ | NOT NULL |

**Indexes:** `BTREE(specialization_id)`  
**RLS:** ENABLE + FORCE (via specialization.school_id)  
**Triggers:** reject hard DELETE

### `vocational.specialization_subjects`

| Column | Type | Constraints |
|--------|------|-------------|
| id | BIGINT | PK |
| specialization_id | BIGINT | FK → specializations |
| subject_id | BIGINT | FK → curriculum.subjects |
| is_required | BOOLEAN | NOT NULL DEFAULT true |
| credit_hours | SMALLINT | |
| status | SMALLINT | 1=Active, 2=Inactive (TV-U06) |

**Indexes:** `UNIQUE(specialization_id, subject_id)`  
**RLS:** ENABLE + FORCE (via specialization.school_id)  
**Triggers:** reject hard DELETE

---

## Schema: `students` (4 tables)

### `students.students`

| Column | Type | Constraints |
|--------|------|-------------|
| id | BIGINT | PK |
| school_id | BIGINT | FK → organization.schools, nullable |
| public_id | UUID | UNIQUE DEFAULT gen_random_uuid() |
| student_code | VARCHAR(50) | UNIQUE NOT NULL |
| national_id | VARCHAR(20) | UNIQUE |
| first_name | VARCHAR(100) | NOT NULL |
| middle_name | VARCHAR(100) | |
| last_name | VARCHAR(100) | NOT NULL |
| full_name | VARCHAR(255) | NOT NULL |
| gender | SMALLINT | NOT NULL |
| birth_date | DATE | NOT NULL |
| birth_place | VARCHAR(255) | |
| nationality | VARCHAR(50) | |
| photo_storage_key | VARCHAR(500) | |
| status | SMALLINT | NOT NULL DEFAULT 1 |
| created_at | TIMESTAMPTZ | NOT NULL |
| updated_at | TIMESTAMPTZ | NOT NULL |

**Indexes:**
- `BTREE(school_id)`
- `UNIQUE(student_code)`
- `UNIQUE(national_id)` (partial: WHERE national_id IS NOT NULL)
- `PARTIAL(status) WHERE status = 1` — active students
- `INCLUDE(student_code, full_name) ON (status)` — covering for lists

### `students.student_contacts`

| Column | Type | Constraints |
|--------|------|-------------|
| id | BIGINT | PK |
| student_id | BIGINT | FK → students |
| contact_type | SMALLINT | NOT NULL |
| value | VARCHAR(255) | NOT NULL |
| is_primary | BOOLEAN | NOT NULL DEFAULT false |
| created_at | TIMESTAMPTZ | NOT NULL |

**Indexes:** `BTREE(student_id)`, `BTREE(student_id, contact_type)`

### `students.student_addresses`

| Column | Type | Constraints |
|--------|------|-------------|
| id | BIGINT | PK |
| student_id | BIGINT | FK → students |
| address_type | SMALLINT | NOT NULL |
| address_line | TEXT | NOT NULL |
| city | VARCHAR(100) | |
| region | VARCHAR(100) | |
| postal_code | VARCHAR(20) | |
| is_current | BOOLEAN | NOT NULL DEFAULT true |
| created_at | TIMESTAMPTZ | NOT NULL |

**Indexes:** `BTREE(student_id)`, `PARTIAL(is_current) WHERE is_current = true`

### `students.student_documents`

| Column | Type | Constraints |
|--------|------|-------------|
| id | BIGINT | PK |
| student_id | BIGINT | FK → students |
| document_type | SMALLINT | NOT NULL |
| storage_key | VARCHAR(500) | NOT NULL |
| file_name | VARCHAR(255) | NOT NULL |
| mime_type | VARCHAR(100) | NOT NULL |
| file_size | BIGINT | NOT NULL |
| file_hash | VARCHAR(64) | NOT NULL |
| uploaded_by | BIGINT | FK → security.users |
| created_at | TIMESTAMPTZ | NOT NULL |

**Indexes:** `BTREE(student_id)`, `BTREE(student_id, document_type)`

---

## Schema: `guardians` (3 tables)

### `guardians.guardians`

| Column | Type | Constraints |
|--------|------|-------------|
| id | BIGINT | PK |
| national_id | VARCHAR(20) | UNIQUE |
| first_name | VARCHAR(100) | NOT NULL |
| last_name | VARCHAR(100) | NOT NULL |
| full_name | VARCHAR(255) | NOT NULL |
| phone | VARCHAR(30) | |
| email | VARCHAR(255) | |
| occupation | VARCHAR(100) | |
| created_at | TIMESTAMPTZ | NOT NULL |
| updated_at | TIMESTAMPTZ | NOT NULL |

**Indexes:** `UNIQUE(national_id)`, `BTREE(phone)`

### `guardians.student_guardians`

| Column | Type | Constraints |
|--------|------|-------------|
| id | BIGINT | PK |
| student_id | BIGINT | FK → students.students |
| guardian_id | BIGINT | FK → guardians |
| relationship_type | SMALLINT | NOT NULL |
| is_primary | BOOLEAN | NOT NULL DEFAULT false |
| is_emergency_contact | BOOLEAN | NOT NULL DEFAULT false |
| created_at | TIMESTAMPTZ | NOT NULL |

**Indexes:** `BTREE(student_id)`, `BTREE(guardian_id)`, `UNIQUE(student_id, guardian_id)`

### `guardians.guardian_addresses`

| Column | Type | Constraints |
|--------|------|-------------|
| id | BIGINT | PK |
| guardian_id | BIGINT | FK → guardians |
| address_line | TEXT | NOT NULL |
| city | VARCHAR(100) | |
| is_current | BOOLEAN | NOT NULL DEFAULT true |
| created_at | TIMESTAMPTZ | NOT NULL |

**Indexes:** `BTREE(guardian_id)`

---

## Schema: `admission` (3 tables)

### `admission.application_periods`

| Column | Type | Constraints |
|--------|------|-------------|
| id | BIGINT | PK |
| academic_year_id | BIGINT | FK → academic_years |
| school_id | BIGINT | FK → schools |
| name | VARCHAR(255) | NOT NULL |
| start_date | TIMESTAMPTZ | NOT NULL |
| end_date | TIMESTAMPTZ | NOT NULL |
| max_applications | INTEGER | nullable; CHECK NULL OR > 0 |
| status | SMALLINT | NOT NULL DEFAULT 1; CHECK IN (0,1,2) |
| created_at | TIMESTAMPTZ | NOT NULL |

**Indexes:** `BTREE(academic_year_id, school_id)`

**RLS:** Fail-closed on `school_id` (Phase 2).

**CHECK:** `end_date >= start_date`

### `admission.applications`

| Column | Type | Constraints |
|--------|------|-------------|
| id | BIGINT | PK |
| application_period_id | BIGINT | FK → application_periods |
| application_number | VARCHAR(50) | UNIQUE NOT NULL |
| first_name | VARCHAR(100) | NOT NULL |
| last_name | VARCHAR(100) | NOT NULL |
| national_id | VARCHAR(20) | nullable — **not** globally unique |
| birth_date | DATE | NOT NULL |
| gender | SMALLINT | NOT NULL; CHECK IN (1, 2) |
| grade_level_id | SMALLINT | FK → grade_levels |
| specialization_id | BIGINT | FK → specializations, nullable |
| status | SMALLINT | NOT NULL DEFAULT 1; CHECK 1–9 (see ApplicationStatus) |
| submitted_at | TIMESTAMPTZ | |
| reviewed_by | BIGINT | FK → public.users, nullable |
| reviewed_at | TIMESTAMPTZ | |
| notes | TEXT | |
| student_id | BIGINT | FK → students.students, nullable — **conversion link only** (Phase 2) |
| created_at | TIMESTAMPTZ | NOT NULL |
| updated_at | TIMESTAMPTZ | NOT NULL |

**Indexes:** `UNIQUE(application_number)`, `BTREE(application_period_id)`, `BTREE(status)`, `PARTIAL BTREE(student_id) WHERE student_id IS NOT NULL`

**Identity rule:** Applicant PII lives on the application until conversion. Do **not** create `admission.students`. Waitlist = status 5; interviews/status_history tables deferred (not in 87 SSOT).

**RLS:** Fail-closed via parent `application_periods.school_id` (Phase 2).

### `admission.application_documents`

| Column | Type | Constraints |
|--------|------|-------------|
| id | BIGINT | PK |
| application_id | BIGINT | FK → applications |
| document_type | SMALLINT | NOT NULL; CHECK > 0 |
| storage_key | VARCHAR(500) | NOT NULL |
| file_name | VARCHAR(255) | NOT NULL |
| file_hash | VARCHAR(64) | NOT NULL |
| created_at | TIMESTAMPTZ | NOT NULL |

**Indexes:** `BTREE(application_id)`

**RLS:** Fail-closed via application → period.school_id (Phase 2).

---

## Schema: `enrollment` (4 tables)

### `enrollment.classes`

| Column | Type | Constraints |
|--------|------|-------------|
| id | BIGINT | PK |
| school_id | BIGINT | FK → schools |
| academic_year_id | BIGINT | FK → academic_years |
| grade_level_id | SMALLINT | FK → grade_levels |
| code | VARCHAR(20) | NOT NULL |
| name | VARCHAR(100) | NOT NULL |
| capacity | SMALLINT | |
| status | SMALLINT | NOT NULL DEFAULT 1 |
| created_at | TIMESTAMPTZ | NOT NULL |
| updated_at | TIMESTAMPTZ | NOT NULL |

**Indexes:** `BTREE(school_id, academic_year_id)`, `UNIQUE(school_id, academic_year_id, code)`

### `enrollment.sections`

| Column | Type | Constraints |
|--------|------|-------------|
| id | BIGINT | PK |
| class_id | BIGINT | FK → classes |
| code | VARCHAR(20) | NOT NULL |
| name | VARCHAR(100) | NOT NULL |
| capacity | SMALLINT | |
| homeroom_teacher_id | BIGINT | FK → teachers.teachers, nullable |
| status | SMALLINT | NOT NULL DEFAULT 1 |
| created_at | TIMESTAMPTZ | NOT NULL |
| updated_at | TIMESTAMPTZ | NOT NULL |

**Indexes:** `BTREE(class_id)`, `UNIQUE(class_id, code)`

### `enrollment.enrollments`

| Column | Type | Constraints |
|--------|------|-------------|
| id | BIGINT | PK |
| student_id | BIGINT | FK → students.students |
| academic_year_id | BIGINT | FK → academic_years |
| school_id | BIGINT | FK → schools |
| class_id | BIGINT | FK → classes |
| section_id | BIGINT | FK → sections |
| specialization_id | BIGINT | FK → specializations, nullable |
| enrollment_number | VARCHAR(50) | UNIQUE NOT NULL |
| status | SMALLINT | NOT NULL DEFAULT 1 |
| effective_from | DATE | NOT NULL |
| effective_to | DATE | |
| enrolled_by | BIGINT | FK → security.users |
| created_at | TIMESTAMPTZ | NOT NULL |
| updated_at | TIMESTAMPTZ | NOT NULL |

**Indexes:**
- `BTREE(student_id)`
- `COMPOSITE(school_id, academic_year_id)`
- `COMPOSITE(student_id, academic_year_id)`
- `PARTIAL UNIQUE(student_id, academic_year_id) WHERE status = 1`

### `enrollment.enrollment_subjects`

| Column | Type | Constraints |
|--------|------|-------------|
| id | BIGINT | PK |
| enrollment_id | BIGINT | FK → enrollments |
| subject_id | BIGINT | FK → curriculum.subjects |
| is_elective | BOOLEAN | NOT NULL DEFAULT false |
| status | SMALLINT | NOT NULL DEFAULT 1 |
| created_at | TIMESTAMPTZ | NOT NULL |

**Indexes:** `BTREE(enrollment_id)`, `UNIQUE(enrollment_id, subject_id)`

---

## Schema: `teachers` (4 tables)

### `teachers.teachers`

| Column | Type | Constraints |
|--------|------|-------------|
| id | BIGINT | PK |
| user_id | BIGINT | FK → security.users, nullable |
| employee_code | VARCHAR(50) | UNIQUE NOT NULL |
| national_id | VARCHAR(20) | UNIQUE |
| first_name | VARCHAR(100) | NOT NULL |
| last_name | VARCHAR(100) | NOT NULL |
| full_name | VARCHAR(255) | NOT NULL |
| specialization_field | VARCHAR(255) | |
| hire_date | DATE | |
| status | SMALLINT | NOT NULL DEFAULT 1 |
| created_at | TIMESTAMPTZ | NOT NULL |
| updated_at | TIMESTAMPTZ | NOT NULL |

**Indexes:** `UNIQUE(employee_code)`, `BTREE(status)`

### `teachers.teacher_schools`

| Column | Type | Constraints |
|--------|------|-------------|
| id | BIGINT | PK |
| teacher_id | BIGINT | FK → teachers |
| school_id | BIGINT | FK → schools |
| academic_year_id | BIGINT | FK → academic_years |
| is_primary | BOOLEAN | NOT NULL DEFAULT true |
| created_at | TIMESTAMPTZ | NOT NULL |

**Indexes:** `BTREE(teacher_id)`, `BTREE(school_id, academic_year_id)`

### `teachers.teacher_subjects`

| Column | Type | Constraints |
|--------|------|-------------|
| id | BIGINT | PK |
| teacher_id | BIGINT | FK → teachers |
| subject_id | BIGINT | FK → curriculum.subjects |
| academic_year_id | BIGINT | FK → academic_years |
| school_id | BIGINT | FK → schools |
| created_at | TIMESTAMPTZ | NOT NULL |

**Indexes:** `BTREE(teacher_id, academic_year_id)`, `UNIQUE(teacher_id, subject_id, academic_year_id, school_id)`

### `teachers.teacher_qualifications`

| Column | Type | Constraints |
|--------|------|-------------|
| id | BIGINT | PK |
| teacher_id | BIGINT | FK → teachers |
| qualification_type | SMALLINT | NOT NULL |
| title | VARCHAR(255) | NOT NULL |
| institution | VARCHAR(255) | |
| year_obtained | SMALLINT | |
| document_storage_key | VARCHAR(500) | |
| created_at | TIMESTAMPTZ | NOT NULL |

**Indexes:** `BTREE(teacher_id)`

---

## Schema: `curriculum` (4 tables)

### `curriculum.subjects`

| Column | Type | Constraints |
|--------|------|-------------|
| id | BIGINT | PK |
| code | VARCHAR(20) | UNIQUE NOT NULL |
| name | VARCHAR(255) | NOT NULL |
| name_en | VARCHAR(255) | |
| subject_type | SMALLINT | NOT NULL |
| credit_hours | SMALLINT | |
| max_grade | SMALLINT | NOT NULL DEFAULT 100 |
| pass_grade | SMALLINT | NOT NULL DEFAULT 50 |
| status | SMALLINT | NOT NULL DEFAULT 1 |
| created_at | TIMESTAMPTZ | NOT NULL |
| updated_at | TIMESTAMPTZ | NOT NULL |

**Indexes:** `UNIQUE(code)`, `BTREE(status)`

### `curriculum.curricula`

| Column | Type | Constraints |
|--------|------|-------------|
| id | BIGINT | PK |
| school_id | BIGINT | FK → schools |
| academic_year_id | BIGINT | FK → academic_years |
| grade_level_id | SMALLINT | FK → grade_levels |
| specialization_id | BIGINT | FK → specializations, nullable |
| name | VARCHAR(255) | NOT NULL |
| status | SMALLINT | NOT NULL DEFAULT 1 |
| created_at | TIMESTAMPTZ | NOT NULL |
| updated_at | TIMESTAMPTZ | NOT NULL |

**Indexes:** `BTREE(school_id, academic_year_id, grade_level_id)`

### `curriculum.curriculum_subjects`

| Column | Type | Constraints |
|--------|------|-------------|
| id | BIGINT | PK |
| curriculum_id | BIGINT | FK → curricula |
| subject_id | BIGINT | FK → subjects |
| weekly_hours | SMALLINT | |
| is_required | BOOLEAN | NOT NULL DEFAULT true |
| subject_order | SMALLINT | NOT NULL DEFAULT 0 |
| created_at | TIMESTAMPTZ | NOT NULL |

**Indexes:** `BTREE(curriculum_id)`, `UNIQUE(curriculum_id, subject_id)`

### `curriculum.prerequisites`

| Column | Type | Constraints |
|--------|------|-------------|
| id | BIGINT | PK |
| subject_id | BIGINT | FK → subjects |
| prerequisite_subject_id | BIGINT | FK → subjects |
| created_at | TIMESTAMPTZ | NOT NULL |

**Indexes:** `UNIQUE(subject_id, prerequisite_subject_id)`

---

## Schema: `timetable` (3 tables)

### `timetable.periods`

> **Phase TV-U01:** FORCE RLS + reject hard DELETE. PK remains SMALLINT (attendance contract; HD-TV-004 debt).

| Column | Type | Constraints |
|--------|------|-------------|
| id | SMALLINT | PK |
| school_id | BIGINT | FK → schools |
| period_number | SMALLINT | NOT NULL |
| start_time | TIME | NOT NULL |
| end_time | TIME | NOT NULL |
| period_type | SMALLINT | NOT NULL DEFAULT 1 |

**Indexes:** `UNIQUE(school_id, period_number)`  
**RLS:** ENABLE + FORCE (`periods_school_isolation`)  
**Triggers:** `periods_reject_delete`

### `timetable.schedules`

> **Phase TV-U02:** Operational capacity schedule rows — **not** grade/result SSOT.  
> Enrichment vs sketch: `school_id`, soft lifecycle (`lifecycle_status` + `cancelled_at`), conflict partial uniques, FORCE RLS.

| Column | Type | Constraints |
|--------|------|-------------|
| id | BIGINT | PK IDENTITY |
| school_id | BIGINT | FK → schools NOT NULL |
| section_id | BIGINT | FK → enrollment.sections |
| academic_year_id | BIGINT | FK → academic_years |
| day_of_week | SMALLINT | CHECK 1–7 |
| period_id | SMALLINT | FK → periods (composite with school_id) |
| subject_id | BIGINT | FK → curriculum.subjects |
| teacher_id | BIGINT | FK → teachers.teachers |
| room_id | BIGINT | FK → rooms, nullable |
| lifecycle_status | SMALLINT | 1=Active, 2=Cancelled |
| cancelled_at | TIMESTAMPTZ | NULL iff Active |
| correlation_id / created_by | | |
| created_at / updated_at | TIMESTAMPTZ | |

**Indexes:** partial UNIQUE active section/teacher/room slots; BTREE school/year, section/year  
**RLS:** ENABLE + FORCE  
**Triggers:** reject hard DELETE

### `timetable.schedule_exceptions`

> **Phase TV-U03:** Per-date substitute teacher/room for a schedule. FORCE RLS; reject hard DELETE.

| Column | Type | Constraints |
|--------|------|-------------|
| id | BIGINT | PK IDENTITY |
| school_id | BIGINT | FK → schools |
| schedule_id | BIGINT | FK → schedules (composite with school_id) |
| exception_date | DATE | NOT NULL |
| substitute_teacher_id | BIGINT | FK → teachers, nullable |
| substitute_room_id | BIGINT | FK → rooms, nullable |
| reason | TEXT | |
| correlation_id / created_by | | |
| created_at / updated_at | TIMESTAMPTZ | |

**Indexes:** `UNIQUE(schedule_id, exception_date)`, `BTREE(exception_date)`, `BTREE(school_id)`  
**RLS:** ENABLE + FORCE  
**Triggers:** reject hard DELETE

---

## Schema: `attendance` (3 tables)

### `attendance.sessions`

| Column | Type | Constraints |
|--------|------|-------------|
| id | BIGINT | PK |
| school_id | BIGINT | FK → organization.schools — **denormalized historical ownership (R1.7 Strategy A)**; stamped at create; authoritative for session RLS |
| section_id | BIGINT | FK → enrollment.sections |
| subject_id | BIGINT | FK → curriculum.subjects |
| academic_year_id | BIGINT | FK → academic_years |
| session_date | DATE | NOT NULL |
| period_id | SMALLINT | FK → timetable.periods, nullable |
| teacher_id | BIGINT | FK → teachers.teachers |
| status | SMALLINT | NOT NULL DEFAULT 1; **CHECK status IN (1,2,3)** (R1.8A vocabulary) |
| created_at | TIMESTAMPTZ | NOT NULL |

**Indexes:** `BTREE(section_id, session_date)`, `BTREE(academic_year_id, session_date)`, `BTREE(school_id)` (RLS)

**Integrity (R1.8A):** Partial UNIQUE `attendance_sessions_open_natural_key_uidx` ON `(school_id, academic_year_id, section_id, subject_id, session_date, period_id) NULLS NOT DISTINCT WHERE status = 1` — at most one OPEN session per natural key; CLOSED recreation allowed; NULL period is its own bucket.

**RLS (R1.7):** ENABLE + FORCE; fail-closed `school_id = app.current_school_id`; SELECT/INSERT/UPDATE only (DELETE denied under RLS).

### `attendance.records` ⚡ PARTITIONED (P0 for 45K scenario)

| Column | Type | Constraints |
|--------|------|-------------|
| id | BIGINT | PK (with academic_year_id) |
| session_id | BIGINT | FK → sessions |
| student_id | BIGINT | FK → students.students |
| enrollment_id | BIGINT | FK → enrollment.enrollments |
| academic_year_id | BIGINT | FK → academic_years, **PARTITION KEY** |
| school_id | BIGINT | FK → organization.schools (denormalized for RLS) |
| attendance_date | DATE | NOT NULL |
| status | SMALLINT | NOT NULL |
| notes | TEXT | |
| recorded_by | BIGINT | FK → users |
| created_at | TIMESTAMPTZ | NOT NULL |
| updated_at | TIMESTAMPTZ | NOT NULL |

**Partition:** LIST by `academic_year_id` — **P0 from year 1** (45M rows/year at 45K students)

**RLS (R1.7):** ENABLE + FORCE; fail-closed school_id; SELECT/INSERT/UPDATE only (DELETE denied under RLS).

**Indexes:**
- `COMPOSITE(student_id, attendance_date)`
- `UNIQUE(session_id, student_id)`
- `BTREE(academic_year_id, attendance_date)`
- `BTREE(school_id, attendance_date)` — RLS + school reports

### `attendance.daily_section_summary` ⚡ NEW (P0 for 45K scenario)

| Column | Type | Constraints |
|--------|------|-------------|
| section_id | BIGINT | FK → enrollment.sections |
| school_id | BIGINT | FK → organization.schools |
| academic_year_id | BIGINT | FK → academic_years |
| attendance_date | DATE | NOT NULL |
| total_students | SMALLINT | NOT NULL DEFAULT 0 |
| present_count | SMALLINT | NOT NULL DEFAULT 0 |
| absent_count | SMALLINT | NOT NULL DEFAULT 0 |
| late_count | SMALLINT | NOT NULL DEFAULT 0 |
| updated_at | TIMESTAMPTZ | NOT NULL |

**PK:** `(section_id, attendance_date)`

**Purpose:** Dashboard reads 900 rows instead of scanning 45,000 attendance records.

**RLS (R1.7):** ENABLE + FORCE; fail-closed school_id; SELECT/INSERT/UPDATE only (DELETE denied under RLS).

**Indexes:**
- `BTREE(school_id, attendance_date)` — school daily report
- `BTREE(academic_year_id, attendance_date)` — directorate report

**Refresh:** After each batch attendance write — see batch-write-patterns.md

---

## Schema: `exams` (5 tables)

### `exams.exam_types`

| Column | Type | Constraints |
|--------|------|-------------|
| id | SMALLINT | PK |
| code | VARCHAR(20) | UNIQUE NOT NULL |
| name | VARCHAR(100) | NOT NULL |
| weight_percentage | SMALLINT | CHECK (weight_percentage BETWEEN 0 AND 100) |

**RLS:** None — global reference catalog (not school-scoped). Codes are data rows, not hard-coded DDL vocabulary.

### `exams.exams`

| Column | Type | Constraints |
|--------|------|-------------|
| id | BIGINT | PK |
| academic_year_id | BIGINT | FK → academic_years |
| school_id | BIGINT | FK → schools — direct ownership for fail-closed RLS |
| term_id | BIGINT | FK → academic.terms |
| exam_type_id | SMALLINT | FK → exam_types |
| name | VARCHAR(255) | NOT NULL |
| start_date | DATE | NOT NULL |
| end_date | DATE | NOT NULL |
| status | SMALLINT | NOT NULL DEFAULT 1 |
| created_at | TIMESTAMPTZ | NOT NULL |
| updated_at | TIMESTAMPTZ | NOT NULL |

**Indexes:** `BTREE(academic_year_id, school_id)`, `BTREE(status)`  
**Also:** `UNIQUE(id, school_id)` — enables composite FKs from sessions (school_id consistency).

**RLS:** ENABLE + FORCE; policy on `school_id` = GUC `app.current_school_id` (fail-closed).

### `exams.exam_sessions`

| Column | Type | Constraints |
|--------|------|-------------|
| id | BIGINT | PK |
| exam_id | BIGINT | FK → exams |
| school_id | BIGINT | FK → schools; composite FK `(exam_id, school_id)` → `exams(id, school_id)` — denorm for fail-closed RLS |
| subject_id | BIGINT | FK → curriculum.subjects |
| session_date | DATE | NOT NULL |
| start_time | TIME | NOT NULL |
| end_time | TIME | NOT NULL |
| room_id | BIGINT | FK → organization.rooms, nullable |
| max_grade | SMALLINT | NOT NULL DEFAULT 100 |
| pass_grade | SMALLINT | NOT NULL DEFAULT 50 |
| status | SMALLINT | NOT NULL DEFAULT 1 |
| created_at | TIMESTAMPTZ | NOT NULL |

**Indexes:** `BTREE(exam_id)`, `BTREE(subject_id, session_date)`, `BTREE(school_id)` (RLS / school schedule)

**RLS:** ENABLE + FORCE; policy on `school_id` = GUC `app.current_school_id` (fail-closed).

### `exams.exam_enrollments`

| Column | Type | Constraints |
|--------|------|-------------|
| id | BIGINT | PK |
| exam_session_id | BIGINT | FK → exam_sessions |
| school_id | BIGINT | FK → schools; composite FK `(exam_session_id, school_id)` → `exam_sessions(id, school_id)`; composite FK `(enrollment_id, school_id)` → `enrollment.enrollments(id, school_id)` — denorm for RLS + prevent cross-school seating |
| enrollment_id | BIGINT | FK → enrollment.enrollments |
| seat_number | VARCHAR(10) | |
| status | SMALLINT | NOT NULL DEFAULT 1 |
| created_at | TIMESTAMPTZ | NOT NULL |

**Indexes:** `BTREE(exam_session_id)`, `UNIQUE(exam_session_id, enrollment_id)`, `BTREE(enrollment_id)`, `BTREE(school_id)`

**RLS:** ENABLE + FORCE; policy on `school_id` = GUC (fail-closed).

> **Phase 3A enrichment (approved):** `school_id` denorm on sessions/enrollments for FORCE RLS without multi-join policies. Does not change blueprint object count (still 87).

### `exams.student_grades` ⚡ PARTITIONED (Phase 3B)

| Column | Type | Constraints |
|--------|------|-------------|
| id | BIGINT | IDENTITY; composite PK `(id, academic_year_id)` |
| academic_year_id | BIGINT | FK → academic_years; **LIST partition key** |
| school_id | BIGINT | FK → schools — direct RLS ownership |
| exam_enrollment_id | BIGINT | FK → exam_enrollments; composite `(exam_enrollment_id, school_id)` |
| exam_session_id | BIGINT | FK → exam_sessions; composite `(exam_session_id, school_id)` — denorm |
| enrollment_id | BIGINT | FK → enrollments; composite `(enrollment_id, school_id)` — denorm |
| student_id | BIGINT | FK → students.students — denorm |
| subject_id | BIGINT | FK → curriculum.subjects — denorm |
| score | NUMERIC(5,2) | nullable when `is_absent`; CHECK with max/absent rules |
| max_score | NUMERIC(5,2) | NOT NULL; snapshot of session max at grading time; `> 0` |
| is_absent | BOOLEAN | NOT NULL DEFAULT false |
| status | SMALLINT | NOT NULL DEFAULT 1 — Draft/Entered/Submitted/Finalized/Voided |
| is_current | BOOLEAN | NOT NULL DEFAULT true — at most one current per enrollment/year |
| correction_of_grade_id | BIGINT | nullable; self-FK `(correction_of_grade_id, academic_year_id)` → `(id, academic_year_id)` |
| entered_by | BIGINT | FK → users, nullable |
| entered_at | TIMESTAMPTZ | NOT NULL |
| finalized_at | TIMESTAMPTZ | nullable |
| created_at | TIMESTAMPTZ | NOT NULL |
| updated_at | TIMESTAMPTZ | NOT NULL |

**Partition:** `PARTITION BY LIST (academic_year_id)` — **no DEFAULT partition**. Explicit partition per academic year required before inserts.

**Uniqueness (active SSOT):** partial UNIQUE `(exam_enrollment_id, academic_year_id) WHERE is_current`

**Indexes:**
- `BTREE(student_id, academic_year_id)` — transcript / student year
- `BTREE(enrollment_id, academic_year_id)` — enrollment profile
- `BTREE(school_id, academic_year_id, subject_id)` — teacher entry grids
- `BTREE(exam_session_id, academic_year_id)` — session markbook
- `BTREE(correction_of_grade_id, academic_year_id)` — correction chain (partial WHERE NOT NULL)

**RLS:** ENABLE + FORCE; fail-closed on `school_id` = GUC `app.current_school_id`

**Correction model:** VOID + INSERT replacement (`is_current`, `correction_of_grade_id`). **No hard DELETE.**

**SSOT:** Authoritative scores live only here — not on `exam_enrollments`. `results.term_results` is a **derived versioned projection** (Phase 7.4-U01); never a second grade ledger. GPA/ranking/transcript remain Phase 7.5.

> **Phase 3B enrichment (approved):** replaces earlier blueprint sketch (`grade` → `score`; adds `exam_enrollment_id`, `school_id`, lifecycle, partition PK). Object count remains **87**.
---

## Schema: `results` (6 tables)

### `results.term_results`

> **Phase 7.4-U01 (LOCKED):** Versioned **derived** subject-term snapshots — **NOT** grade SSOT.  
> Supersedes earlier blueprint sketch (`total_grade` / `grade_letter` / `rank_*`).  
> Letter / GPA / ranking deferred to Phase 7.5. Object count remains **87** (physicalizes existing object).

| Column | Type | Constraints |
|--------|------|-------------|
| id | BIGINT | PK IDENTITY |
| school_id | BIGINT | FK → schools NOT NULL |
| enrollment_id | BIGINT | FK → enrollments (composite school/year) |
| student_id | BIGINT | FK → students |
| academic_year_id | BIGINT | FK → academic_years NOT NULL |
| term_id | BIGINT | FK → terms (composite year) |
| subject_id | BIGINT | FK → subjects |
| result_version | INT | ≥ 1; UNIQUE with identity |
| lifecycle_status | SMALLINT | 1=Calculated 2=Finalized 3=Superseded |
| is_official | BOOLEAN | NOT NULL |
| is_current_operational | BOOLEAN | partial UNIQUE per identity |
| is_current_official | BOOLEAN | partial UNIQUE per identity |
| weighted_total | NUMERIC(8,2) | nullable |
| pass_fail | SMALLINT | NULL/0/1 |
| incomplete | BOOLEAN | NOT NULL DEFAULT false |
| source_fingerprint | VARCHAR(128) | NOT NULL |
| calculation_version | INT | NOT NULL |
| policy_pin | JSONB | NOT NULL |
| calculated_at | TIMESTAMPTZ | NOT NULL |
| finalized_at | TIMESTAMPTZ | |
| superseded_at | TIMESTAMPTZ | |
| correlation_id | VARCHAR(64) | |
| created_by | BIGINT | FK → users SET NULL |
| created_at / updated_at | TIMESTAMPTZ | NOT NULL |

**Indexes:** UNIQUE identity+version; partial UNIQUE current ops/official; BTREE student/year/term; school/year/subject; enrollment/year  
**RLS:** ENABLE + FORCE school isolation  
**Triggers:** reject hard DELETE  
**SSOT:** Scores remain on `exams.student_grades` only.

### `results.annual_results`

> **Phase 7.4-U05 (LOCKED):** Versioned **derived** year rollup — **NOT** grade SSOT.  
> Supersedes earlier blueprint sketch (`gpa` / `rank_*`). GPA/ranking deferred to Phase 7.5. Object count remains **87**.

| Column | Type | Constraints |
|--------|------|-------------|
| id | BIGINT | PK IDENTITY |
| school_id | BIGINT | FK → schools NOT NULL |
| enrollment_id | BIGINT | FK → enrollments (composite school/year) |
| student_id | BIGINT | FK → students |
| academic_year_id | BIGINT | FK → academic_years NOT NULL |
| result_version | INT | ≥ 1 |
| lifecycle_status | SMALLINT | 1=Calculated 2=Finalized 3=Superseded |
| is_official | BOOLEAN | NOT NULL |
| is_current_operational / is_current_official | BOOLEAN | partial UNIQUE |
| subjects_counted / subjects_passed / subjects_incomplete | INT | ≥ 0 |
| average_weighted_total | NUMERIC(8,2) | nullable |
| incomplete | BOOLEAN | NOT NULL |
| source_fingerprint | VARCHAR(128) | NOT NULL |
| calculation_version | INT | NOT NULL |
| policy_pin | JSONB | NOT NULL |
| calculated_at / finalized_at / superseded_at | TIMESTAMPTZ | |
| correlation_id / created_by | | |
| created_at / updated_at | TIMESTAMPTZ | NOT NULL |

**Indexes:** UNIQUE identity+version; partial UNIQUE current ops/official; BTREE student/year; school/year  
**RLS:** ENABLE + FORCE school isolation  
**Triggers:** reject hard DELETE  
**SSOT:** Inputs from official/operational `results.term_results` + grades foundation — never a second mark ledger.

### `results.gpa_results`

> **Phase 7.5-U01 (LOCKED):** Versioned **derived** year-scope GPA — **NOT** grade SSOT.  
> v1 scale = `PERCENT_100` (= official annual `average_weighted_total`). 4.0 conversion deferred.  
> Blueprint object count **88** (+1).

| Column | Type | Constraints |
|--------|------|-------------|
| id | BIGINT | PK IDENTITY |
| school_id | BIGINT | FK → schools NOT NULL |
| enrollment_id | BIGINT | FK → enrollments (composite) |
| student_id | BIGINT | FK → students |
| academic_year_id | BIGINT | FK → academic_years |
| gpa_scope | SMALLINT | 1=academic_year (v1 only) |
| result_version | INT | ≥ 1 |
| lifecycle_status | SMALLINT | 1/2/3 |
| is_official / is_current_operational / is_current_official | BOOLEAN | |
| gpa_value | NUMERIC(8,2) | nullable |
| scale_code | VARCHAR(32) | `PERCENT_100` |
| source_annual_result_id | BIGINT | FK → annual_results |
| incomplete | BOOLEAN | NOT NULL |
| source_fingerprint / policy_pin / calculation_version | | |
| calculated_at / finalized_at / superseded_at | TIMESTAMPTZ | |
| correlation_id / created_by | | |
| created_at / updated_at | TIMESTAMPTZ | NOT NULL |

**Indexes:** UNIQUE identity+scope+version; partial UNIQUE current ops/official; BTREE student/year; school/year  
**RLS:** ENABLE + FORCE  
**Triggers:** reject hard DELETE

### `results.ranking_snapshots`

> **Phase 7.5-U05:** Comparative projection — **NOT** academic truth (DL-005).  
> Cohort: school + academic_year + `enrollment.classes`. Metric v1: official year GPA PERCENT_100.

| Column | Type | Notes |
|--------|------|-------|
| id | BIGINT PK | |
| school_id / academic_year_id / class_id | BIGINT | FKs |
| snapshot_version | INT | |
| lifecycle_status / is_current | | supersede pattern |
| metric_code | VARCHAR | `YEAR_GPA_PERCENT_100` |
| participant_count | INT | |
| source_fingerprint / policy_pin | | |
| calculated_at / superseded_at | TIMESTAMPTZ | |

### `results.ranking_snapshot_entries`

| Column | Type | Notes |
|--------|------|-------|
| ranking_snapshot_id | BIGINT FK | |
| school_id / enrollment_id / student_id | BIGINT | |
| gpa_result_id | BIGINT FK | official GPA source |
| metric_value | NUMERIC(8,2) | |
| rank_position | INT | dense rank |

**RLS:** FORCE on both tables. Hard-delete rejected.

### `results.transcripts`

> **Phase 7.5-U07:** Issued transcript **metadata** only (PDF/render deferred). Immutable issued rows; supersede via new version. FORCE RLS.

| Column | Type | Notes |
|--------|------|-------|
| id | BIGINT PK | |
| school_id / student_id / enrollment_id / academic_year_id | BIGINT | FKs + composite enrollment guards |
| transcript_version | INT | |
| transcript_number | VARCHAR(50) | UNIQUE |
| lifecycle_status / is_current | | Finalized issued; Superseded not current |
| storage_key | VARCHAR(500) | nullable until PDF engine |
| payload_hash | VARCHAR(64) | content/hash commitment |
| source_fingerprint / policy_pin | | official GPA provenance |
| issued_at / issued_by / superseded_at | | |
| correlation_id | VARCHAR(64) | |

**Indexes:** UNIQUE number; UNIQUE identity+version; partial UNIQUE current; BTREE student/year  
**RLS:** ENABLE + FORCE  
**Triggers:** reject hard DELETE

---

## Schema: `promotion` (2 tables)

### `promotion.rules`

| Column | Type | Constraints |
|--------|------|-------------|
| id | BIGINT | PK |
| school_id | BIGINT | FK → schools |
| from_grade_level_id | SMALLINT | FK → grade_levels |
| to_grade_level_id | SMALLINT | FK → grade_levels |
| min_gpa | NUMERIC(4,2) | |
| min_pass_subjects | SMALLINT | |
| max_failed_subjects | SMALLINT | |
| is_active | BOOLEAN | NOT NULL DEFAULT true |
| created_at | TIMESTAMPTZ | NOT NULL |

**Indexes:** `BTREE(school_id, from_grade_level_id)`

### `promotion.records`

| Column | Type | Constraints |
|--------|------|-------------|
| id | BIGINT | PK |
| enrollment_id | BIGINT | FK → enrollment.enrollments |
| academic_year_id | BIGINT | FK → academic_years |
| from_grade_level_id | SMALLINT | FK → grade_levels |
| to_grade_level_id | SMALLINT | FK → grade_levels |
| promotion_status | SMALLINT | NOT NULL |
| gpa_at_promotion | NUMERIC(4,2) | |
| decided_by | BIGINT | FK → security.users |
| decided_at | TIMESTAMPTZ | NOT NULL |
| notes | TEXT | |
| created_at | TIMESTAMPTZ | NOT NULL |

**Indexes:** `BTREE(enrollment_id)`, `BTREE(academic_year_id, promotion_status)`

---

## Schema: `transfers` (2 tables)

### `transfers.transfer_requests`

| Column | Type | Constraints |
|--------|------|-------------|
| id | BIGINT | PK |
| student_id | BIGINT | FK → students.students |
| from_school_id | BIGINT | FK → schools |
| to_school_id | BIGINT | FK → schools |
| from_enrollment_id | BIGINT | FK → enrollment.enrollments |
| academic_year_id | BIGINT | FK → academic_years |
| reason | TEXT | |
| status | SMALLINT | NOT NULL DEFAULT 1 |
| requested_by | BIGINT | FK → security.users |
| requested_at | TIMESTAMPTZ | NOT NULL |
| approved_by | BIGINT | FK → security.users, nullable |
| approved_at | TIMESTAMPTZ | |
| created_at | TIMESTAMPTZ | NOT NULL |

**Indexes:** `BTREE(student_id)`, `BTREE(status)`, `BTREE(academic_year_id)`

### `transfers.transfer_records`

| Column | Type | Constraints |
|--------|------|-------------|
| id | BIGINT | PK |
| transfer_request_id | BIGINT | FK → transfer_requests |
| student_id | BIGINT | FK → students.students |
| from_enrollment_id | BIGINT | FK → enrollment.enrollments |
| to_enrollment_id | BIGINT | FK → enrollment.enrollments |
| effective_date | DATE | NOT NULL |
| completed_at | TIMESTAMPTZ | NOT NULL |
| created_at | TIMESTAMPTZ | NOT NULL |

**Indexes:** `BTREE(student_id)`, `BTREE(transfer_request_id)`

---

## Schema: `graduation` (Phase 3C.12 LIVE — 14 tables)

> **STALE / NON-AUTHORITATIVE (do not implement):** former sketches `graduation.eligibility_rules`, `graduation.records` (min_gpa / credits / etc.). Authority: Phase 3C.10A + 3C.12 migrations `2026_09_10_170100`–`170900`.

### LIVE tables (Option A RLS on each)

`eligibility_policies`, `eligibility_policy_versions`, `requirement_definitions`, `requirement_definition_versions`, `completion_outcomes`, `completion_outcome_versions`, `evidence_sets`, `evidence_items`, `requirement_evaluations`, `graduation_approvals`, `graduation_awards`, `graduation_award_versions`, `outcome_supersessions`, `revocation_records`

Identity grain: `(school_id, enrollment_id)`. Completion ≠ Approval ≠ Award ≠ StudentStatus projection. No launch partitioning. Details: `.cursor/database/phase-3c-10/` + `.cursor/database/phase-3c-12/`.

---

## Schema: `certificates` (6 tables) — Phase 4.1 LIVE

> **Authoritative:** Phase 4.0B / 4.1A Design Locks + migrations `2026_09_11_180100`–`180300`.  
> **STALE (do not implement):** prior sketch `templates` / `issued_certificates.graduation_id` → `graduation.records`.

### `certificates.certificate_templates`

| Column | Type | Constraints |
|--------|------|-------------|
| id | BIGINT IDENTITY | PK |
| school_id | BIGINT | NOT NULL, FK → schools RESTRICT |
| certificate_type | SMALLINT | NOT NULL, CHECK ≥ 1 |
| name | VARCHAR(255) | NOT NULL |
| status | SMALLINT | NOT NULL, CHECK 1–3 |
| created_at | TIMESTAMPTZ | NOT NULL |
| created_by | BIGINT | nullable |

**UNIQUE:** `(school_id, certificate_type, name)`, `(id, school_id)`  
**RLS:** ENABLE + FORCE

### `certificates.certificate_template_versions`

| Column | Type | Constraints |
|--------|------|-------------|
| id | BIGINT IDENTITY | PK |
| school_id | BIGINT | NOT NULL |
| template_id | BIGINT | NOT NULL, composite FK `(template_id, school_id)` RESTRICT |
| version_no | INTEGER | NOT NULL, CHECK ≥ 1 |
| locale | VARCHAR(16) | nullable (no locale uniqueness) |
| content_hash | VARCHAR(128) | NOT NULL (immutable) |
| template_storage_key | VARCHAR(500) | NOT NULL (immutable) |
| effective_from / effective_to | TIMESTAMPTZ | nullable |
| created_at | TIMESTAMPTZ | NOT NULL |
| created_by | BIGINT | nullable |

**UNIQUE:** `(template_id, version_no)`, `(id, school_id)`  
**Immutability:** UPDATE of content/identity columns rejected by trigger

### `certificates.certificates`

| Column | Type | Constraints |
|--------|------|-------------|
| id | BIGINT IDENTITY | PK |
| school_id | BIGINT | NOT NULL |
| enrollment_id | BIGINT | NOT NULL, composite FK `(enrollment_id, school_id)` RESTRICT |
| student_id | BIGINT | NOT NULL, FK → students RESTRICT (denorm) |
| certificate_type | SMALLINT | NOT NULL, CHECK ≥ 1 |
| created_at | TIMESTAMPTZ | NOT NULL |
| created_by | BIGINT | nullable |

**UNIQUE:** `(school_id, enrollment_id, certificate_type)`, `(id, school_id)`

### `certificates.certificate_issuances`

| Column | Type | Constraints |
|--------|------|-------------|
| id | BIGINT IDENTITY | PK |
| school_id | BIGINT | NOT NULL |
| certificate_id | BIGINT | composite FK with school RESTRICT |
| issuance_no | INTEGER | NOT NULL, CHECK ≥ 1 |
| graduation_award_version_id | BIGINT | composite FK `(id, school_id)` → award_versions RESTRICT |
| graduation_award_id | BIGINT | FK → graduation_awards RESTRICT |
| enrollment_id | BIGINT | denorm; composite FK with school RESTRICT |
| template_version_id | BIGINT | composite FK with school RESTRICT |
| lifecycle_status | SMALLINT | NOT NULL, CHECK 1–5 |
| certificate_number | VARCHAR(50) | NOT NULL |
| verification_code | VARCHAR(128) | NOT NULL |
| supersedes_issuance_id | BIGINT | nullable; composite self-FK with school RESTRICT |
| issued_at / issued_by / revoked_* / correlation_id | | nullable as designed |
| created_at | TIMESTAMPTZ | NOT NULL |
| created_by | BIGINT | nullable |

**UNIQUE:** `(certificate_id, issuance_no)`, `(school_id, certificate_number)`, `(verification_code)` global, `(id, school_id)`

### `certificates.certificate_artifacts`

| Column | Type | Constraints |
|--------|------|-------------|
| id | BIGINT IDENTITY | PK |
| school_id | BIGINT | NOT NULL |
| issuance_id | BIGINT | composite FK with school RESTRICT |
| attempt_no | INTEGER | NOT NULL, CHECK ≥ 1 |
| storage_key | VARCHAR(500) | NOT NULL |
| content_type | VARCHAR(100) | NOT NULL |
| file_hash | VARCHAR(64) | NOT NULL |
| byte_size | BIGINT | NOT NULL, CHECK ≥ 0 |
| generated_at | TIMESTAMPTZ | NOT NULL |
| generator_version | VARCHAR(64) | NOT NULL |
| is_current | BOOLEAN | NOT NULL DEFAULT false |
| created_at | TIMESTAMPTZ | NOT NULL |

**UNIQUE:** `(issuance_id, attempt_no)`; partial UNIQUE `(issuance_id) WHERE is_current`

### `certificates.certificate_generation_jobs`

| Column | Type | Constraints |
|--------|------|-------------|
| id | BIGINT IDENTITY | PK |
| school_id | BIGINT | NOT NULL |
| issuance_id | BIGINT | UNIQUE; composite FK with school RESTRICT |
| job_status | SMALLINT | NOT NULL, CHECK 1–4 |
| attempt_count | INTEGER | NOT NULL DEFAULT 0, CHECK ≥ 0 |
| last_error_ref | VARCHAR(255) | nullable |
| correlation_id | VARCHAR(64) | nullable |
| queued_at / started_at / finished_at | TIMESTAMPTZ | nullable |
| created_at | TIMESTAMPTZ | NOT NULL |

**Reject-delete:** all six tables. **No** `(school_id, job_status)` operational index in Phase 4.1 (deferred).

---

## Schema: `documents` (1 table)

### `documents.files`

| Column | Type | Constraints |
|--------|------|-------------|
| id | BIGINT | PK |
| entity_type | VARCHAR(50) | NOT NULL |
| entity_id | BIGINT | NOT NULL |
| document_type | SMALLINT | NOT NULL |
| storage_key | VARCHAR(500) | NOT NULL |
| file_name | VARCHAR(255) | NOT NULL |
| mime_type | VARCHAR(100) | NOT NULL |
| file_size | BIGINT | NOT NULL |
| file_hash | VARCHAR(64) | NOT NULL |
| uploaded_by | BIGINT | FK → security.users |
| created_at | TIMESTAMPTZ | NOT NULL |

**Indexes:** `BTREE(entity_type, entity_id)`, `BTREE(file_hash)`

---

## Schema: `finance` (4 tables)

### `finance.fee_types`

| Column | Type | Constraints |
|--------|------|-------------|
| id | BIGINT | PK |
| school_id | BIGINT | FK → schools |
| code | VARCHAR(20) | NOT NULL |
| name | VARCHAR(255) | NOT NULL |
| amount | NUMERIC(12,2) | NOT NULL |
| is_recurring | BOOLEAN | NOT NULL DEFAULT false |
| status | SMALLINT | NOT NULL DEFAULT 1 |
| created_at | TIMESTAMPTZ | NOT NULL |

**Indexes:** `BTREE(school_id)`, `UNIQUE(school_id, code)`

### `finance.student_fees`

| Column | Type | Constraints |
|--------|------|-------------|
| id | BIGINT | PK |
| enrollment_id | BIGINT | FK → enrollment.enrollments |
| fee_type_id | BIGINT | FK → fee_types |
| academic_year_id | BIGINT | FK → academic_years |
| amount | NUMERIC(12,2) | NOT NULL |
| due_date | DATE | |
| status | SMALLINT | NOT NULL DEFAULT 1 |
| created_at | TIMESTAMPTZ | NOT NULL |

**Indexes:** `BTREE(enrollment_id)`, `BTREE(academic_year_id, status)`

### `finance.payments`

| Column | Type | Constraints |
|--------|------|-------------|
| id | BIGINT | PK |
| student_fee_id | BIGINT | FK → student_fees |
| amount | NUMERIC(12,2) | NOT NULL CHECK (amount > 0) |
| payment_method | SMALLINT | NOT NULL |
| payment_reference | VARCHAR(100) | |
| idempotency_key | VARCHAR(100) | UNIQUE |
| paid_at | TIMESTAMPTZ | NOT NULL |
| received_by | BIGINT | FK → security.users |
| created_at | TIMESTAMPTZ | NOT NULL |

**Indexes:** `BTREE(student_fee_id)`, `UNIQUE(idempotency_key)`

### `finance.transactions` ⚡ PARTITION CANDIDATE

| Column | Type | Constraints |
|--------|------|-------------|
| id | BIGINT | PK |
| student_id | BIGINT | FK → students.students |
| academic_year_id | BIGINT | FK → academic_years |
| transaction_type | SMALLINT | NOT NULL |
| amount | NUMERIC(12,2) | NOT NULL |
| balance_after | NUMERIC(12,2) | |
| reference_type | VARCHAR(50) | |
| reference_id | BIGINT | |
| notes | TEXT | |
| created_by | BIGINT | FK → security.users |
| created_at | TIMESTAMPTZ | NOT NULL |

**Partition key:** `academic_year_id` or `created_at`

**Indexes:** `BTREE(student_id, academic_year_id)`, `BTREE(created_at)`

---

## Schema: `communication` (3 tables)

### `communication.notification_templates`

| Column | Type | Constraints |
|--------|------|-------------|
| id | BIGINT | PK |
| code | VARCHAR(50) | UNIQUE NOT NULL |
| name | VARCHAR(255) | NOT NULL |
| channel | SMALLINT | NOT NULL |
| subject_template | TEXT | |
| body_template | TEXT | NOT NULL |
| is_active | BOOLEAN | NOT NULL DEFAULT true |
| created_at | TIMESTAMPTZ | NOT NULL |

**Indexes:** `UNIQUE(code)`

### `communication.messages` ⚡ PARTITION CANDIDATE

| Column | Type | Constraints |
|--------|------|-------------|
| id | BIGINT | PK |
| template_id | BIGINT | FK → notification_templates, nullable |
| recipient_type | VARCHAR(50) | NOT NULL |
| recipient_id | BIGINT | NOT NULL |
| channel | SMALLINT | NOT NULL |
| subject | TEXT | |
| body | TEXT | NOT NULL |
| status | SMALLINT | NOT NULL DEFAULT 1 |
| sent_at | TIMESTAMPTZ | |
| idempotency_key | VARCHAR(100) | UNIQUE |
| created_at | TIMESTAMPTZ | NOT NULL |

**Partition key:** `created_at` (range monthly/yearly)

**Indexes:** `BTREE(recipient_type, recipient_id)`, `BTREE(status)`, `BTREE(created_at)`

### `communication.notification_jobs`

| Column | Type | Constraints |
|--------|------|-------------|
| id | BIGINT | PK |
| job_id | UUID | UNIQUE NOT NULL |
| template_id | BIGINT | FK → notification_templates |
| target_filter | JSONB | NOT NULL |
| total_count | INTEGER | NOT NULL |
| sent_count | INTEGER | NOT NULL DEFAULT 0 |
| status | SMALLINT | NOT NULL DEFAULT 1 |
| idempotency_key | VARCHAR(100) | UNIQUE |
| created_by | BIGINT | FK → security.users |
| created_at | TIMESTAMPTZ | NOT NULL |
| completed_at | TIMESTAMPTZ | |

**Indexes:** `BTREE(status)`

---

## Schema: `workflow` (2 tables)

### `workflow.approval_flows`

| Column | Type | Constraints |
|--------|------|-------------|
| id | BIGINT | PK |
| entity_type | VARCHAR(50) | NOT NULL |
| name | VARCHAR(255) | NOT NULL |
| steps | JSONB | NOT NULL |
| is_active | BOOLEAN | NOT NULL DEFAULT true |
| created_at | TIMESTAMPTZ | NOT NULL |

**Indexes:** `BTREE(entity_type)`

### `workflow.approval_requests`

| Column | Type | Constraints |
|--------|------|-------------|
| id | BIGINT | PK |
| flow_id | BIGINT | FK → approval_flows |
| entity_type | VARCHAR(50) | NOT NULL |
| entity_id | BIGINT | NOT NULL |
| current_step | SMALLINT | NOT NULL DEFAULT 1 |
| status | SMALLINT | NOT NULL DEFAULT 1 |
| requested_by | BIGINT | FK → security.users |
| created_at | TIMESTAMPTZ | NOT NULL |
| completed_at | TIMESTAMPTZ | |

**Indexes:** `BTREE(entity_type, entity_id)`, `BTREE(status)`

---

## Schema: `security` (8 tables)

### `security.users`

| Column | Type | Constraints |
|--------|------|-------------|
| id | BIGINT | PK |
| public_id | UUID | UNIQUE DEFAULT gen_random_uuid() |
| email | VARCHAR(255) | UNIQUE NOT NULL |
| password | VARCHAR(255) | |
| first_name | VARCHAR(100) | NOT NULL |
| last_name | VARCHAR(100) | NOT NULL |
| status | SMALLINT | NOT NULL DEFAULT 1 |
| last_login_at | TIMESTAMPTZ | |
| created_at | TIMESTAMPTZ | NOT NULL |
| updated_at | TIMESTAMPTZ | NOT NULL |

**Indexes:** `UNIQUE(email)`, `PARTIAL(status) WHERE status = 1`

### `security.roles`

| Column | Type | Constraints |
|--------|------|-------------|
| id | SMALLINT | PK |
| code | VARCHAR(50) | UNIQUE NOT NULL |
| name | VARCHAR(100) | NOT NULL |
| description | TEXT | |
| is_system | BOOLEAN | NOT NULL DEFAULT false |
| created_at | TIMESTAMPTZ | NOT NULL |

**Indexes:** `UNIQUE(code)`

### `security.permissions`

| Column | Type | Constraints |
|--------|------|-------------|
| id | SMALLINT | PK |
| code | VARCHAR(100) | UNIQUE NOT NULL |
| name | VARCHAR(255) | NOT NULL |
| module | VARCHAR(50) | NOT NULL |
| created_at | TIMESTAMPTZ | NOT NULL |

**Indexes:** `UNIQUE(code)`, `BTREE(module)`

### `security.role_permissions`

| Column | Type | Constraints |
|--------|------|-------------|
| role_id | SMALLINT | FK → roles |
| permission_id | SMALLINT | FK → permissions |

**PK:** `(role_id, permission_id)`

### `security.user_roles`

| Column | Type | Constraints |
|--------|------|-------------|
| id | BIGINT | PK |
| user_id | BIGINT | FK → users |
| role_id | SMALLINT | FK → roles |
| school_id | BIGINT | FK → schools, nullable |
| directorate_id | BIGINT | FK → directorates, nullable |
| effective_from | DATE | NOT NULL |
| effective_to | DATE | |
| created_at | TIMESTAMPTZ | NOT NULL |

**Indexes:** `BTREE(user_id)`, `BTREE(school_id)`, `BTREE(user_id, role_id)`

### `security.scopes`

| Column | Type | Constraints |
|--------|------|-------------|
| id | BIGINT | PK |
| user_id | BIGINT | FK → users |
| scope_type | VARCHAR(50) | NOT NULL |
| scope_id | BIGINT | NOT NULL |
| created_at | TIMESTAMPTZ | NOT NULL |

**Indexes:** `BTREE(user_id)`, `UNIQUE(user_id, scope_type, scope_id)`

### `security.sessions`

| Column | Type | Constraints |
|--------|------|-------------|
| id | BIGINT | PK |
| user_id | BIGINT | FK → users |
| token_hash | VARCHAR(64) | UNIQUE NOT NULL |
| ip_address | INET | |
| user_agent | TEXT | |
| expires_at | TIMESTAMPTZ | NOT NULL |
| created_at | TIMESTAMPTZ | NOT NULL |

**Indexes:** `BTREE(user_id)`, `UNIQUE(token_hash)`, `BTREE(expires_at)`

### `security.security_audit_logs`

| Column | Type | Constraints |
|--------|------|-------------|
| id | BIGINT | PK |
| event_id | VARCHAR(64) | NOT NULL |
| occurred_at | TIMESTAMPTZ | NOT NULL |
| actor_id | BIGINT | nullable |
| actor_type | VARCHAR(50) | NOT NULL DEFAULT 'user' |
| action | VARCHAR(100) | NOT NULL |
| target_type | VARCHAR(50) | nullable |
| target_id | VARCHAR(100) | nullable |
| school_id | BIGINT | nullable |
| result | VARCHAR(50) | NOT NULL |
| correlation_id | VARCHAR(100) | nullable |
| metadata | JSONB | nullable |
| created_at | TIMESTAMPTZ | NOT NULL |

**Indexes:** `BTREE(event_id)`, `BTREE(occurred_at)`, `BTREE(actor_id)`, `BTREE(school_id)`, `BTREE(correlation_id)`

---

## Schema: `audit` (2 tables)

### `audit.audit_logs` ⚡ PARTITION CANDIDATE

| Column | Type | Constraints |
|--------|------|-------------|
| id | BIGINT | PK |
| user_id | BIGINT | FK → security.users, nullable |
| action | VARCHAR(50) | NOT NULL |
| entity_type | VARCHAR(50) | NOT NULL |
| entity_id | BIGINT | |
| old_values | JSONB | |
| new_values | JSONB | |
| ip_address | INET | |
| user_agent | TEXT | |
| correlation_id | VARCHAR(100) | |
| created_at | TIMESTAMPTZ | NOT NULL |

**Partition key:** `created_at` (range monthly)

**Indexes:**
- `BTREE(entity_type, entity_id)`
- `BTREE(user_id, created_at)`
- `BTREE(correlation_id)`
- `BRIN(created_at)` — for time-range scans

### `audit.login_history`

| Column | Type | Constraints |
|--------|------|-------------|
| id | BIGINT | PK |
| user_id | BIGINT | FK → security.users |
| ip_address | INET | |
| user_agent | TEXT | |
| login_status | SMALLINT | NOT NULL |
| created_at | TIMESTAMPTZ | NOT NULL |

**Indexes:** `BTREE(user_id, created_at)`, `BRIN(created_at)`

---

## Schema: `reports` (8 materialized views)

These are **not** transactional OLTP tables. Populated by background jobs.

### `reports.mv_school_student_statistics`

| Column | Type |
|--------|------|
| school_id | BIGINT |
| academic_year_id | BIGINT |
| total_students | INTEGER |
| active_students | INTEGER |
| male_count | INTEGER |
| female_count | INTEGER |
| graduated_count | INTEGER |
| refreshed_at | TIMESTAMPTZ |

**Refresh:** Nightly or on-demand via queue job.

### `reports.mv_daily_attendance`

| Column | Type |
|--------|------|
| school_id | BIGINT |
| section_id | BIGINT |
| academic_year_id | BIGINT |
| attendance_date | DATE |
| total_students | INTEGER |
| present_count | INTEGER |
| absent_count | INTEGER |
| attendance_percentage | NUMERIC(5,2) |
| refreshed_at | TIMESTAMPTZ |

### `reports.mv_subject_results`

| Column | Type |
|--------|------|
| school_id | BIGINT |
| subject_id | BIGINT |
| academic_year_id | BIGINT |
| exam_type_id | SMALLINT |
| total_students | INTEGER |
| pass_count | INTEGER |
| fail_count | INTEGER |
| average_grade | NUMERIC(5,2) |
| pass_rate | NUMERIC(5,2) |
| refreshed_at | TIMESTAMPTZ |

### `reports.mv_academic_performance`

| Column | Type |
|--------|------|
| enrollment_id | BIGINT |
| student_id | BIGINT |
| academic_year_id | BIGINT |
| gpa | NUMERIC(4,2) |
| attendance_percentage | NUMERIC(5,2) |
| rank_in_section | SMALLINT |
| final_status | SMALLINT |
| refreshed_at | TIMESTAMPTZ |

### `reports.mv_graduation_statistics`

| Column | Type |
|--------|------|
| school_id | BIGINT |
| academic_year_id | BIGINT |
| specialization_id | BIGINT |
| eligible_count | INTEGER |
| graduated_count | INTEGER |
| graduation_rate | NUMERIC(5,2) |
| refreshed_at | TIMESTAMPTZ |

### `reports.mv_directorate_school_comparison` ⚡ NEW (45K scenario)

| Column | Type |
|--------|------|
| directorate_id | BIGINT |
| school_id | BIGINT |
| academic_year_id | BIGINT |
| total_students | INTEGER |
| attendance_percentage | NUMERIC(5,2) |
| average_gpa | NUMERIC(4,2) |
| pass_rate | NUMERIC(5,2) |
| rank_in_directorate | SMALLINT |
| refreshed_at | TIMESTAMPTZ |

**Refresh:** Nightly. **Read by:** Directorate dashboard (20 schools).

### `reports.mv_section_attendance_weekly` ⚡ NEW (45K scenario)

| Column | Type |
|--------|------|
| section_id | BIGINT |
| school_id | BIGINT |
| academic_year_id | BIGINT |
| week_start_date | DATE |
| attendance_percentage | NUMERIC(5,2) |
| refreshed_at | TIMESTAMPTZ |

### `reports.mv_vocational_department_stats` ⚡ NEW (45K scenario)

| Column | Type |
|--------|------|
| school_id | BIGINT |
| specialization_id | BIGINT |
| academic_year_id | BIGINT |
| total_students | INTEGER |
| pass_rate | NUMERIC(5,2) |
| average_gpa | NUMERIC(4,2) |
| refreshed_at | TIMESTAMPTZ |

---

## Relationship Summary

```
organization.schools
    ├── enrollment.classes → enrollment.sections → enrollment.enrollments
    ├── teachers.teacher_schools
    └── curriculum.curricula

students.students
    ├── guardians.student_guardians
    ├── enrollment.enrollments (1:N per year)
    ├── attendance.records
    ├── exams.student_grades
    └── graduation.records

enrollment.enrollments
    ├── enrollment.enrollment_subjects
    ├── attendance.records
    ├── exams.student_grades
    ├── results.term_results
    ├── results.annual_results
    └── finance.student_fees

curriculum.subjects
    ├── curriculum.curriculum_subjects
    ├── teachers.teacher_subjects
    ├── timetable.schedules
    ├── attendance.sessions
    └── exams.exam_sessions
```

## Table Count Summary

| Schema | Tables |
|--------|--------|
| organization | 6 |
| academic | 5 |
| vocational | 3 |
| students | 4 |
| guardians | 3 |
| admission | 3 |
| enrollment | 4 |
| teachers | 4 |
| curriculum | 4 |
| timetable | 3 |
| attendance | 3 |
| exams | 5 |
| results | 6 |
| promotion | 2 |
| transfers | 2 |
| graduation | 2 |
| certificates | 6 |
| documents | 1 |
| finance | 4 |
| communication | 3 |
| workflow | 2 |
| security | 8 |
| audit | 2 |
| reports | 8 |
| **Total** | **93** |

## Partition Strategy (⚡)

| Table | Partition Key | Strategy | Priority (45K) |
|-------|--------------|----------|----------------|
| attendance.records | academic_year_id | LIST | **P0 — Year 1** (45M rows/year) |
| exams.student_grades | academic_year_id | LIST | **P0 — Year 1** (2.7M rows/year) |
| audit.audit_logs | created_at | RANGE (monthly) | P1 |
| communication.messages | created_at | RANGE (monthly) | P1 |
| finance.transactions | academic_year_id | LIST | P1 |

**45K scenario:** attendance.records and student_grades **must** be partitioned from first migration — not deferred.

See: [capacity-planning.md](./capacity-planning.md) (authoritative), [capacity-planning-45k.md](./capacity-planning-45k.md) (baseline snapshot), [phases/PHASE-C-OPERATIONS.md](./phases/PHASE-C-OPERATIONS.md)

---

## Schema: `intelligence` (9 tables — Runtime Platform)

> **Not counted in the 87 blueprint objects.** Operational intelligence layer — see `DATABASE-INTELLIGENCE-LAYER.md`.

| Table | Purpose |
|-------|---------|
| `monitoring_snapshots` | Health metrics (size, connections, cache hit, replica lag) |
| `table_metrics` | Per-table size, growth, seq_scan ratio |
| `query_metrics` | P50/P95/P99, degradation vs baseline |
| `detections` | Threshold + expert detections |
| `recommendations` | Explainability package pending approval |
| `optimization_events` | Before/after logged optimizations (Tier 2+) |
| `baseline_snapshots` | Adaptive threshold baselines |
| `human_feedback_events` | Approved/rejected/modified learning signal |
| `self_healing_actions` | Tier 1 auto-healing audit trail |

**Migration:** `2026_09_06_100000_create_intelligence_tables.php`  
**Runtime:** `app/Intelligence/` + `config/intelligence.php` + `php artisan intelligence:guardian`
