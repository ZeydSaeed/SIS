# EXAMIS — معايير القبول

كل معيار قابل للتحقق بأمر أو ملف. المرحلة لا تُعلَن `PASS` إلا بتحقق كل معاييرها؛ `PASS WITH CONDITIONS` يذكر الشرط صراحة.

| المرحلة | المعايير |
|---|---|
| 0 | مسارات SIS/SurveyKing/المختبر/الأدلة مثبتة بأوامر · حالة Git لكل مستودع · لا ملف تطبيق معدّل · قائمة الأوامر الآمنة والممنوعة |
| 1 | النسخة والترخيص · نموذج البيانات · خريطة الوظائف مع ملف دليل لكل وظيفة ودرجة ثقة · ما لم يُتحقق منه مسجّل |
| 2 | جرد routes/handlers/صفحات/جداول EXAMIS · تصنيف كل نقص في القسم 3 من البروتوكول · سجل المخلفات · مصدر الدرجة الرسمي مُتحقق من migration · صورة الصلاحيات وRLS |
| 3 | قرار المالك لكل F-xx في v1 · لا عنصر «مفيد نظريًا» بلا دليل حاجة · DEC-P01..P05 مُجابة أو مسجلة كقيود |
| 4 | كل سياسة تؤثر في النتيجة لها مصدر (قرار مالك أو كود قائم) · ADR معتمد لكل نظام فرعي جديد · مخطط الحالات والانتقالات |
| 5 | `EXAMIS-UI-CONTRACT.md` معتمد · صفحة نموذجية واحدة تمر بفحص متصفح RTL على 390/820/1366 px قبل أي صفحة ثانية |
| 6 | قاعدة مؤقتة باسم مثبت (`SELECT current_database()` داخل التشغيل) ≠ `sis` · `SIS_DESTRUCTIVE_ALLOWED_DATABASES` يحتوي اسمها فقط · حذفها بعد الاستعمال مسجّل |
| 7 (لكل وحدة) | خطة الوحدة مكتوبة · اختبار Feature لكل route (نجاح + 403 مدرسة أخرى + تحقق) · `npm run types:check` بلا أخطاء جديدة في الملفات المعدلة · `architecture:feature-check` للسياق · diff مراجَع · الوثائق محدّثة |
| 7 — U-00 (خط الأساس) | قاعدة `sis_test_examis_*` جديدة مثبتة بـ `probe` · تشغيل الحزمة الكاملة `phpunit.database-pgsql.xml` + `tests/Feature/Exams` على قاعدة ثانية جديدة · جدول فشل لكل صنف مصنف (G1 / قديم معروف / جديد) · حذف القواعد · لا تعديل كود |
| 7 — U-01 (مدخل نشر نتائج السنة، يغلق R-01 للموظفين) | انظر §U-01 أدناه |
| 8 | اختبارات تزامن للنشر والتسليم والتصحيح · لا تغيير صامت لنتيجة تاريخية · أثر تغيّر التسجيل موثق |
| 9 | RLS بدور غير superuser لكل جدول · مسح routes للتفويض · قياس N+1 للصفحات · e2e للتدفق الأساسي |
| 10/12 | مصفوفة وظائف بحالات ودليل لكل وظيفة · تقرير اختبارات بأوامر ونتائج فعلية · تقييمات جاهزية منفصلة (تصميم/تنفيذ/اختبار/أمان/تشغيل/إنتاج) |
| 11 | `EXAMIS-NEXT-SESSION.md` و`EXAMIS-PROJECT-STATE.md` محدّثان في نهاية كل جلسة، بتاريخ وحالة Git |

## نتيجة بوابة U-00 (2026-10-25): `PASS WITH CONDITIONS`

