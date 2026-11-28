# EXAMIS — سجل الاختبارات

قاعدة: لا يُكتب `PASS` إلا لأمر شُغّل فعلًا وسُجّلت نتيجته. النتائج المنقولة من جلسات سابقة تُعلَّم «سابق — غير مُعاد التحقق».

## 1. ما شُغّل في جلسة 2026-10-24 (تأسيس البروتوكول)

| # | الأمر | النطاق | النتيجة | التصنيف |
|---|---|---|---|---|
| T-0001 | `npm run types:check` | المستودع كله (شجرة العمل) | خروج 2؛ **20 خطأ TS**؛ 9 منها في `pages/grades/*` و`pages/results/*` (TS17001 خاصية `dir` مكررة) | الأخطاء التسعة موجودة في `1738c8c` (كود أصلي)، ليست انحدارًا؛ الباقي في attendance/enrollments/reports خارج EXAMIS |
| T-0002 | `git show 1738c8c:resources/js/pages/grades/actions.tsx` | تحقق من أصل الخطأ | السطران `dir="rtl"` و`dir="ltr"` موجودان في الأصل | دليل لـ L-E2 |

لم تُشغَّل اختبارات PHP: لم يُنشأ ولم يُثبت عزل قاعدة اختبار في هذه الجلسة (المرحلة 6 لم تبدأ). الحالة: `NOT RUN`.

## 1b. جلسة 2026-10-24 (الثانية — بعد تفويض إعادة البناء)

| # | الأمر | النتيجة |
|---|---|---|
| T-0003 | `php examis_db_probe.php create sis_test_examis_20261024_1` ثم `probe` (سكربت: `D:\Projects\sis-examis-lab\scripts\examis_db_probe.php`) | **دليل العزل:** `configured_database` = `current_database` = `sis_test_examis_20261024_1`؛ المنفذ 5432؛ `current_user` = `postgres` (superuser = true)؛ 0 جداول قبل التشغيل؛ `guard_is_protected_sis` = true؛ `guard_allows_destructive_sis` = false؛ `guard_allows_destructive_here` = true. `probe sis` رُفض قبل أي اتصال (exit 2) |
| T-0004 | `DB_DATABASE=X SIS_PGSQL_TEST_DATABASE=X SIS_DESTRUCTIVE_ALLOWED_DATABASES=X php -d memory_limit=2G vendor/bin/phpunit -c phpunit.database-pgsql.xml --filter "DestructiveCommandsGuard\|ExamisNewTablesRls\|PublicationDriftReport\|Phase78Portal\|ResultPublicationWeb\|GradeReviewWeb\|ExamTypeWeightVersionWeb\|ExamTypesWeb\|ExamSeating\|TermResultSheetWeb\|YearResultSheetWeb\|ExamisDemoDataWeb"` (X = القاعدة أعلاه) | خروج 2 · **93 اختبار · 27 نجح · 59 فشل · 7 أخطاء** · 199 ثانية |
| T-0005 | `php examis_db_probe.php drop sis_test_examis_20261024_1` | `dropped` |

تفصيل T-0004 لكل صنف (عدد = `--list-tests`؛ الفشل من تقرير JSON):

| الصنف | ناجح / الكل | السبب الغالب للفشل | التصنيف |
|---|---|---|---|
| `DestructiveCommandsGuardPostgreSqlTest` | 3/3 | — | PASS |
| `ExamisNewTablesRlsPostgreSqlTest` | 3/3 | — | PASS (RLS بدور غير superuser للجداول الثلاثة الجديدة) |
| `PublicationDriftReportPostgreSqlTest` | 3/3 | — | PASS |
| `Phase78PortalDenyMatrixPostgreSqlTest` | 6/6 | — | PASS |
| `Phase78PortalResultsHttpApiPostgreSqlTest` | 7/9 | الاختباران `web_page_*` → 404 (صفحة `/portal/results` حُذفت) | API: PASS · الصفحة: متوقع بسبب G1 |
| `ResultPublicationWebPostgreSqlTest` | 3/11 | routes النشر غائبة (404 / flash فارغ) | متوقع بسبب G1 |
| `GradeReviewWebPostgreSqlTest` | 0/11 | routes المراجعة غائبة | متوقع بسبب G1 |
| `ExamTypesWebPostgreSqlTest` | 1/7 | routes الأنواع غائبة | متوقع بسبب G1 |
| `ExamTypeWeightVersionWebPostgreSqlTest` | 1/6 | routes الأوزان غائبة | متوقع بسبب G1 |
| `ExamSeatingPostgreSqlTest` | 0/8 | route التسكين غائب | متوقع بسبب G1 |
| `TermResultSheetWebPostgreSqlTest` | 0/7 | route الكشف غائب | متوقع بسبب G1 |
| `YearResultSheetWebPostgreSqlTest` | 0/9 | route الكشف غائب | متوقع بسبب G1 |
| `ExamisDemoDataWebPostgreSqlTest` | 0/10 | routes إنشاء الامتحان/الكشف غائبة | متوقع بسبب G1 |

**ما يثبته:** طبقة الأمان (الحارس، RLS، بوابة الـ portal في API) تعمل على شجرة العمل الحالية؛ كل فشل مقاس سببه غياب routes الويب.
**ما لا يثبته:** حالة بقية الحزمة (TR-01 لم يُشغَّل)؛ أن handlers اليتيمة ما زالت صحيحة منطقيًا (اختباراتها تمر عبر الويب فقط).

## 1c. جلسة 3 (2026-10-24 مساءً) — U-00 **جزئي، توقف بطلب المالك (إغلاق الجهاز)**

