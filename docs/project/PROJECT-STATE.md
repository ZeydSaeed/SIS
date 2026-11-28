# SIS — حالة المشروع (عامة)

> **تحديث 2026-11-28 ~17:30 (+03:00) — جلسة `5e7bb484` (المعلمون):** الفرع `V2`، HEAD `965ee7e`، الشجرة نظيفة عند البدء. جلسة `f3e0d7ae` كانت نشطة قبل 11 دقيقة من البدء، ولم تظهر لها تعديلات أثناء العمل.
> قاعدة `sis`:
> - migration `2026_11_28_110000` مطبّقة.
> - المعلمون: 91 مستوردًا من cyber_security (قراءة فقط منه)، بعد حذف 21 تجريبيًا عبر الحذف الآمن.
> - بنك الأسئلة ما زال معلّقًا.
> التفاصيل: `PROJECT-CHANGELOG.md` 2026-11-28، القرار PD-012. المتصفح `NOT RUN`.
> خطر جديد: PR-08 `employee_code` (الرقم التعريفي) نص صريح في SIS، وهو مشفّر في cyber_security.

> **تحديث 2026-11-27 ~19:50 (+03:00) — جلسة `f3e0d7ae` (المواد والمناهج):** الفرع `V2`، HEAD `5a76726`، لا commit. شجرة العمل عند البدء 21 مدخلًا (md5 `6ebbabda…`)؛
> بعدها أضافت جلسة متزامنة `1ab08ec2` (سحب وإفلات الشريط الجانبي) `use-nav-order.ts` و`nav-main.tsx` و`app.css` و`dashboard.tsx`، **وشغّلت `git stash` / `stash pop` مؤقتًا (18:49–18:51) للمقارنة** — استُعيدت الشجرة كاملة.
> ما نُفّذ وما اختُبر: `PROJECT-CHANGELOG.md` (2026-11-27). قاعدة `sis`: جداول المواد/المناهج/المعلمين فارغة، migration `2026_11_27_130000` مطبّقة، `2026_10_27_100000_create_bank_questions_and_versions` **معلّقة** (EXAMIS، لم تُطبّق عمدًا). القرارات: PD-005، PD-006.
> المخاطر الجديدة: PR-06 جلسات متزامنة تستعمل `git stash` على الشجرة المشتركة؛ PR-07 `/grades/sheet` و exam seating تفشل اختباراتها (مسارات غير موجودة في HEAD — عمل EXAMIS غير مكتمل).

**آخر تحديث:** 2026-10-25 18:21 (+03:00) · **كاتبه:** جلسة إعداد نظام الذاكرة (`c7b35a5e`) — وثائق فقط، لا تغيير في كود التطبيق.
كل بند مُتحقَّق منه بالأمر المذكور ما لم يُكتب `NOT VERIFIED`. حالة EXAMIS التفصيلية **ليست هنا**: `docs/examis/EXAMIS-PROJECT-STATE.md`.

## 1. المستودع (آخر حالة مؤكدة)

| البند | القيمة | الأمر |
|---|---|---|
| المسار | `D:\Projects\sis` | — |
| الفرع | `V2` (يتتبع `origin/V2`) | `git branch --show-current` |
| HEAD | `4472454` (2026-10-24) | `git log --oneline -1` |
| شجرة العمل | **55** مدخلًا عند بدء الجلسة (md5 `e5205390…`)، **59** عند إغلاقها 18:21 (md5 `8b8d6c8a…`) — كلها غير محفوظة في commit، والعدد يتغير ما دامت جلسة U-01 تعمل | `scripts/session-resume-check.ps1` |
| stash | فارغ | `git stash list` |

تصنيف أول 52 مدخلًا: `docs/examis/EXAMIS-LEGACY-REGISTER.md` §L-G. **الانحراف عن آخر توثيق (52 → 55):** انظر §3.

## 2. مسارات العمل