| معيار | النتيجة |
|---|---|
| قاعدة جديدة مثبتة بـ probe لكل تشغيل | ✓ `_pg`، `_ex` (T-0007، T-0021، T-0022) |
| الحزمة الكاملة PostgreSQL + `tests/Feature/Exams` على قاعدتين | ✓ T-0021، T-0022 |
| كل فشل مصنف | ✓ 101/101 (R1)؛ 1179/1179 (R3) — TEST-REGISTER §1d |
| حذف القواعد | ✓ T-0025 |
| لا تعديل كود | ✓ md5 لـ `git status` ثابت؛ 52 مدخلًا |

**الشروط:** Playwright BLOCKED (اسم القاعدة)؛ composer audit BLOCKED (شبكة)؛ Leave غير محسوم (خارج EXAMIS)؛ L-F2، L-F3 مفتوحة.
**الحماية مؤكدة** (الحارس، العزل، RLS، بوابة الـ portal) ولا فشل منطقي في EXAMIS ← لا مانع تقني من U-01. **الانتقال التنفيذي ينتظر موافقة المالك** (تكليف الجلسة 3 §C).

## نتيجة U-01 (2026-10-25): `PASS WITH CONDITIONS`

| # | المعيار | الحالة | الدليل |
|---|---|---|---|
| 1 | نشر نطاق جاهز → صف واحد؛ التكرار لا ينشئ ثانيًا | PASS | `publishing_a_complete_scope_…`؛ خاسر السباق: `the_loser_of_a_race_…` (T-0027) |
| 2 | نطاق ناقص → رفض بالرمز القائم ولا صف | PASS | `an_incomplete_scope_publishes_nothing`، `an_official_result_marked_incomplete_…` |
| 3 | بلا `results.publish` → 403 للنشر والسحب | PASS | `only_the_publish_permission_…` (`results_manager`، `results_viewer`) |
| 4 | مدرسة أخرى → رفض بلا كشف | PASS | `another_school_cannot_…` (نشر مرفوض، سحب 404، لا صفوف ولا نشرات) |
| 5 | السحب بسبب؛ بعده البوابة تعيد 404 للطالب | PASS | `withdrawal_needs_a_reason_…` (200 قبل، 404 بعد) |
| 6 | الاختيار بالصف/الشعبة/السنة بأدوات اختيار | PASS | `OpsYearFilter` + `SisListSelect`؛ Playwright (T-0039) |
| 7 | لا أخطاء TS جديدة · feature-check · متصفح ar/en على 390/820/1366 | PASS | T-0033، T-0031، T-0039 (RTL/LTR، بلا تمرير أفقي للصفحة) |
| 8 | مراجعة توقعات الاختبارات القديمة | PASS WITH CONDITIONS | سلوكيات `ResultPublicationWebPostgreSqlTest` أُعيد تغطيتها في الصنف الجديد (عدا term/transcript)؛ الملف القديم باقٍ بـ 8 فشلات G1 (routes `/results/year-sheet` و`/results/sheet`) — لم يُعدَّل |
| 9 | L-F3 في `PublicationSnapshotBuilder` + اختبار `incomplete` | PASS | T-0028 (0 أخطاء)؛ `an_official_result_marked_incomplete_…` |

**الشروط:**
- **(أ)** النقطة تقبل `target=transcript` لأنها ترث `PublishYearResultsRequest`. الواجهة لا تعرضه، ولم يُختبر في U-01 (`NOT VERIFIED`).
- **(ب)** فحص المتصفح كان على chromium فقط، بعروض مضبوطة داخل الاختبار. مشروعا tablet/mobile (Pixel 7) لم يُشغّلا.
- **(ج)** `public/build` أُعيد إلى نسخته السابقة، فالصفحة **غير ظاهرة على الموقع المحلي** حتى `npm run build`. هذا قرار المالك.
- **(د)** المخاطر R-07 وR-08 مفتوحة (`EXAMIS-SECURITY-MODEL.md`).

## R-07 / R-08 (2026-10-25): `PASS`

