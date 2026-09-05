# Database Dictionary — Key Tables

> **Full schema:** [database-blueprint.md](./database-blueprint.md) — **89 tables (authoritative count)**  
> **Format:** Purpose, columns, constraints, PII, retention, indexes

---

## students.students

| Attribute | Value |
|-----------|-------|
| **Purpose** | Master identity record for each learner — stable across years |
| **PK** | `id BIGINT IDENTITY` |
| **Retention** | Permanent (never hard-delete) |

| Column | Type | Null | Unique | PII | Meaning |
|--------|------|------|--------|-----|---------|
| id | BIGINT | NO | PK | | Internal ID |
| public_id | UUID | NO | YES | | External/public identifier |
| student_code | VARCHAR(50) | NO | YES | | Official province student number |
| national_id | VARCHAR(20) | YES | YES | **YES** | National ID card |
| first_name | VARCHAR(100) | NO | | **YES** | |
| last_name | VARCHAR(100) | NO | | **YES** | |
| full_name | VARCHAR(255) | NO | | **YES** | Display name |
| gender | SMALLINT | NO | | | 1=M, 2=F (app enum) |
| birth_date | DATE | NO | | **YES** | |
| status | SMALLINT | NO | | | 1=active, 2=inactive, etc. |
| created_at | TIMESTAMPTZ | NO | | | |
| updated_at | TIMESTAMPTZ | NO | | | |

**Indexes:** UNIQUE(student_code), partial UNIQUE(national_id), partial(status=1)  
**Used by:** enrollment, attendance, exams, graduation, certificates

---

## enrollment.enrollments

| Attribute | Value |
|-----------|-------|
| **Purpose** | Yearly student placement — temporal academic record |
| **PK** | `id BIGINT` |
| **Retention** | Permanent — official academic history |

| Column | Type | Null | FK | Meaning |
|--------|------|------|-----|---------|
| id | BIGINT | NO | PK | |
| student_id | BIGINT | NO | students | |
| academic_year_id | BIGINT | NO | academic_years | Scope key |
| school_id | BIGINT | NO | schools | Current school this year |
| class_id | BIGINT | NO | classes | |
| section_id | BIGINT | NO | sections | 50 students max typical |
| specialization_id | BIGINT | YES | specializations | Vocational track |
| enrollment_number | VARCHAR(50) | NO | UNIQUE | Official enrollment # |
| status | SMALLINT | NO | | 1=active, transferred, etc. |
| effective_from | DATE | NO | | Start date |
| effective_to | DATE | YES | | End on transfer/withdraw |

**Constraints:** Partial UNIQUE(student_id, academic_year_id) WHERE status=1  
**Indexes:** (school_id, academic_year_id), (student_id, academic_year_id)  
**RLS:** school_id scope — see rls-policies.md

---

## attendance.records ⚡ PARTITIONED

| Attribute | Value |
|-----------|-------|
| **Purpose** | Individual attendance event — highest volume table |
| **Partition key** | academic_year_id (LIST) |
| **Expected volume** | ~45M rows/year at 45K students |

| Column | Type | Null | FK | Meaning |
|--------|------|------|-----|---------|
| id | BIGINT | NO | PK | |
| session_id | BIGINT | NO | attendance.sessions | |
| student_id | BIGINT | NO | students | |
| enrollment_id | BIGINT | NO | enrollments | |
| academic_year_id | BIGINT | NO | academic_years | **Partition key** |
| school_id | BIGINT | NO | schools | Denormalized for RLS |
| attendance_date | DATE | NO | | |
| status | SMALLINT | NO | | 1=present, 2=absent, 3=late |
| recorded_by | BIGINT | NO | users | Teacher |

**Constraints:** UNIQUE(session_id, student_id)  
**Indexes:** (student_id, attendance_date), (school_id, attendance_date)  
**Never:** Dashboard aggregate queries on this table — use daily_section_summary

---

## attendance.daily_section_summary

