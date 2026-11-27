<?php

use App\Database\SafeDeleteTriggerBypass;
use App\Database\SchemaHelper;
use Illuminate\Database\Migrations\Migration;

/**
 * «حذف آمن» batch 1 — students and teachers (and the rows they own). The no-delete triggers of these tables
 * accept a DELETE only from PostgresSafeDeleter (sis.safe_delete = on); foreign keys still refuse it when other
 * data references the record.
 */
return new class extends Migration
{
    private const TABLES = [
        'students.students',
        'students.student_documents',
        'enrollment.enrollments',
        'enrollment.enrollment_subjects',
        'teachers.teachers',
        'teachers.teacher_schools',
        'teachers.teacher_qualifications',
        'teachers.teaching_assignments',
        'timetable.availability',
    ];

    public function up(): void
    {
        if (SchemaHelper::isPostgreSql()) {
            SafeDeleteTriggerBypass::install(self::TABLES);
        }
    }

    public function down(): void
    {
        if (SchemaHelper::isPostgreSql()) {
            SafeDeleteTriggerBypass::uninstall(self::TABLES);
        }
    }
};
