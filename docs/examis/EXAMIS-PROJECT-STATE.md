# EXAMIS — حالة المشروع الحقيقية

**آخر تحديث:** 2026-10-27 16:25 · فحص الإغلاق T-0110..T-0123 + إكمال الواجهة T-0124..T-0133 منجزان (بوابة توقف) · **المرحلة الحالية:** 7 — U-00..U-03 وR-09 و7.2 منجزة (PASS). **7.3a بنك الأسئلة (الأسئلة فقط) = PASS WITH CONDITIONS** (T-0093..T-0109): الانحدار الكامل 0 فشل جديد في التطبيق، Playwright 12/12، `sis` الحية لم تُرحَّل. DEC-055..058 PROPOSED. التفاصيل: `EXAMIS-NEXT-SESSION.md`.
كل بند أدناه مُتحقَّق منه بالأمر/الملف المذكور ما لم يُكتب `NOT VERIFIED`.

## 1. البيئة (المرحلة 0 — PASS)

| البند | القيمة | الدليل |
|---|---|---|
| جذر SIS | `D:\Projects\sis` — مستودع Git، فرع `V2`، HEAD `4472454` | `git branch`، `git log` |
| SurveyKing | `D:\Projects\SurveyKing` — **ليس مستودع Git**؛ v1.13.0؛ MIT؛ للقراءة فقط | `git status` يفشل؛ `README.md`؛ `LICENSE` |
| المختبر | `D:\Projects\sis-examis-lab` — `README.md` + `scripts/` (probe وملخصات PHPUnit)؛ لا أسرار | الجلسة 2 |
| الأدلة | `D:\Projects\sis-examis-evidence` — فارغ (DEC-012) | `ls -la` |
| نسخة احتياطية سابقة | `D:\Projects\sis_backup_examis_pages_2026-10-24\` | `ls` |
| مجلدات أخرى | `D:\Projects\External_Link` (مشروع آخر، لم يُفحص)، `D:\Projects\sis_backup.sql` (**لم يُفتح**) | `ls` |
| عملية جارية | `java.exe` (على الأغلب SurveyKing من جلسة سابقة)؛ لم تُلمس | `tasklist` |
| `.env` | قراءته مرفوضة → قاعدة التطبيق الحية `NOT VERIFIED` من الملف | رفض الأداة |
| قاعدة الاختبار | `phpunit.database-pgsql.xml`: `sis_test`، `postgres`، `SIS_PROTECTED_DATABASES=sis`، `SIS_DESTRUCTIVE_ALLOWED_DATABASES=sis_test,:memory:` | قراءة الملف |

## 2. حالة Git

52 مدخلًا في `git status --short` = 49 (إرجاع الصفحات + تحسينات backend، ليست من جلسات البروتوكول) + 3 من الجلسة 1
(`CLAUDE.md`، `docs/examis/`، `.claude/skills/`). **التصنيف G1–G5 في `EXAMIS-LEGACY-REGISTER.md` L-G.** لم يُغيَّر أي ملف تطبيق؛ لا commit (DEC-021).

## 3. ما هو موجود في SIS الآن

| القدرة | الحالة | الدليل |
|---|---|---|
| امتحان → جلسة → تسجيل (seat) → درجة رسمية | backend + API | `routes/api.php` |
| مصدر الدرجة الرسمي `exams.student_grades` (`is_current`، VOID + INSERT، trigger `ENABLE ALWAYS`) | مؤكد في migration (DEC-025) | `2026_10_22_120000_*` |
| حساب/اعتماد/إعادة بناء نتائج الفصل والسنة والمعدل، الترتيب، كشف السجل | backend + API فقط | `ResultsWriteController` |
| بوابة الطالب (API): لا نتيجة رسمية دون نشر فعّال | **PASS** — T-0004 (`Phase78PortalResultsHttpApi` 7/7 لاختبارات API، `Phase78PortalDenyMatrix` 6/6) | `EXAMIS-TEST-REGISTER.md` |
| RLS للجداول الثلاثة الجديدة بدور غير superuser | **PASS** — T-0004 (3/3) | — |
| حارس القواعد المحمية | **PASS** — T-0004 (3/3) + T-0003 | — |
| 25 handler بلا نقطة استدعاء (من 73) | backend بلا واجهة | `EXAMIS-LEGACY-REGISTER.md` L-A8 |
| صفحات الويب | exams: index/show؛ grades: index/enter/actions (معرّفات خام)؛ results: index/show/term/transcript | `routes/web.php` 377–397 |
| بنك الأسئلة، الامتحان الإلكتروني، rubrics | مفقود — اتجاه ADR-024/025 معتمد مبدئيًا (DEC-022) | — |

## 4. R-01 — النتيجة

**مؤكد بالدليل:** حساب ✓ → اعتماد ✓ → **نشر ✗ / سحب ✗ (لا route ويب ولا API)** → بوابة API ✓ → صفحة الطالب ✗.
أي نتيجة تُعتمد الآن لا يمكن أن تصل للطالب. أصغر علاج: U-01 (مدخل نشر/سحب annual/gpa لصف) ثم U-02 (صفحة «نتائجي»، DEC-P06).
لم يُجرَ أي نشر فعلي ولم تُلمس بيانات الإنتاج (DEC-024، DEC-027).

## 5. الاختبارات

T-0001 (types: 20 خطأ، 9 أصلية في صفحات EXAMIS) · T-0003 (إثبات العزل) · T-0004 (93 اختبار: 27 نجح، 66 فشل/خطأ، كلها بسبب G1) · T-0005 (حذف القاعدة).
الحزمة الكاملة (TR-01) **لم تُشغَّل**.

## 6. قاعدة البيانات

`sis` لم تُتصل بها. أُنشئت `sis_test_examis_20261024_1` ورُحّلت بواسطة الاختبارات ثم حُذفت (T-0005).

## 7. البوابات

| المرحلة | الحالة | الشرط المتبقي |
|---|---|---|
| 0 | PASS | — |
| 1 | PASS WITH CONDITIONS | واجهة SurveyKing غير محللة (لا مصدر) |
| 2 | PASS WITH CONDITIONS | مخطط `sis` الحي غير مفحوص؛ L-F1 غير مجرّب |
| 3 | PASS WITH CONDITIONS | DEC-020..028 تحسم النطاق العام؛ عناصر v1 في المصفوفة تُعتمد وحدةً وحدة؛ DEC-P03/P04/P06 مفتوحة |
| 4 | NOT STARTED | يبدأ مع أول وحدة تحتاج سياسة (DEC-P04) |
| 5 | IN PROGRESS | العقد مسودة؛ يُثبَّت بالصفحة النموذجية الأولى (U-01) |
| 6 | PASS WITH CONDITIONS | العزل مثبت لكل تشغيل بـ probe؛ الاتصال superuser (R-02)؛ اختبارات المتصفح لم تُجهَّز بعد |
| 7 | NOT STARTED | U-00 أولًا |

## 8. المخاطر

R-01 (عالٍ، مؤكد) · R-02 superuser يتجاوز RLS (عالٍ) · R-03 52 مدخلًا غير محفوظ (متوسط) · R-06 handlers اليتيمة لا تُختبر إلا عبر الويب، فقد تنحرف دون أن يلاحظ أحد (متوسط) · L-F1 (منخفض).

## 8b. الجلسة 3 (U-00 جزئي — توقف بطلب المالك)

- الحارس والعزل: **PASS** (T-0006، T-0007). عقد الميزات Exams/Results: PASS (T-0018).
- فحوص ساكنة: TypeScript 20 خطأ قديم (T-0010)؛ PHPStan 2264 مستودعًا / 177 EXAMIS (T-0013، T-0014)؛ vp تنسيق 1190 ملفًا (T-0011).
- معماري: `DecideGradeReviewHandler` و`ReopenExamEnrollmentHandler` يتجاوزان حد التعقيد (T-0015) — بند EXAMIS جديد في السجل.
- BLOCKED: composer audit (شبكة)، Pint (مهلة)، Playwright (اسم القاعدة).
- **R1/R2 لم يكتملا** → بوابة U-00 لم تُقيَّم بعد. الاستئناف: `EXAMIS-NEXT-SESSION.md`.

## 8c. U-00 مكتمل (2026-10-25) — `PASS WITH CONDITIONS`

- R1 PostgreSQL كاملة: 796 · 695 نجح · 101 فشل/خطأ = **97 G1** (routes/props أزالها الإرجاع) + 4 خارج EXAMIS (قديمة/غير محسومة).
- R2 `tests/Feature/Exams`: **136/136**.
- R3 الافتراضية (SQLite): 1175 خطأ بيئي (migrations PostgreSQL) + 4 فشل (شبكة + 3 معمارية قديمة في Enrollment/Curriculum).
- **فشل منطقي في EXAMIS: 0.** بنود جودة مفتوحة: L-F2 (تعقيد handlerين)، L-F3 (PHPStan).
- البوابة: لا مانع تقني من U-01؛ **الانتقال ينتظر موافقة المالك**. التفاصيل: TEST-REGISTER §1d، ACCEPTANCE «نتيجة بوابة U-00».

## 8d. U-01 (2026-10-25) — `PASS WITH CONDITIONS`

- صفحة `/results/publications` + نشر/سحب annual/gpa لصف أو شعبة، بإعادة استخدام الـ handlers والطلبات والسياسة القائمة (DEC-032).
- اختبارات: Feature 11/11، Playwright 10/10 (ar/en، 390/820/1366)، PHPStan 0 للملفات، لا مخالفة معمارية جديدة، TS بلا أخطاء جديدة.
- R-01: مُغلق للموظفين؛ صفحة الطالب = U-02. مخاطر جديدة: R-07، R-08.
- `public/build` أُعيد لنسخته السابقة (DEC-033) → الصفحة لا تظهر على الموقع المحلي قبل `npm run build` (DEC-P08).
- **البوابة التالية:** موافقة المالك على U-02 (DEC-P07). التفاصيل: ACCEPTANCE «نتيجة U-01» و§U-02.

## 8e. R-07 / R-08 / build / U-02 (2026-10-25)

- R-07 وR-08: **مُغلقان** (T-0041؛ T-0048 بجلستين حقيقيتين).
- `public/build` = بناء جديد فيه صفحتا U-01 وU-02 (DEC-035) — **ظاهرتان الآن على الموقع المحلي المتصل بـ `sis`**.
- U-02 «نتائجي»: `PASS WITH CONDITIONS` — PHPUnit 37/37 في المجموعة المرتبطة، Playwright 12/12 (U-01 + U-02).
- R-01: مُغلق لـ annual/gpa من الطرفين؛ يبقى term/transcript من الويب.
- خطر جديد R-09 (الشريط الجانبي لمستخدم البوابة) — خارج EXAMIS.
- **البوابة التالية:** اختيار المالك للوحدة التالية (DEC-P09).

## 8f. U-03 (2026-10-25) — `PASS WITH CONDITIONS`

- نشر نتائج الفصول (`/results/term-publications`، فصل + مادة) وكشوف الدرجات (زر على `/results/publications`) بالعقود القائمة.
- PHPUnit: 63 في المجموعة المرتبطة، نجح 55، وفشل 8 = G1 المعروفة؛ U-03 12/12.
- R-01 مُغلق لكل الأنواع.
- **BLOCKED:** Playwright (يحتاج `npm run build`، DEC-P10).
- **بيئة:** القرص C: ممتلئ (DEC-P11 / R-10). القواعد على D: سليمة.
- `public/build` لم يتغير منذ U-02 (md5 في `runs/2026-10-25-u03/build-before.txt`)، **فصفحة الفصول وزر الكشوف غير ظاهرين على الموقع المحلي بعد**.

## 8g. U-03 متصفح + R-09 + 7.2 (2026-10-26/27)

- U-03: **PASS** (Playwright 20/20 بكل المشاريع).
- R-09: **PASS** (PHPUnit 24/24، Playwright 23/23).
- **7.2:** `PASS WITH CONDITIONS`:
  - PHPUnit 14/14 (منها 8 اختبارات قديمة عادت ناجحة).
  - Playwright 12/12 بكل المشاريع.
  - spec القديم 8/9 (الفشل = «كشف المرشحين» → 7.5).
  - ~~الشرط: انحدار PostgreSQL الكامل~~ → **اكتمل (T-0089..T-0091): 0 فشل جديد، 14 أُصلح، `tests/Feature/Exams` 136/136 → 7.2 = PASS**.
- **بوابة بنك الأسئلة (7.3):** DEC-P13 (أجوبة ADR-024) + DEC-P14 (تفويض migrations `bank`) — **محجوب**.
- `public/build`: آخر بناء يحوي 7.2 (DEC-040). النسخ الاحتياطية في `sis-examis-lab/build-backup-*`.

## 9. الخطوة التالية

U-00: خط أساس كامل على قاعدتين مؤقتتين (انظر `EXAMIS-ACCEPTANCE-CRITERIA.md`). ثم U-01. التفاصيل في `EXAMIS-NEXT-SESSION.md`.
