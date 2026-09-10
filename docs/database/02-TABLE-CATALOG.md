# 02 — Table Catalog

**Status:** Phase 1 catalog  
**Blueprint SSOT:** **87** objects (see `database-blueprint.md`)  
**Legend:** `LIVE` = exists in PostgreSQL · `BP` = in blueprint only · `ERP` = master prompt expansion (design pending) · `FW` = Laravel framework

Column-level detail for blueprint tables remains in `.cursor/architecture/database-blueprint.md`. This catalog tracks **inventory and status**, not a second column SSOT.

---

## 1. LIVE domain tables

### organization

| Table | Status | Role |
|-------|--------|------|
| ministries | LIVE | Top education authority |
| directorates | LIVE | Regional grouping |
| schools | LIVE | Tenant root |
| branches | LIVE | Campus |
| departments | LIVE | School departments |
| rooms | LIVE | Rooms / labs / workshops locations |

### academic

| Table | Status | Role |
|-------|--------|------|
| academic_years | LIVE | Year scope |
| terms | LIVE | Terms within year |
| grade_levels | LIVE | Grade reference |
| holidays | LIVE | Calendar closures |
| system_settings | LIVE | School/global JSON settings |

### vocational

| Table | Status | Role |
|-------|--------|------|
| specializations | LIVE | Vocational specialization |
| tracks | LIVE | Tracks within specialization |
| specialization_subjects | LIVE | Subject mapping |

### students / guardians

| Table | Status | Role |
|-------|--------|------|
| students.students | LIVE | Student master (+ school_id) |
| students.student_contacts | LIVE | Phones/emails |
| students.student_addresses | LIVE | Addresses |
| students.student_documents | BP | Document links |
| guardians.guardians | LIVE | Guardian master |
| guardians.student_guardians | LIVE | Relationship + emergency flags |
| guardians.guardian_addresses | LIVE | Guardian addresses |

### admission (Phase 2 LIVE)

| Table | Status | Role |
|-------|--------|------|
| admission.application_periods | LIVE | School/year application windows |
| admission.applications | LIVE | Applicant lifecycle (not student master); optional `student_id` conversion link |
| admission.application_documents | LIVE | File metadata only |
| interviews / waitlists / status_history | DEFERRED | Not in blueprint 87 SSOT |

### enrollment / teachers / curriculum / timetable / attendance

| Table | Status | Role |
|-------|--------|------|
| enrollment.classes | LIVE | Class cohort |
| enrollment.sections | LIVE | Section |
| enrollment.enrollments | LIVE | Enrollment (RLS) |
| enrollment.enrollment_subjects | LIVE | Subject enrollment |
| teachers.teachers | LIVE | Teacher profile |
| teachers.teacher_schools | LIVE | School assignment |
| teachers.teacher_subjects | LIVE | Subject capability |
| teachers.teacher_qualifications | LIVE | Qualifications |
| curriculum.subjects | LIVE | Subject catalog |
| curriculum.curricula | LIVE | Curriculum header |
| curriculum.curriculum_subjects | LIVE | Curriculum lines |
| curriculum.prerequisites | BP | Prerequisites |
| timetable.periods | LIVE | Period definitions |
| timetable.schedules | BP | Timetable entries |
| timetable.schedule_exceptions | BP | Exceptions |
| attendance.sessions | LIVE | Attendance session |
| attendance.records | LIVE | Partitioned attendance facts (RLS) |
| attendance.daily_section_summary | LIVE | Dashboard aggregate |

### security / audit / reports / intelligence

