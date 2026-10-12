<?php

namespace App\Application\Attendance\Support;

use App\Domain\Attendance\Services\AttendanceSheetWorkflow;
use App\Domain\Attendance\ValueObjects\LessonWindow;
use App\Domain\Attendance\ValueObjects\SessionStatus;
use App\Domain\Attendance\ValueObjects\SheetStatus;

/** UI hints for a sheet (what the viewer may do now). The command handlers re-check everything. */
final class SheetPermissions
{
    /**
     * @param  array<string, mixed>  $header
     * @return array<string, mixed>
     */
    public static function for(SheetActor $actor, bool $canCorrect, array $header, LessonWindow $window): array
    {
        $sheet = SheetStatus::from((int) $header['sheet_status']);
        $teaches = $actor->teacherId !== null && in_array($actor->teacherId, [(int) $header['teacher_id'], $header['co_teacher_id']], true);
        $open = (int) $header['status'] === SessionStatus::Open->value && $sheet->allowsTeacherEditing();
        $editable = $open && ($actor->isReviewer || ($teaches && $window->teacherMayWork()));
        $reviewActions = $actor->isReviewer
            ? array_values(array_filter(AttendanceSheetWorkflow::availableActions($sheet), static fn (string $a): bool => $a !== 'submit'))
            : [];

        return [
            'can_edit' => $editable,
            'can_submit' => $editable,
            'can_mark_excused' => $actor->isReviewer,
            'review_actions' => $reviewActions,
            'can_correct' => $canCorrect && (int) $header['status'] !== SessionStatus::Cancelled->value,
            'acting_on_behalf' => $editable && ! $teaches,
        ];
    }
}
