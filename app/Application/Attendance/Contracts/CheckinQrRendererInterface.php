<?php

namespace App\Application\Attendance\Contracts;

/**
 * Renders the QR shown on the school's attendance screen. It encodes only the check-in address and the short-lived
 * code — never a student or teacher identity.
 */
interface CheckinQrRendererInterface
{
    /** Inline SVG markup. */
    public function svgForCode(string $code): string;
}