- **R-07:** المفتاح المُعاد مع نشر آخر يُرفض الآن بـ `results.publish.idempotency_conflict`، والنشر الثاني يبقى منشورًا. إعادة المفتاح نفسه للنشر نفسه ما زالت replay. الدليل: T-0041.
- **R-08:** اللقطة تُبنى وتُقفل صفوفها بـ `FOR SHARE` داخل معاملة الإدراج نفسها (`lockSnapshot`).
  - إعادة البناء قبل القفل → `results.publish.changed` ولا صف (T-0041).
  - أثناء القفل → إعادة البناء تنتظر (T-0048، بجلستين حقيقيتين).

## نتيجة U-02 (2026-10-25): `PASS WITH CONDITIONS`

| # | المعيار | الحالة | الدليل |
|---|---|---|---|
| 1 | المنشور فقط؛ لا غير المنشور ولا المسحوب ولا النسخة الأحدث | PASS | `nothing_is_shown_before…`، `a_withdrawn_publication_or_a_newer…`؛ Playwright (T-0051) |
| 2 | لا معامل يغيّر من يُعرض | PASS | `only_the_users_own_student…` (`student_id`/`enrollment_id` لطالب آخر منشور → 0)؛ Playwright |
| 3 | ولي الأمر يرى طلابه المرتبطين | PASS | `a_guardian_sees_the_linked_student` |
| 4 | موظف → 403؛ صلاحية بلا نطاق → فارغة | PASS | `staff_are_refused…`؛ `Phase78…::web_page_needs_the_portal_permission_and_a_scope` |
| 5 | لا تسرب بين المدارس | PASS | `another_school_context_shows_nothing…` |
| 6 | حالات: فراغ، منشور، بعد السحب | PASS | PHPUnit + Playwright |
| 7 | ar/en، RTL/LTR، 390/820/1366 بلا تمرير أفقي | PASS | T-0051 (chromium) |
| 8 | رابط الشريط الجانبي لمستخدمي البوابة فقط | PASS | prop `portalNav.results` (true/false) في PHPUnit؛ في Playwright: ظاهر للطالب، غائب عن `teacher` |
| 9 | لا مصدر موازٍ | PASS | الـ handler القائم `GetPortalPublishedResultsHandler`؛ لا migration |

**الشروط:**
- **(أ)** الشريط الجانبي يعرض لمستخدم البوابة كل وحدات الموظفين (سلوك سابق، والخادم يرفضها). هذا دَين UX خارج U-02.
- **(ب)** الشريط على اليمين في LTR (سابق).
- **(ج)** مشروعا tablet/mobile في Playwright لم يُشغّلا. العروض اختُبرت داخل chromium.
- **(د)** نتائج الفصول (`terms`) في الصفحة لم تُختبر في المتصفح، لأن الـ fixture لا ينشر term. هي مغطاة في `Phase78…::web_page_lists_only_published…` (`rows.0.terms` = 1).

## نتيجة U-03 (2026-10-25، المتصفح 2026-10-26): `PASS` — بقيد معلن: الكشف المنشور سجل بلا مستند (DEC-038)

**العقود المكتشفة في المرحلة 0:**
- **الفصول:** النشر لكل **فصل دراسي + مادة** (لا فصل كامل)، لصف أو شعبة.
  - الطلاب = المُجلَسون في جلسات المادة لذلك الفصل.
  - الطلب `PublishTermResultsRequest` كان يتيمًا، والـ handler نفسه بـ `kind=term`.
- **الكشوف:** `target=transcript` مدعوم كاملًا للبيانات الوصفية للكشف الصادر (الرقم والنسخة والتاريخ):
  - لقطة: «محدَّث» = صدر بعد آخر اعتماد للمعدل.
  - قفل وانحراف (`is_current`، `transcript_version`).
  - سحب، وصفحة البوابة، والـ API.
  - **لا يوجد مستند/محتوى للكشف (PDF)**. لم يُنشأ أي مصدر جديد (F-23 مؤجل).

