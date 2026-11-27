# SIS — السياق الرئيسي للمشروع (خريطة الذاكرة الدائمة)

هذا الملف **خريطة** وليس مصدرًا ثانيًا للحقيقة: كل بند يشير إلى الوثيقة المعتمدة. عند التعارض، الوثيقة المعتمدة ثم حالة المستودع الفعلية هي المرجع.

## 1. المشروع

نظام معلومات طلاب مؤسسي (SIS). **التقنيات المعتمدة:** Laravel 13 + Inertia/React 19 + PostgreSQL + Redis (cache فقط).
المعمارية: Clean + DDD + CQRS — `.cursor/architecture/ARCHITECTURE-STACK.md`. أي إطار/runtime جديد يحتاج ADR وموافقة بشرية.

## 2. الثوابت التي لا تُخرق

مصدرها `CLAUDE.md` (Non-negotiables، Priority order، STOP) و`.cursor/architecture/SIS-CONSTITUTION.md`. أهمها للجلسات:

- لا كتابة على قاعدة `sis` الحية ولا بيانات إنتاج دون موافقة صريحة **لتلك العملية بعينها**.
- لا حذف نهائي للسجلات الأكاديمية؛ migrations فقط؛ عزل المستأجر وصلاحيات الخادم في كل عملية محمية.
- لا `git reset` / `clean` / `checkout --` / `stash` / commit دون طلب المالك (DEC-021 في EXAMIS، و`CLAUDE.md` §12).
- قراءة `.env` مرفوضة من نظام الصلاحيات — لا تحاول الالتفاف.

## 3. أين يعيش كل شيء

| المكان | ما هو | ملاحظة |
|---|---|---|
| `D:\Projects\sis` | **المستودع الحقيقي الوحيد** (Git، الفرع العامل `V2`) | كل الوثائق المعتمدة هنا |
| `D:\Projects\sis-examis-lab` | أدوات اختبار EXAMIS (scripts + `runs/`) | **ليس مستودع Git ولا نسخة من SIS**؛ لا حالة خاصة به — حالته جزء من EXAMIS (DEC-013) |
| `D:\Projects\sis-examis-evidence` | مجلد أدلة EXAMIS | فارغ عمدًا (DEC-012) |
| `D:\Projects\SurveyKing` | مرجع وظيفي لـ EXAMIS | للقراءة فقط، ليس Git (DEC-010) |
| `D:\Projects\sis_backup_examis_pages_2026-10-24\` | نسخة صفحات EXAMIS قبل الإرجاع | لا تُستعاد دون قرار |
| `D:\Projects\sis_backup.sql` | dump | **لم يُفتح**؛ قد يحوي بيانات حقيقية |
| `D:\Projects\External_Link` | مشروع آخر | خارج النطاق |

## 4. نظام الذاكرة — طبقتان

| الطبقة | المكان | ماذا يُحفظ فيها |
|---|---|---|
| **وثائق المشروع (المرجع)** | `docs/project/` (SIS عامة) و`docs/examis/` (برنامج EXAMIS) | الحالة، القرارات، الاستئناف، الاختبارات، التغييرات |
| الذاكرة التلقائية لـ Claude Code | `C:\Users\TWENTY F C\.claude\projects\d--Projects-sis\memory\` | تفضيلات المالك، تصحيحاته، دروس البيئة فقط — **ليست سجل قرارات ولا نتائج** |

ملفات SIS العامة: [PROJECT-STATE.md](PROJECT-STATE.md) · [PROJECT-NEXT-SESSION.md](PROJECT-NEXT-SESSION.md) · [PROJECT-DECISIONS.md](PROJECT-DECISIONS.md) · [PROJECT-CHANGELOG.md](PROJECT-CHANGELOG.md).

ملفات EXAMIS المكافئة (لا تُكرَّر هنا):

| الوظيفة | EXAMIS |
|---|---|
| السياق والخطة | `docs/examis/EXAMIS-MASTER-PLAN.md`، `EXAMIS-SECURITY-MODEL.md`، `EXAMIS-OPERATIONS.md` |
| الحالة | `docs/examis/EXAMIS-PROJECT-STATE.md` |
| القرارات | `docs/examis/EXAMIS-DECISIONS.md` |
| الاستئناف | `docs/examis/EXAMIS-NEXT-SESSION.md` |
| التغييرات | `docs/examis/EXAMIS-CHANGELOG.md` |
| الاختبارات | `docs/examis/EXAMIS-TEST-REGISTER.md` |
| الإجراء | `.claude/skills/examis-project-protocol/SKILL.md` (`/examis-project-protocol`) |

## 5. الوثائق المعمارية الرئيسية

`AGENTS.md` (فهرس الحوكمة) · `.cursor/architecture/SIS-CONSTITUTION.md` · `GOVERNANCE-MAP.md` · `ARCHITECTURE-STACK.md` ·
`database-blueprint.md` · `.cursor/architecture/adr/` · `.cursor/skills/*/SKILL.md` · وثائق الوحدات في `docs/sis/<module>/`.
