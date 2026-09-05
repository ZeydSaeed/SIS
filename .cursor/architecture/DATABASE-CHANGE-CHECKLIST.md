# Database Change Checklist — إلزامي

> **كل** تعديل على قاعدة البيانات يجب أن يمر بهذه القائمة.  
> Skill: `.cursor/skills/database-change/SKILL.md`

---

## قبل كتابة أي كود

- [ ] قرأت `.cursor/architecture/database-blueprint.md` (جدول موجود أو أضفته أولاً)
- [ ] قرأت `.cursor/architecture/01-principles-and-layers.md`
- [ ] قرأت `.cursor/architecture/indexing-matrix.md` (إذا كان التغيير يتعلق بفهارس)
- [ ] حددت نوع التغيير: CREATE | ALTER | INDEX | FK | CONSTRAINT | PARTITION

---

## قواعد إلزامية (لا استثناء)

- [ ] Migration مُ versioning — لا SQL يدوي في Production
- [ ] PK = `BIGINT` عبر `$table->id()`
- [ ] الحالة = `smallInteger` — **ممنوع** TINYINT
- [ ] كل FK له `constrained()` + `restrictOnDelete()`
- [ ] كل عمود FK مستخدم في JOIN/WHERE له Index
- [ ] الجداول الأكاديمية تحتوي `academic_year_id` حيث ينطبق
- [ ] الجداول Transactional تحتوي `created_at` / `updated_at`
- [ ] **ممنوع** hard-delete للسجلات الأكademية — استخدم `status` + `effective_to`
- [ ] CHECK constraints للقيم المحدودة (مثلاً الدرجة 0–100)
- [ ] الملفات (PDF/صور) = metadata في DB + تخزين خارجي
- [ ] Schema PostgreSQL صحيح (`organization`, `academic`, `students`, …)

---

## بعد كتابة Migration

- [ ] `php artisan migrate` ينجح
- [ ] `php artisan migrate:rollback --step=1` ينجح
- [ ] Model محدّث ويتطابق مع Migration
- [ ] **`.cursor/architecture/database-blueprint.md` محدّث** ← إلزامي
- [ ] **`.cursor/architecture/indexing-matrix.md` محدّث** ← إذا تغيرت الفهارس
- [ ] PHPStan يمر على Models المتأثرة

---

## ممنوعات

| ❌ ممنوع | ✅ البديل |
|---------|----------|
| SQL يدوي في Production | Migration مُ versioning |
| `onDelete('cascade')` على بيانات أكاديمية | `restrictOnDelete()` |
| `tinyInteger` / TINYINT | `smallInteger` |
| حذف جداول/أعمدة أكادemic history | `status` + `effective_to` |
| Index بدون سبب query محدد | Index مبني على query pattern |
| تغيير schema بدون تحديث blueprint | تحديث blueprint دائماً |
| تخزين ملفات في DB | Object storage + metadata |

---

## ترتيب التنفيذ

```
1. Blueprint (database-blueprint.md)
2. Migration (database/migrations/)
3. Model (app/Models/{Domain}/)
4. تحديث Blueprint + Indexing Matrix
5. Verify (migrate up/down)
```

---

## مراجع سريعة

| الموضوع | الملف |
|---------|-------|
| جداول وأعمدة | `database-blueprint.md` |
| مبادئ وأنواع بيانات | `01-principles-and-layers.md` |
| فهارس | `indexing-matrix.md` |
| Partitioning / أداء | `improvement-matrix.md` |
| دورة حياة الطالب | `../brain/student-lifecycle.md` |
| Workflow كامل | `../skills/database-change/SKILL.md` |
