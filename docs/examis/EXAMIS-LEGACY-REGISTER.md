# EXAMIS — سجل المكونات القديمة والمخلفات

**القاعدة:** لا يُحذف أي عنصر هنا قبل إثبات عدم استخدامه + موافقة المالك. خيارات المعالجة: احتفاظ · إصلاح · إعادة استخدام · عزل · ترحيل مخطط · إزالة بعد الموافقة.
**الفحص:** 2026-10-24، grep على `app/` خارج `app/Application/**` (controllers، console، jobs، infrastructure) ثم على `routes/*.php`.

## L-A. Backend بلا نقطة دخول بعد إرجاع الصفحات (DEC-006)

استعمالها الوحيد خارج `Application` هو FormRequests لا يستدعيها أي controller.

| ID | المكوّن | الوظيفة | الاعتمادات | الخطر | المعالجة المقترحة |
|---|---|---|---|---|---|
| L-A1 | `PublishYearResultsHandler`، `WithdrawResultPublicationHandler`، `PublicationSnapshotBuilder`، `PublishYearResultsRequest`، `WithdrawResultPublicationRequest` | نشر/سحب النتائج | `results.result_publications`، `results.publish` | ~~عالٍ~~ | **أُعيد استخدامها في U-01** عبر `ResultPublicationController` (`/results/publications`). `GetYearResultSheetHandler` (L-A7) كذلك |
| L-A2 | `OpenGradeReviewHandler`، `StartGradeReviewHandler`، `DecideGradeReviewHandler`، `WithdrawGradeReviewHandler`، `ListGradeReviewsHandler` + FormRequests | مراجعات الدرجات | `exams.grade_reviews`، `grades.review.*` | متوسط — لا يمكن فتح مراجعة | إعادة استخدام |
| L-A3 | `SaveExamTypeHandler`، `SaveExamTypeWeightVersionHandler`، `ListExamTypesHandler` + FormRequests | أنواع الامتحانات وإصدارات الأوزان | `exams.exam_types`، `exams.exam_type_weight_versions` | متوسط — لا إدارة للأوزان من الويب | إعادة استخدام |
| L-A4 | `SeatClassInExamSessionHandler`، `ExamSeatingTargetResolver`، `GetExamSeatingContextHandler`، `GetExamSessionRosterHandler` | تسكين صف كامل + كشف الجلسة | `exams.exam_enrollments` | منخفض — يبقى API تسجيل فردي | إعادة استخدام |
| L-A5 | `EnterSessionGradesHandler`، `GetGradeEntrySheetHandler`، `EnterSessionGradesRequest` | إدخال درجات جلسة كاملة | `exams.student_grades` | منخفض — يبقى إدخال فردي | إعادة استخدام |
| L-A6 | `ProcessTermResultsBatchHandler`، `ProcessYearResultsBatchHandler`، `IssueTranscriptsBatchHandler`، `TermResultsBatchTargetValidator` + FormRequests | حساب/اعتماد/إصدار جماعي | `results.*` | منخفض — يبقى API فردي | إعادة استخدام (مع Queue حسب القاعدة 7 إن كان الحجم كبيرًا) |
| L-A7 | ~~`GetTermResultSheetHandler`~~ (U-03، مع `PublishTermResultsRequest`)، ~~`GetYearResultSheetHandler`~~ (U-01)، `GetExamFormLookupsHandler`، `GetGradeListContextHandler`، ~~`GetPortalPublishedResultsHandler`~~ (U-02) | كشوف ومحددات اختيار | — | منخفض | إعادة استخدام — اثنان منها مستخدمان الآن |

### L-A8. الجرد الكامل الآلي (2026-10-24، الجلسة 2)

الأمر: لكل `*Handler.php` في `app/Application/{Exams,Results}/{Commands,Queries}` → `grep -rlw <Handler>` في
`app/Http/Controllers`، `app/Console`، `app/Jobs`، `app/Listeners`، `app/Infrastructure`. النتيجة: **73 handler، 48 مستخدم، 25 بلا نقطة استدعاء**:

~~`CancelExamSessionHandler`~~ (7.2) · `DecideGradeReviewHandler` · `EnterSessionGradesHandler` · ~~`GetExamDisplayNamesHandler`~~ (7.2) · ~~`GetExamFormLookupsHandler`~~ (7.2) ·
`GetExamSeatingContextHandler` · `GetExamSessionRosterHandler` · `GetGradeEntrySheetHandler` · `GetGradeListContextHandler` · `GetPortalPublishedResultsHandler` ·
`GetTermResultSheetHandler` · `GetYearResultSheetHandler` · `IssueTranscriptsBatchHandler` · `ListExamTypesHandler` · `ListGradeReviewsHandler` ·
`OpenGradeReviewHandler` · `ProcessTermResultsBatchHandler` · `ProcessYearResultsBatchHandler` · `PublishYearResultsHandler` · `SaveExamTypeHandler` ·
`SaveExamTypeWeightVersionHandler` · `SeatClassInExamSessionHandler` · `StartGradeReviewHandler` · `WithdrawGradeReviewHandler` · `WithdrawResultPublicationHandler`.

