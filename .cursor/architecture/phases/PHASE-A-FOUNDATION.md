# Phase A — Foundation

> **الحالة:** دليل مرجع — لا migration  
> **المدة المقدّرة:** 1–2 أسبوع  
> **التالي:** [PHASE-B-CORE.md](./PHASE-B-CORE.md)

## الهدف

إنشاء الطبقة التأسيسية: التنظيم، الأكademia، الأمان، وعزل بيانات 20 مدرسة.

## الجداول (18)

| Schema | الجداول | العدد |
|--------|---------|-------|
| organization | ministries, directorates, schools, branches, departments, rooms | 6 |
| academic | academic_years, terms, grade_levels, holidays, system_settings | 5 |
| security | users, roles, permissions, role_permissions, user_roles, scopes, sessions | 7 |

## خطوات التنفيذ (عند البدء)

### 1. إنشاء PostgreSQL Schemas

```sql
CREATE SCHEMA IF NOT EXISTS organization;
CREATE SCHEMA IF NOT EXISTS academic;
CREATE SCHEMA IF NOT EXISTS security;
-- ... باقي الـ 23 schema — راجع database-blueprint.md
```

### 2. organization — 20 مدرسة

```
directorate (محافظة واحدة)
  └── schools × 20
        └── branches (optional)
        └── departments × 5 (أقسام مهنية)
        └── rooms
```

**Seed data:** 20 مدرسة، 100 قسم مهني، rooms لكل مدرسة.

### 3. academic — السنة الدراسية

```
academic_years (is_current = true لواحدة)
terms (2 per year typical)
grade_levels (3 مراحل لكل قسم مهني)
```

### 4. security — RBAC

```
Roles: Administrator, Teacher, Principal, Registrar, Directorate, Ministry
Permissions: per module (students.read, attendance.write, ...)
user_roles: scoped by school_id for school staff
```

### 5. RLS — P0 لـ 20 مدرسة

راجع [rls-policies.md](../rls-policies.md) — تفعيل قبل أي بيانات طلاب.

## Indexes إلزامية (Phase A)

| الجدول | Index |
|--------|-------|
| schools | `BTREE(directorate_id)`, `UNIQUE(code)` |
| academic_years | `PARTIAL(is_current) WHERE is_current = true` |
| user_roles | `BTREE(user_id)`, `BTREE(school_id)` |

## Checklist قبل Phase B

- [ ] 20 school records seeded
- [ ] academic_year current defined
- [ ] 3 grade_levels per vocational track
- [ ] RBAC roles + permissions seeded
- [ ] RLS policies created (not yet on student tables)
- [ ] database-blueprint.md synced
- [ ] migrate up + rollback tested

## مراجع

- Blueprint: `database-blueprint.md` → organization, academic, security
- RLS: `rls-policies.md`
- Mandatory workflow: `.cursor/skills/database-change/SKILL.md`
