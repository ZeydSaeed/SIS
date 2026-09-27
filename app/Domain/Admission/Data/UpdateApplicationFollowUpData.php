<?php

namespace App\Domain\Admission\Data;

final readonly class UpdateApplicationFollowUpData
{
    public function __construct(
        public int $applicationId,
        public string $firstName,
        public ?string $fatherName,
        public ?string $grandfatherName,
        public ?string $greatGrandfatherName,
        public string $lastName,
        public ?string $rejectionReason,
        public ?string $withdrawalReason,
        public bool $updateRejectionReason,
        public bool $updateWithdrawalReason,
    ) {}
}
