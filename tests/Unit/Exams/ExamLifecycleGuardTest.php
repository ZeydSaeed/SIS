<?php

namespace Tests\Unit\Exams;

use App\Domain\Exams\Data\ExamSnapshot;
use App\Domain\Exams\Exceptions\ExamCancelBlockedException;
use App\Domain\Exams\Exceptions\ExamCompletionBlockedException;
use App\Domain\Exams\Exceptions\ExamUpdateForbiddenException;
use App\Domain\Exams\Support\ExamCancelGuard;
use App\Domain\Exams\Support\ExamUpdateGuard;
use App\Domain\Exams\ValueObjects\ExamStatus;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ExamLifecycleGuardTest extends TestCase
{
    #[Test]
    public function draft_allows_name_and_type_updates(): void
    {
        ExamUpdateGuard::assertMetadataAllowed($this->exam(ExamStatus::Draft), [
            'name' => 'N',
            'exam_type_id' => 2,
            'term_id' => 3,
        ]);
        $this->assertTrue(true);
    }

    #[Test]
    public function scheduled_rejects_exam_type_mutation(): void
    {
        $this->expectException(ExamUpdateForbiddenException::class);
        ExamUpdateGuard::assertMetadataAllowed($this->exam(ExamStatus::Scheduled), [
            'exam_type_id' => 2,
        ]);
    }

    #[Test]
    public function completion_fails_closed_with_zero_sessions(): void
    {
        $this->expectException(ExamCompletionBlockedException::class);
        ExamUpdateGuard::assertStatusTransition(
            ExamStatus::InProgress,
            ExamStatus::Completed,
            ['total' => 0, 'scheduled' => 0, 'in_progress' => 0, 'completed' => 0, 'cancelled' => 0],
        );
    }

    #[Test]
    public function cancel_blocked_when_current_grade_exists(): void
    {
        $this->expectException(ExamCancelBlockedException::class);
        ExamCancelGuard::assertCancellable($this->exam(ExamStatus::Scheduled), true, false);
    }

    #[Test]
    public function update_cannot_target_cancelled(): void
    {
        $this->expectException(ExamUpdateForbiddenException::class);
        ExamUpdateGuard::assertStatusTransition(
            ExamStatus::Draft,
            ExamStatus::Cancelled,
            ExamUpdateGuard::emptySessionCounts(),
        );
    }

    private function exam(ExamStatus $status): ExamSnapshot
    {
        return new ExamSnapshot(1, 10, 20, 30, 40, 'Exam', '2026-11-01', '2026-11-15', $status->value);
    }
}
