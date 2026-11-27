# EXAMIS — تحليل SurveyKing (المرحلة 1، تحليل ساكن)

**تاريخ الفحص:** 2026-10-24 · **المنفّذ:** Claude Code · **الطريقة:** قراءة ملفات فقط. لم يُشغَّل SurveyKing ولم تُثبَّت تبعيات ولم يُعدَّل أي ملف فيه.
**الحالة:** `PASS WITH CONDITIONS` — الخادم (Java) فُحص من المصدر؛ الواجهة الأمامية **غير متاحة كمصدر** فلم تُحلَّل إلا من README والصور.

## 1. وصف النسخة

| البند | القيمة | الدليل |
|---|---|---|
| المسار | `D:\Projects\SurveyKing` | `ls D:\Projects` |
| النسخة | v1.13.0 (شارة README)؛ وحدة `server/exam/build.gradle` تحمل `v0.3.0-beta.17` (وحدة فارغة) | `README.md`، `server/exam/build.gradle` |
| Git | **ليس مستودع Git** — لا commit ولا تاريخ | `git status` → `not a git repository` |
| الترخيص | MIT (Copyright 2021-present SurveyKing) | `LICENSE` |
| التقنيات | Java / Spring Boot / Spring Security (JWT) / MyBatis-Plus، قاعدة H2 مضمّنة أو MySQL | `server/*/src`، `scripts/init-*.sql` |
| الوحدات | `api` (12 controller)، `shared` (DTO/خدمات/أدوات)، `rdbms` (نماذج + تنفيذ)، `flow` (سير عمل)، `ai`، `exam` (فارغة) | 387 ملف Java خارج `target/` |
| الواجهة الأمامية | `client/README.md` = "todo" فقط؛ حزمة مبنية (chunks) داخل `run/app/BOOT-INF/classes/static` | لا مصدر React/TS |
| حالة التشغيل | `run/` يحوي `survey.mv.db` و`sk.log` (آخر سطر 2026-10-24 16:56)، وعملية `java.exe` تعمل على الجهاز — **لم تُشغَّل من هذه الجلسة ولم تُلمس** | `ls -la run`، `tasklist` |

**الترخيص وإعادة التنفيذ:** MIT يسمح بالنسخ بشرط إبقاء إشعار الترخيص، لكن قرار المشروع (DEC-010) هو **إعادة تنفيذ السلوك فقط** داخل SIS، دون نسخ شفرة أو مخطط أو أصول أو بيانات.

## 2. نموذج البيانات (من `server/rdbms/src/main/resources/scripts/init-mysql.sql`)

| الجدول | الدور | ملاحظة مهمة لـ EXAMIS |
|---|---|---|
| `t_project` | الاستبيان/الامتحان؛ `mode` = survey/exam/folder؛ `survey` = **مخطط JSON كامل للأسئلة**؛ `setting` = JSON إعدادات | الامتحان والأسئلة والإجابات الصحيحة في عمود JSON واحد |
| `t_answer` | محاولة/إجابة واحدة؛ `answer` JSON، `temp_answer` (حفظ مؤقت)، `temp_save` (0 مؤقت/1 مكتمل)، `exam_score` **float**، `exam_info` (درجة كل سؤال)، `survey` (**لقطة من المخطط داخل المحاولة**) | لقطة المخطط = تثبيت نسخة الامتحان للمحاولة (مفيد) |
| `t_repo` | بنك أسئلة؛ `mode`، `shared`، `category`، `tag`، `is_practice` | — |
| `t_template` | سؤال في البنك؛ `question_type`، `template` JSON، `tag`، `category` | لا إصدارات للسؤال |
| `t_repo_template` | ربط سؤال ↔ بنك | — |
| `t_user_book` | دفتر الأخطاء/المفضلة لكل مستخدم (`wrong_times`، `correct_times`، `note`) | ميزة تدريب |
| `t_project_partner` / `t_repo_partner` | صلاحيات على مستوى المشروع/البنك (OWNER/COLLABORATOR…) | لا عزل مدارس (tenant) |
| `t_user`، `t_role`، `t_dept`، `t_position` | المستخدمون والأدوار والأقسام | — |

لا مفاتيح أجنبية في المخطط؛ المعرّفات `varchar(64)`؛ فهرس وحيد على `t_answer.project_id`.

## 3. الوظائف المؤكدة (مع درجة الثقة)