| # | معيار التكليف | الحالة | الدليل |
|---|---|---|---|
| 1 | نشر نتائج فصل مكتملة | PASS | `a_complete_term_scope_is_published_once…` |
| 2 | رفض المكرر | PASS | نفسه + خاسر السباق |
| 3 | رفض الناقص | PASS | `an_incomplete_or_invalid_term_scope…` |
| 4 | بلا صلاحية → منع | PASS | `only_the_publish_permission…` (فصول + كشوف) |
| 5 | مدرسة أخرى | PASS | `another_school_cannot…` (فصول + كشوف) |
| 6 | ثبات اللقطة | PASS | `the_term_snapshot_is_stable…` |
| 7 | سحب بسبب | PASS | `a_term_publication_is_withdrawn…`، `an_issued_up_to_date_transcript…` |
| 8 | idempotency | PASS | replay بالمفتاح؛ رفض المفتاح لنشر آخر (U-01) |
| 9 | تزامن النشر/إعادة البناء | PASS | `changed` للفصل وللكشف؛ قفل الجلستين مُثبت في T-0048 لنفس الشفرة |
| 10 | البوابة تعرض المنشور فقط | PASS | فصول + كشوف (صفحة + API) |
| 11 | لا تجاوز للهوية بمعاملات URL | PASS | `the_portal_shows_only_the_published_term…` |
| 12 | اختبارات مستقلة للكشوف | PASS | 3 اختبارات |
| — | متصفح ar/en على 390/820/1366 + tablet/mobile | **PASS** (2026-10-26) | T-0068: 20/20 على setup + chromium + tablet + mobile |

**الشروط:**
- **(أ)** اختبارات المتصفح لم تُنفّذ.
- **(ب)** القرص C: ممتلئ (T-0063).
- **(ج)** «نتائجي» لم تُعدَّل: كانت تعرض الفصول والكشوف أصلًا، وتحقق PHPUnit من ذلك.

## U-02 — «نتائجي» (صفحة الطالب/ولي الأمر) — الخطة الأصلية

**الهدف:** يرى الطالب أو ولي الأمر النتائج **المنشورة** فقط (السنوية، المعدل، وغيرهما بحسب ما تعيده البوابة)، فيُغلق R-01 من جهة الطالب.

**الموجود المُعاد استخدامه:**
- `GetPortalPublishedResultsHandler` / `GetPortalPublishedResultsQuery(schoolId, studentIds)` و`PortalPublishedResultsReadRepositoryInterface::forStudents`.
- `PortalPartyAccessService::allowedStudentIds($user)`: **هوية الطالب من السياق الموثوق، لا من مدخل المستخدم**.
- Gate `viewPortalOfficial` / `portal.results.view`.
- `Phase78PortalResultsHttpApiPostgreSqlTest::web_page_*`، وفيه توقعان قائمان (component `portal/results`، `rows`، 403 للموظف).

**ملفات متوقعة:**
- controller رفيع `app/Http/Controllers/Portal/`.
- route `GET /portal/results` تحت `require.school.context`.
- صفحة `resources/js/pages/portal/results.tsx` (ar/en).
- رابط «نتائجي» في الشريط الجانبي لمستخدمي البوابة فقط، عبر prop مشترك محسوب في الخادم.
- اختبار Feature وspec Playwright.
- **لا migration.**

