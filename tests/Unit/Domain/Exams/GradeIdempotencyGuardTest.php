<?php

namespace Tests\Unit\Domain\Exams;

use App\Domain\Exams\Exceptions\GradeIdempotencyConflictException;
use App\Domain\Exams\Support\GradeIdempotencyGuard;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class GradeIdempotencyGuardTest extends TestCase
{
    #[Test]
    public function the_fingerprint_ignores_key_order_and_score_formatting(): void
    {
        $a = GradeIdempotencyGuard::fingerprint('EnterStudentGrade', ['score' => GradeIdempotencyGuard::score('88.5'), 'seat' => 7]);
        $b = GradeIdempotencyGuard::fingerprint('EnterStudentGrade', ['seat' => 7, 'score' => GradeIdempotencyGuard::score('88.50')]);

        $this->assertSame($a, $b);
        $this->assertNotSame($a, GradeIdempotencyGuard::fingerprint('EnterStudentGrade', ['seat' => 7, 'score' => GradeIdempotencyGuard::score('88.51')]));
        $this->assertNotSame($a, GradeIdempotencyGuard::fingerprint('CorrectStudentGrade', ['seat' => 7, 'score' => GradeIdempotencyGuard::score('88.5')]));
        $this->assertNull(GradeIdempotencyGuard::score(null));
    }

    #[Test]
    public function a_replay_must_be_the_same_request(): void
    {
        $fingerprint = GradeIdempotencyGuard::fingerprint('VoidStudentGrade', ['grade_id' => 1, 'reason' => 'x']);

        GradeIdempotencyGuard::assertSameRequest([GradeIdempotencyGuard::FINGERPRINT => $fingerprint], $fingerprint, 'VoidStudentGrade');
        // Entries stored before fingerprints existed still replay.
        GradeIdempotencyGuard::assertSameRequest(['grade_id' => 1], $fingerprint, 'VoidStudentGrade');

        try {
            GradeIdempotencyGuard::assertSameRequest([GradeIdempotencyGuard::FINGERPRINT => str_repeat('0', 64)], $fingerprint, 'VoidStudentGrade');
            $this->fail('a different request was replayed');
        } catch (GradeIdempotencyConflictException $e) {
            $this->assertSame('grades.idempotency_payload_conflict', $e->errorCode());
        }
    }
}
