<?php

return [
    /*
    | School-local time zone used for lesson windows and «today» until a school saves its own attendance policy
    | (attendance.policies.timezone). The application clock stays UTC; only attendance windows are localized.
    */
    'default_timezone' => env('SIS_ATTENDANCE_TIMEZONE', 'Asia/Baghdad'), // confirmed 2026-10-12

    /* Tahoma TTF for PDF exports (an approved family); when missing, mPDF's bundled Arabic font is used. */
    'pdf_font_path' => env('SIS_ATTENDANCE_PDF_FONT', PHP_OS_FAMILY === 'Windows' ? 'C:\Windows\Fonts\tahoma.ttf' : '/usr/share/fonts/truetype/msttcorefonts/tahoma.ttf'),

    /* Page sizes of the attendance screens (server pagination). */
    'per_page' => [
        'review' => 25,
        'excuses' => 25,
        'alerts' => 25,
        'reports' => 50,
    ],
];