**معايير القبول:**
1. مستخدم بنطاق طالب يرى نتائج طالبه المنشورة فقط، ولا يرى غير المنشورة ولا المسحوبة ولا النسخة الأحدث غير المنشورة.
2. لا معامل `student_id`/`enrollment_id` يغيّر من يُعرض. أي معامل كهذا يُتجاهل أو يُرفض، ويُختبر ذلك.
3. ولي الأمر يرى طلابه المرتبطين فقط.
4. مستخدم بلا صلاحية البوابة، أو موظف، → 403. صلاحية بلا نطاق → صفحة فارغة.
5. لا تسرب بين المدارس: نشر في مدرسة أخرى لا يظهر.
6. حالات: لا نتائج (رسالة فراغ)، نتائج منشورة، بعد السحب.
7. ar/en وRTL/LTR وبلا تمرير أفقي على 390/820/1366، على قاعدة `sis_e2e_examis_*` ببيانات اصطناعية.
8. رابط الشريط الجانبي لا يظهر لغير مستخدمي البوابة، ويُختبر prop الخادم.
9. لا مصدر بيانات موازٍ: القراءة من `results.result_publications` + جداول النتائج فقط عبر الـ handler القائم.

**التراجع:** حذف الـ controller والـ route والصفحة والرابط. لا أثر على البيانات.

## 7.3a — بنك الأسئلة: الأسئلة ونسخها والموافقة (خطة 2026-10-27، DEC-046..054)

**التصنيف:** ميزة تطبيق + تغيير قاعدة بيانات (CREATE: مخطط جديد `bank`، جدولان) + واجهة. قُرئ: `database-change` SKILL، `application-feature` SKILL،
`database-changes-mandatory.mdc`، `DATABASE-CHANGE-CHECKLIST.md`، نمط `exams.grade_reviews` (migration/handler/four-eyes).

**نموذج البيانات (مخطط `bank`، RLS + FORCE على `school_id`، لا حذف أبدًا):**
- `bank.questions`:
  - **الأعمدة:** id، `school_id`، `subject_id`، `status` (`active`|`retired`)، `created_by`، `retired_by`/`retired_at`/`retire_reason`، الطوابع الزمنية.
  - **الحارس:** لا DELETE؛ المتقاعد نهائي؛ المدرسة والمادة والمنشئ ثابتة.
- `bank.question_versions`:
  - **الأعمدة:** id، (`question_id`، `school_id`) → questions، `version_no` (UNIQUE مع السؤال)، `type`، `stem`، `payload` JSONB (الخيارات/مفتاح الإجابة)، `max_mark` NUMERIC(6,2) > 0، `status` (`draft`|`in_review`|`approved`|`rejected`|`superseded`)، `created_by`، `submitted_by/at`، `decided_by/at`، `decision_note`.
  - **فهارس جزئية:** نسخة مفتوحة واحدة (draft|in_review) لكل سؤال، ونسخة معتمدة واحدة لكل سؤال.
  - **CHECK:** موافقة الأربع عيون `decided_by ≠ created_by` للمعتمَد.
  - **الحارس:** لا DELETE؛ المحتوى ثابت بعد مغادرة `draft`؛ `rejected`/`superseded` نهائيان؛ الانتقالات المسموحة فقط.

**الطبقات:**
- **Domain/Bank:**
  - أنواع: `QuestionType`، `QuestionVersionStatus`.
  - `QuestionContent` (التحقق من المحتوى لكل نوع: 2–8 خيارات، صحيح واحد للاختيار الواحد، ≥1 للمتعدد، صح/خطأ قيمة منطقية، الطويلة بلا خيارات؛ نص ≤ 5000).
  - `QuestionReviewPolicy` (الانتقالات + الأربع عيون).
  - `QuestionBankException`.
- **Application/Bank:**
  - **الأوامر الستة**، كلها بمفتاح idempotency وداخل `UnitOfWork`: إنشاء سؤال (مع نسخة 1 مسودة)، تعديل المسودة (مؤلفها فقط)، إرسال للمراجعة (المؤلف)، قرار اعتماد/رفض (غير المؤلف، مع ملاحظة للرفض، الاعتماد يُحيل المعتمد السابق إلى `superseded`)، مراجعة (نسخة مسودة جديدة من آخر نسخة، إن لم تكن نسخة مفتوحة)، تقاعد بسبب.
  - **الاستعلامان:** قائمة مرقّمة في الخادم بمرشحات (المادة، الحالة، بحث)، وسؤال مع نسخه.
