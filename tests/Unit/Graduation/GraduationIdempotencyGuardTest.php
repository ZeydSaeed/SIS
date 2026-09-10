<?php

namespace Tests\Unit\Graduation;

use App\Domain\Graduation\Exceptions\IdempotencyPayloadConflictException;
use App\Domain\Graduation\Support\GraduationIdempotencyGuard;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class GraduationIdempotencyGuardTest extends TestCase
{
    #[Test]
    public function same_payload_produces_stable_fingerprint(): void
    {
        $a = GraduationIdempotencyGuard::fingerprint('Graduation.IssueAward', 10, ['enrollment_id' => 1]);
        $b = GraduationIdempotencyGuard::fingerprint('Graduation.IssueAward', 10, ['enrollment_id' => 1]);

        $this->assertSame($a, $b);
    }

    #[Test]
    public function different_payload_conflicts(): void
    {
        $fp = GraduationIdempotencyGuard::fingerprint('Graduation.IssueAward', 10, ['enrollment_id' => 1]);
        $cached = GraduationIdempotencyGuard::withFingerprint(['award_id' => 9], $fp, 10);

        GraduationIdempotencyGuard::assertFingerprintMatch($cached, $fp);

        $other = GraduationIdempotencyGuard::fingerprint('Graduation.IssueAward', 10, ['enrollment_id' => 2]);

        $this->expectException(IdempotencyPayloadConflictException::class);
        GraduationIdempotencyGuard::assertFingerprintMatch($cached, $other);
    }
}
