<?php

namespace App\Domain\Bank\Support;

use App\Domain\Bank\Exceptions\QuestionBankException;

/**
 * Question bank commands require a non-empty idempotency key; the request is fingerprinted and stored with the result,
 * and the same key with a different request is refused, never replayed as the first one (lesson of R-07).
 */
final class QuestionIdempotencyGuard
{
    public const FINGERPRINT = 'request_fingerprint';

    public static function requireKey(?string $key): string
    {
        $trimmed = trim((string) $key);
        if ($trimmed === '') {
            throw QuestionBankException::keyRequired();
        }

        return $trimmed;
    }

    /** @param  array<string, mixed>  $canonicalPayload */
    public static function fingerprint(string $command, array $canonicalPayload): string
    {
        ksort($canonicalPayload);

        return hash('sha256', json_encode(['command' => $command, 'payload' => $canonicalPayload], JSON_THROW_ON_ERROR));
    }

    /** @param  array<string, mixed>  $cached */
    public static function assertSameRequest(array $cached, string $fingerprint, string $command): void
    {
        $stored = $cached[self::FINGERPRINT] ?? null;
        if (! is_string($stored) || ! hash_equals($stored, $fingerprint)) {
            throw QuestionBankException::keyConflict($command);
        }
    }
}
