<?php

namespace App\Application\Attendance\Support;

use App\Application\Attendance\Contracts\CheckinCodeHasherInterface;
use App\Domain\Attendance\Exceptions\AttendanceRuleViolation;
use App\Domain\Attendance\Repositories\StaffAttendanceRepositoryInterface;
use App\Domain\Attendance\ValueObjects\AttendancePolicy;
use App\Domain\Attendance\ValueObjects\EvidenceMethod;

/**
 * Proof of presence for a staff check-in: the school's current rotating code (typed or scanned from its QR) or an
 * NFC station token (only when the policy enables NFC). The evidence never approves anything by itself.
 */
final class CheckinVerifier
{
    public const VIA = ['code', 'qr', 'nfc'];

    public function __construct(
        private readonly StaffAttendanceRepositoryInterface $staff,
        private readonly CheckinCodeHasherInterface $hasher,
    ) {}

    public function verify(int $schoolId, string $code, string $via, AttendancePolicy $policy, \DateTimeImmutable $now): EvidenceMethod
    {
        [$kind, $method, $allowed] = match ($via) {
            'qr' => [1, EvidenceMethod::Qr, $policy->qrEnabled],
            'nfc' => [2, EvidenceMethod::Nfc, $policy->nfcEnabled],
            default => [1, EvidenceMethod::CheckinCode, true],
        };
        if (! $allowed) {
            throw AttendanceRuleViolation::evidenceDisabled($via);
        }

        $code = preg_replace('/\s+/', '', $code) ?? '';
        $hash = $code === '' ? '' : $this->hasher->hash($schoolId, $code);
        foreach ($this->staff->validCodeHashes($schoolId, $now, $kind) as $valid) {
            if ($hash !== '' && hash_equals($valid, $hash)) {
                return $method;
            }
        }

        throw AttendanceRuleViolation::checkinCodeInvalid();
    }
}
