# EXAMIS — نموذج الأمان

**الحالة:** مسودة مبنية على الفحص الساكن 2026-10-24. العقد الملزم المفصل: `docs/sis/exams/SECURITY-CONTRACT.md` و`SECURITY-TEST-MATRIX.md`.

## 1. طبقات الحماية القائمة

| الطبقة | الآلية | الدليل |
|---|---|---|
| المصادقة | Laravel (جلسات + passkeys/2FA) | `components/manage-passkeys.tsx`، `two-factor-*` |
| سياق المدرسة | middleware `require.school.context` على routes الويب؛ `set_config('app.current_school_id', …)` في المستودعات | `routes/web.php`، `app/Infrastructure/**` |
| التفويض | ثوابت `App\Security\Authorization\Permission`: `exam.*` (view/create/update/cancel، session.*، enrollment.*)، `grades.*` (view/create/enter.any/correct/void/finalize/review.request/review.decide)، `results.*` (view/calculate/finalize/ranking.build/transcript.issue/rebuild/publish)، `portal.results.view` | `app/Security/Authorization/Permission.php` |
| الأدوار | `grades_teacher`، `grades_manager`، `grades_viewer`، `results_manager`، `results_publisher`، `results_viewer`، `school_manager`… | `database/seeders/SecurityPermissionSeeder.php` |
| قاعدة البيانات | RLS + FORCE RLS بـ `school_id`؛ triggers `ENABLE ALWAYS` (منع UPDATE للدرجة، منع حذف النشر والمراجعات) | migrations؛ `SIS-EXAM-FINAL-REPORT.md` §4 |
| بوابة الطالب | لا نتيجة رسمية دون نشر فعّال لنفس الإصدار | `PortalResultsController` |

## 2. حقيقة RLS يجب عدم تجاهلها

**R-02 (عالٍ، موثق سابقًا):** الاتصال المحلي للتطبيق والاختبارات يتم كـ `postgres` (superuser) — `phpunit.database-pgsql.xml` `DB_USERNAME=postgres`.
الـ superuser يتجاوز RLS حتى مع FORCE. لذلك **العزل الفعلي في التطبيق يعتمد على Laravel** (السياق + الصلاحيات + شروط الاستعلام)، وRLS دفاع إضافي
لا يعمل إلا بدور غير superuser. اختبارات RLS الحقيقية تستعمل `Tests\Support\Database\PostgreSqlRlsActor::become()` (`sis_rls_tester`).
دور الاتصال في الإنتاج: `NOT VERIFIED` (`.env` غير مقروء).

## 3. التهديدات الخاصة بـ EXAMIS

| ID | التهديد | الضبط المطلوب |
|---|---|---|
| TH-01 | قراءة/تعديل بيانات مدرسة أخرى بتغيير المعرّف | سياق المدرسة + `whereNumber` + فحص الملكية في handler + اختبار 403/404 لكل route |
| TH-02 | معلم يدخل درجة لمادة/صف غير مسند | `GradePolicy::enterSeat` (موجود)؛ اختبار لكل مدخل جديد |
| TH-03 | تعديل درجة رسمية في المكان | trigger (DEC-001) |
| TH-04 | نشر نتيجة غير معتمدة أو ناقصة | `PublishYearResultsHandler` يرفض؛ فهرس جزئي نشر واحد فعّال؛ تحويل unique violation إلى `already_published` |
| TH-05 | الطالب يرى نتيجة غير منشورة | بوابة `isPublished` (موجودة) |
| TH-06 | التلاعب بالوقت في الامتحان الإلكتروني | موعد نهائي يحدده الخادم مرة واحدة (ADR-025)؛ لا ثقة بأوقات العميل (درس SK-02) |
| TH-07 | تسليم مكرر/متزامن | idempotency key + فهرس جزئي «محاولة واحدة جارية لكل مقعد» |
| TH-08 | تسريب الإجابات الصحيحة قبل النهاية | لا تُرسل مفاتيح الإجابة للعميل أبدًا؛ التحليل بعد النشر فقط |
| TH-09 | XSS في نص السؤال/الإجابة (إن بُني بنك أسئلة) | تعقيم HTML في الخادم؛ لا `dangerouslySetInnerHTML` دون تعقيم |
| TH-10 | ملفات مرفوعة (استيراد Excel) | فحص النوع والحجم والمحتوى؛ تخزين خارج public |
| TH-11 | دور تقني يقرّر درجات أكاديمية | فصل الواجبات (`EXAMIS-TARGET-DESIGN.md` §1.9) |
| TH-12 | تسرب بيانات شخصية في السجلات/الأدلة | لا إجابات كاملة ولا بيانات طلاب في logs أو `sis-examis-evidence` |

## 4. الأسرار

لا قراءة لـ `.env` ولا طباعة لقيمها. لا مفاتيح بريد/WhatsApp في الكود أو الوثائق. الإشعارات خارج الإنتاج لمستلمي اختبار فقط.

## 5. سجل المخاطر الأمنية المفتوحة

| ID | الخطر | المستوى | الخطوة |
|---|---|---|---|
| ~~R-01~~ | النشر بلا مدخل، والبوابة تحجب كل جديد | **مُغلق** لكل الأنواع (annual/gpa: U-01؛ term/transcript: U-03؛ الطالب: U-02) — المتصفح لـ U-03 لم يُشغَّل (DEC-P10) | — |
| R-10 | القرص C: ممتلئ 100% | متوسط (بيئي؛ قواعد PostgreSQL على D: فلم تتأثر) | DEC-P11 — المالك |
| ~~R-07~~ | مفتاح سحب مُعاد مع نشر آخر | **مُغلق** (T-0041): `results.publish.idempotency_conflict` | — |
| ~~R-08~~ | لقطة النشر خارج المعاملة | **مُغلق** (T-0041، T-0048): بناء + قفل `FOR SHARE` + إدراج في معاملة واحدة | — |
| ~~R-09~~ | الشريط الجانبي ولوحة التحكم يعرضان لمستخدم البوابة وحدات الموظفين | **مُغلق** (T-0071، T-0075): `PortalPartyAccessService::isPortalOnly` → `portalNav.portalOnly`؛ الشريط واللوحة يعرضان «نتائجي» فقط؛ الموظفون دون تغيير | — |
| R-11 | الشريط الجانبي ثابت على اليمين في الواجهة الإنجليزية (`side="right"`) | منخفض (UX، سابق) | خارج EXAMIS — لم يُعالج |
| R-06 | 25 handler يتيم لا تختبره إلا اختبارات الويب الفاشلة → قد ينحرف دون كشف | متوسط | كل وحدة بناء تعيد تغطيته |
| R-02 | superuser يتجاوز RLS | عالٍ | توثيق دور الإنتاج؛ اختبارات `PostgreSqlRlsActor` لكل جدول جديد |
| R-03 | 49 ملفًا غير محفوظ | متوسط | DEC-P02 |
| R-05 | لا مراجعة أمنية خارجية لأي جزء | متوسط | قبل الإنتاج |
