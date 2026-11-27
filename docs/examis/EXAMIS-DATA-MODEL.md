# EXAMIS — نموذج البيانات المفاهيمي

**المصدر:** أسماء الجداول مأخوذة من `database/migrations/*` (2026-10-24). لم يُفحص المخطط الحي على `sis` في هذه الجلسة — `NOT VERIFIED`.
المرجع التفصيلي للأعمدة: `.cursor/architecture/database-blueprint.md` و`database-dictionary.md`.

## 1. الجداول القائمة

```text
SIS (مالك الهوية)                      exams.*                                   results.*
school ─┬─ academic_year ─ term      exams.exam_types (لكل مدرسة)               results.term_results
        ├─ enrollment.enrollments ──┐ exams.exam_type_weight_versions (لكل سنة)   results.annual_results
        ├─ subjects                 │ exams.exams ─ exams.exam_sessions          results.gpa_results
        └─ classes / sections       └─ exams.exam_enrollments (المقعد)           results.ranking_snapshots
                                        └─ exams.student_grades (رسمي، مقسم      results.ranking_snapshot_entries
                                             بالسنة، is_current، VOID+INSERT)     results.transcripts
                                        exams.grade_reviews                       results.result_publications
```

| الجدول | المفتاح المفاهيمي | قاعدة جوهرية |
|---|---|---|
| `exams.exams` | مدرسة + سنة + مادة + نوع | حالات `ExamStatus`: Draft / Scheduled / InProgress / Completed / Cancelled |
| `exams.exam_sessions` | امتحان + موعد | فتح/إغلاق بانتقالات محروسة |
| `exams.exam_enrollments` | جلسة + تسجيل طالب (`enrollment_id`) | المقعد ≠ المحاولة؛ فهارس فريدة `(id, school_id)` |
| `exams.student_grades` | مقعد؛ صف حالي واحد (`WHERE is_current`) | `correction_of_grade_id` يربط السلسلة؛ `CHECK (status <> 5 OR is_current = false)`؛ trigger يمنع UPDATE |
| `exams.grade_reviews` | درجة | تتجمد بعد القرار؛ لا حذف |
| `exams.exam_type_weight_versions` | نوع + سنة + إصدار | insert-only؛ يُثبَّت في `policy_pin.weights` للنتيجة |
| `results.*_results` | تسجيل + فترة + إصدار | `source_fingerprint`، `policy_pin`؛ لا إعادة حساب صامتة |
| `results.result_publications` | نطاق (مدرسة، سنة، نوع term/annual/gpa/transcript، فصل، صف، شعبة اختياري) | نشر فعّال واحد لكل نطاق؛ السحب بسبب؛ لا حذف |
| `bank.questions` (7.3a، 2026-10-27) | مدرسة + مادة (**بلا سنة**، DEC-055) | `active` / `retired` (نهائي)؛ لا حذف؛ الهوية ثابتة |
| `bank.question_versions` (7.3a) | سؤال + رقم نسخة | `draft → in_review → approved\|rejected`، `approved → superseded`؛ المحتوى يتجمد بعد الإرسال؛ أربع عيون (CHECK)؛ نسخة مفتوحة واحدة ونسخة معتمدة واحدة لكل سؤال؛ المفتاح (`correct`/`answer`/`guidance`) لا يُرسل لمن يملك العرض فقط |

> **7.3a:** مخطط `bank` الأول (جدولان) بُني على قاعدة اختبار فقط (`sis_test_examis_20261027_bank`)؛ `sis` الحية **لم تُرحَّل**. باقي `bank.*` (rubrics، categories، assets، blueprints) و`exams.exam_forms` ما زالت مقترحات ADR-024.

## 2. مقابلة مفاهيم SurveyKing

| SurveyKing | SIS القائم | المقترح (ADR-024/025 — PROPOSED) |
|---|---|---|
| `t_project` (mode=exam) | `exams.exams` + `exam_sessions` | + `exam_forms` (نسخة مجمدة من أسئلة معتمدة) |
| `t_repo` / `t_template` | — | `bank.questions` + `bank.question_versions` |
| `t_answer` | المقعد `exam_enrollments` + الدرجة `student_grades` | `delivery.attempts` + `delivery.item_responses` + `delivery.item_marks` |
| `t_answer.survey` (لقطة) | — | المحاولة تشير إلى `exam_form_id` مجمّد |
| `t_answer.exam_score` (float) | `student_grades` (دقيق) | علامات المحاولة «مقترحات» تُكتب رسميًا عبر `EnterStudentGrade` فقط |
| `t_user_book` | — | خارج النطاق (F-31) |

## 3. قيود التصميم

- لا نسخ لبيانات الطالب أو المادة أو المدرسة داخل جداول EXAMIS؛ الإشارة بالمعرّف فقط.
- كل جدول جديد: `school_id` + RLS + FORCE RLS + إضافته إلى `SchemaHelper::schemas()` إن كان مخططًا جديدًا + تحديث `database-blueprint.md`.
- لا مخطط نهائي لـ `bank.*` / `delivery.*` قبل DEC-P03.
