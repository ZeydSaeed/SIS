# EXAMIS — سجل التغييرات المؤكدة

التغييرات السابقة لهذا البروتوكول (U1–U15، A1–A3، B، C، إرجاع الصفحات) مسجلة في تاريخ Git (`bc4de38` … `4472454`) و`docs/sis/exams/SIS-EXAM-REQUIREMENTS-TRACEABILITY.md`.

## 2026-11-28 — «النتائج» (`/results`): لوحة بتصميم لوحة تحكم cyber_security — بطلب المالك (جلسة `5e7bb484`)

- **cyber_security: قراءة فقط.** قُرئت `overview.tsx` و`settings-tabs.tsx` و`settings-icons.tsx` و`status-tiles.tsx` وألوان `themes.css`/`palette.css` والنصوص العربية من `lang/ar.json`. لم يُعدَّل فيه شيء.
- **حُذف** محتوى `/results` السابق (قائمة نتائج الفصل بمعرّف التسجيل + فلتر السنة + روابط الصفحات الفرعية).
- **جديد:**
  - `components/results/results-board.tsx`: شريط «نسبة الإنجاز في كل بطاقة» مع مفتاح الألوان، ثم 9 بطاقات بنفس الترتيب والألوان. كل بطاقة فيها شارة دائرية بالرسم، والعنوان، وشريط تقدّم.
  - `components/results/results-board-icons.tsx`: الرسوم التسعة.
  - `app.css`: قسم «النتائج» board، فيه ألوان البطاقات التسعة بقيم oklch نفسها.
  - مفاتيح `results.board.*` في `ar.ts` و`en.ts`.
- **بلا شريط جانبي أو شريط أدوات من cyber_security، وبلا بيانات:** لا مدرسين ولا مواد ولا مناهج ولا درجات ولا طلاب. الأشرطة فارغة والنسب «—».
- **الخادم:** `ResultsPageController::index` يتحقق من `viewOfficial` وسياق المدرسة ثم يعرض `results/index` بلا خصائص. لا قراءة للنتائج، فلا يُسجَّل حدث وصول إلى البيانات.
- **باقية كما هي:** الصفحات `/results/show` و`/term` و`/transcript` و`/publications` و`/term-publications` ومساراتها. الوصول إليها الآن بعنوانها فقط، فرابطها كان في الصفحة المحذوفة.
- **الاختبارات:** حُدّث `PhaseUiResTermResultsPagePostgreSqlTest` (يتحقق أن `results` و`filters` غير موجودة) و`GradesResultsPickersWebPostgreSqlTest` (أُزيلت منه فحوص منتقي `/results`). النتائج في T-0134.

## 2026-10-27 مساءً (2) — إكمال واجهة بنك الأسئلة (L-F7، L-F8) — بموافقة المالك

**جديد:**
- `resources/js/components/bank/question-status.tsx`: شارة الحالة على `StatusBadge` + نغمات الجدول القائمة؛ `formatInstant` بمنطقة صريحة.

**معدّل:**
- `QuestionBankReadRepository`: الأوقات ISO 8601 UTC؛ `created_at` و`submitted_by_name` للنسخة.
- `QuestionBankController`: `timezone` من `attendance.default_timezone`، و`UTC` عند قيمة غير صالحة.
- `pages/bank/questions/index.tsx`: شارات الحالة، حالة التحميل، «لا نتائج» مقابل «لا أسئلة بعد»، «مسح التصفية»، عدد النتائج.
- `pages/bank/questions/show.tsx`: بطاقات `sis-ops-hub__gate`، زر رجوع، سجل الحالة بالأسماء والأوقات، سبب غياب الإجراءات.
- `ar.ts`/`en.ts`: 20 مفتاحًا في `bank`.
- الاختبارات: `QuestionBankWebPostgreSqlTest` (+ التوقيت) و`e2e/bank/questions.spec.ts` (+ التنقل والتوقيت بمتصفح UTC).

**لم يتغير:** لا migration، لا صلاحيات، لا handlers، لا `app.css`، لا `sis`.

**اكتُشف (عام في SIS):** L-F9، L-F10.

## 2026-10-27 مساءً — فحص إغلاق 7.3a + معاينة محلية

