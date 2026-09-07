<?php

namespace App\Optimization\SelfHealing;

/**
 * Fail-closed autonomous kill switch — explicit OFF required to allow execution.
 */
final class AutonomousKillSwitch
{
    public function isEngaged(): bool
    {
        $raw = config('optimization.autonomous.kill_switch');

        if ($raw === true || $raw === 'true' || $raw === '1' || $raw === 1) {
            return true;
        }

        if ($raw === false || $raw === 'false' || $raw === '0' || $raw === 0 || $raw === null) {
            return false;
        }

        // Invalid state → fail closed (treat as engaged).
        return true;
    }
}