- **Infrastructure:**
  - مستودع Eloquent/Query Builder بنمط grade reviews، مع `lock`.
  - منفذ نطاق التأليف من `teaching_assignments`، يعيد استعمال `TeacherDirectoryInterface`.
- **Http:**
  - `QuestionBankPageController` (الصفحتان)، و`QuestionBankController` (الأفعال الستة).
  - FormRequests، وPolicy.
- **الصلاحيات الجديدة:** `bank.question.view` و`bank.question.author` و`bank.question.approve`.
  - `grades_teacher` يملك view وauthor، ويرى ويؤلف مواد تدريسه فقط.
  - `grades_manager` يملك الثلاث، ويرى المدرسة كلها.
- **الواجهة:** `/bank/questions` (قائمة) و`/bank/questions/{id}` (سؤال ونسخه).
  - أوامر شريط «تحرير» داخل `AppLayout`، وأدوات اختيار المادة، ونص عادي فقط (DEC-049).
  - مفتاح الإجابة يظهر لمن يملك author أو approve فقط.
  - رابط «بنك الأسئلة» من شريط صفحة الامتحانات.

**الاختبارات المطلوبة:**
- Unit: التحقق من المحتوى لكل نوع.
- Feature PostgreSQL:
  - دورة الحياة كاملة.
  - الأربع عيون، في الـ handler وفي DB.
  - المعلم خارج مواده يُرفض بـ 403.
  - مدرسة أخرى 404.
  - المحتوى المعتمد لا يتغير (trigger)، ولا حذف.
  - تكرار المفتاح replay.
  - نسخة مفتوحة واحدة.
  - RLS بدور غير superuser.
- Playwright: ar/en وكل المشاريع.
- الانحدار الكامل: 0 فشل جديد.

**المخاطر وفحوص STOP:**
- لا migration على `sis` (DEC-053).
- لا حذف ولا cascade.
- المخطط الجديد يُضاف إلى `SchemaHelper::schemas()`، وإلا تبقى الجداول بعد `migrate:fresh` في الاختبارات.
- الأسئلة ليست مرتبطة بسنة دراسية: هي أصل منهجي لا سجل أكاديمي سنوي. نطاق التأليف وحده يُحسب من تكليفات السنة الحالية (DEC-055).
- إعداد «الموافقة الذاتية» لم يُبنَ بعد. الـ CHECK في DB يفرض الأربع عيون دائمًا، وتفعيل الإعداد لاحقًا يحتاج migration (DEC-056).

**التراجع:** `down()` يحذف الجدولين والدوال والمخطط على قواعد الاختبار فقط. لم يُطبَّق على `sis`.

## نتيجة 7.2 — إدارة الامتحانات والجلسات (2026-10-27): `PASS`

| المعيار (من البروتوكول §7.2 وعقد الواجهة) | الحالة | الدليل |
|---|---|---|
| إنشاء/تعديل/إلغاء امتحان وجلسة، فتح/إغلاق الجلسة، بالأوامر والطلبات والصلاحيات والـ idempotency القائمة | PASS | `ExamAdministrationWeb` (القديم، 5) + `ExamManagementWeb` (6)؛ T-0080 |
| المدرسة والسنة: لا وصول لامتحان أو جلسة مدرسة أخرى | PASS | 404 في الاختبارين؛ DEC-045 |
| الصلاحية لكل فعل (لا اعتماد على إخفاء الأزرار) | PASS | 403 لـ `GradesTeacher`/`GradesViewer` |
| الحالات وانتقالاتها (Draft→Scheduled→InProgress→Completed؛ الإلغاء بأمر خاص؛ منع الإلغاء مع درجات حالية أو جلسة مكتملة) | PASS | `the_exam_moves_only_along_its_lifecycle`، `a_session_with_a_current_grade_cannot_be_cancelled…`؛ Playwright (رفض إلغاء امتحان بجلسة مكتملة) |
| لا معرّفات خام (أسماء الفصل والسنة والمادة) | PASS | `ExamPagesDisplayNames` (2)؛ لقطة T-0086 |
| أوامر على شريط «تحرير» (عقد الواجهة)، تأكيد قبل الأفعال المؤثرة | PASS | T-0084، T-0085 |
| ar/en، RTL/LTR، 390/820/1366 + tablet/mobile | PASS | T-0084 (12/12) |
| لا انحدار | PASS | T-0090 (0 فشل جديد)، T-0091 (136/136) |