- لا تغيير في كود التطبيق.
- `e2e/bank/questions.spec.ts`: اختبار جديد (البحث، التصفية، الصفحة الفارغة، حفظ مرفوض، الإنجليزية).
- خطة L-F5 مكتوبة (PROPOSED، بلا migration).
- قاعدتان مؤقتتان (DEC-059) وخادم معاينة على قاعدة المعاينة فقط.
- اكتُشف L-F7 (أوقات UTC) وL-F8 (شكل البطاقة) — لم يُصلحا.

## 2026-10-27 — 7.3a: بنك الأسئلة (الأسئلة فقط) — PASS WITH CONDITIONS

بتفويض المالك (أجوبة DEC-047..054). قرارات التصميم DEC-055..058 **PROPOSED**. لا commit؛ `sis` لم تُرحَّل.

**جديد:**
- migration `2026_10_27_100000_create_bank_questions_and_versions.php` (مخطط `bank`، جدولان، triggers، RLS)
- `app/Domain/Bank/**` (الأنواع، الحالات، `QuestionContent`، `QuestionReviewRules`، `QuestionIdempotencyGuard`، `QuestionBankException`، واجهة المستودع)
- `app/Application/Bank/**` (6 أوامر + handlers + Results، استعلامان، منفذ نطاق التأليف، `QuestionAuthorAccess`)
- `app/Infrastructure/Bank/TeachingAssignmentQuestionAuthorScope.php`، `app/Infrastructure/Persistence/Bank/*` (كتابة + قراءة)، `Eloquent/BankQuestionRecord.php`
- `app/Security/Policies/QuestionBankPolicy.php`، `app/Http/Controllers/Bank/QuestionBankController.php`، `app/Http/Requests/Bank/*`
- `resources/js/pages/bank/questions/{index,show}.tsx`، `resources/js/components/bank/question-forms.tsx`
- الاختبارات: `tests/Unit/Domain/Bank/QuestionContentTest.php`، `QuestionBankSchemaPostgreSqlTest`، `QuestionBankWebPostgreSqlTest`، `e2e/bank/questions.spec.ts`

**معدّل:**
- `config/security.php` و`Permission.php` (3 صلاحيات؛ `grades_teacher` += view/author، `grades_manager` += الثلاث)
- `SecurityServiceProvider` (policy)، `ArchitectureServiceProvider` (3 bindings)، `SchemaHelper` (`bank`)
- `routes/web.php` (8 routes)، `ExamPageController` + `pages/exams/index.tsx` (رابط «بنك الأسئلة» في الشريط)
- `ar.ts`/`en.ts` (`bank`، `flash.bank`، `errors.codes['bank.*']`)
- `tests/Support/Database/PostgreSqlRlsActor.php` (`bank`)
- التوثيق: `database-blueprint.md` (bank + 3 جداول EXAMIS كانت ناقصة، المجموع 117)، `indexing-matrix.md`، `schema-change-impact.md`، ADR-024 (قسم «ما بُني في 7.3a»)
- المختبر: `e2e_u01_fixture.php` (إجراء `bank-approver`)

**اكتُشف:** L-F5 (CHECK يقبل NULL في جدولين سابقين)، L-F6 (tsc الكامل محجوب بالذاكرة).

## 2026-10-27 — الانحدار الكامل بعد 7.2 → 7.2 = PASS

- لا تغيير في كود التطبيق. T-0088..T-0092: فحص الجلستين (الأخرى نظّفت C: ولم تعدّل المستودع)، الانحدار الكامل 836 اختبارًا (0 فشل جديد، 14 أُصلح)، `tests/Feature/Exams` 136/136.
- المختبر: `scripts/compare_runs.py` (فرق الاختبارات الفاشلة بين تشغيلين).

## 2026-10-26/27 — 7.2: إدارة الامتحانات والجلسات من الويب — PASS WITH CONDITIONS

**جديد:**
- `app/Http/Controllers/Exams/ExamManagementController.php` (8 أفعال)
- `resources/js/components/exams/exam-forms.tsx` (نافذتا الامتحان والجلسة)
- `resources/js/components/exams/edit-ribbon.tsx`
- `tests/…/ExamManagementWebPostgreSqlTest.php` (6)
- `e2e/exams/management.spec.ts`

**معدّل:**
- `ExamPageController.php` (`names`، `admin`)
- `routes/web.php` (8 routes + `whereNumber` لـ L-F1)
- `pages/exams/index.tsx` (أسماء، شريط «امتحان جديد/عرض»، صف مختار)
- `pages/exams/show.tsx` (أعيدت كتابته: شريط بثلاث مجموعات، أسماء، `examSession` — L-F4)
- `ar.ts` (`exams.admin.run`)
- `en.ts` (`exams`، `flash.exams`)

