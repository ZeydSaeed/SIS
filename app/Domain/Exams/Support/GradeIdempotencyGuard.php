<?php

namespace App\Domain\Exams\Support;

use App\Domain\Exams\Exceptions\GradeIdempotencyConflictException;
use App\Domain\Exams\Exceptions\MissingGradeIdempotencyKeyException;

/**
 * Phase 7.3-U01 — HD-7.3-001 = A / P7-D5: grade mutating commands require non-empty idempotency keys.
 *
 * A key identifies one request: its canonical payload is fingerprinted and stored with the result, and a replay with
 * the same key but a different payload is refused (same pattern as ExamIdempotencyGuard, DR-006).
 */
final class GradeIdempotencyGuard
{
    public const FINGERPRINT = 'request_fingerprint';

    public static function requireKey(?string $idempotencyKey): string
    {
        if ($idempotencyKey === null || trim($idempotencyKey) === '') {
            throw MissingGradeIdempotencyKeyException::required();
        }

        return $idempotencyKey;
    }

    /**
     * @param  array<string, scalar|null>  $canonicalPayload
     */
    public static function fingerprint(string $command, array $canonicalPayload): string
    {
        ksort($canonicalPayload);

        return hash('sha256', json_encode(['command' => $command, 'payload' => $canonicalPayload], JSON_THROW_ON_ERROR));
    }

    /** A score as a canonical 2-decimal string, so «88.5» and «88.50» are the same request; null stays null. */
    public static function score(?string $score): ?string
    {
        return $score === null || ! is_numeric($score) ? $score : number_format((float) $score, 2, '.', '');
    }

    /**
     * Replay only the same request. Entries stored before fingerprints existed carry none and still replay.
     *
     * @param  array<string, mixed>  $cached
     */
    public static function assertSameRequest(array $cached, string $fingerprint, string $command): void
    {
        $stored = $cached[self::FINGERPRINT] ?? null;
        if (is_string($stored) && ! hash_equals($stored, $fingerprint)) {
            throw GradeIdempotencyConflictException::payloadMismatch($command);
        }
    }
}
