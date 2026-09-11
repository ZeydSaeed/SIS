<?php

namespace App\Domain\Exams\Support;

use App\Domain\Exams\Exceptions\IdempotencyPayloadConflictException;

/**
 * Phase 7.1 — Exam administration idempotency fingerprint (DR-006).
 * Reuses audit.idempotency_keys; stores request_fingerprint inside response_payload JSON.
 */
final class ExamIdempotencyGuard
{
    /**
     * @param  array<string, mixed>  $canonicalPayload
     */
    public static function fingerprint(string $commandName, int $schoolId, array $canonicalPayload): string
    {
        ksort($canonicalPayload);

        return hash('sha256', json_encode([
            'command' => $commandName,
            'school_id' => $schoolId,
            'payload' => $canonicalPayload,
        ], JSON_THROW_ON_ERROR));
    }

    /**
     * @param  array<string, mixed>  $cached
     */
    public static function assertFingerprintMatch(array $cached, string $expectedFingerprint): void
    {
        $stored = $cached['request_fingerprint'] ?? null;
        if (! is_string($stored) || ! hash_equals($stored, $expectedFingerprint)) {
            throw IdempotencyPayloadConflictException::mismatch();
        }
    }

    /**
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>
     */
    public static function withFingerprint(array $result, string $fingerprint, int $schoolId): array
    {
        return array_merge($result, [
            'request_fingerprint' => $fingerprint,
            'school_id' => $schoolId,
        ]);
    }
}