\* لم يكونا في الجرد الأول: `CancelExamSessionHandler` (الـ API لا يملك route لإلغاء جلسة؛ كان في `ExamAdministrationController` المحذوف) و`GetExamDisplayNamesHandler`.
ملاحظة: `E2eSeedExamsCommand` يستدعي handlers إنشاء الامتحان/الجلسة/التسجيل/الدرجة (أمر بذور e2e محروس باسم `sis_e2e_*`).

### L-A9. مسار R-01 بالتفصيل (مُتحقَّق)

```text
حساب (API results/*/calculate) ✓ → اعتماد (API results/*/finalize) ✓ → نشر ✗ (لا route: كان POST /results/sheet/publish و/results/year-sheet/publish)
→ سحب ✗ (كان POST /results/year-sheet/publications/{id}/withdraw) → عرض للطالب: API portal/results/* ✓ (بوابة isPublished) · صفحة GET /portal/results ✗ (حُذفت)
```
الدليل: `git diff routes/web.php` (الأسطر المحذوفة)، `routes/api.php` 294–337، وتشغيل T-0004 أدناه في `EXAMIS-TEST-REGISTER.md`
(اختبارات بوابة الـ API نجحت 7/7؛ اختبارا صفحة `/portal/results` أعادا 404).

### L-F. ملاحظات صغيرة مكتشفة

| ID | الملاحظة | الأثر |
|---|---|---|
| L-F2 | `architecture:validate --fitness` (T-0015): `DecideGradeReviewHandler` تعقيد دوري 12 و`ReopenExamEnrollmentHandler` 11 (الحد 10) — كود EXAMIS ملتزم (`faea262` وما قبله). التقرير السابق قال إن فشل fitness كله أقدم من EXAMIS: **غير دقيق** | متوسط (جودة)؛ يُعالج في وحدة تلمس هذين الملفين، لا الآن |
| L-F3 | PHPStan (T-0014) — للمراجعة، غير مثبت أنه خلل: `ProcessYearResultsBatchHandler:39,53` (match.unhandled، offset على union)، ~~`PublicationSnapshotBuilder:72-73`~~ (**أُصلح في U-01**: اختيار صريح لـ `gpa`/`annual`، T-0028 = 0 أخطاء، السلوك نفسه)، `ExamSessionCancelGuard:29` و`ExamSessionUpdateGuard:41,45` و`UpdateExamEnrollmentGuard:45` (شروط دائمًا false/true = منطق ميت محتمل) | منخفض–متوسط |
| ~~L-F1~~ | route `GET /exams/{exam}` بلا `whereNumber` | **أُصلح في 7.2**؛ `/exams/abc` → 404 (`a_non_numeric_exam_id_is_not_found…`) |
| L-F4 | صفحة الامتحان كانت تعرض حالة الجلسة بـ `StatusChip kind="exam"` (1 = «مسودة») بدل `examSession` (1 = «مجدولة») | **أُصلح في 7.2** |
| L-F5 | (اكتُشف في 7.3a باختبار) قيد `CHECK (... length(btrim(col)) > 0)` على عمود يقبل NULL يمرّ مع NULL (نتيجة unknown = قبول). صُحّح في migration البنك بـ `COALESCE`. **ما زال قائمًا** في `results.result_publications` (`withdraw_reason` عند `withdrawn`) و`exams.grade_reviews` (`decision_note` عند القرار) — الـ handlers تشترط النص، فالخلل في خط الدفاع الثاني فقط | متوسط (سلامة بيانات)؛ إصلاحه = migration جديدة تحتاج موافقة، لا الآن |
| L-F6 | `npx tsc --noEmit` الكامل يفشل بـ «Fatal process out of memory: Zone» (الذاكرة الافتراضية الحرة ~1.4 GB أثناء 7.3a؛ Chrome وCursor مفتوحان). ليس خللًا في الكود | **مغلق 2026-10-27 15:4x** (T-0112): اكتمل الفحص الكامل، 20 = خط الأساس |
| L-F7 | (معاينة 7.3a، T-0123) صفحة السؤال تعرض `submitted_at`/`decided_at` كما يعيدها PostgreSQL بتوقيت UTC (`value.slice(0,16)`)، فتتأخر 3 ساعات عن التوقيت المحلي | **أُصلح 2026-10-27** (T-0125/T-0129): الخادم يرسل ISO 8601 UTC + `timezone` من `attendance.default_timezone`؛ الصفحة تنسّق بتلك المنطقة وبلغة الصفحة |
| L-F8 | (معاينة 7.3a، T-0123) بطاقة النسخة ورابط «العودة للقائمة» في `bank/questions/show.tsx` يستعملان صنف الرابط `sis-ops-hub__link` بدل مكوّن البطاقة القائم، فتظهر كتلة داكنة ثقيلة | **أُصلح 2026-10-27** (بموافقة المالك): بطاقات `sis-ops-hub__gate` القائمة، ورابط الرجوع `Button variant="outline"` |
| L-F9 | (معاينة 2026-10-27، T-0131) بالإنجليزية (LTR) يُرسم الشريط الجانبي على اليمين فوق المحتوى ويبقى فراغ داكن يسارًا — في كل الصفحات (`/exams`، `/students`)، موجود قبل تغييرات البنك | متوسط (UI عام)؛ خارج نطاق EXAMIS — تخطيط AppLayout/الشريط الجانبي؛ يحتاج قرارًا |
| L-F10 | (معاينة 2026-10-27، T-0131) 403/404 على صفحات الويب = صفحة Laravel الافتراضية بالإنجليزية («This action is unauthorized.») لكل الوحدات؛ `bootstrap/app.php` لا يعرض صفحة خطأ SIS معرّبة | متوسط (UX عام)؛ خارج نطاق 7.3a — صفحة خطأ Inertia مشتركة تحتاج قرارًا |

