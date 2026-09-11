<?php

namespace App\Application\Graduation\DTOs;

/**
 * Lightweight approval-attempt reference for history (not full GetGraduationApproval contract).
 */
final readonly class OutcomeHistoryApprovalRefDTO
{
    public function __construct(
        public int $approvalId,
        public int $attemptNo,
        public int $decisionStatus,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'approval_id' => $this->approvalId,
            'attempt_no' => $this->attemptNo,
            'decision_status' => $this->decisionStatus,
        ];
    }
}
