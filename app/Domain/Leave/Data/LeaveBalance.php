<?php

namespace App\Domain\Leave\Data;

/**
 * One person's balance of one leave type in one year, from the ledger: granted + adjusted − used + restored, minus
 * what open (pending / returned) requests hold back.
 */
final readonly class LeaveBalance
{
    public function __construct(
        public float $entitled,
        public float $adjusted,
        public float $used,
        public float $restored,
        public float $reserved,
    ) {}

    /** Net ledger balance (deductions are stored negative, so «used» is their absolute value). */
    public function balance(): float
    {
        return round($this->entitled + $this->adjusted - $this->used + $this->restored, 2);
    }

    /** What a new request may still take. */
    public function available(): float
    {
        return round($this->balance() - $this->reserved, 2);
    }

    /** @return array<string, float> */
    public function toArray(): array
    {
        return [
            'entitled' => $this->entitled,
            'adjusted' => $this->adjusted,
            'used' => round($this->used - $this->restored, 2),
            'reserved' => $this->reserved,
            'balance' => $this->balance(),
            'available' => $this->available(),
        ];
    }
}
