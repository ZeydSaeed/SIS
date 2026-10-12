<?php

namespace Tests\Unit\Attendance;

use App\Domain\Attendance\Data\RosterEntry;
use App\Domain\Attendance\Data\SheetMark;
use App\Domain\Attendance\Exceptions\AttendanceRuleViolation;
use App\Domain\Attendance\Exceptions\DuplicateStudentInPayloadException;
use App\Domain\Attendance\Services\AttendanceAlertRules;
use App\Domain\Attendance\Services\AttendanceSheetWorkflow;
use App\Domain\Attendance\Services\AttendanceStatistics;
use App\Domain\Attendance\Services\LessonAccessGuard;
use App\Domain\Attendance\Services\SheetMarksGuard;
use App\Domain\Attendance\Services\SheetTally;
use App\Domain\Attendance\ValueObjects\AttendancePolicy;
use App\Domain\Attendance\ValueObjects\AttendanceRecordStatus;
use App\Domain\Attendance\ValueObjects\LessonType;
use App\Domain\Attendance\ValueObjects\LessonWindow;
use App\Domain\Attendance\ValueObjects\SheetAction;
use App\Domain\Attendance\ValueObjects\SheetStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class AttendanceDomainRulesTest extends TestCase
{
    /**
     * @return iterable<string, array{SheetStatus, SheetAction, SheetStatus|null}>
     */
    public static function transitions(): iterable
    {
        yield 'draft submit' => [SheetStatus::Draft, SheetAction::Submit, SheetStatus::Submitted];
        yield 'returned resubmit' => [SheetStatus::Returned, SheetAction::Submit, SheetStatus::Submitted];
        yield 'submitted start review' => [SheetStatus::Submitted, SheetAction::StartReview, SheetStatus::UnderReview];
        yield 'submitted approve' => [SheetStatus::Submitted, SheetAction::Approve, SheetStatus::Approved];
        yield 'under review return' => [SheetStatus::UnderReview, SheetAction::Return, SheetStatus::Returned];
        yield 'under review reject' => [SheetStatus::UnderReview, SheetAction::Reject, SheetStatus::Rejected];
        yield 'draft cannot be approved' => [SheetStatus::Draft, SheetAction::Approve, null];
        yield 'approved cannot be approved again' => [SheetStatus::Approved, SheetAction::Approve, null];
        yield 'approved cannot be returned' => [SheetStatus::Approved, SheetAction::Return, null];
        yield 'submitted cannot be submitted again' => [SheetStatus::Submitted, SheetAction::Submit, null];
        yield 'rejected is final' => [SheetStatus::Rejected, SheetAction::Submit, null];
        yield 'under review cannot restart' => [SheetStatus::UnderReview, SheetAction::StartReview, null];
    }

    #[Test]
    #[DataProvider('transitions')]
    public function sheet_transitions_are_defined_only_in_the_workflow(SheetStatus $from, SheetAction $action, ?SheetStatus $to): void
    {
        if ($to === null) {
            $this->expectException(AttendanceRuleViolation::class);
        }

        $this->assertSame($to, AttendanceSheetWorkflow::next($from, $action));
    }

    #[Test]
    public function return_and_reject_require_a_reason(): void
    {
        $this->assertNull(AttendanceSheetWorkflow::assertReason(SheetAction::Approve, '  '));
        $this->assertSame('خطأ', AttendanceSheetWorkflow::assertReason(SheetAction::Return, ' خطأ '));
        $this->expectException(AttendanceRuleViolation::class);
        AttendanceSheetWorkflow::assertReason(SheetAction::Reject, '');
    }

    #[Test]
    public function editing_is_allowed_only_for_draft_and_returned(): void
    {
        $this->assertTrue(SheetStatus::Draft->allowsTeacherEditing());
        $this->assertTrue(SheetStatus::Returned->allowsTeacherEditing());
        foreach ([SheetStatus::Submitted, SheetStatus::UnderReview, SheetStatus::Approved, SheetStatus::Rejected] as $status) {
            $this->assertFalse($status->allowsTeacherEditing(), $status->name);
        }
        $this->assertSame(['start_review', 'approve', 'return', 'reject'], AttendanceSheetWorkflow::availableActions(SheetStatus::Submitted));
    }

    #[Test]
    public function lesson_window_follows_the_policy_in_school_time(): void
    {
        $policy = new AttendancePolicy(null, 'Asia/Baghdad', openBeforeMinutes: 10, submitDeadlineMinutes: 60, editWindowDays: 1);
        $at = static fn (string $utc): \DateTimeImmutable => new \DateTimeImmutable($utc, new \DateTimeZone('UTC'));

        // Lesson 08:00–08:45 local (UTC+3).
        $this->assertSame(LessonWindow::NotYetOpen, $policy->windowFor('2026-10-15', '08:00', '08:45', $at('2026-10-15 04:49:00')));
        $this->assertSame(LessonWindow::Open, $policy->windowFor('2026-10-15', '08:00', '08:45', $at('2026-10-15 04:50:00')));
        $this->assertSame(LessonWindow::Open, $policy->windowFor('2026-10-15', '08:00', '08:45', $at('2026-10-15 06:45:00')));
        $this->assertSame(LessonWindow::LateSubmission, $policy->windowFor('2026-10-15', '08:00', '08:45', $at('2026-10-15 06:46:00')));
        $this->assertSame(LessonWindow::LateSubmission, $policy->windowFor('2026-10-15', '08:00', '08:45', $at('2026-10-16 20:00:00')));
        $this->assertSame(LessonWindow::Closed, $policy->windowFor('2026-10-15', '08:00', '08:45', $at('2026-10-16 21:00:00')));
        $this->assertSame('2026-10-16', $policy->localDate($at('2026-10-15 22:30:00')));
    }

    #[Test]
    public function late_arrivals_are_classified_by_grace_and_absent_threshold(): void
    {
        $policy = new AttendancePolicy(null, 'UTC', lateGraceMinutes: 5, lateAsAbsentMinutes: 30);

        $this->assertSame(AttendanceRecordStatus::Present, $policy->classifyLate(5));
        $this->assertSame(AttendanceRecordStatus::Late, $policy->classifyLate(6));
        $this->assertSame(AttendanceRecordStatus::Late, $policy->classifyLate(29));
        $this->assertSame(AttendanceRecordStatus::Absent, $policy->classifyLate(30));
        $this->assertSame(AttendanceRecordStatus::Late, $policy->classifyLate(null));
        $this->assertSame(AttendanceRecordStatus::Late, (new AttendancePolicy(null, 'UTC'))->classifyLate(500));
    }

    #[Test]
    public function staff_lateness_counts_from_day_start_beyond_grace(): void
    {
        $policy = new AttendancePolicy(null, 'Asia/Baghdad', staffDayStart: '07:30', staffLateGraceMinutes: 10);

        $this->assertSame(0, $policy->staffMinutesLate(new \DateTimeImmutable('2026-10-15 04:40:00', new \DateTimeZone('UTC'))));
        $this->assertSame(0, $policy->staffMinutesLate(new \DateTimeImmutable('2026-10-15 04:25:00', new \DateTimeZone('UTC'))));
        $this->assertSame(11, $policy->staffMinutesLate(new \DateTimeImmutable('2026-10-15 04:41:00', new \DateTimeZone('UTC'))));
    }

    #[Test]
    public function marks_must_belong_to_the_roster_and_appear_once(): void
    {
        $roster = [new RosterEntry(10, 100), new RosterEntry(11, 101)];
        $policy = new AttendancePolicy(null, 'UTC');

        $result = SheetMarksGuard::normalize([new SheetMark(10, 3, 2), new SheetMark(11, 3, 15)], $roster, $policy, false);
        $this->assertSame(1, $result['normalized']);
        $this->assertSame(1, $result['marks'][0]['mark']->status);
        $this->assertNull($result['marks'][0]['mark']->minutesLate);
        $this->assertSame(3, $result['marks'][1]['mark']->status);
        $this->assertSame(15, $result['marks'][1]['mark']->minutesLate);
        $this->assertSame(101, $result['marks'][1]['student_id']);

        try {
            SheetMarksGuard::normalize([new SheetMark(99, 1)], $roster, $policy, false);
            $this->fail('foreign enrollment accepted');
        } catch (AttendanceRuleViolation $e) {
            $this->assertSame('attendance.student_not_in_roster', $e->errorCode());
        }

        $this->expectException(DuplicateStudentInPayloadException::class);
        SheetMarksGuard::normalize([new SheetMark(10, 1), new SheetMark(10, 2)], $roster, $policy, false);
    }

    #[Test]
    public function excused_status_needs_a_reviewer_or_the_policy(): void
    {
        $roster = [new RosterEntry(10, 100)];

        $this->assertSame(4, SheetMarksGuard::normalize([new SheetMark(10, 4)], $roster, new AttendancePolicy(null, 'UTC'), true)['marks'][0]['mark']->status);
        $this->assertSame(4, SheetMarksGuard::normalize([new SheetMark(10, 4)], $roster, new AttendancePolicy(null, 'UTC', teacherCanMarkExcused: true), false)['marks'][0]['mark']->status);
        $this->expectException(AttendanceRuleViolation::class);
        SheetMarksGuard::normalize([new SheetMark(10, 4)], $roster, new AttendancePolicy(null, 'UTC'), false);
    }

    #[Test]
    public function tally_counts_roster_students_without_a_decided_status_as_unmarked(): void
    {
        $roster = [new RosterEntry(1, 1), new RosterEntry(2, 2), new RosterEntry(3, 3), new RosterEntry(4, 4)];

        $this->assertSame(
            ['expected' => 4, 'present' => 1, 'absent' => 1, 'late' => 0, 'excused' => 0, 'left_early' => 0, 'unmarked' => 2],
            SheetTally::of($roster, [1 => 1, 2 => 2, 3 => 0, 99 => 1]),
        );
    }

    #[Test]
    public function statistics_use_one_formula(): void
    {
        $rates = AttendanceStatistics::rates(['present' => 6, 'late' => 1, 'left_early' => 1, 'absent' => 1, 'excused' => 1]);

        $this->assertSame(10, $rates['decided']);
        $this->assertSame(80.0, $rates['attendance_rate']);
        $this->assertSame(20.0, $rates['absence_rate']);
        $this->assertSame(10.0, $rates['unexcused_rate']);
        $this->assertNull(AttendanceStatistics::rates([])['attendance_rate']);
    }

    #[Test]
    public function alert_rules_compare_one_measure_with_one_threshold(): void
    {
        $policy = new AttendancePolicy(null, 'UTC', absenceAlertCount: 3, absenceRateAlertPercent: 20, lateAlertCount: 3, returnedAlertCount: 2);

        $student = AttendanceAlertRules::forStudents([
            ['student_id' => 7, 'absences' => 3, 'decided' => 10, 'lates' => 3, 'practical_absences' => 0],
            ['student_id' => 8, 'absences' => 1, 'decided' => 20, 'lates' => 0, 'practical_absences' => 0],
        ], $policy);
        $this->assertEqualsCanonicalizing(['student_absence_count', 'student_absence_rate', 'student_late_repeat'], array_map(static fn ($c) => $c->ruleCode, $student));

        $teachers = AttendanceAlertRules::forTeachers([['teacher_id' => 5, 'missing' => 1, 'returned' => 2, 'absent_lessons' => 0]], $policy);
        $this->assertEqualsCanonicalizing(['teacher_sheet_missing', 'teacher_sheet_returned_repeat'], array_map(static fn ($c) => $c->ruleCode, $teachers));

        $spikes = AttendanceAlertRules::forSectionSpikes([
            ['section_id' => 1, 'absent' => 6, 'total' => 20],
            ['section_id' => 2, 'absent' => 1, 'total' => 40],
            ['section_id' => 3, 'absent' => 1, 'total' => 40],
        ], $policy);
        $this->assertCount(1, $spikes);
        $this->assertSame(1, $spikes[0]->subjectId);
    }

    #[Test]
    public function only_the_lesson_teachers_or_a_reviewer_may_work_on_a_lesson(): void
    {
        LessonAccessGuard::assertCanWork(5, 5, null, false);
        LessonAccessGuard::assertCanWork(6, 5, 6, false);
        LessonAccessGuard::assertCanWork(null, 5, null, true);

        try {
            LessonAccessGuard::assertCanWork(null, 5, null, false);
            $this->fail('unlinked user accepted');
        } catch (AttendanceRuleViolation $e) {
            $this->assertSame('attendance.teacher_profile_missing', $e->errorCode());
        }
        $this->expectException(AttendanceRuleViolation::class);
        LessonAccessGuard::assertCanWork(7, 5, 6, false);
    }

    #[Test]
    public function record_status_semantics(): void
    {
        $this->assertFalse(AttendanceRecordStatus::isValid(0));
        $this->assertTrue(AttendanceRecordStatus::isStorable(0));
        $this->assertTrue(AttendanceRecordStatus::isValid(5));
        $this->assertFalse(AttendanceRecordStatus::isValid(6));
        $this->assertTrue(AttendanceRecordStatus::LeftEarly->attended());
        $this->assertTrue(AttendanceRecordStatus::Excused->isAbsence());
        $this->assertSame(LessonType::Practical, LessonType::infer(3, null));
        $this->assertSame(LessonType::Practical, LessonType::infer(1, 2));
        $this->assertSame(LessonType::Theory, LessonType::infer(1, 1));
    }
}
