<?php

namespace App\Domain\Graduation\Support;

use App\Domain\Graduation\Exceptions\IdempotencyPayloadConflictException;

/**
 * Phase 3C.12 — Graduation idempotency fingerprint (F-11A-003 binding).
 * Reuses audit.idempotency_keys; stores request_fingerprint inside response_payload JSON.
 */
final class GraduationIdempotencyGuard
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
