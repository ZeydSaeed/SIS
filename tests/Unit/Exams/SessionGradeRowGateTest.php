<?php

namespace Tests\Unit\Exams;

use App\Application\Exams\Support\SessionGradeRowGate;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SessionGradeRowGateTest extends TestCase
{
    #[Test]
    public function allowed_eligible_first_occurrence_proceeds(): void
    {
        $this->assertNull(SessionGradeRowGate::rejection(5, [5 => true], [5 => true], []));
    }

    #[Test]
    public function rejections_in_order_duplicate_then_session_then_scope(): void
    {
        $this->assertSame(SessionGradeRowGate::DUPLICATE, SessionGradeRowGate::rejection(5, [], [], [5 => true]));
        $this->assertSame(SessionGradeRowGate::NOT_IN_SESSION, SessionGradeRowGate::rejection(5, [6 => true], [5 => true], []));
        $this->assertSame(SessionGradeRowGate::FORBIDDEN, SessionGradeRowGate::rejection(5, [5 => true], [6 => true], []));
    }

    #[Test]
    public function a_covered_seat_outside_the_session_is_still_not_in_session(): void
    {
        // Scope alone never admits a seat: it must also be an active seat of the posted session.
        $this->assertSame(SessionGradeRowGate::NOT_IN_SESSION, SessionGradeRowGate::rejection(9, [5 => true], [9 => true], []));
    }
}