| Attribute | Value |
|-----------|-------|
| **Purpose** | Pre-aggregated daily attendance per section — dashboard read model |
| **PK** | (section_id, attendance_date) |
| **Consistency** | Eventual (< 5s after batch write) |

| Column | Type | Meaning |
|--------|------|---------|
| section_id | BIGINT | FK sections |
| school_id | BIGINT | FK schools |
| academic_year_id | BIGINT | FK academic_years |
| attendance_date | DATE | |
| total_students | SMALLINT | Expected count |
| present_count | SMALLINT | |
| absent_count | SMALLINT | |
| late_count | SMALLINT | |
| updated_at | TIMESTAMPTZ | Show in UI |

---

## exams.student_grades ⚡ PARTITIONED

| Attribute | Value |
|-----------|-------|
| **Purpose** | Official grade per student per exam session |
| **Partition key** | academic_year_id |
| **Volume** | ~2.7M rows/year |

| Column | Type | Constraints |
|--------|------|-------------|
| grade | NUMERIC(5,2) | CHECK >= 0 |
| is_pass | BOOLEAN | Computed or stored |
| is_absent | BOOLEAN | DEFAULT false |

**Constraints:** UNIQUE(exam_session_id, student_id)

---

## organization.schools

| Column | Type | Meaning |
|--------|------|---------|
| id | BIGINT | PK |
| directorate_id | BIGINT | FK — province |
| code | VARCHAR(20) | UNIQUE school code |
| name | VARCHAR(255) | Arabic name |
| school_type | SMALLINT | Vocational, etc. |
| status | SMALLINT | 1=active |

**45K scenario:** Exactly 20 active schools in target seed.

---

## security.user_roles

| Column | Type | Meaning |
|--------|------|---------|
| user_id | BIGINT | FK users |
| role_id | SMALLINT | FK roles |
| school_id | BIGINT | NULL = ministry/directorate scope |
| directorate_id | BIGINT | NULL = broader scope |
| effective_from | DATE | Temporal role assignment |

**Used for:** Application auth + RLS session context.

---

## audit.audit_logs ⚡ PARTITIONED

| Column | Type | PII | Meaning |
|--------|------|-----|---------|
| action | VARCHAR(50) | | created/updated/deleted |
| entity_type | VARCHAR(50) | | Model/table name |
| entity_id | BIGINT | | |
| old_values | JSONB | Maybe | No passwords |
| new_values | JSONB | Maybe | |
| correlation_id | VARCHAR(100) | | Request trace |

**Partition:** created_at RANGE monthly  
**Retention:** Append-only, archive after 5+ years

---

## Dictionary Index — All 89 Tables

| Schema | Tables | Dictionary Status |
|--------|--------|-------------------|
| organization | 6 | Key tables above + see blueprint |
| academic | 5 | blueprint |
| vocational | 3 | blueprint |
| students | 4 | students documented above |
| guardians | 3 | blueprint |
| admission | 3 | blueprint |
| enrollment | 4 | enrollments documented above |
| teachers | 4 | blueprint |
| curriculum | 4 | blueprint |
| timetable | 3 | blueprint |
| attendance | 3 | records + summary documented |
| exams | 5 | student_grades documented |
| results | 3 | blueprint |
| promotion | 2 | blueprint |
| transfers | 2 | blueprint |
| graduation | 2 | blueprint |
| certificates | 3 | blueprint |
| documents | 1 | blueprint |
| finance | 4 | blueprint |
| communication | 3 | blueprint |
| workflow | 2 | blueprint |
| security | 7 | user_roles documented |
| audit | 2 | audit_logs documented |
| reports | 8 | MV — see blueprint |

> **Rule:** When adding a table, add its dictionary entry here AND blueprint.

---

## Related

- [database-blueprint.md](./database-blueprint.md) — AUTHORITATIVE
- [data-quality-rules.md](./data-quality-rules.md)
- [erd-overview.md](./erd-overview.md)