**خارج النطاق (7.5):** الإجلاس وكشف المرشحين؛ `administration.spec.ts::a viewer gets no administration controls` ينتظرها.

## U-01 — مدخل نشر نتائج السنة لصف (الخطة الأصلية)

**الهدف:** إعادة نقطة دخول واحدة، صغيرة، لمسار النشر/السحب الموجود، حتى لا تبقى بوابة الطالب تحجب كل نتيجة جديدة (R-01).
**النطاق:** نوعا `annual` و`gpa` لصف (وشعبة اختيارية) في سنة؛ نشر + سحب بسبب + عرض النشرات الفعالة مع `drifted`. خارج النطاق: term/transcript، صفحة الطالب (U-02)، الإشعارات.
**الموجود المُعاد استخدامه:** `PublishYearResultsHandler`، `WithdrawResultPublicationHandler`، `GetYearResultSheetHandler`، `PublishYearResultsRequest`
(التفويض: `ResultsPolicy::publish` → `results.publish`)، `WithdrawResultPublicationRequest`، `ResultPublicationRepositoryInterface::activeForClass`.
**ملفات متوقعة:** controller ويب رفيع جديد تحت `app/Http/Controllers/Results/`، routes في مجموعة `results` القائمة (`require.school.context`)،
صفحة Inertia واحدة تعيد استخدام `data-table` و`ops-year-filter` و`sis-list-select` و`confirm-dialog` و`sis-table-theme`، نصوص في `ar.ts` و`en.ts`، اختبار Feature PostgreSQL.
**DB:** لا migration. **قواعد العمل:** كلها في الـ handler القائم (رفض النطاق الناقص `results.publish.not_ready`، نشر فعّال واحد، `already_published`).

**معايير القبول:**
1. مستخدم بـ `results.publish` ينشر نطاقًا جاهزًا → صف نشر واحد؛ تكرار الطلب لا ينشئ صفًا ثانيًا.
2. نطاق ينقصه نتيجة رسمية → رفض بالرمز القائم، ولا صف.
3. مستخدم بلا `results.publish` (مثل `results_manager`) → 403 للنشر والسحب.
4. مستخدم مدرسة أخرى → 403/404، ولا كشف بيانات.
5. السحب يتطلب سببًا؛ بعده تعيد بوابة `api/v1/portal/results/annual` 404 للطالب (اختبار تكامل يربط المدخل الجديد بالبوابة القائمة).
6. الاختيار بالصف/الشعبة/السنة عبر أدوات اختيار، لا معرّفات خام.
7. `npm run types:check` بلا أخطاء جديدة في الملفات المضافة؛ `architecture:feature-check Results` يمر؛ فحص متصفح ar/en على 390/820/1366 px ببيانات اصطناعية على قاعدة مؤقتة.
8. توقعات `ResultPublicationWebPostgreSqlTest` القديمة تُراجع: ما يطابق السلوك المعتمد يُعاد استخدامه، والباقي يُستبدل بتوثيق السبب.
9. مراجعة L-F3 في `PublicationSnapshotBuilder:72-73` قبل الاعتماد عليه، مع اختبار يغطي النتيجة الرسمية الناقصة (`incomplete`).
**نقطة البداية من U-00:** `ResultPublicationWebPostgreSqlTest` (8 من 11 تفشل بسبب G1) و`YearResultSheetWebPostgreSqlTest` (9/9) يصفان السلوك المطلوب نفسه — تُقرأ توقعاتهما أولًا.
**التراجع:** حذف الـ controller والـ route والصفحة؛ لا أثر على البيانات (لا migration).
**المخاطر:** النشر على `sis` الحي ممنوع (DEC-027) — الاختبار على قاعدة مؤقتة فقط.