**لم يتغير:** لا migration · لا handler · لا مجال · لا `sis`.

## 2026-10-26 — U-03 متصفح + R-09 — PASS

- U-03: Playwright بكل المشاريع 20/20؛ spec جديد `e2e/results/term-and-transcript.spec.ts`.
- **R-09:**
  - `PortalPartyAccessService::isPortalOnly()`.
  - `HandleInertiaRequests` (`portalNav.portalOnly`).
  - `app-sidebar.tsx` (تصفية الوحدات).
  - `pages/dashboard.tsx` (بلاطة «نتائجي» وحدها للحساب البوابي فقط).
  - `tests/…/PortalNavigationPostgreSqlTest.php` (3).
  - `e2e/portal/navigation.spec.ts`.
- `public/build` أُعيد بناؤه مرتين (DEC-040)؛ النسخة قبل U-03 في `sis-examis-lab/build-backup-2026-10-26-pre-u03`.
- المختبر: `e2e_u01_fixture.php` (`term`، `transcript`).

## 2026-10-25 — U-03: نشر نتائج الفصول وكشوف الدرجات — PASS WITH CONDITIONS

**جديد:**
- `app/Http/Controllers/Results/TermResultPublicationController.php`
- `resources/js/pages/results/term-publications.tsx`
- `resources/js/components/results/active-publications.tsx` (قائمة النشرات + السحب، مشتركة)
- `tests/Feature/Database/PostgreSql/TermAndTranscriptPublicationPostgreSqlTest.php` (12)

**معدّل:**
- `routes/web.php` (+2 routes، import)
- `ResultPublicationController.php` (خلية `transcript` في الصفوف)
- `pages/results/publications.tsx` (زر/عمود الكشوف، رابط صفحة الفصول، استعمال `ActivePublications`)
- `ar.ts` / `en.ts` (`termPublications`، نصوص الكشوف، ترجمات رموز `results.batch.*` ومطابقة الفصل، `flash.results.batch.publish`)

**لم يتغير:** لا migration · لا handler · لا `public/build` · لا اتصال بـ `sis` · «نتائجي» دون تعديل.

**المختبر:** `scripts/pg_space.php` (قراءة فقط)؛ `tmp/` (مجلد TEMP على D:).

## 2026-10-25 — R-07 + R-08 + build + U-02 «نتائجي»

**R-07/R-08 (كود، غير محفوظ في commit):**
- `ResultPublicationException`: إضافة `snapshotChanged` (`results.publish.changed`) و`idempotencyConflict` (`results.publish.idempotency_conflict`).
- `WithdrawResultPublicationHandler`: رفض المفتاح المُعاد لنشر آخر.
- `PublishYearResultsHandler`: بناء اللقطة وقفلها وإدراجها في معاملة واحدة.
- `ResultPublicationRepositoryInterface::lockSnapshot` + التنفيذ في `EloquentResultPublicationRepository` (`FOR SHARE OF r`، مع `officialColumns` مشتركة مع `drifted`).
- نصوص الرمزين الجديدين في `ar.ts` و`en.ts`.

**البناء:** `npm run build` — البناء الحالي جديد، والسابق في `sis-examis-lab/build-backup-2026-10-25`.

**U-02:**
- جديد: `app/Http/Controllers/Portal/PortalPublishedResultsPageController.php` · `resources/js/pages/portal/results.tsx` (مسار موجود في HEAD، والمحتوى جديد) ·
  `tests/Feature/Database/PostgreSql/PortalResultsPagePostgreSqlTest.php` (6) · `tests/Support/Results/SeedsOfficialYearScene.php` (مستخرج من اختبار U-01) · `e2e/portal/my-results.spec.ts`.
- معدّل: `routes/web.php` (route + import؛ Pint رتّب الـ imports) · `HandleInertiaRequests.php` (`portalNav`) · `app-sidebar.tsx` (كما في HEAD + سطر تعليق) ·
  `ar.ts` / `en.ts` (`portalResults`).
- لا migration، لا كتابة على `sis`.
- المختبر: `e2e_u01_fixture.php` (إضافة `portal-user` و`lock-test`).

