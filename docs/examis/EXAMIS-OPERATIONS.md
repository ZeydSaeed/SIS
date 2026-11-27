# EXAMIS — التشغيل الآمن، المختبر، والأدلة

لا أسرار في هذا الملف. التفاصيل التشغيلية السابقة: `docs/sis/exams/SIS-EXAM-OPERATIONS.md`.

## 1. أوامر آمنة (قراءة فقط أو بلا أثر على البيانات)

`git status` / `git diff` / `git log` · `npm run types:check` · `npm run build` (يكتب `public/build` فقط) ·
`php artisan architecture:feature-check {Context}` · `php artisan route:list` · قراءة الملفات وgrep.

## 2. أوامر ممنوعة دون موافقة منفصلة صريحة

على أي قاعدة غير مؤقتة مثبتة: `migrate`، `migrate:fresh`، `migrate:rollback`، `db:wipe`، `db:seed`، `sis:rebuild-demo-data`، أي truncate/delete،
أوامر `results/*/rebuild` أو `calculate` أو `finalize` على `sis`. وعلى SurveyKing: أي تشغيل أو تعديل أو تثبيت.
Git: `reset --hard`، `checkout -- .`، `clean`، `push --force`، commit/push دون طلب.

## 3. اختبارات الكتابة — الإجراء المعتمد (DEC-013 / DEC-023، ACCEPTED، مُجرَّب في T-0003..T-0005)

1. اختر اسمًا جديدًا: `sis_test_examis_<yyyymmdd>_<n>` (لا `sis`، ولا `sis_test` المشترك لأن جلسات أخرى تستعمله).
2. من `D:\Projects\sis`: `php D:/Projects/sis-examis-lab/scripts/examis_db_probe.php create <name>` ثم `probe <name>`؛
   يجب أن يطبع `current_database` = الاسم، `tables_in_db` = 0، `guard_is_protected_sis` = true، `guard_allows_destructive_sis` = false. وإلا: توقف (`BLOCKED`).
   سلسلة الحماية في الكود: `TestCase::beforeRefreshingDatabase()` و`PostgreSqlIntegrationTestCase::refreshTestDatabase()` يستدعيان
   `ProtectedDatabaseGuard::assertSafeForDestructiveOperations()` **قبل** `dropSchemas()` و`migrate:fresh`؛ و`AppServiceProvider` يحرس أوامر artisan الهدامة ويفعّل `DB::prohibitDestructiveCommands` على القاعدة المحمية.
3. شغّل مع التجاوزات الثلاثة:
   `DB_DATABASE=<x> SIS_PGSQL_TEST_DATABASE=<x> SIS_DESTRUCTIVE_ALLOWED_DATABASES=<x> php -d memory_limit=2G vendor/bin/phpunit -c phpunit.database-pgsql.xml --filter "<pattern>"`
4. دليل العزل في سجل الاختبار: اسم القاعدة، وأن `SIS_DESTRUCTIVE_ALLOWED_DATABASES` لا يحوي `sis`.
5. احذف القاعدة بعد التشغيل وسجّل ذلك في `EXAMIS-TEST-REGISTER.md`.
6. اختبارات RLS الحقيقية عبر `PostgreSqlRlsActor::become()` (الاتصال الافتراضي superuser).
7. `tests/Feature/Exams` يحتاج قاعدة **جديدة** لم يرحّلها الـ pg suite.

## 4. المختبر `D:\Projects\sis-examis-lab` (أُنشئ في الجلسة 2: `README.md` + `scripts/examis_db_probe.php`، `summarize.py`، `detail.py`)

- **المقترح:** لا نسخة ثانية من SIS (مكلفة، وخطر خلط `.env` والأسرار). المختبر يحتوي فقط:
  `README.md` (الغرض والقواعد)، `scripts/` (إنشاء/حذف قاعدة مؤقتة، تشغيل مرشح اختبارات)، `fixtures/` (بيانات اصطناعية فقط إن لزم).
- يُنشأ فقط بعد موافقة DEC-P05. لا `.env`، لا dumps، لا `sis_backup.sql`.
- تشغيل SurveyKing معزولًا (إن احتجنا لفهم الواجهة): نسخة من `run/app` إلى مجلد داخل المختبر مع H2 جديدة ومنفذ مختلف — **يحتاج موافقة**، ولن يمس `D:\Projects\SurveyKing`.

## 5. خطة مجلد الأدلة `D:\Projects\sis-examis-evidence` (لم تُنفذ)

```text
sis-examis-evidence/
  00-environment/     تقارير المرحلة 0 (بلا أسرار)
  01-surveyking/      نسخة من EXAMIS-SURVEYKING-ANALYSIS.md + لقطات واجهة إن شُغّل معزولًا
  02-sis-audit/       مخرجات grep/route:list المختصرة
  tests/<date>/       ملخصات JSON لتشغيلات phpunit (بلا بيانات طلاب)
  screenshots/<date>/ لقطات متصفح لبيانات اصطناعية فقط
  readiness/          التقرير النهائي
```
الوثائق الحاكمة تبقى في `docs/examis/` حتى لا يعتمد الاستئناف على المجلد الخارجي.

## 6. الاستعادة

- الصفحات المُرجَعة: `git restore --source=HEAD -- <path>` أو `D:\Projects\sis_backup_examis_pages_2026-10-24\` — فقط بقرار DEC-P01.
- migrations EXAMIS الجديدة لها `down()` يرفض إن وُجدت سجلات (النشر) — لا تُرجع على `sis` دون موافقة.
