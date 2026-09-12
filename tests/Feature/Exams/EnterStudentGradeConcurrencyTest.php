<?php

namespace Tests\Feature\Exams;

use App\Application\Exams\Commands\EnterStudentGradeCommand;
use App\Application\Exams\Commands\EnterStudentGradeHandler;
use App\Domain\Exams\Exceptions\CurrentGradeAlreadyExistsException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Exams\SeedsApplicationExamGradeGraph;
use Tests\TestCase;

class EnterStudentGradeConcurrencyTest extends TestCase
{
    use InteractsWithSecurity;
    use RefreshDatabase;
    use SeedsApplicationExamGradeGraph;

    #[Test]
    public function second_enter_without_idempotency_conflicts(): void
    {
        $schoolId = $this->createSchool('SCH-GC', 'School GC');
        $graph = $this->seedExamGradeGraph($schoolId, suffix: 'C');
        $this->markSessionInProgressForGradeEntry($graph['session_id']);
        $handler = app(EnterStudentGradeHandler::class);

        $handler->handle(new EnterStudentGradeCommand(
            schoolId: $schoolId,
            examEnrollmentId: $graph['exam_enrollment_id'],
            score: '70',
            isAbsent: false,
            enteredBy: null,
            idempotencyKey: 'gc-enter-1',
        ));

        $this->expectException(CurrentGradeAlreadyExistsException::class);
        $handler->handle(new EnterStudentGradeCommand(
            schoolId: $schoolId,
            examEnrollmentId: $graph['exam_enrollment_id'],
            score: '71',
            isAbsent: false,
            enteredBy: null,
            idempotencyKey: 'gc-enter-2',
        ));
    }
}