## L-F5 — خطة إصلاح قيود السبب/الملاحظة (PROPOSED، لم تُنفَّذ، 2026-10-27)

**المشكلة (مثبتة بالاختبار في 7.3a):** في PostgreSQL، قيد CHECK نتيجته NULL يُعامَل كنجاح. `length(btrim(NULL)) > 0` = NULL، فيمرّ:
- `results.result_publications_withdrawn_check`: صف `withdrawn` مع `withdraw_reason IS NULL`.
- `exams.grade_reviews_decision_check`: صف `accepted`/`rejected` مع `decision_note IS NULL`.
النص الفارغ أو المسافات فقط يُرفض أصلًا (الطول 0 = FALSE). الـ handlers تشترط النص؛ الثغرة في خط الدفاع الثاني (DB) فقط.

**الإصلاح:** migration جديدة واحدة، مثل `2026_10_28_100000_tighten_examis_reason_checks.php` (PostgreSQL فقط):
1. فحص مسبق برسالة واضحة: عدّ الصفوف المخالفة في الجدولين. إن وُجد أي صف → `throw` (لا تغيير)، ويقرر المالك.
2. في معاملة واحدة: `ALTER TABLE … DROP CONSTRAINT <name>, ADD CONSTRAINT <name> CHECK (…)` بالتعريف نفسه مع `length(btrim(COALESCE(col, ''))) > 0`.
3. `ADD CONSTRAINT` (بلا `NOT VALID`) يتحقق من **كل** الصفوف ولا تحجبه RLS. هذا هو الضمان الحقيقي، حتى لو شُغِّلت الـ migration بدور غير superuser يرى صفرًا في الفحص المسبق.
4. `down()`: يعيد التعريفين الأصليين حرفيًا.

**الأثر على البيانات:** لا تعديل لأي صف. على قواعد الاختبار: لا صفوف مخالفة (كل الكتابات عبر الـ handlers). على `sis` الحية: **غير معروف** ويحتاج تحققًا:
- هل الـ migrations `2026_10_24_100000` و`2026_10_24_110000` مطبّقة على `sis` أصلًا؟
- إن كانت مطبّقة: كم صفًّا مخالفًا يوجد؟
هذا استعلام قراءة يحتاج موافقة منفصلة.
- إن وُجدت صفوف مخالفة: لا تُعدَّل، لأن الـ triggers تمنع تعديل القرار أو السحب النهائي. البديل `NOT VALID` للصفوف الجديدة فقط، مع توثيق القديمة، وهذا قرار المالك.

**الأقفال:** `ADD CONSTRAINT` بالتحقق يأخذ `ACCESS EXCLUSIVE` على الجدول طوال الفحص. الجدولان صغيران (نشر ومراجعات)، فالأثر ثوانٍ. يُفضَّل وقت هادئ.

**الاختبارات (تُكتب قبل الإصلاح وتفشل بدونه):** ملف `ExamisReasonChecksPostgreSqlTest`:
- إدراج/تحديث مباشر في DB برفض: `withdrawn` + NULL، و`rejected`/`accepted` + NULL، و`''` و`'   '`.
- قبول الحالات الصحيحة: `published` + NULL، والنص الحقيقي.
- القيد نفسه تحت دور غير superuser (`PostgreSqlRlsActor`).
- up → down → up على قاعدة مؤقتة.
- إعادة `GradeReviewWeb*` و`ResultPublication*` و`ExamisNewTablesRls*` بلا انحدار.

**التراجع:** `migrate:rollback --step=1` يعيد التعريف القديم؛ لا بيانات تتأثر.