## 2026-10-25 — U-01 (مدخل نشر/سحب نتائج السنة) — PASS WITH CONDITIONS

**كود (غير محفوظ في commit):**
- جديد: `app/Http/Controllers/Results/ResultPublicationController.php` · `resources/js/pages/results/publications.tsx` ·
  `tests/Feature/Database/PostgreSql/ResultPublicationPagePostgreSqlTest.php` (11) · `e2e/results/publications.spec.ts` (4 + setup).
- معدّل: `routes/web.php` (3 routes + import) · `app/Application/Results/Support/PublicationSnapshotBuilder.php` (سطر واحد، أنواع) ·
  `resources/js/i18n/ar.ts` (كتلة `results.publications`) · `resources/js/i18n/en.ts` (+74 سطرًا: الصفحة، رسائل flash، رموز أخطاء النشر، المطابقة) ·
  `resources/js/pages/results/index.tsx` (رابط واحد).
- لا migration، لا تغيير في handlers أو المصدر الرسمي، لا كتابة على `sis`.
- المختبر: `e2e_u01_fixture.php`؛ probe يقبل `sis_e2e_examis_*` (DEC-030)؛ نسخة احتياطية `build-backup-2026-10-25`؛ مخرجات `runs/2026-10-25-u01/`.

## 2026-10-25 (الجلسة 3، استئناف) — U-00 مكتمل

- لا تغيير في كود التطبيق ولا `sis`. قاعدتا `sis_test_examis_20261025_pg`/`_ex` أُنشئتا وفُحصتا وحُذفتا.
- T-0021..T-0025؛ تصنيف كامل للفشلات (TEST-REGISTER §1d)؛ L-F2، L-F3؛ نتيجة بوابة U-00 في ACCEPTANCE.
- أدوات جديدة في `sis-examis-lab/scripts/`: `pint_overlap.py`، `errors_by_message.py`.

## 2026-10-24 (الجلسة 3) — U-00 جزئي

- لا تغيير في كود التطبيق ولا `sis`. قاعدتان مؤقتتان أُنشئتا وحُذفتا.
- تقوية `examis_db_probe.php` (خروج 5 عند أي عدم تطابق)؛ `phpstan_sum.py` جديد في `sis-examis-lab/scripts/`؛ مخرجات في `sis-examis-lab/runs/2026-10-24-u00-partial/`.
- T-0006..T-0020 في سجل الاختبارات. R1 أُوقف بطلب المالك قبل انتهائه.

## 2026-10-24 (الجلسة 2) — تدقيق R-01 وإثبات عزل الاختبار

- **لا تغيير في كود التطبيق ولا قاعدة `sis`.** قاعدة مؤقتة `sis_test_examis_20261024_1` أُنشئت ورُحّلت بالاختبارات ثم حُذفت.
- سُجّل تفويض إعادة البناء كقرارات DEC-020..028؛ حُسمت DEC-P01/P02/P05؛ أُضيفت DEC-P06.
- جرد آلي: 25 handler يتيم من 73 (L-A8)؛ مسار R-01 (L-A9)؛ تصنيف 52 مدخلًا في Git (L-G)؛ L-F1.
- T-0003..T-0005 في سجل الاختبارات؛ خطة U-00 وU-01 في معايير القبول؛ العقد: لغتان وتجاوب (DEC-026).
- المختبر: `D:\Projects\sis-examis-lab\README.md` و`scripts/` (خارج المستودع).

## 2026-10-24 — تأسيس البروتوكول (المراحل 0–2، بداية 3 و5)

- **وثائق فقط. لا تغيير في كود التطبيق ولا قاعدة البيانات ولا SurveyKing.**
- أُنشئ `docs/examis/` (14 ملفًا): MASTER-PLAN، PROJECT-STATE، DECISIONS، PARITY-MATRIX، SURVEYKING-ANALYSIS، LEGACY-REGISTER، TEST-REGISTER،
  CHANGELOG، NEXT-SESSION، UI-CONTRACT، SECURITY-MODEL، DATA-MODEL، ACCEPTANCE-CRITERIA، OPERATIONS.
- أُضيف قسم `EXAMIS PROJECT PROTOCOL` إلى `CLAUDE.md` (إضافة فقط، لم يُحذف شيء).
- أُضيف الإجراء `.claude/skills/examis-project-protocol/SKILL.md`.
- لم يُعمل commit (بانتظار طلب المالك).
