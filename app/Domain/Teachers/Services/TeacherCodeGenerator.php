<?php

namespace App\Domain\Teachers\Services;

/**
 * «الرقم التعريفي» made by the application (owner, 2026-11-28 — the same shape as the cyber_security
 * teachers' codes): XXXX-XXXX, always starting with T, the other seven characters drawn from an alphabet
 * without look-alikes (no I, L, O, 0, 1). 31^7 ≈ 2.8 × 10^10 codes.
 */
final class TeacherCodeGenerator
{
    public const ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    public const MARK = 'T';

    public const LENGTH = 8;

    public function generate(): string
    {
        $code = self::MARK;
        $last = strlen(self::ALPHABET) - 1;
        for ($i = 1; $i < self::LENGTH; $i++) {
            $code .= self::ALPHABET[random_int(0, $last)];
        }

        return substr($code, 0, 4).'-'.substr($code, 4);
    }
}
