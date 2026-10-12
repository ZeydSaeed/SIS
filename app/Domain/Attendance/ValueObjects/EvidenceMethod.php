<?php

namespace App\Domain\Attendance\ValueObjects;

/**
 * How a status was established (attendance.records.marked_via / attendance.staff_records.method).
 * Evidence is separate from the status itself and from administrative approval: a successful
 * code / QR / NFC capture never approves a sheet by itself.
 */
enum EvidenceMethod: int
{
    case TeacherManual = 1;
    case Administrative = 2;
    case ExcuseApplication = 3;
    case CheckinCode = 4;
    /** Reserved adapters — not implemented in this release (no QR / NFC / kiosk hardware integration). */
    case Kiosk = 5;
    case Qr = 6;
    case Nfc = 7;
}