| المسار | الحالة | المرجع المعتمد |
|---|---|---|
| EXAMIS (امتحانات/درجات/نتائج) | المرحلة 7؛ U-00 `PASS WITH CONDITIONS`؛ **U-01 قيد التنفيذ** في جلسة أخرى (§3) | `docs/examis/` |
| الإجازات والغياب | مبني ومُعمَّل commit (`43d6edb` … `4f9cd60`)؛ بنود مفتوحة في الوثيقة | `docs/sis/leave/LEAVE-MODULE.md` |
| الجدول الدراسي (Workbench) | مُسلَّم 2026-10-09 | `docs/SIS-CHANGE-REPORT-2026-10-09.md`، `docs/timetable/` |
| بيانات العرض التجريبية | `sis:rebuild-demo-data` + `sis:audit-data-quality` | الذاكرة التلقائية `sis-demo-data-rebuild.md` |
| الحوكمة العامة | `AGENTS.md` يذكر «Next implementation step: Phase 2 Observability» — **قديم، NOT VERIFIED كأولوية حالية** | `AGENTS.md` |

## 3. انحراف مسجَّل (2026-10-25)

- **جلسة Claude أخرى نشطة** (`0905a823`، آخر نشاط 18:15) تنفّذ U-01 بموافقة المالك المؤرخة 2026-10-25 15:07Z (انظر [PROJECT-DECISIONS.md](PROJECT-DECISIONS.md) PD-101..103).
- ملفات عدّلها عمل U-01 بعد آخر توثيق EXAMIS (02:06)، بحسب وقت التعديل (18:12–18:15):
  `app/Http/Controllers/Results/ResultPublicationController.php` (جديد)، `resources/js/pages/results/publications.tsx` (جديد)،
  `app/Application/Results/Support/PublicationSnapshotBuilder.php`، `routes/web.php` (مسارات `results.publications.*`)،
  `resources/js/i18n/ar.ts`، `resources/js/i18n/en.ts`، `resources/js/pages/results/index.tsx`.
  (قائمة U-00 المحفوظة `sis-examis-lab/runs/2026-10-25-u00/changed.txt` فارغة، فلا يمكن تحديد أي 3 من هذه هي المدخلات الجديدة بدقة؛ المرجح: الـ controller و`PublicationSnapshotBuilder` و`en.ts`.)
- عند إغلاق جلسة الذاكرة (18:21): 59 مدخلًا = 55 + `publications.tsx` و`tests/.../ResultPublicationPagePostgreSqlTest.php` (U-01) + `docs/project/` و`scripts/session-resume-check.ps1` (جلسة الذاكرة).
- ملفات `docs/examis/*` **لم تُحدَّث بعد** بهذه الموافقة ولا بهذا العمل. مسؤولية تحديثها على الجلسة المنفِّذة؛ هذه الجلسة لم تلمسها لتجنّب تعارض الكتابة.

## 4. المخاطر

| ID | الخطر | المستوى |
|---|---|---|
| PR-01 | **`docs/examis/` (14 ملفًا) و`.claude/skills/` و`docs/project/` غير متتبعة في Git** — أي `git clean` أو عطل قرص يمحو الذاكرة الدائمة كلها | عالٍ |
| PR-02 | 55 مدخلًا غير محفوظ في commit (بينها حذف 9 controllers و14 صفحة) | متوسط (R-03 في EXAMIS) |
| PR-03 | جلستان تكتبان على شجرة العمل نفسها في وقت واحد، بلا قفل | متوسط |
| PR-04 | الذاكرة التلقائية `sis-examis-program.md` تحوي توجيهات تجاوزتها القرارات (مثل «لا تُعد بناء الصفحات») — صُحّحت بملاحظة في رأسها | منخفض |
| PR-05 | قاعدة `sis` الحية: آخر حالة مطبَّقة للـ migrations `NOT VERIFIED` من هذه الجلسة | — |