المخرجات المحفوظة: `D:\Projects\sis-examis-lab\runs\2026-10-24-u00-partial\`.

| # | الفحص | الأمر | النتيجة | التصنيف |
|---|---|---|---|---|
| T-0006 | تقوية الـ probe | `examis_db_probe.php`: يرفض قبل الإقلاع أي اسم خارج `^sis_test_examis_[a-z0-9_]+$`؛ `probe` يخرج 5 إذا اختلف `current_database` أو الاسم المهيأ، أو كان المضيف غير محلي، أو لم يعامل الحارس القاعدة كمؤقتة، أو لم يحمِ `sis` | 8 أسماء سيئة (`sis`، `sis_test`، `SIS`، فارغ اللاحقة، `;drop`، مسافة، بادئة `x`، `-`) → كلها exit 2 قبل أي اتصال | PASS |
| T-0007 | العزل | `create` + `probe` لـ `sis_test_examis_20261024_pg` و`_ex` | `problems: []`، 0 جداول، `db_url_set: false`، المضيف محلي | PASS |
| T-0008 | R1: حزمة PostgreSQL كاملة | `phpunit -c phpunit.database-pgsql.xml` على `_pg` | **أُوقف قبل الانتهاء** (طلب المالك)؛ لا تقرير | NOT VERIFIED — يُعاد |
| T-0009 | R2: `tests/Feature/Exams` | — | **لم يبدأ** | NOT STARTED |
| T-0010 | TypeScript | `npx tsc --noEmit` | خروج 2 · 20 خطأ: 9 في صفحات EXAMIS (TS17001، أصلية في `1738c8c`) + 11 في ملفات غير معدّلة في شجرة العمل (`student-profile-gaps.test.ts`، `curriculum-workspace.tsx`، `student-list.tsx`، `title-bar-home.tsx`×2، `attendance/mark.tsx`، `enrollments/create.tsx`، `reports/attendance-daily.tsx`×4) | قديم (موجود في HEAD) — ليس من الإرجاع |
| T-0011 | `vp check` (بلا `--fix`) | `npm run check` | خروج 1 · «Found formatting issues in 1190 files»؛ لا أخطاء lint غير التنسيق؛ `git status` لم يتغير (md5 مطابق) | قديم/بيئي (المستودع غير منسق بـ vp) |
| T-0012 | Pint | `vendor/bin/pint --test` | انتهت المهلة (exit 124 بعد 500 ث) بلا مخرجات، أثناء تشغيل R1 | BLOCKED (موارد) — يُعاد مع `--parallel` بلا تشغيل موازٍ |
| T-0013 | PHPStan كامل | `phpstan analyse --memory-limit=3G` | خروج 1 · **2264 خطأ** (التقرير مقطوع) | قديم على مستوى المستودع |
| T-0014 | PHPStan لمسارات EXAMIS | نفس الأمر على Application/Domain/Persistence/Http لـ Exams وResults | **177 خطأ**: property.notFound 65، missingType.generics 46، return.type 26، argument.type 22، always-true/false 12، match.unhandled 1، offsetAccess 3 | غالبًا أنواع؛ **غير محسوم للمراجعة:** `ProcessYearResultsBatchHandler:39,53` (match.unhandled + offset)، `PublicationSnapshotBuilder:72-73`، `ExamSessionCancelGuard:29` و`ExamSessionUpdateGuard:41` (شروط دائمًا false) |
| T-0015 | معمارية | `architecture:validate --fitness` (DB_DATABASE مؤقت) | خروج 1 · FAIL: static_analysis، controller_thinness، complexity_gate، security_architecture. **EXAMIS:** `DecideGradeReviewHandler` تعقيد 12، `ReopenExamEnrollmentHandler` 11 (الحد 10). الباقي Admission/Student/Teachers/Enrollment/Curriculum | فشل معماري (EXAMIS: 2 بنود) — يناقض ادعاء التقرير السابق أن الفشل كله قديم |
| T-0016 | أمن | `security:validate` | خروج 1 · فقط `SEC-DEP-001` | بيئي |
| T-0017 | `composer audit --no-dev` | — | `curl error 28` مهلة إلى packagist.org | BLOCKED (شبكة) |
| T-0018 | عقد الميزات | `architecture:feature-check Exams` / `Results` | كلاهما «Feature contract validation passed» | PASS |
| T-0019 | Playwright e2e | — | لم يُشغَّل: يتطلب قاعدة `sis_e2e*` و`sis:e2e-seed` (حارسه يرفض غير `sis_e2e`)، وهذا يخالف قاعدة «`sis_test_examis_*` فقط» | BLOCKED (يحتاج قرار) |
| T-0020 | تنظيف | `drop` للقاعدتين | `dropped` ×2 | PASS |

## 1d. جلسة 3 (استئناف 2026-10-25) — U-00 مكتمل

المخرجات: `D:\Projects\sis-examis-lab\runs\2026-10-25-u00\` (`r1_pg.txt`، `r2_exams.txt`، `r3_default.txt`، `pint.txt`). كل قاعدة أُنشئت وفُحصت بـ `probe` (`problems: []`، 0 جداول) ثم حُذفت.

| # | التشغيل | الأمر | النتيجة |
|---|---|---|---|
| T-0021 | R1 — حزمة PostgreSQL الكاملة | `DB_DATABASE=X SIS_PGSQL_TEST_DATABASE=X SIS_DESTRUCTIVE_ALLOWED_DATABASES=X php -d memory_limit=3G vendor/bin/phpunit -c phpunit.database-pgsql.xml` (X = `sis_test_examis_20261025_pg`) | PHPUnit exit 2 · **796 اختبار · 695 نجح · 86 فشل · 15 خطأ** · 1457 ث |
| T-0022 | R2 — `tests/Feature/Exams` | نفس التجاوزات، X = `sis_test_examis_20261025_ex`، المسار `tests/Feature/Exams` | exit 0 · **136/136 نجح** · 463 تأكيدًا |
| T-0023 | R3 — الحزمة الافتراضية | `DB_CONNECTION=sqlite DB_DATABASE=:memory: php -d memory_limit=3G vendor/bin/phpunit -c phpunit.xml` | exit 2 · 1737 اختبار · 514 نجح · 4 فشل · 1175 خطأ · 44 متخطى · 1 تحذير |
| T-0024 | Pint | `vendor/bin/pint --test --parallel` | exit 1 · 190 ملفًا بملاحظات تنسيق؛ تقاطع مع ملفات شجرة العمل: `app/Http/Middleware/HandleInertiaRequests.php` فقط؛ `git status` لم يتغير |
| T-0025 | تنظيف | `drop` لـ `_pg` و`_ex` | `dropped` ×2 |

### تصنيف كل فشل (الفئات السبع)

| الفئة | العدد | التفاصيل والدليل |
|---|---|---|
| **1. مسار ويب محذوف بسبب الإرجاع (G1)** | **97** (R1) | 15 صنفًا: `ExamAdministrationWeb` 5، `ExamPagesDisplayNames` 2، `ExamRosterWeb` 5، `ExamSeating` 8، `ExamTypeWeightVersionWeb` 5، `ExamTypesWeb` 6، `ExamisDemoDataWeb` 10، `GradeEntrySheet` 16، `GradeIdempotencyPayload` 1، `GradeReviewWeb` 11، `GradesResultsPickersWeb` 2، `Phase78PortalResultsHttpApi` 2 (الاختباران `web_page_*`)، `ResultPublicationWeb` 8، `TermResultSheetWeb` 7، `YearResultSheetWeb` 9. **الدليل:** الاختبارات تطلب routes محذوفة في `git diff routes/web.php` (`/grades/sheet`، `/exams/{e}/sessions/{s}/seats/...`، `/results/year-sheet`، `/portal/results`…) → 404 أو `getSession does not exist` (استجابة 404 عادية)؛ أو تطلب props أزالها الإرجاع: `names`/`admin.can_create` موجودة في `ExamPageController` في HEAD (5 مواضع) وغائبة الآن (0)، `picker` في `ResultsPageController` (5 في HEAD، 0 الآن) |
| 2. فشل في منطق التطبيق | **0** | لا فشل في اختبارات الأوامر/الـ API لـ Exams (R2 136/136) ولا Results؛ بوابة الـ portal API ناجحة |
| 3. مخطط أو بيانات اختبار غير مكتملة | 1 | `LeaveDemoSeederPostgreSqlTest::the_seeded_data_is_consistent…` («first school of the empty test database»: 53 مقابل 379) — الاختبار يفترض أن مدرسته أول مدرسة في قاعدة فارغة، والحزمة الكاملة أنشأت مدارس قبله (السبب مرجّح من نص الرسالة، **غير مثبت** بتشغيل منفرد هذه المرة)؛ خارج EXAMIS؛ مسجل سابقًا |
| 4. إعداد أو اعتماد بيئي | 1175 + 1 | R3: كل الـ 1175 خطأ = `SQLSTATE[HY000]: near "CONSTRAINT": syntax error` في 346 صنفًا (SQLite لا يشغّل migrations PostgreSQL)؛ `SecurityArchitectureValidatorTest` = `SEC-DEP-001` (composer audit: مهلة شبكة، T-0017) |
| 5. فشل أمني أو معماري | 3 (R3) + بنود ساكنة | `ArchitectureFitnessTest`، `ArchitectureHardeningTest`، `CleanArchitectureTest`: `EnrollmentPageController` (Eloquent مباشر)، `CurriculumPageController` (bypass) — خارج EXAMIS، قديمة. بند EXAMIS: تعقيد `DecideGradeReviewHandler` (12) و`ReopenExamEnrollmentHandler` (11) في T-0015 (لا اختبار يفشل بسببه) |
| 6. اختبار قديم أو غير صالح | 2 | `PhasePtPromotionSchemaPostgreSqlTest::transfers_tables_remain_absent` (يتوقع 0 جداول transfers، الموجود 2)؛ `GraduationWritePathPostgreSqlTest::multi_enrollment_outcomes_are_independent` (unique violation `enrollments_student_year_active_unique`) — كلاهما مسجل قديمًا (FINAL-REPORT §6)، خارج EXAMIS |
| 7. غير محسوم | 1 + بنود ساكنة | `LeaveModulePostgreSqlTest::the_pages_render_with_live_data` (`LeaveRuleViolation: duration_zero` لإجازة يوم `now()`): فشل يوم السبت 10-24 **ويوم الأحد 10-25 أيضًا** ← فرضية «عطلة نهاية الأسبوع» ضعفت؛ خارج EXAMIS. ساكن: PHPStan T-0014 (5 مواضع للمراجعة) |

المجموع R1: 97 + 1 + 2 + 1 = **101** = 86 فشل + 15 خطأ ✓. ملاحظة: `Phase3C12GraduationSchemaTest` لم يفشل هذه المرة رغم الاسم المعزول.

## 1e. U-01 (2026-10-25) — مدخل نشر/سحب نتائج السنة

المخرجات: `D:\Projects\sis-examis-lab\runs\2026-10-25-u01\`. كل قاعدة أُنشئت وفُحصت (`problems: []`، 0 جداول) ثم حُذفت.

| # | الفحص | الأمر | النتيجة |
|---|---|---|---|
| T-0026 | اختبار U-01 الجديد (التشغيل الأول) | phpunit `--filter ResultPublicationPagePostgreSqlTest` على `sis_test_examis_20261025_u01` | 10 اختبارات · 9 نجح · 1 فشل: `drifted` = 0 بدل 1 — **خطأ في الاختبار** (النسخة الرسمية عبر الـ API كانت 2 أصلًا، فتعيينها 2 لا يغيّر شيئًا)؛ صُحّح إلى `result_version + 10` |
| T-0027 | U-01 + فحوص الأمان المرتبطة | `--filter "ResultPublicationPagePostgreSqlTest\|Phase78Portal\|PublicationDriftReport\|ExamisNewTablesRls\|DestructiveCommandsGuard"` | 34 · 32 نجح · 2 فشل = `Phase78PortalResultsHttpApi::web_page_*` (صفحة `/portal/results` — نطاق U-02، G1 معروف). **U-01: 10/10** |
| T-0028 | PHPStan للملفين | `phpstan analyse ResultPublicationController.php PublicationSnapshotBuilder.php` | **0 أخطاء** (كان 2 في `PublicationSnapshotBuilder:72-73` — L-F3 مغلق) |
| T-0029 | Pint | `pint --test` للـ controller والـ builder والاختبار و`routes/web.php` | passed |
| T-0030 | معمارية | `architecture:validate --fitness` | نفس قائمة المخالفات حرفيًا كخط الأساس (`diff` فارغ) — لا مخالفة جديدة |
| T-0031 | عقد الميزات | `architecture:feature-check Results` | passed |
| T-0032 | Routes | `route:list --path=results/publications` | 3 routes: GET index، POST store، POST `{publication}/withdraw` (whereNumber) |
| T-0033 | TypeScript | `npx tsc --noEmit` | 20 خطأ = نفس خط الأساس؛ 0 في الملفات المعدلة |
| T-0034 | vp للملفين الجديدين | `vp check --fix` ثم `vp check` على `publications.tsx` و`publications.spec.ts` فقط | تنسيق فقط؛ ثم «no warnings or lint errors» |
| T-0035 | قاعدة e2e | probe: 6 أسماء سيئة مرفوضة (منها `sis_e2e` المشتركة)؛ `create`/`probe` `sis_e2e_examis_u01`؛ `migrate --force`؛ `sis:e2e-seed --no-backup` (كلمة مرور عشوائية في scratchpad فقط) | exit 0؛ fixture: 16 نتيجة سنوية رسمية اصطناعية للصف 1 / الشعبة A (`e2e_u01_fixture.php`) |
| T-0036 | Playwright (تشغيل 1) | `E2E_DB=sis_e2e_examis_u01 npx playwright test e2e/results/publications.spec.ts --project=setup --project=chromium` | **10/10** |
| T-0037 | فحص بصري | لقطتان (ar 390، en 1366) لبيانات اصطناعية | خلل واحد: تسمية فلتر السنة عربية في الإنجليزية → مُرّر `label={i18n.context.year}` |
| T-0038 | Playwright (تشغيل 2) | نفسه بعد الإصلاح | 9/10: فشل الاختبار الأخير لأن سبب السحب من التشغيل 1 موجود — **خطأ تصميم في الاختبار** (غير idempotent)؛ صُحّح بوسم `RUN` |
| T-0039 | Playwright (تشغيل 3) | نفسه | **10/10** |
| T-0040 | تنظيف | استعادة `public/build` من النسخة الاحتياطية (manifest 79380 بايت، بلا `publications`)؛ `drop` لـ `sis_e2e_examis_u01` و`sis_test_examis_20261025_u01` | تم |

**ما يغطيه `ResultPublicationPagePostgreSqlTest` (10):** الصفحة والجاهزية والصلاحية · نشر نطاق كامل = لقطة واحدة + تكرار مرفوض + سجلات النتائج لم تُمس + `published_by` · نطاق ناقص لا ينشر شيئًا · `results_manager`/`results_viewer` → 403 للنشر والسحب · مدرسة أخرى لا تنشر ولا تسحب (404) ولا ترى · التحقق من النوع (`term`، قيمة عشوائية، `enrollment_id` ممنوع) · **خاسر السباق** بعد تجاوز فحص التداخل → `already_published` وصف واحد · السحب: سبب مطلوب، إعادة نفس المفتاح = replay، مفتاح جديد = 404، `withdrawn_by/at/reason`، **البوابة تعيد 404 للطالب بعد السحب و200 قبله**، نشر جديد بعده · ثبات اللقطة + `drifted` + إخفاء النسخة الأحدث عن الطالب · تسجيل التدقيق الأمني للنشر والسحب.

## 1f. R-07 + R-08 + build + U-02 (2026-10-25)

المخرجات: `runs/2026-10-25-r07-r08/` و`runs/2026-10-25-u02/`. كل قاعدة: `create` ← `probe` (`problems: []`) ← … ← `drop`.

| # | الفحص | الأمر | النتيجة |
|---|---|---|---|
| T-0041 | R-07/R-08 + الصفحات المرتبطة | phpunit `--filter "ResultPublicationPage\|ResultPublicationWeb\|Phase78Portal\|PublicationDriftReport\|ExamisNewTablesRls\|TermResultSheetWeb\|YearResultSheetWeb"` على `sis_test_examis_20261025_r78` | 61 اختبارًا: نجح 35، وفشل 22، وأخطأ 4. الفشلات والأخطاء الـ26 كلها G1 معروفة (2+8+7+9). **`ResultPublicationPage` 13/13**، ومنها: `a_rebuild_between_building_the_snapshot_and_locking_it_refuses_the_stale_snapshot`، `a_withdraw_key_reused_for_another_publication_is_refused_not_replayed` |
| T-0042 | PHPStan للملفات الخمسة المعدّلة | — | 5 أخطاء = **نفس الخمسة في خط الأساس** (generics ×2، return.type ×3 لدوال قائمة)؛ 0 جديد |
| T-0043 | Pint | الملفات المعدّلة | passed |
| T-0044 | `npm run build` (بموافقة المالك) | — | exit 0؛ manifest يحوي `pages/results/publications.tsx`؛ لاحقًا أُعيد البناء لـ U-02 (يحوي `pages/portal/results.tsx`) — **البناء الحالي هو الجديد** (النسخة السابقة في `sis-examis-lab/build-backup-2026-10-25`) |
| T-0045 | U-02 + البوابة + U-01 | phpunit `--filter "PortalResultsPage\|Phase78Portal\|ResultPublicationPage\|PublicationDriftReport"` على `sis_test_examis_20261025_u02` | **37/37** — منها اختبارا `Phase78…::web_page_*` اللذان كانا يفشلان منذ الإرجاع (G1) |
| T-0046 | ثابت | PHPStan للـ controller الجديد و`HandleInertiaRequests` = 0؛ tsc = 20 (خط الأساس)؛ `architecture:validate` = نفس المخالفات؛ `feature-check Results` passed | — |
| T-0047 | حادثة تنسيق | `vp check --fix` على `app-sidebar.tsx` أعاد تنسيق أسطر لا تخصني | **صُحّح**: كُتب محتوى HEAD للملف (مطابق للنية + نفس التنسيق الأصلي) ثم أُضيف سطر تعليق واحد؛ `git diff HEAD` = سطر التعليق فقط |
| T-0048 | **دليل R-08 بجلستين حقيقيتين وبيانات ملتزمة** | `e2e_u01_fixture.php lock-test sis_e2e_examis_u02 1 7 1` | `{"a_locked":true,"b_update_while_locked":"blocked: SQLSTATE 55P03","b_update_after_release_rows":1}`: أثناء قفل A (`lockSnapshot`) انتظر `UPDATE` الاستبدال في B حتى انتهت المهلة (1.5 ث)، وبعد التحرير نجح. أُعيد على قاعدة نظيفة بالنتيجة نفسها |
| T-0049 | Playwright (تشغيل 1) | U-01 + U-02 | فشل setup: مستخدم البوابة الاصطناعي بريده `e2e-portal…` طابق نمط `e2e-%@sis.test` (يتوقع 5) — **خطأ في الـ fixture**؛ غُيّر إلى `examis-portal-u02@sis.test` وأُعيد بناء القاعدة |
| T-0050 | Playwright (تشغيل 2) | نفسه | 10/11: افترض الاختبار أن `admin` لا يرى «نتائجي»، لكن admin يملك كل الأدوار ومنها بوابة النتائج — **افتراض خاطئ في الاختبار**؛ الآن يُتحقق من الغياب عند persona `teacher` (بلا دور بوابة، تحقق بـ SQL) |
| T-0051 | Playwright (تشغيل 3) | `E2E_DB=sis_e2e_examis_u02 npx playwright test e2e/results/publications.spec.ts e2e/portal/my-results.spec.ts --project=setup --project=chromium` | **12/12** |
| T-0052 | فحص بصري U-02 | لقطتان ar 390 / en 1366 لبيانات اصطناعية | الصفحة سليمة. ملاحظتان **سابقتان** خارج النطاق: الشريط الجانبي يعرض كل وحدات الموظفين لمستخدم البوابة؛ والشريط على اليمين في LTR (`side="right"` ثابت) |
| T-0053 | تنظيف | `drop sis_e2e_examis_u02` | تم؛ لا عمليات PHP |

ملاحظة: مستخدم البوابة الأول (قبل إعادة البناء) أُنشئ خطأً بنطاق `scope_id = 0` (رقم تسجيل 0 بسبب أمر خاطئ مني) — في قاعدة مؤقتة حُذفت؛ لا أثر.

## 1g. U-03 — نشر نتائج الفصول وكشوف الدرجات (2026-10-25)

المخرجات: `runs/2026-10-25-u03/` (`git-before.txt`، `build-before.txt`، `baseline.txt`، `run1.txt`، `run2.txt`، `arch.txt`، `phpstan.json`).

| # | الفحص | الأمر | النتيجة |
|---|---|---|---|
| T-0054 | المرحلة 0: جلسات أخرى | `tasklist`؛ `find … -newermt "-90 minutes"` | 11 عملية `claude.exe` (شجرة هذه الجلسة على الأغلب)؛ **لا ملف معدّل في آخر 90 دقيقة إلا وثائق `docs/examis/` من هذه الجلسة**؛ `docs/project/` آخر تعديل 18:18 (جلسة سابقة) — لا تعارض |
| T-0055 | خط الأساس قبل U-03 | phpunit `--filter "ResultPublicationPage\|PortalResultsPage\|Phase78Portal\|ResultPublicationWeb\|PublicationDriftReport\|ExamisNewTablesRls"` على `sis_test_examis_20261025_u03a` | 51 اختبارًا: نجح 43، وفشل 8 = `ResultPublicationWeb` (G1). **R-07/R-08 مُعاد التحقق منهما**: اختباراهما ضمن `ResultPublicationPage` 13/13 |
| T-0056 | U-03 تشغيل 1 | `--filter TermAndTranscriptPublication` على `sis_test_examis_20261025_u03b` | 12 اختبارًا: نجح 11، وفشل 1. **السبب خطأ في الاختبار**: الـ decorator الثاني لفّ الأول لأن `app(Interface)` يعيد المُزيَّن. صُحّح بلف `EloquentResultPublicationRepository` مباشرة |
| T-0057 | U-03 + كل اختبارات النشر والبوابة | `--filter "TermAndTranscriptPublication\|ResultPublicationPage\|PortalResultsPage\|Phase78Portal\|ResultPublicationWeb\|PublicationDriftReport\|ExamisNewTablesRls"` | 63 اختبارًا: نجح 55، وفشل 8 = `ResultPublicationWeb` (G1). **`TermAndTranscriptPublication` 12/12** |
| T-0058 | PHPStan | `TermResultPublicationController`، `ResultPublicationController` | 0 |
| T-0059 | Pint | الملفان + الاختبار + `routes/web.php` | المحاولة الأولى: **`No space left on device`** (القرص C: ممتلئ 100%). بعد توجيه `TMP/TEMP` إلى `D:\Projects\sis-examis-lab\tmp`: passed |
| T-0060 | TypeScript | `npx tsc --noEmit` | 20 = خط الأساس؛ 0 في الملفات الجديدة |
| T-0061 | vp (ملفات U-03 فقط) | `vp check --fix` على `active-publications.tsx`، `term-publications.tsx`، `publications.tsx` (كلها ملفات EXAMIS الجديدة) | no warnings or lint errors |
| T-0062 | معمارية / عقد / routes | `architecture:validate --fitness`؛ `feature-check Results`؛ `route:list --path=results` | نفس مخالفات خط الأساس؛ passed؛ 5 routes للنشر |
| T-0063 | مساحة القرص | `df -h`؛ `pg_space.php` (قراءة فقط عبر قاعدة `postgres`) | **C: 0 بايت متاح**؛ D: 42G متاح؛ بيانات PostgreSQL على `D:/PostgreSQL/18/data` (القواعد لم تتأثر). قواعد ليست من هذه الجلسة: `sis_test_examis`، `sis_test_examis_f`، `examis_perf`… — لم تُلمس |
| T-0064 | Playwright | — | **BLOCKED**: يتطلب `npm run build` (يغيّر ما يخدمه الموقع المحلي المتصل بـ `sis`) — التكليف يشترط موافقة منفصلة. لم يُشغَّل، ولا مشروعا tablet/mobile |

**تغطية `TermAndTranscriptPublicationPostgreSqlTest` (12):**
- **الفصول:**
  - الصفحة ومحددات الاختيار والجاهزية.
  - نشر النطاق الكامل مرة واحدة مع لقطته، والتكرار → `already_published`، وسجلات `term_results` لم تُمس.
  - نطاق ناقص أو غير صالح → `not_ready` / `scope_invalid` / أخطاء تحقق، ولا صف.
  - `results_manager` و`results_viewer` → 403.
  - مدرسة أخرى: `results.batch.class_invalid`، والسحب 404، ولا صفوف ولا نشرات.
  - ثبات اللقطة، و`drifted` = 1، والنسخة الأحدث مخفية عن الطالب.
  - السحب بسبب، وreplay بالمفتاح، ثم الصفحة والـ API (`/api/v1/portal/results/term`) → لا شيء / 404.
  - إعادة بناء تسبق القفل → `changed`، وخاسر السباق → `already_published` وصف واحد.
  - البوابة تعرض فصل الطالب المسجّل فقط، والمعاملات لا تغيّر الهوية.
- **الكشوف:**
  - بلا كشف → `not_ready`.
  - كشف صادر محدَّث → نشر، وتكرار مرفوض، واللقطة = رقم الكشف.
  - البوابة (الصفحة + `/api/v1/portal/results/transcripts/issued`) تعرضه فقط أثناء النشر.
  - كشف قديم (اعتماد معدل بعده) → `not_ready`.
  - إعادة إصدار تسبق القفل → `changed`.
  - الصلاحية والعزل.

## 1h. U-03 — المتصفح (2026-10-26، بموافقة DEC-040)

المخرجات: `runs/2026-10-26-u03-e2e/`.

| # | الفحص | النتيجة |
|---|---|---|
| T-0065 | القرص C: | **2.3G متاح** (حرّره المالك). أبقيت `TMP/TEMP` على D: احتياطًا |
| T-0066 | `public/build` | نسخة احتياطية `sis-examis-lab/build-backup-2026-10-26-pre-u03` (md5 `0844998…`)، ثم `npm run build` exit 0؛ manifest يحوي `term-publications.tsx` و`active-publications` |
| T-0067 | قاعدة e2e | `sis_e2e_examis_u03`: `probe` (`problems: []`، 0 جداول)، `migrate --force`، `sis:e2e-seed --no-backup`، `sis:e2e-seed-exams` (الجلسات 1–4، tag TNJ59Q). fixtures: `term` (الفصل 1 / المادة 1 / الصف 1 / الشعبة A، 5 مُجلَسين)، `seed` (16 سنوية)، `transcript` (16 معدل + كشف)، `portal-user` (التسجيل 102) |
| T-0068 | **Playwright كل المشاريع** | `E2E_DB=sis_e2e_examis_u03 npx playwright test e2e/results/term-and-transcript.spec.ts e2e/results/publications.spec.ts e2e/portal/my-results.spec.ts --project=setup --project=chromium --project=tablet --project=mobile` | **20/20**: setup 6؛ chromium 10 (U-02 ×2، U-01 ×4، U-03 ×4)؛ tablet 2 + mobile 2 (`@responsive` لـ U-03) |
| T-0069 | فحص بصري | لقطتان (ar 390، en 1366) لصفحة الفصول | سليمة |
| T-0070 | تنظيف | `drop sis_e2e_examis_u03`؛ لا عمليات PHP | تم |

## 1i. R-09 — تنقّل الحساب البوابي فقط (2026-10-26)

المخرجات: `runs/2026-10-26-r09/`.

| # | الفحص | النتيجة |
|---|---|---|
| T-0071 | PHPUnit `--filter "PortalNavigation\|PortalResultsPage\|Phase78Portal"` على `sis_test_examis_20261026_r09` | **24/24**؛ منها `PortalNavigationPostgreSqlTest` (3): الحساب البوابي فقط مُعلَّم، وصفحات الموظفين ما زالت 403؛ البوابي الذي يملك دورًا وظيفيًا يحتفظ بالوحدات؛ الموظف ليس بوابيًا |
| T-0072 | PHPStan `PortalPartyAccessService` / Pint | 0 / passed |
| T-0073 | vp (بلا `--fix`) | `dashboard.tsx` و`app-sidebar.tsx` فيهما ملاحظات تنسيق **موجودة أصلًا في HEAD** (تحقق على `git show HEAD:…dashboard.tsx`) — لم يُعَد تنسيقهما |
| T-0074 | `npm run build` (ضمن موافقة DEC-040) | exit 0 |
| T-0075 | Playwright كل المشاريع على `sis_e2e_examis_r09` (4 specs: navigation، my-results، publications، term-and-transcript) | **23/23**: R-09 على chromium وtablet وmobile (السياق اليدوي يرث جهاز المشروع)، وكل U-01/U-02/U-03 بلا انحدار |
| T-0076 | تنظيف | `drop`؛ لا عمليات PHP |

## 1j. 7.2 — إدارة الامتحانات والجلسات (2026-10-26/27)

المخرجات: `runs/2026-10-26-u72/`.

| # | الفحص | النتيجة |
|---|---|---|
| T-0077 | الاختباران القديمان `ExamAdministrationWeb` و`ExamPagesDisplayNames` (عقد الـ routes والـ props، دون تعديلهما) | **8/8** |
| T-0078 | `ExamManagementWebPostgreSqlTest` (جديد، 6) — تشغيل 1 | Fatal: اسم `session()` يتعارض مع `TestCase::session()`؛ أُعيدت تسميته `sessionRow()` |
| T-0079 | نفسه — تشغيل 2 | 5/6: تعديل امتحان مدرسة أخرى أعاد 302 لا 404. **السبب مُثبت:** `ExamNotFoundException` يرث `DomainException`. أُضيف فحص الملكية في الـ controller (DEC-045) |
| T-0080 | `--filter "ExamManagementWeb\|ExamAdministrationWeb\|ExamPagesDisplayNames\|ExamisDemoDataWeb"` | 24 اختبارًا: نجح 18، وفشل 6. **7.2: 14/14**. `ExamisDemoDataWeb`: 4 من 10 عادت ناجحة (إنشاء الامتحانات)، والـ 6 الباقية تحتاج `/grades/sheet` (خارج 7.2 — G1) |
| T-0081 | ثابت | PHPStan 0 للـ controllerين؛ Pint passed (أُصلح `ordered_imports` يدويًا في `ExamPageController` ولم يُعَد تنسيق غيره)؛ tsc = 20؛ vp: ملفات 7.2 نظيفة؛ تنسيق `index.tsx` قديم (موجود في `1738c8c`) |
| T-0082 | Playwright تشغيل 1 (كل specs EXAMIS، 4 مشاريع، `sis_e2e_examis_u72`) | 23 نجح، 3 فشل كلها في `management.spec.ts`: (أ) «امتحان جديد» غير موجود؛ (ب، ج) على tablet/mobile `examId()` = NaN، لأن تلك المشاريع تشغّل `@responsive` فقط — **خطأ في الاختبار** صُحّح باستعمال امتحان الـ fixture |
| T-0083 | تشخيص (spec مؤقت، حُذف) | بعد نقر «تحرير» لا يحوي الشريط إلا «تثبيت» و«طي». **السبب مُثبت:** `useRegisterPageRibbon` استُدعي في الصفحة **قبل** `AppLayout`، والمزوّد داخل `AppLayout`. الإصلاح: المكوّن `EditRibbon` يُرسم داخل `AppLayout` (DEC-044) |
| T-0084 | Playwright `management.spec.ts` بكل المشاريع | **12/12** (chromium 4 + tablet 1 + mobile 1 + setup 6) |
| T-0085 | spec المسجَّل القديم `e2e/exams/administration.spec.ts` | 8/9: تدفقات الإنشاء والجلسات والتعديل والإلغاء **تنجح مع تنفيذ 7.2**؛ يفشل فقط `a viewer gets no administration controls` عند «كشف المرشحين» (7.5) |
| T-0086 | فحص بصري | لقطتان: ar 1366 (شريط بثلاث مجموعات، أسماء بدل معرّفات، حالات الجلسة صحيحة) و en 390 (LTR) — سليمتان |
| T-0087 | انحدار PostgreSQL كامل — المحاولة الأولى | أُوقفت قبل انتهائها (إعادة تشغيل الجهاز). المحاولة الثانية في T-0089 |

## 1k. الانحدار الكامل بعد 7.2 (2026-10-27، بعد إعادة التشغيل)

المخرجات: `runs/2026-10-27-regression/` (`full_pg.txt`، `exams.txt`).

| # | الفحص | النتيجة |
|---|---|---|
| T-0088 | فحص الجلسات (`session-resume-check.ps1` + سجل الجلسة الأخرى، قراءة فقط) | جلستا Claude: `0905a823` (هذه) و`c33141da`. الثانية نظّفت القرص C: (ذاكرة npm وComposer، وملفات Temp قديمة، وسكربت مرتفع الصلاحية في scratchpad خاص بها)؛ **لم تعدّل أي ملف في المستودع** (0 من 25 استدعاء). تغيّر `ar.ts` و`app.css` و`curriculum-workspace.tsx` عند 01:07 لا يعود لأي جلسة Claude — Cursor مفتوح (24 عملية)، وهو المصدر المرجّح. مفاتيح EXAMIS في `ar.ts` سليمة؛ الملفان الآخران بلا فرق عن HEAD. لم تُوقف الجلسة الأخرى ولم يُعدَّل أي ملف لها |
| T-0089 | **انحدار PostgreSQL الكامل** على `sis_test_examis_20261027_full` (`probe`: `problems: []`، 0 جداول) | PHPUnit exit 2 · **836 اختبارًا · نجح 749 · فشل 72 · أخطاء 15** (87) · 1072 ث. ملف `full_exit.txt` القديم (00:46) من المحاولة الموقوفة — تجاهلته |
| T-0090 | مقارنة مع خط أساس U-00 (`compare_runs.py`، 796 اختبارًا، 101 فاشل) | **فشلات جديدة: 0.** أُصلح 14: `ExamAdministrationWeb` ×5، `ExamPagesDisplayNames` ×2، `ExamisDemoDataWeb` ×4، `Phase78PortalResultsHttpApi::web_page_*` ×2، `LeaveModulePostgreSqlTest::the_pages_render_with_live_data` (خارج EXAMIS، نجح يوم الثلاثاء — ما زال **غير محسوم**: فشل يوم الأحد أيضًا). الـ 87 الباقية: G1 المعروفة (routes الإرجاع: `ExamRosterWeb`، `ExamSeating`، `ExamTypes*`، `GradeEntrySheet`، `GradeReviewWeb`، `GradesResultsPickers`، `ResultPublicationWeb`، `Term/YearResultSheetWeb`، 6 من `ExamisDemoDataWeb`، `GradeIdempotencyPayload`) + 3 قديمة (`PhasePtPromotionSchema`، `GraduationWritePath`، `LeaveDemoSeeder`) |
| T-0091 | `tests/Feature/Exams` على قاعدة جديدة `sis_test_examis_20261027_ex` | exit 0 · **136/136** |
| T-0092 | تنظيف | `drop` للقاعدتين؛ لا عمليات PHP |

## 1l. 7.3a — بنك الأسئلة (الأسئلة فقط) (2026-10-27)

| ID | النوع | الأمر / النطاق | النتيجة |
|---|---|---|---|
| T-0093 | قاعدة مؤقتة | `examis_db_probe.php create/probe sis_test_examis_20261027_bank` | `problems: []`، 0 جداول |
| T-0094 | migration | `migrate --force` ← `migrate:rollback --step=1` (المخطط `bank` اختفى) ← `migrate --force`؛ على القاعدة المؤقتة فقط | PASS؛ RLS + FORCE على الجدولين |
| T-0095 | Unit | `tests/Unit/Domain/Bank/QuestionContentTest.php` | **22/22** |
| T-0096 | PG Feature (المحاولة الأولى) | `QuestionBankSchemaPostgreSqlTest` + `QuestionBankWebPostgreSqlTest` | 10/17: **خلل حقيقي** — `CHECK length(btrim(NULL)) > 0` يقبل NULL (رفض بلا ملاحظة، إيقاف بلا سبب) → أُصلح بـ COALESCE في migration البنك (L-F5)؛ + افتراضان خاطئان في الاختبار (ترتيب مفاتيح jsonb، الفهرس الفريد يسبق FK)؛ + الصفحات لم تكن مكتوبة بعد |
| T-0097 | PG Feature (بعد الإصلاح والصفحات) | الملفان نفسهما | **17/17** (206 تأكيدًا) |
| T-0098 | ثابت | PHPStan لملفات البنك + `ExamPageController` | **0** |
| T-0099 | ثابت | Pint لملفات البنك الجديدة | passed |
| T-0100 | ثابت | `architecture:validate --fitness` | أظهر ARCH-202 ×6 (Result لكل أمر) → أُضيفت 6 فئات Result؛ بعدها **0 مخالفة في البنك**؛ الباقي قديم (L-F2، composer audit) |
| T-0101 | ثابت | `architecture:feature-check Bank` | passed |
| T-0102 | TypeScript | `npx tsc --noEmit` الكامل | **BLOCKED** — «out of memory: Zone» (الذاكرة الافتراضية الحرة ~1.4 GB؛ L-F6) |
| T-0103 | TypeScript | `tsc -p sis-examis-lab/tmp/tsconfig.bank.json` (صفحتا البنك، المكوّن، `exams/index.tsx`، وما تستورده بما فيه `ar.ts`/`en.ts`) | **0 أخطاء** |
| T-0104 | تنسيق | `vp check --fix` على ملفات البنك الثلاثة الجديدة فقط | passed |
| T-0105 | انحدار كامل (محاولة 1) | `phpunit -c phpunit.database-pgsql.xml` على `sis_test_examis_20261027_bank` | **توقف** 09:42 — «paging file is too small» داخل `Phase74FinalizeAnnualResult…` (بيئة، ليس فشل اختبار). المخرجات: `runs/2026-10-27-bank/full_pg_attempt1_oom.txt` |
| T-0106 | انحدار كامل (محاولة 2) | نفس الأمر، 14:30–14:47 | exit 2 · **853 اختبارًا · نجح 765 · فشل 73 · أخطاء 15** (88). `compare_runs.py` مع T-0089: **فشل جديد واحد** = `QuestionBankWebPostgreSqlTest::the_list_filters_and_searches_on_the_server` — خطأ في الاختبار (افترض مادتين فقط؛ المواد عامة بلا `school_id` وتتركها اختبارات أخرى: 14). الـ 87 الباقية هي نفسها المعروفة؛ 0 أُصلح |
| T-0106b | إصلاح الاختبار | التحقق من وجود المادتين بدل العدد؛ إعادة الملفين | **17/17**؛ Pint passed |
| T-0107a | بناء | `npx vite build` (نسخة احتياطية: `sis-examis-lab/build-backup-2026-10-27-pre-bank`) | exit 0؛ صفحتا البنك في `manifest.json` |
| T-0107b | قاعدة e2e | `sis_e2e_examis_bank`: `probe` (`problems: []`) → `migrate` → `sis:e2e-seed --no-backup` → `sis:e2e-seed-exams` (TNKEAP) → `bank-approver` (المدرسة 1، سؤال عيّنة 1). كلمة السر عشوائية ولم تُطبع | تم |
| T-0107 | **Playwright كل المشاريع** | `e2e/bank/questions.spec.ts` · setup/chromium/tablet/mobile | **12/12**: إنشاء + تعديل + إرسال، الكاتب لا يرى «اعتماد»، معتمد ثانٍ يعتمد (decided_by صحيح)، المستعرض 403، بلا تمرير أفقي ar/en على 1366/820/390 + لوحي + هاتف |
| T-0108 | Playwright (انحدار 7.2) | `e2e/exams/management.spec.ts` بكل المشاريع (أُضيف أمر في شريط الامتحانات) | **12/12** |
| T-0109 | تنظيف | `drop` لـ `sis_e2e_examis_bank` و`sis_test_examis_20261027_bank`؛ حُذف ملف كلمة السر؛ لا عمليات PHP | تم؛ Git 103 مدخلًا md5 `ef6944ab…0431` |

## 1m. 7.3a — فحص الإغلاق (2026-10-27 15:37–، بأمر «Controlled Closure»)

| ID | النوع | الأمر / النطاق | النتيجة |
|---|---|---|---|
| T-0110 | حالة | `git` + `session-resume-check.ps1` | `V2` · `4472454` · 103 مدخلًا md5 `ef6944ab…0431` (مطابق لـ 14:57)؛ جلسة Claude واحدة. **ChatGPT/`codex.exe` يعمل منذ 15:27**؛ `ar.ts` و`app.css` و`curriculum-workspace.tsx` لُمست 15:29 — محتوى `ar.ts` مطابق (163 سطرًا = ما قبل 7.3a + البنك)، والملفان الآخران مطابقان لـ HEAD. لم تُعدَّل الثلاثة |
| T-0111 | قواعد | قائمة `pg_database` عبر قاعدة `postgres` (لا اتصال بـ `sis`) | لا توجد قواعد 7.3a (حُذفت T-0109). `sis_test_examis_f` موجودة ومصدرها مجهول — لم تُستعمل. الحارس `ProtectedDatabaseGuard` يُستدعى في `TestCase` و`PostgreSqlIntegrationTestCase` |
| T-0112 | TypeScript الكامل | `node --max-old-space-size=1536 tsc --noEmit` | **اكتمل**: exit 2، **20 خطأ = خط الأساس T-0010 حرفيًا** (9 TS17001 في صفحات EXAMIS من `1738c8c` + 11 في ملفات غير معدّلة)؛ **0 في ملفات 7.3a و`exams/index.tsx`**. L-F6 مغلق |
| T-0113 | Unit | `tests/Unit/Domain/Bank` (SQLite ذاكرة) | 22/22 |
| T-0114 | ثابت | PHPStan · Pint · `feature-check Bank` | 0 · passed · passed |
| T-0115 | ثابت | `architecture:validate --fitness` | 0 في البنك؛ القائمة مطابقة لـ 02:35 (الفرق: composer audit exit 100 بدل 1 — لا شبكة) |
| T-0116 | بناء | مقارنة `public/build` (14:49) بالنسخة الاحتياطية | الاحتياطية سليمة (203 مدخلًا، 0 ملف مفقود)؛ الفرق صفحتا البنك + تجزئات. **لم تُستعد** |
| T-0117 | PG + Playwright + المعاينة | — | كان **BLOCKED** حتى موافقة المالك (DEC-059) |
| T-0118 | قواعد | `create/probe` `sis_test_examis_closure` و`sis_e2e_examis_preview` | `problems: []`، 0 جداول، الحارس يسمح بهما فقط |
| T-0119 | PG Feature | البنك (2 ملف) + `ExamManagementWeb` + `ExamisNewTablesRls` على `sis_test_examis_closure` | **26/26** (281 تأكيدًا) |
| T-0120 | **انحدار كامل** | `phpunit -c phpunit.database-pgsql.xml` على `sis_test_examis_closure` | exit 2 · 853 اختبارًا · نجح 766 · فشل 72 · أخطاء 15 (87). `compare_runs` مع T-0089: **0 جديد، 0 مُصلح** — الـ 87 هي المجموعة المعروفة نفسها |
| T-0121 | **Playwright كل المشاريع** | `e2e/bank/questions.spec.ts` (+ اختبار جديد: البحث، تصفية الحالة، الصفحة الفارغة، حفظ مرفوض برسالة، الإنجليزية) + `e2e/exams/management.spec.ts` على `sis_e2e_examis_preview` | **19/19** |
| T-0122 | **معاينة حية** | خادم `php -S 127.0.0.1:8124` بـ `DB_DATABASE=sis_e2e_examis_preview` (المتغيرات تغلب `.env`)؛ فحص headless + 9 لقطات في `runs/2026-10-27-closure/screens/` | `/up` 200؛ رابط «بنك الأسئلة» في شريط الامتحانات يفتح `/bank/questions`؛ القائمة تعرض سؤال العيّنة الموجود في قاعدة المعاينة فقط (إثبات أنها ليست `sis`)؛ الفتح المباشر لـ `/bank/questions/1` يعمل؛ المفتاح ظاهر للمدير؛ en = `ltr` و«Question bank»؛ هاتف/لوحي: تجاوز أفقي 0px؛ المستعرض 403 ولا يرى الرابط؛ 0 أخطاء صفحة أو 5xx |
| T-0123 | ملاحظة بصرية (اللقطات) | صفحة السؤال | (أ) **الأوقات تُعرض بـ UTC** (13:16 والساعة المحلية ~16:10)؛ (ب) بطاقة النسخة ورابط «العودة للقائمة» يستعملان `sis-ops-hub__link` فتظهر كتلة زرقاء رمادية ثقيلة. **لم يُصلحا** — بوابة التوقف |

## 1n. 7.3a — إكمال واجهة بنك الأسئلة (2026-10-27 16:34–17:05، بأمر «Question Bank UI Completion»)

| ID | النوع | الأمر / النطاق | النتيجة |
|---|---|---|---|
| T-0124 | حالة | git + الجلسات | 103 مدخلًا (md5 مطابق)؛ جلسة Claude ثانية `f79b0cab` = سؤال عن الحسابات التجريبية، قراءة فقط، 0 تعديلات؛ `codex.exe` يعود للعمل أحيانًا ولم يلمس الملفات منذ 15:29؛ خادم المعاينة 8124 والقاعدتان `problems: []` |
| T-0125 | PG Feature | البنك (2 ملف، + اختبار التوقيت الجديد) على `sis_test_examis_closure` | **18/18** (231 تأكيدًا): ISO 8601 UTC + `timezone = Asia/Baghdad`؛ 13:16:44Z = 16:16 بغداد؛ التخزين لم يتغير؛ منطقة غير صالحة → UTC |
| T-0126 | ثابت | PHPStan (البنك) · Pint · `vp check` (3 ملفات البنك) · tsc المضيّق | 0 · passed · passed · 0 |
| T-0127 | بناء | `vite build` ×2 (نسخة احتياطية جديدة `build-backup-2026-10-27-pre-ui`؛ القديمة لم تُلمس) | exit 0 |
| T-0128 | Playwright المحاولة 1 | البنك + 7.2، 4 مشاريع، `sis_e2e_examis_preview` | 14 نجح / 1 فشل: خطأ في الاختبار (بطاقة النسخة صارت `li` فطابق محدد `li` عنصرين) → حُصر في قائمة الخيارات |
| T-0129 | **Playwright** | نفس الأمر بعد الإصلاح، ثم بعد إصلاح عرض التاريخ العربي | **20/20** مرتين: إنشاء/تعديل/إرسال، الكاتب لا يُعرض عليه «اعتماد» ويرى سبب الأربع عيون، معتمد ثانٍ يعتمد، البحث والتصفية و«لا نتائج» و«مسح التصفية»، حفظ مرفوض برسالة، الإنجليزية، قائمة ← سؤال ← رجوع، **متصفح بتوقيت UTC يعرض وقت بغداد**، المستعرض 403، بلا تمرير أفقي ar/en على 1366/820/390 ولوحي وهاتف |
| T-0130 | لقطات قبل/بعد | `runs/2026-10-27-closure/screens/` (قبل) و`screens-after/` (بعد) | البطاقة والرابط صارا خفيفين؛ التاريخ العربي ظهر مقلوبًا داخل `dir="ltr"` → أُزيل الاتجاه المفروض، صار «16:16 ،2026/10/27» صحيحًا |
| T-0131 | ملاحظات خارج البنك | لقطات `/exams` و`/students` بالإنجليزية؛ صفحة 403 | (أ) في LTR يُرسم الشريط الجانبي يمينًا فوق المحتوى مع فراغ يسار — عام في SIS، موجود قبل التغيير (L-F9)؛ (ب) 403/404 على الويب = صفحة Laravel الافتراضية بالإنجليزية «This action is unauthorized» لكل الوحدات (L-F10) |
| T-0132 | TypeScript الكامل | `tsc --noEmit` (`--max-old-space-size=1536`) | 20 = خط الأساس (نفس الملفات)، 0 في البنك |
| T-0134 | لوحة «النتائج» (2026-11-28) | قاعدة مؤقتة `sis_test_res` (حُذفت بعد الفحص)، `--filter "PhaseUiResTermResultsPage\|GradesResultsPickersWeb\|PhaseOpsPageContentGuest\|TermAndTranscriptPublication\|PortalResultsPage\|ExamisDemoDataWeb"` | 32: 24 ناجحة و8 فاشلة. **الفاشلة الثمانية نفسها على HEAD قبل التغيير** (git stash): 6 في `ExamisDemoDataWeb` (رسائل flash لصفحة الدرجات)، و`the_grades_page_lists…` (`sessions`)، و`the_results_picker…` (`picker.*` غير موجودة؛ صارت تفشل عند `/results/show` بدل `/results`). لا فشل جديد |
| T-0135 | لوحة «النتائج» — فحوص أخرى | `tsc --noEmit` · `npm run build` · `architecture:validate --fitness` · `pint --test` · لقطة Chrome headless لنسخة ثابتة بالـ CSS المبني | 20 = خط الأساس (أخطاء `results/show,term,transcript` قديمة) · نجح · نفس المخالفات القديمة · passed · التصميم مطابق. الصفحة الحية: `NOT RUN` |
| T-0136 | «النتائج» بتنسيق «المناهج» (2026-11-28) | `tsc --noEmit` · `npm run build` · `--filter "PhaseUiResTermResultsPage\|PhaseOpsPageContentGuest"` (قاعدة `sis_test_res` مؤقتة، حُذفت) | 20 = خط الأساس · نجح · 2/2. المتصفح الحي `NOT RUN` |
| T-0137 | صفحة `/results/entry-window` (2026-11-28) | قاعدة `sis_test_res` مؤقتة (حُذفت) · `--filter "PhaseUiResTermResultsPage\|PhaseOpsPageContentGuest"` · `tsc` · `npm run build` · `pint --test` | 3/3 · 20 = خط الأساس · نجح · المتحكم والاختبار نظيفان (`routes/web.php` ordered_imports سابق). المتصفح `NOT RUN` |
| T-0138 | `/results/entry-window` مع بنية المدرسة وتبويب «تحرير» (2026-11-28) | قاعدة `sis_test_res` مؤقتة (حُذفت) · `--filter "PhaseUiResTermResultsPage\|PhaseOpsPageContentGuest"` · `tsc` · `npm run build` · `pint --test` · `architecture:validate --fitness` | 3/3 · 20 = خط الأساس · نجح · passed · لا مخالفة في ملفات النتائج. المتصفح `NOT RUN` |
| T-0133 | معمارية | `architecture:validate --fitness` · `feature-check Bank` | نفس المخالفات القديمة (الفرق: composer audit حسب الشبكة)، 0 في البنك · passed |

## 2. نتائج سابقة (من `docs/sis/exams/SIS-EXAM-FINAL-REPORT.md` — غير مُعاد التحقق، وقبل إرجاع الصفحات)

| المرجع | النتيجة المسجّلة |
|---|---|
| الحزمة الكاملة PostgreSQL على `sis_test_full` | 787 اختبار · 783 نجح · 3 فشل · 1 خطأ (كلها مصنفة خارج EXAMIS) |
| مرشّح `Grade|Permission|Exam|Result|Portal|Security|Phase7` | 282 / 282 |
| `tests/Unit/Results` | 29 نجح |
| متصفح headless (15 فحصًا) | 15 نجح — **على صفحات حُذفت الآن** |

بعد الإرجاع، يُتوقع فشل اختبارات صفحات الويب المحذوفة (L-E1) — لم يُقس.

## 3. الاختبارات المطلوبة (لم تُنفذ)

| ID | الاختبار | المرحلة | الحالة |
|---|---|---|---|
| TR-01 | خط أساس كامل لشجرة العمل الحالية على قاعدة مؤقتة | 6 | NOT STARTED |
| TR-02 | قائمة الاختبارات التي تفشل بسبب الإرجاع، صنفًا صنفًا | 6 | NOT STARTED |
| TR-03 | اختبارات RLS بدور غير superuser (`PostgreSqlRlsActor`) لكل جدول exams/results | 9 | سابق جزئيًا (`ExamisNewTablesRlsPostgreSqlTest`) |
| TR-04 | 403 عبر المدارس لكل route جديد | 7 | NOT STARTED |
| TR-05 | نشر متزامن / تسليم متزامن / تصحيح متزامن | 8 | سابق جزئيًا (تجارب سباق يدوية للنشر والمراجعة) |
| TR-06 | انتقال ناجح↔راسب عند التقريب (49.995) | 8 | سابق (U1) |
| TR-07 | e2e Playwright للصفحات الجديدة، RTL، هاتف/لوحي | 9 | NOT STARTED |

## 4. الأوامر المعتمدة

```bash
npm run types:check
php -d memory_limit=2G vendor/bin/phpunit -c phpunit.database-pgsql.xml --filter "<pattern>"   # فقط على قاعدة مؤقتة، انظر EXAMIS-OPERATIONS.md
php artisan architecture:feature-check Exams
php artisan architecture:feature-check Results
php artisan architecture:validate --fitness   # يفشل مسبقًا على بنود قديمة خارج EXAMIS
```
