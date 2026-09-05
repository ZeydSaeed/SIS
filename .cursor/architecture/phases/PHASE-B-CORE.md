# Phase B — Core Academic

> **الحالة:** دليل مرجع — لا migration  
> **المتطلب:** Phase A مكتمل  
> **التالي:** [PHASE-C-OPERATIONS.md](./PHASE-C-OPERATIONS.md)

## الهدف

طلاب، تسجيل، مناهج، معلمين — يدعم **45,000 طالب نشط** و **900 شعبة**.

## الهيكل المستهدف

```
20 schools × 5 departments × 3 stages × 3 sections × 50 students
= 45 sections/school = 900 sections total = 45,000 enrollments
```

## الجداول (22)

| Schema | الجداول | العدد |
|--------|---------|-------|
| students | students, student_contacts, student_addresses, student_documents | 4 |
| guardians | guardians, student_guardians, guardian_addresses | 3 |
| enrollment | classes, sections, enrollments, enrollment_subjects | 4 |
| curriculum | subjects, curricula, curriculum_subjects, prerequisites | 4 |
| vocational | specializations, tracks, specialization_subjects | 3 |
| teachers | teachers, teacher_schools, teacher_subjects, teacher_qualifications | 4 |

## خطوات التنفيذ

### 1. students

- `student_code` UNIQUE per province
- `national_id` UNIQUE (partial where not null)
- Partial index: `WHERE status = 1` (active)

### 2. enrollment — الأهم

```sql
-- قيد: تسجيل نشط واحد لكل طالب/سنة
UNIQUE (student_id, academic_year_id) WHERE status = 1
```

**Seed structure per school:**
```
class (grade_level + academic_year + school)
  └── section × 3
        └── enrollment × 50 students
```

### 3. vocational

```
specialization (5 per school)
  └── linked to curriculum + grade_levels
```

### 4. curriculum

```
curriculum (per school + year + grade + specialization)
  └── curriculum_subjects (15 subjects typical)
```

### 5. teachers

```
teacher_schools (teacher assigned to school for year)
teacher_subjects (teacher teaches subject X in school Y)
```

## Indexes إلزامية (Phase B)

| الجدول | Index | السبب |
|--------|-------|-------|
| enrollments | `(school_id, academic_year_id)` | تقارير المدرسة |
| enrollments | `(student_id, academic_year_id)` | سجل الطالب |
| sections | `BTREE(class_id)` | شعب الصف |
| students | `INCLUDE(student_code, full_name) ON status` | قوائم |

## RLS — تفعيل على enrollment

```sql
ALTER TABLE enrollment.enrollments ENABLE ROW LEVEL SECURITY;
-- policies: rls-policies.md
```

## Checklist قبل Phase C

- [ ] 45,000 enrollment records (seed or import test)
- [ ] 900 sections across 20 schools
- [ ] UNIQUE active enrollment per student/year verified
- [ ] RLS: school admin sees only own school
- [ ] Eager load tested: enrollment → student → section
- [ ] database-blueprint.md synced

## مراجع

- Blueprint: students, guardians, enrollment, curriculum, vocational, teachers
- Capacity: `capacity-planning.md` (45K = baseline)
- Indexing: `indexing-matrix.md`
