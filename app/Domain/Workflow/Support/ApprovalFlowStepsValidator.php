<?php

namespace App\Domain\Workflow\Support;

final class ApprovalFlowStepsValidator
{
    /**
     * @param  list<mixed>  $steps
     * @return list<string>
     */
    public static function validate(array $steps): array
    {
        if ($steps === []) {
            return ['workflow.steps_empty'];
        }

        $errors = [];
        $seen = [];

        foreach ($steps as $index => $step) {
            if (! is_array($step)) {
                $errors[] = 'workflow.steps_invalid_item';
                continue;
            }

            $order = $step['step'] ?? null;
            $role = $step['role'] ?? null;

            if (! is_int($order) && ! (is_string($order) && ctype_digit($order))) {
                $errors[] = 'workflow.steps_step_invalid';
                continue;
            }

            $orderInt = (int) $order;
            if ($orderInt < 1) {
                $errors[] = 'workflow.steps_step_invalid';
                continue;
            }

            if (! is_string($role) || trim($role) === '' || strlen($role) > 100) {
                $errors[] = 'workflow.steps_role_invalid';
                continue;
            }

            if (isset($seen[$orderInt])) {
                $errors[] = 'workflow.steps_step_duplicate';
            }
            $seen[$orderInt] = true;
        }

        return array_values(array_unique($errors));
    }

    /**
     * @param  list<array{step:int|string, role:string}>  $steps
     * @return list<array{step:int, role:string}>
     */
    public static function normalize(array $steps): array
    {
        $normalized = [];
        foreach ($steps as $step) {
            $normalized[] = [
                'step' => (int) $step['step'],
                'role' => trim((string) $step['role']),
            ];
        }

        usort($normalized, static fn (array $a, array $b): int => $a['step'] <=> $b['step']);

        return $normalized;
    }
}
