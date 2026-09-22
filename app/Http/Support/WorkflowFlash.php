<?php

namespace App\Http\Support;

use Illuminate\Http\RedirectResponse;

/**
 * Unified in-app workflow guidance flash (admission → student → enrollment).
 *
 * @phpstan-type WorkflowNotice array{
 *     tone: 'info'|'success'|'warning'|'error',
 *     title: string,
 *     message: string,
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

        return $redirect
            ->with('workflow', [
                'tone' => $tone,
                'title' => $notice['title'],
                'message' => $notice['message'],
                'action_href' => $notice['action_href'] ?? null,
                'action_label' => $notice['action_label'] ?? null,
                'step' => $notice['step'] ?? null,
            ])
            ->with('toast', [
                'type' => $toastType,
                'message' => $notice['title'].' — '.$notice['message'],
            ]);
    }
}