## L-G. تصنيف ملفات شجرة العمل غير المحفوظة (49 + 3 من جلسة 1 = 52)

لم يُغيَّر أي منها في هذه الجلسة. لا reset/checkout/clean/stash/commit.

| المجموعة | الملفات | المصدر والغرض | الحكم |
|---|---|---|---|
| G1 — إرجاع الواجهة (DEC-006) | 9 controllers محذوفة؛ `ExamPageController`، `GradesPageController`، `ResultsPageController` معدّلة؛ 14 صفحة/مكوّن محذوف؛ 10 صفحات معدّلة إلى نسخة `1738c8c`؛ `routes/web.php`؛ `app-sidebar.tsx`، `titlebar-ssot.ts`، `title-bar-ribbon.tsx`، `ar.ts`؛ `HandleInertiaRequests.php` (حذف `examisShell`، `portalNav`)؛ `pages/grades/enter.tsx` (غير متتبع، أصلي) | طلب المالك | احتفاظ. البناء الجديد يجري فوقها بوحدات صغيرة |
| G2 — تحسينات backend مقصودة | `ResultPublicationRepositoryInterface` + `EloquentResultPublicationRepository` (تحويل unique violation إلى `already_published`، `activeWithDrift`، `published_by_name`)؛ `EloquentGradeReviewRepository` (`resulting_score`)؛ `ReportPublicationDriftCommand` (غير متتبع) | إصلاح سباق + تقرير drift | احتفاظ — مرشح لـ commit مستقل لاحقًا بطلب المالك |
| G3 — اختبارات | `GradeReviewWebPostgreSqlTest`، `ResultPublicationWebPostgreSqlTest` (معدّلة)؛ `ExamisNewTablesRlsPostgreSqlTest`، `PublicationDriftReportPostgreSqlTest` (غير متتبعة) | تغطية G2 | احتفاظ؛ اختبارات Web تفشل بسبب G1 (T-0004) |
| G4 — وثائق | `SIS-EXAM-FINAL-REPORT.md`، `SIS-EXAM-REQUIREMENTS-TRACEABILITY.md` (ملاحظة الإرجاع)؛ `CLAUDE.md`، `docs/examis/`، `.claude/skills/` (جلسة 1) | توثيق | احتفاظ |
| G5 — لا علاقة | `_fix_utf8.py` | سكربت ترميز لـ `enrollment-list.tsx` | خارج النطاق — لا يُلمس |

## L-B. ملفات محذوفة في شجرة العمل (موجودة في HEAD `4472454`)

