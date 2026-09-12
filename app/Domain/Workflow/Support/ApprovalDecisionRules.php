<?php

namespace App\Domain\Workflow\Support;

use App\Domain\Workflow\ValueObjects\ApprovalRequestStatus;

final class ApprovalDecisionRules
{
    public const Approve = 'approve';

    public const Reject = 'reject';

    /**
     * @param  list<array{step:int, role:string}>  $steps
     */
    public static function maxStep(array $steps): int
    {
        $max = 0;
        foreach ($steps as $step) {
            $max = max($max, (int) $step['step']);
        }

        return $max;
    }

    /**
     * @return list<string>
     */
    public static function validateDecision(string $decision): array
    {
        if (! in_array($decision, [self::Approve, self::Reject], true)) {
            return ['workflow.decision_invalid'];
        }

        return [];
    }

    /**
     * @return list<string>
     */
    public static function validatePending(int $status): array
    {
        if ($status !== ApprovalRequestStatus::Pending) {
            return ['workflow.approval_request_not_pending'];
        }

        return [];
    }

    /**
     * @param  list<array{step:int, role:string}>  $steps
     */
    public static function roleForStep(array $steps, int $currentStep): ?string
    {
        foreach ($steps as $step) {
            if ((int) $step['step'] === $currentStep) {
                $role = trim((string) $step['role']);

                return $role !== '' ? $role : null;
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $actorRoleCodes
     * @return list<string>
     */
    public static function validateActorRole(?string $requiredRole, array $actorRoleCodes): array
    {
        if ($requiredRole === null || $requiredRole === '') {
            return ['workflow.step_role_undefined'];
        }

        $normalized = array_map(
            static fn (string $code): string => strtolower(trim($code)),
            $actorRoleCodes,
        );

        if (! in_array(strtolower($requiredRole), $normalized, true)) {
            return ['workflow.step_role_mismatch'];
        }

        return [];
    }

    /**
     * @return array{status:int, current_step:int, completed:bool}
     */
    public static function applyApprove(int $currentStep, int $maxStep): array
    {
        if ($currentStep < $maxStep) {
            return [
                'status' => ApprovalRequestStatus::Pending,
                'current_step' => $currentStep + 1,
                'completed' => false,
            ];
        }

        return [
            'status' => ApprovalRequestStatus::Approved,
            'current_step' => $currentStep,
            'completed' => true,
        ];
    }

    /**
     * @return array{status:int, current_step:int, completed:bool}
     */
    public static function applyReject(int $currentStep): array
    {
        return [
            'status' => ApprovalRequestStatus::Rejected,
            'current_step' => $currentStep,
            'completed' => true,
        ];
    }
}
