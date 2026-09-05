# Domain Glossary

## Organization

| Term | Definition |
|------|-----------|
| Ministry | Top-level education authority |
| Directorate | Regional education office under ministry |
| School | Individual institution |
| Branch | Physical campus or branch of a school |
| Department | Academic or administrative department |

## Academic Structure

| Term | Definition |
|------|-----------|
| Academic Year | Primary temporal scope (e.g. 2025–2026) |
| Term / Semester | Subdivision of academic year |
| Grade Level | Year of study (1st, 2nd, 3rd…) |
| Class | Group of students at same grade level |
| Section | Subdivision of a class (Section A, B…) |
| Specialization | Track or major (vocational/professional) |
| Curriculum | Set of subjects for a specialization/grade |
| Subject | Individual course or module |

## People

| Term | Definition |
|------|-----------|
| Student | Enrolled learner |
| Guardian | Parent or legal guardian |
| Teacher | Staff member assigned to teach subjects |
| Registrar | Staff handling enrollment/admission |
| Principal | School administrator |
| Supervisor | Academic supervisor / inspector |

## Operations

| Term | Definition |
|------|-----------|
| Enrollment | Student registration for an academic year |
| Attendance | Record of student presence/absence |
| Exam Session | Scheduled exam event |
| Grade | Numeric or letter result for a subject |
| Promotion | Advancement to next grade level |
| Transfer | Move between schools/sections |
| Graduation | Completion of program |
| Certificate | Official document of achievement |

## Technical Terms

| Term | PostgreSQL Equivalent |
|------|----------------------|
| Filtered Index | **Partial Index** (`WHERE condition`) |
| Covering Index | **B-Tree + INCLUDE** columns |
| TINYINT | **smallint** (PostgreSQL has no TINYINT) |
| Index Reorganize | **VACUUM / ANALYZE / REINDEX** |
| Snowflake ID | Use only if distributed; default = **BIGINT IDENTITY** |
| Soft Delete | Prefer **status + effective_to + archived_at** |
| Source of Truth | **PostgreSQL** (Redis is cache only) |

## RBAC Roles

```
Administrator
Teacher
Supervisor
Accountant
Registrar
Principal
Directorate
Ministry
```

Authorization happens in the application layer. PostgreSQL RLS is an additional layer where appropriate — not a replacement.

## Data Retention Tiers

| Tier | Age | Access |
|------|-----|--------|
| HOT | Current years (2025–2027) | Fast, primary DB |
| WARM | Recent past (2020–2024) | Primary DB, may partition |
| ARCHIVE | Older (2010–2019) | Archive storage |
| COLD | Long-term | Backup / object storage |

Official records are never deleted except per legal/administrative policy.