| # | الوظيفة | الدليل | الثقة |
|---|---|---|---|
| SK-01 | امتحان بنافذة زمنية (`startTime`/`endTime`) **مفروضة في الخادم** عند التحميل والإرسال | `SurveyServiceImpl::validateExamSetting` (≈ سطر 702) | مؤكدة |
| SK-02 | حدّ أدنى/أقصى لزمن التسليم، وضع «تجاوز المراحل» مع ثوانٍ لكل سؤال، حدّ تبديل الشاشة | حقول `ProjectSetting.ExamSetting`؛ **لم يُعثر على فرضها في الخادم** (grep على `getMaxSubmitMinutes`/`getMaxSwitchScreenTimes` = لا استعمال) | جزئية — غالبًا في المتصفح فقط |
| SK-03 | التصحيح الآلي في الخادم عند الحفظ، على لقطة المخطط المخزّنة في المحاولة | `AnswerServiceImpl::beforeSaveAnswer` → `computeExamScore` → `AnswerScoreEvaluator` | مؤكدة |
| SK-04 | أنماط التصحيح: `onlyOne`، `selectAll` (مطابقة تامة)، `selectCorrect` (جزئي)، `select` (حسب الخيار)، `manual`، `none`؛ قواعد مطابقة النص `completeSame`/`contain` | `SurveySchema.ExamScoreMode`، `ExamMatchRule` | مؤكدة |
| SK-05 | أنواع الأسئلة القابلة للتصحيح الآلي: FillBlank، Textarea، MultipleBlank، Radio، Checkbox، Select، HorzBlank (`Judge` نوع موجود لكنه خارج `examType()`) | `SurveySchema.QuestionType.examType()` | مؤكدة |
| SK-06 | سحب عشوائي من بنوك الأسئلة: عدد الأسئلة + وسوم + أنواع + درجة لكل سؤال؛ ترتيب عشوائي | `RandomSurveyCondition`، `RandomSurveyProcessor` | مؤكدة (منطق السحب لم يُقرأ سطرًا سطرًا) |
| SK-07 | حفظ مؤقت للإجابة (`tempSaveAnswer`) للمستخدم المسجَّل فقط، مربوط بكوكي المحاولة | `SurveyServiceImpl::tempSaveAnswer` | مؤكدة |
| SK-08 | إظهار التحليل/الإجابة الصحيحة بعد انتهاء الامتحان فقط، كشف درجات، ترتيب | `SubmittedSetting.answerAnalysis/transcriptVisible/rankVisible`، `examFinished()` | مؤكدة |
| SK-09 | الترتيب يُحسب لحظيًا بـ `indexOf` على قائمة الدرجات | `AnswerServiceImpl` ≈ سطر 186–191 | مؤكدة |
| SK-10 | بنك أسئلة: إنشاء/تعديل/حذف/استيراد Excel (مع `ExcelImportSecurity`)/تصدير/اختيار أسئلة إلى امتحان (`/repo/pick`) | `RepoApi` | مؤكدة على مستوى endpoints |
| SK-11 | تدريب: عشوائي/متسلسل/الأخطاء (`ExamExerciseTypeEnum` R/O/W)، دفتر أخطاء، ملاحظات، مفضلة | `ExerciseApi`، `t_user_book` | مؤكدة على مستوى endpoints |
| SK-12 | الاستعلام العام عن النتائج بكلمة مرور وصلاحيات حقول | `PublicQuery`، `/public/loadQuery` | مؤكدة |
| SK-13 | الصلاحيات: Spring `@PreAuthorize` بصلاحيات مثل `project:update` و`repo:update` + جداول partners | 108 تعليق `PreAuthorize` في `api/` | مؤكدة |
| SK-14 | تعديل الإجابة من الإدارة يعيد احتساب الدرجة ويكتب فوق الصف (`updateById`) | `updateAnswerWithPermission` | مؤكدة |

## 4. ما لا يناسب SIS (سبب عدم النسخ)

- الدرجة `float` → SIS يستعمل أرقامًا دقيقة وتقريب half-up بـ bcmath (`ResultRounding`).
- الكتابة فوق الإجابة والدرجة دون تاريخ → يخالف VOID + INSERT في `exams.student_grades`.
- لا مفهوم للمقعد/الأهلية/التسجيل الرسمي ولا للسنة الأكاديمية ولا عزل مدارس.
- المؤقتات وحدود تبديل الشاشة غير مفروضة في الخادم (SK-02) → في SIS يجب أن يكون الموعد النهائي في الخادم (ADR-025).
- أوقات بداية/نهاية المحاولة في `metaInfo` تأتي من العميل (لم يُعثر على تعيينها في الخادم) → غير موثوقة.
- الامتحان كله JSON واحد بلا إصدارات للسؤال → ADR-024 يقترح أسئلة بإصدارات ومراجعة.

## 5. أنماط تستحق إعادة التنفيذ (مقترحة، تحتاج قرار النطاق)

1. لقطة نسخة الامتحان داخل المحاولة (SK-03) ← تقابل `exam_form` المجمّد في ADR-025.
2. فصل الحفظ المؤقت عن التسليم النهائي (SK-07).
3. خريطة درجة لكل سؤال (`exam_info.questionScore`) ← `delivery.item_marks` في ADR-025.
4. أنماط التصحيح الأربعة + اليدوي (SK-04) كقواعد معلنة لكل سؤال.
5. قواعد السحب العشوائي بالعدد والوسوم والأنواع (SK-06).
6. إخفاء الإجابات الصحيحة حتى انتهاء الامتحان (SK-08) ← في SIS: حتى النشر.
7. استيراد Excel لبنك الأسئلة مع فحص أمان الملف (SK-10).

## 6. ما لم يُتحقق منه

- سلوك الواجهة (التنقل بين الأسئلة، عرض المؤقت، الحفظ التلقائي في المتصفح): لا مصدر؛ يحتاج تشغيلًا معزولًا — **NOT VERIFIED**.
- منطق `RandomSurveyProcessor` بالتفصيل، ووحدة `flow`، ووحدة `ai`.
- الاختبارات الآلية: لا يوجد أي مجلد `src/test` ولا أي ملف `*Test.java` في النسخة المحلية (find = 0) — لا يمكن الاعتماد على اختبارات المرجع كدليل سلوك.
- منع التسليم المكرر: لم يُعثر على قيد فريد أو idempotency؛ يوجد `UniqueLimitSetting` (حدود IP/cookie/login) — جزئي.
