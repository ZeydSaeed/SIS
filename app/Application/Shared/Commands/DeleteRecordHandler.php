<?php

namespace App\Application\Shared\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\SafeDeleter;
use App\Application\Shared\Results\DeleteRecordResult;

final class DeleteRecordHandler implements CommandHandler
{
    public function __construct(private readonly SafeDeleter $deleter) {}

    public function handle(Command $command): DeleteRecordResult
    {
        assert($command instanceof DeleteRecordCommand);
        if (! in_array($command->entity, $this->deleter->entities(), true)) {
            return DeleteRecordResult::refused('delete.not_supported');
        }

        $outcome = $command->ids !== []
            ? $this->deleter->deleteMany($command->entity, $command->schoolId, array_values(array_map('intval', $command->ids)))
            : $this->deleter->delete($command->entity, $command->schoolId, $command->id);

        return match ($outcome['status']) {
            'deleted' => DeleteRecordResult::deleted($command->id),
            'not_found' => DeleteRecordResult::refused('delete.not_found'),
            // The UI names what still uses the record (e.g. «الدرجات») and offers «تعطيل» instead.
            'linked' => DeleteRecordResult::refused('delete.linked?with='.rawurlencode(self::label($outcome['linked_to'] ?? ''))),
        };
    }

    /** Arabic name of the data that still references the record (by referencing table). */
    private static function label(string $table): string
    {
        $table = strtolower(preg_replace('/^.*\./', '', $table) ?? $table);
        $table = preg_replace('/_ay_\d+$|_default$/', '', $table) ?? $table;

        return match (true) {
            $table === 'other_school' => 'مدرسة أخرى ما زال يعمل فيها',
            $table === 'current_school' => 'المدرسة التي تعمل عليها الآن — بدّل إلى مدرسة أخرى أولاً',
            $table === 'schools' => 'المدارس',
            $table === 'user_roles' => 'صلاحيات المستخدمين',
            in_array($table, ['applications', 'application_periods', 'application_transfers'], true) => 'القبول',
            $table === 'exam_enrollments' => 'توزيع الطلاب على مقاعد الامتحان',
            in_array($table, ['exam_sessions', 'exams', 'exam_types'], true) => 'الامتحانات',
            $table === 'policies' => 'سياسات الإجازات',
            in_array($table, ['workshops', 'workshop_equipment', 'specializations', 'tracks'], true) => 'التعليم المهني',
            in_array($table, ['availability', 'divisions', 'division_groups', 'activities', 'periods', 'configs', 'versions'], true) => 'الجدول الدراسي',
            $table === 'daily_section_summary' => 'سجلات الحضور',
            $table === 'result_publications' => 'النتائج',
            in_array($table, ['holidays', 'system_settings'], true) => 'إعدادات المدرسة',
            $table === 'enrollments' => 'التسجيل في الصفوف',
            str_starts_with($table, 'student_grades'), $table === 'grade_reviews' => 'الدرجات',
            in_array($table, ['term_results', 'annual_results', 'gpa_results', 'transcripts', 'ranking_snapshot_entries'], true) => 'النتائج',
            in_array($table, ['records', 'sessions', 'staff_records', 'excuses', 'sheet_events'], true) => 'سجلات الحضور',
            in_array($table, ['schedules', 'version_entries', 'activity_teachers', 'activity_sections', 'schedule_exceptions', 'constraint_rules', 'group_members'], true) => 'الجدول الدراسي',
            in_array($table, ['transactions', 'student_fees', 'payments'], true) => 'السجلات المالية',
            str_starts_with($table, 'certificate') => 'الشهادات',
            str_starts_with($table, 'transfer') => 'النقل',
            in_array($table, ['completion_outcomes', 'graduation_awards', 'graduation_approvals'], true) => 'التخرج',
            $table === 'exam_enrollments' => 'الامتحانات',
            in_array($table, ['requests', 'balance_entries'], true) => 'الإجازات',
            in_array($table, ['curriculum_subjects', 'specialization_subjects'], true) => 'المناهج',
            in_array($table, ['curricula'], true) => 'المناهج',
            in_array($table, ['teaching_assignments', 'teacher_subjects'], true) => 'إسناد التدريس',
            in_array($table, ['sections', 'classes'], true) => 'الصفوف والشعب',
            in_array($table, ['rooms'], true) => 'الغرف',
            in_array($table, ['students'], true) => 'الطلاب',
            $table === 'departments' => 'الاختصاصات',
            $table === 'branches' => 'الفروع',
            default => 'بيانات أخرى في النظام',
        };
    }
}
