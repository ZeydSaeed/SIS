# SIS — سجل التغييرات المهمة (عام)

سجل موجز بالأحدث أولًا. تاريخ الكود الكامل في `git log`؛ تغييرات EXAMIS في `docs/examis/EXAMIS-CHANGELOG.md`؛ تقارير الوحدات في `docs/SIS-CHANGE-REPORT-*.md`.
يُسجَّل ما نُفّذ فعلًا فقط، مع نتيجة الاختبار أو `NOT RUN`.

## 2026-10-25 — نظام الذاكرة الدائمة والاستئناف

- **وثائق فقط. لا تغيير في كود التطبيق ولا قاعدة بيانات ولا `docs/examis/`.**
- أُنشئ `docs/project/`: MASTER-CONTEXT، STATE، DECISIONS، NEXT-SESSION، CHANGELOG.
- أُضيف قسم `SESSION MEMORY PROTOCOL` إلى `CLAUDE.md` (إضافة فقط).
- أُضيف `scripts/session-resume-check.ps1` (قراءة فقط: Git + الجلسات النشطة + رؤوس ملفات الاستئناف).
- الذاكرة التلقائية: ملاحظة «متجاوَز» في رأس `sis-examis-program.md`؛ ذاكرة جديدة `sis-session-memory-system.md`.
- `D:\Projects\sis-examis-lab\README.md`: فقرة «الحالة» (المختبر بلا حالة خاصة).
- سُجّل انحراف Git (52 → 55) وجلسة U-01 النشطة في PROJECT-STATE §3، وموافقات 2026-10-25 في PROJECT-DECISIONS §3.
- الاختبار: تشغيل السكربت + محاكاة استئناف موثقة (PROJECT-NEXT-SESSION §6). لا اختبارات PHP/JS (لا كود تغيّر).

## قبل 2026-10-25 (من Git، للمرجع)

| الفترة | الأبرز | commits |
|---|---|---|
| 2026-10-22 → 24 | EXAMIS U1–U15، النشر، المراجعات، أوزان الأنواع | `bc4de38` … `4472454` |
| 2026-10-19 → 21 | وحدة الإجازات والعطل، ثيم الجداول | `43d6edb` … `25c93b7` |
| 2026-10-16 → 17 | متابعة القبول، e2e، load-test | `59f992a` … `920b167` |
| 2026-10-08 → 09 | Timetable Workbench | `docs/SIS-CHANGE-REPORT-2026-10-09.md` |
