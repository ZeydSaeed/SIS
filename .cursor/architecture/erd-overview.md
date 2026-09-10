# ERD Overview — Entity Relationship Diagram

> **Authoritative detail:** [database-blueprint.md](./database-blueprint.md) (87 blueprint objects)  
> **Dictionary:** [database-dictionary.md](./database-dictionary.md)

## Core Domain Flow

```mermaid
flowchart TB
    subgraph organization
        DIR[directorates]
        SCH[schools]
        BR[branches]
        DEP[departments]
        RM[rooms]
    end

    subgraph academic
        AY[academic_years]
        TR[terms]
        GL[grade_levels]
    end

    subgraph students
        ST[students]
        SG[student_guardians]
    end

    subgraph guardians
        GD[guardians]
    end

    subgraph enrollment
        CL[classes]
        SEC[sections]
        ENR[enrollments]
        ES[enrollment_subjects]
    end

    subgraph curriculum
        SUB[subjects]
        CUR[curricula]
        CS[curriculum_subjects]
    end

    subgraph vocational
        SP[specializations]
    end

    subgraph teachers
        TH[teachers]
        TS[teacher_subjects]
    end

    subgraph attendance
        AS[attendance.sessions]
        AR[attendance.records]
        DS[daily_section_summary]
    end

    subgraph exams
        EX[exams]
        ESS[exam_sessions]
        GR[student_grades]
    end

    DIR --> SCH
    SCH --> BR
    SCH --> DEP
    BR --> RM
    AY --> TR
    SCH --> CL
    AY --> CL
    GL --> CL
    CL --> SEC
    ST --> ENR
    SCH --> ENR
    AY --> ENR
    SEC --> ENR
    SP --> ENR
    ENR --> ES
    SUB --> ES
    SCH --> CUR
    CUR --> CS
    SUB --> CS
    SCH --> SP
    TH --> TS
    SUB --> TS
    SEC --> AS
    SUB --> AS
    AS --> AR
    ST --> AR
    ENR --> AR
    SEC --> DS
    AY --> EX
    EX --> ESS
    SUB --> ESS
    ESS --> GR
    ST --> GR
    ST --> SG
    GD --> SG
```

---

## Student Lifecycle ERD

```mermaid
stateDiagram-v2
    [*] --> Admission: application
    Admission --> Enrolled: accepted
    Enrolled --> Active: start year
    Active --> Active: attendance + exams
    Active --> Promoted: end year pass
    Active --> Repeated: fail
    Active --> Transferred: school change
    Active --> Withdrawn: leave
    Promoted --> Graduated: final year
    Graduated --> Certificate: issued
    Certificate --> [*]
```

---

## Key Relationships (Cardinality)

| From | To | Type | Notes |
|------|-----|------|-------|
| directorates | schools | 1:N | 20 schools per province |
| schools | sections | 1:N via classes | 45 sections/school |
| students | enrollments | 1:N | One per year |
| enrollments | enrollment_subjects | 1:N | ~15 subjects |
| sections | enrollments | 1:N | 50 students/section |
| students | attendance.records | 1:N | Millions over years |
| exam_sessions | student_grades | 1:N | One grade per student |
| students | guardians | N:M | student_guardians |

---

## Schema Dependency Order (Migration)

```
1. organization, academic
2. security
3. students, guardians, vocational, curriculum
4. teachers, enrollment
5. timetable, attendance
6. exams, results, promotion, transfers, graduation
7. finance, communication, workflow, documents, audit
8. reports (materialized views)
```

---

## Partition Boundaries

```mermaid
flowchart LR
    AR[attendance.records]
    AR --> P1[records_y2024]
    AR --> P2[records_y2025]
    AR --> P3[records_y2026]
    AR --> PN[records_y20NN]

    SG[student_grades]
    SG --> G1[grades_y2024]
    SG --> G2[grades_y2025]
    SG --> G3[grades_y2026]
```

---

## Read vs Write Paths (CQRS-Lite)

```mermaid
flowchart TB
    subgraph writes [Command Side]
        C1[RecordAttendance]
        C2[EnrollStudent]
        C3[EnterGrades]
    end

    subgraph oltp [PostgreSQL Primary]
        T1[(attendance.records)]
        T2[(enrollments)]
        T3[(student_grades)]
        T4[(daily_section_summary)]
    end

    subgraph reads [Query Side]
        Q1[SchoolDashboard]
        Q2[DirectorateReport]
        Q3[StudentProfile]
    end

    subgraph readmodels [Read Models]
        MV[(Materialized Views)]
        CACHE[(Redis)]
    end

    C1 --> T1
    C1 --> T4
    C2 --> T2
    C3 --> T3

    Q1 --> T4
    Q1 --> MV
    Q2 --> MV
    Q2 --> CACHE
    Q3 --> T2
    Q3 --> T1
```

---

## Related

- [database-blueprint.md](./database-blueprint.md)
- [database-dictionary.md](./database-dictionary.md)
- [normalization-and-cqrs.md](./normalization-and-cqrs.md)
- [student-lifecycle.md](../brain/student-lifecycle.md)
