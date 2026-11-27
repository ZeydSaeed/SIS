# SIS — سجل التغييرات المهمة (عام)

سجل موجز بالأحدث أولًا. تاريخ الكود الكامل في `git log`؛ تغييرات EXAMIS في `docs/examis/EXAMIS-CHANGELOG.md`؛ تقارير الوحدات في `docs/SIS-CHANGE-REPORT-*.md`.
يُسجَّل ما نُفّذ فعلًا فقط، مع نتيجة الاختبار أو `NOT RUN`.

## 2026-11-27 — المواد والمناهج: صفحتان، مكان المنهج، مدرسو المواد، الحذف المتسلسل (جلسة `f3e0d7ae`)

- **قاعدة بيانات:** migration `2026_11_27_130000_curriculum_scope_and_subject_teachers` (إضافية، قابلة للتراجع — up/down/up نجحت على قاعدة مؤقتة):
  `curriculum.curricula` + `branch_id` / `class_id` / `section_id` (FK restrict)، CHECK الشعبة ⇒ الصف والفرع، trigger اتساق المكان، UNIQUE جزئي منهج فعّال واحد لكل (فرع، اختصاص، شعبة)؛
  `teachers.teaching_assignments` + `curriculum_subject_id` (FK restrict) + `role` (1 رئيسي، 2 مساعد)، UNIQUE جزئي مدرس رئيسي فعّال واحد لكل مادة منهج، trigger اتساق سطر المنهج. الـ blueprint و indexing-matrix محدّثان.
- **صفحتان مستقلتان:** «المواد» `/curriculum` و«المناهج» `/curriculum/curricula` (+ overview بطاقة لكل منهج مع «مكتمل / غير مكتمل») — عمل جلسة سابقة غير محفوظ، أُكمل هنا.
- **المنهج:** نموذج الإنشاء يطلب الفرع والاختصاص والصف والشعبة (`CreateCurriculumPageRequest`؛ API v1 بلا تغيير). صفحة المنهج: عمود «المدرسون» + «إسناد مدرس» (رئيسي / مساعد / تغيير الرئيسي) + «إلغاء الإسناد».
- **الحذف:** حذف المنهج = حذف مواده (الأسطر) وإسناد مدرسيها و«المواد المسندة» التي أعطاها وحده؛ حذف المادة المربوطة = تُحذف من المناهج أولًا (مع مدرسيها) بعد التنبيه. المواد نفسها تبقى عند حذف المنهج (انظر PD-006).
- **قاعدة `sis` الحية (بطلب المالك):** صُفّرت جداول المواد/المناهج (15 مادة، 2 منهج، 23 سطرًا) وأُعيدت العدادات إلى 1؛ طُبّقت الـ migration أعلاه وحدها (`--path`؛ `2026_10_27_100000_create_bank_questions_and_versions` تُركت معلّقة). فحص حي عبر مسارات الويب: 30 مادة، 5 مناهج، 31 سطرًا، 8 معلمين فحص، 31 رئيسيًا + 11 مساعدًا، كل المناهج «مكتمل»، ثم حُذف كل شيء بإجراءات الحذف نفسها. النتيجة: الجداول فارغة، حُذفت آثار الفحص (116 idempotency key `qa-*`، 74 رسالة outbox غير معالجة)، وبقي سجل التدقيق الأمني (186 صفًا).
- **الاختبارات:** `CurriculumScopeTeachersPostgreSqlTest` 5/5 جديدة، `CurriculumTeacherGuardTest` 3/3 جديدة؛ مجموعة Curriculum|Teacher|SafeDelete|… 205/211 (الفاشلة سابقة: GradeEntrySheet/ExamSeating مسارات غير موجودة في HEAD، GraduationWritePath معروف)؛ `architecture:validate --fitness` بلا مخالفة جديدة؛ `tsc` لا أخطاء في الملفات المعدّلة؛ `npm run build` نجح. المتصفح: NOT RUN.

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