| Table | Status | Role |
|-------|--------|------|
| security.roles | LIVE | Roles |
| security.permissions | LIVE | Permissions |
| security.role_permissions | LIVE | Role↔permission |
| security.user_roles | LIVE | User↔role (+ school scope) |
| security.scopes | LIVE | Scope definitions |
| security.security_audit_logs | LIVE | Security events |
| security.users | BP | Superseded by public.users |
| security.sessions | BP | Superseded by public.sessions |
| audit.outbox_messages | LIVE | Transactional outbox |
| audit.idempotency_keys | LIVE | Idempotency store |
| audit.audit_logs | BP | Domain audit (partition candidate) |
| audit.login_history | BP | Login trail |
| reports.mv_* (3) | LIVE | Partial MV set |
| reports.mv_* (5 more) | BP | Remaining MVs |
| intelligence.* (9) | LIVE | Platform |
| intelligence.optimization_validation_target | LIVE | Harness (non-prod purpose) |

### Framework (public)

| Table | Status |
|-------|--------|
| users, sessions, password_reset_tokens, passkeys, personal_access_tokens | FW LIVE |
| cache, cache_locks, jobs, job_batches, failed_jobs, migrations | FW LIVE |

---

## 2. BLUEPRINT not yet migrated (priority backlog)

| Schema | Tables |
|--------|--------|
| admission | application_periods, applications, application_documents — **Phase 2 LIVE** |
| exams | exam_types, exams, exam_sessions, exam_enrollments, student_grades |
| results | term_results, annual_results, transcripts |
| promotion | rules, records |
| transfers | transfer_requests, transfer_records |
| graduation | eligibility_rules, records |
| certificates | templates, issued_certificates, generation_jobs |
| documents | files |
| finance | fee_types, student_fees, payments, transactions |
| communication | notification_templates, messages, notification_jobs |
| workflow | approval_flows, approval_requests |

---

## 3. ERP expansion candidates (not in blueprint — design before create)

Grouped by proposed schema; **names are conceptual** until phase design freezes them.

### Workshops / scheduling enhancements

`course_components`, `section_batches`, `workshops`, `workshop_sessions`, `workshop_equipment`, `safety_rules`, `safety_incidents`, block fields on schedules (`start_period`, `end_period`)

### Identity / admissions enrichment

`persons` (optional), application status history, interviews, waitlists, admission decisions

### HR / payroll

`employees`, `contracts`, `positions`, `employee_assignments`, `leaves`, `leave_requests`, `shifts`, `substitutions`, `salary_components`, `payroll_runs`, `payroll_items`

### Behavior / activities / duties

`incident_types`, `incidents`, `actions_taken`, `merits`, `demerits`, `clubs`, `activities`, `events`, `school_assemblies`, `duty_types`, `duty_rosters`, `duty_assignments`, `volunteer_tasks`, `student_volunteer_logs`

### Medical / support

`student_health_records`, `allergies`, `medications`, `vaccinations`, `clinic_visits`, `iep_plans`, `iep_goals`, `accommodations`, `support_services`, `counseling_sessions`

### Internship / alumni / library / transport

`partners`, `opportunities`, `placements`, `logbooks`, `evaluations`, `alumni*`, `books`/`loans`, `buses`, `routes`, `stops`, `student_transport_subscriptions`, `bus_attendance`

### Inventory / facilities / full finance

`items`, `warehouses`, `stock_levels`, `stock_transactions`, `assets`, `maintenance_requests`, `chart_of_accounts`, `journal_entries`, `journal_entry_lines`, vendors/POs

### Student enrichment

`student_profiles`, `student_identifiers`, `student_status_history`, `student_notes`, `guardian_permissions`

---

## 4. Counts (approximate)

| Bucket | Approx. count |
|--------|---------------|
| LIVE domain OLTP | 41 |
| LIVE MVs | 3 |
| LIVE intelligence | 10 |
| LIVE framework | ~10 |
| BP pending | ~37+ (admission LIVE; exams/results/etc. remain) |
| ERP conceptual | candidates only — reduced by YAGNI per phase (D3) |

---

## 5. Governance note

Creating ERP tables without a phase gate, blueprint update, and relationship map entry is **forbidden**. Prefer extending existing LIVE tables when a column or child table suffices.
