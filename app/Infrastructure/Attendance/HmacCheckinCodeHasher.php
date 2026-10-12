<?php

namespace App\Infrastructure\Attendance;

use App\Application\Attendance\Contracts\CheckinCodeHasherInterface;

/** HMAC-SHA256 keyed with the application key and scoped by school — a stolen hash cannot be reversed or reused elsewhere. */
final class HmacCheckinCodeHasher implements CheckinCodeHasherInterface
{
    public function __construct(
        private readonly string $key,
    ) {}

    public function hash(int $schoolId, string $code): string
    {
        return hash_hmac('sha256', $schoolId.':'.$code, $this->key);
    }

    public function generate(): string
    {
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }
}
