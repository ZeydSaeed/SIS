<?php

namespace App\Http\Support;

use Illuminate\Http\RedirectResponse;

/**
 * Unified in-app workflow guidance flash (admission → student → enrollment).
 *
 * Prefer `step` keys so the React host resolves Arabic copy from resources/js/i18n/ar.ts.
 * Optional title/message/action_label may be i18n paths (workflow.*) — never hardcode prose here.
 *
 * @phpstan-type WorkflowNotice array{
 *     tone: 'info'|'success'|'warning'|'error',
 *     title?: string|null,
 *     message?: string|null,
 *     action_href?: string|null,
 *     action_label?: string|null,
 *     step?: string|null
 * }
 */
final class WorkflowFlash
{
    /**
     * @param  WorkflowNotice  $notice
     */
    public static function with(RedirectResponse $redirect, array $notice): RedirectResponse
    {
        $tone = $notice['tone'];
        $toastType = match ($tone) {
            'info', 'success', 'warning', 'error' => $tone,
            default => 'info',
        };

        $step = $notice['step'] ?? null;
        $title = $notice['title'] ?? '';
        $message = $notice['message'] ?? '';

        return $redirect
            ->with('workflow', [
                'tone' => $tone,
                'title' => $title,
                'message' => $message,
                'action_href' => $notice['action_href'] ?? null,
                'action_label' => $notice['action_label'] ?? null,
                'step' => $step,
            ])
            ->with('toast', [
                'type' => $toastType,
                // Host resolves step / flash.* / workflow.* keys to Arabic SSOT.
                'message' => is_string($step) && $step !== '' ? $step : $title,
            ]);
    }
}