Controllers: `Exams/ExamAdministrationController`، `ExamRosterController`، `ExamSeatingController`، `ExamTypeController`،
`Grades/GradeReviewController`، `GradeSheetController`، `Portal/PortalResultsPageController`، `Results/TermResultSheetController`، `YearResultSheetController`.
صفحات/مكونات: `pages/exams/session.tsx`، `types.tsx`، `pages/grades/reviews.tsx`، `sheet.tsx`، `pages/portal/results.tsx`، `pages/results/sheet.tsx`، `year-sheet.tsx`،
`components/examis/examis-chrome.tsx`، `components/results/publication-list.tsx`، `reconciliation-panel.tsx`، `results-student-picker.tsx`.
**الاسترجاع ممكن:** `git restore --source=HEAD -- <path>` أو النسخة في `D:\Projects\sis_backup_examis_pages_2026-10-24\`. لا يُسترجع شيء دون DEC-P01.

## L-C. قاعدة البيانات

| ID | العنصر | الحالة | ملاحظة |
|---|---|---|---|
| L-C1 | `results.result_publications` (+ أعمدة term/transcript) | مطبّق على `sis` حسب التقرير السابق — `NOT VERIFIED` هنا | مستخدم بالقراءة من `PortalResultsController` |
| L-C2 | `exams.grade_reviews` | مطبّق — `NOT VERIFIED` هنا | بلا مدخل كتابة (L-A2) |
| L-C3 | `exams.exam_type_weight_versions` | مطبّق — `NOT VERIFIED` هنا | بلا مدخل (L-A3)؛ insert-only |
| L-C4 | `phase3a/3b` exams tables، `phase74/75` results tables | أساس قائم | — |
| L-C5 | `D:\Projects\sis_backup.sql` | dump خارج المستودع، **لم يُفتح** | قد يحوي بيانات حقيقية: لا يُستعمل كبيانات اختبار |

## L-D. ملفات غير متتبعة / متفرقة

| ID | الملف | العلاقة | المعالجة |
|---|---|---|---|
| L-D1 | `app/Console/Commands/ReportPublicationDriftCommand.php` (`sis:report-publication-drift`) | EXAMIS، مُحتفظ به عمدًا | احتفاظ — ينتظر commit من المالك |
| L-D2 | `tests/.../ExamisNewTablesRlsPostgreSqlTest.php`، `PublicationDriftReportPostgreSqlTest.php` | EXAMIS | احتفاظ |
| L-D3 | `resources/js/pages/grades/enter.tsx` | صفحة أصلية مُستعادة من `1738c8c` (غير موجودة في HEAD) | احتفاظ |
| L-D4 | `_fix_utf8.py` | **لا علاقة بـ EXAMIS** (يصلح `enrollment-list.tsx`) | خارج النطاق — `TECHNICAL DEBT` |

## L-E. اختبارات

| ID | العنصر | الحالة |
|---|---|---|
| L-E1 | اختبارات صفحات الويب لـ EXAMIS تفشل لأن routes حُذفت | **مُقاس جزئيًا (T-0004):** 66 من 93 في 9 أصناف. لا تُعدَّل لتصبح خضراء؛ كل وحدة بناء جديدة تحدد مصير اختباراتها (إعادة استخدام التوقعات أو استبدال موثق) |
| L-E2 | أخطاء TS17001 (خاصية `dir` مكررة) في `grades/actions.tsx`، `grades/enter.tsx`، `results/show.tsx`، `results/term.tsx`، `results/transcript.tsx` | **موجودة أصلًا في `1738c8c`** (تم التحقق بـ `git show`) — ليست ناتجة عن الإرجاع |

TECHNICAL DEBT: `App\Domain\Exams\Exceptions\ExamNotFoundException` يرث `DomainException` لا `SisDomainException`: على الويب يظهر نصّه الإنجليزي الخام (مع رقم الامتحان) بدل 404 أو رمز مترجم. 7.2 يتجنبه بفحص الملكية في الـ controller (DEC-045)؛ لم يُعدَّل المجال.
TECHNICAL DEBT: `OpsYearFilter` (مكوّن مشترك) تسميته الافتراضية `i18n.enrollments.academicYear` غير مترجمة في `en.ts`، فتظهر عربية في الواجهة الإنجليزية لكل صفحة لا تمرر `label` — U-01 يمرر `i18n.context.year`؛ لم يُعدَّل المكوّن.
TECHNICAL DEBT: ملفات ظهرت أثناء الجلسة 3 وليست من EXAMIS ولم تُلمس: `docs/project/`، `scripts/session-resume-check.ps1`، وتعديل على `CLAUDE.md` من خارج هذه الجلسة.
TECHNICAL DEBT: `_fix_utf8.py` غير متتبع في جذر المستودع؛ و`reports/attendance-daily.tsx` فيه أخطاء `dir` مكررة نفسها — خارج EXAMIS.
