# Work Plan — دليل التنفيذ (مرجع فقط)

> **السينario:** 45,000 طالب، 20 مدرسة، 10 سنوات دراسية  
> **الحالة:** ملفات دليل — **لا قاعدة بيانات، لا migrations منفّذة**  
> **ابدأ من:** [phases/PHASE-A-FOUNDATION.md](./phases/PHASE-A-FOUNDATION.md)

---

## Phase 0 — Reference Documentation ✅

| الملف | الحالة | الغرض |
|-------|--------|-------|
| `capacity-planning.md` | ✅ | نموذج السعة الديناميكي (مرجعي) |
| `capacity-planning-45k.md` | ✅ | لقطة baseline 45K |
| `DATABASE-INTELLIGENCE-LAYER.md` | ✅ | طبقة الذكاء — Expert + Learning |
| `DATABASE-KNOWLEDGE-BASE.md` | ✅ | قاعدة المعرفة |
| `SELF-HEALING-RUNBOOK.md` | ✅ | إصلاح ذاتي آمن |
| `PERFORMANCE-BUDGET.md` | ✅ | أهداف أداء قابلة للقياس |
| `batch-write-patterns.md` | ✅ | كتابة جماعية للحضور |
| `postgresql-tuning.md` | ✅ | إعدادات PostgreSQL |
| `peak-hour-strategy.md` | ✅ | ذروة 8:00–8:30 |
| `production-readiness.md` | ✅ | checklist الإطلاق |
| `improvement-matrix.md` | ✅ | أولويات 45K |
| `database-blueprint.md` | ✅ | **89 tables** (authoritative) |
| `WORK-PLAN.md` | ✅ | هذا الملف |
| `phases/PHASE-*.md` | ✅ | أدلة مراحل A–F |
| `rls-policies.md` | ✅ | سياسات RLS |
| `load-test-results.md` | ✅ | قالب نتائج الاختبار |

---

## Phase A — Foundation (دليل جاهز ⏳ تنفيذ لاحق)

| المهمة | الدليل | الجداول | Migration (عند التنفيذ) |
|--------|--------|---------|------------------------|
| PostgreSQL schemas (23) | PHASE-A | — | `V001__create_schemas` |
| organization | PHASE-A | 6 | `V002__organization` |
| academic | PHASE-A | 5 | `V003__academic` |
| security + RBAC | PHASE-A | 7 | `V004__security` |
| RLS policies | rls-policies.md | — | `V005__enable_rls` |

**معايير الإكمال:** راجع checklist في PHASE-A قبل الانتقال لـ Phase B.

---

## Phase B — Core Academic (دليل جاهز ⏳)

| المهمة | الدليل | الجداول |
|--------|--------|---------|
| students + guardians | PHASE-B | 7 |
| enrollment | PHASE-B | 4 |
| curriculum + vocational | PHASE-B | 7 |
| teachers | PHASE-B | 4 |

**معايير الإكمال:** 45,000 enrollment قابل للاستيعاب، FK كاملة، indexes حسب indexing-matrix.

---

## Phase C — Daily Operations (دليل جاهز ⏳)

| المهمة | الدليل | ملاحظة |
|--------|--------|--------|
| timetable.periods | PHASE-C | — |
| attendance.sessions | PHASE-C | — |
| attendance.records | PHASE-C | **Partitioned P0** |
| attendance.daily_section_summary | PHASE-C | **P0 — 45K** |
| Materialized views | PHASE-C | 5 + 3 directorate |
| AttendanceBatchService | batch-write-patterns.md | لا HTTP sync |
| Peak hour queue | peak-hour-strategy.md | 8 workers 8:00–8:30 |

---

## Phase D — Lifecycle (دليل جاهز ⏳)

| المهمة | الجداول |
|--------|---------|
| admission | 3 |
| exams + results | 8 |
| promotion + transfers | 4 |
| graduation + certificates | 5 |

---

## Phase E — Supporting (دليل جاهز ⏳)

| المهمة | الجداول |
|--------|---------|
| finance | 4 |
| communication + workflow | 5 |
| documents + audit | 3 |

---

## Phase F — Production (دليل جاهز ⏳)

| المهمة | المرجع |
|--------|--------|
| PgBouncer + Read Replica | production-readiness.md |
| PostgreSQL tuning | postgresql-tuning.md |
| Load testing | capacity-planning.md + load-test-results.md |
| Monitoring + PITR | production-readiness.md |

---

## ترتيب التنفيذ عند البدء الفعلي

```
Phase 0 ✅ (الدليل — مكتمل)
    ↓
Phase A → migrate → verify → update blueprint
    ↓
Phase B → migrate → verify → seed test data (20 schools)
    ↓
Phase C → migrate + partition → batch service → MV
    ↓
Load test (900 teachers attendance)
    ↓
Phase D → E → F
    ↓
production-readiness checklist → go-live
```

## قواعد إلزامية قبل أي migration

1. `.cursor/skills/database-change/SKILL.md`
2. `.cursor/architecture/DATABASE-CHANGE-CHECKLIST.md`
3. تحديث `database-blueprint.md` بعد كل تغيير

## روابط سريعة

| الموضوع | الملف |
|---------|-------|
| حجم / سعة | [capacity-planning.md](./capacity-planning.md) |
| baseline 45K | [capacity-planning-45k.md](./capacity-planning-45k.md) |
| حوكمة تكيفية | [DATABASE-ADAPTIVE-GOVERNANCE.md](./DATABASE-ADAPTIVE-GOVERNANCE.md) |
| طبقة الذكاء | [DATABASE-INTELLIGENCE-LAYER.md](./DATABASE-INTELLIGENCE-LAYER.md) |
| أولويات | [improvement-matrix.md](./improvement-matrix.md) |
| Phase A | [phases/PHASE-A-FOUNDATION.md](./phases/PHASE-A-FOUNDATION.md) |
| Phase B | [phases/PHASE-B-CORE.md](./phases/PHASE-B-CORE.md) |
| Phase C | [phases/PHASE-C-OPERATIONS.md](./phases/PHASE-C-OPERATIONS.md) |
| Phase D | [phases/PHASE-D-LIFECYCLE.md](./phases/PHASE-D-LIFECYCLE.md) |
| Phase E | [phases/PHASE-E-SUPPORTING.md](./phases/PHASE-E-SUPPORTING.md) |
| Phase F | [phases/PHASE-F-PRODUCTION.md](./phases/PHASE-F-PRODUCTION.md) |
| RLS | [rls-policies.md](./rls-policies.md) |
| الإطلاق | [production-readiness.md](./production-readiness.md) |
