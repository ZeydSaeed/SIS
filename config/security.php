<?php

return [
    'permissions' => [
        'students.view' => 'View student records',
        'students.create' => 'Create student records',
        'students.update' => 'Update student records',
        'students.view_pii' => 'View student PII (national ID)',
        'enrollment.view' => 'View enrollment records',
        'enrollment.create' => 'Create enrollment records',
        'enrollment.update' => 'Update enrollment placement',
        'enrollment.cancel' => 'Cancel enrollment records',
        'grades.view' => 'View student grades',
        'grades.create' => 'Enter student grades',
        'grades.correct' => 'Correct student grades',
        'grades.void' => 'Void student grades',
        'grades.finalize' => 'Finalize student grades',
        'exam.create' => 'Create exams',
        'exam.update' => 'Update exams',
        'exam.cancel' => 'Cancel exams',
        'attendance.view' => 'View attendance sessions and records',
        'attendance.session.create' => 'Create attendance sessions',
        'attendance.mark' => 'Mark attendance for open sessions',
        'attendance.correct' => 'Correct attendance with audited reason',
        'attendance.session.close' => 'Close open attendance sessions',
        'attendance.session.cancel' => 'Cancel open or closed attendance sessions',
        'security.manage_users' => 'Manage users and security settings',
    ],

    'roles' => [
        'student_manager' => [
            'students.view',
            'students.create',
            'students.update',
            'students.view_pii',
        ],
        'student_viewer' => [
            'students.view',
        ],
        'enrollment_manager' => [
            'enrollment.view',
            'enrollment.create',
            'enrollment.update',
            'enrollment.cancel',
            'students.view',
        ],
        'enrollment_viewer' => [
            'enrollment.view',
            'students.view',
        ],
        'grades_manager' => [
            'grades.view',
            'grades.create',
            'grades.correct',
            'grades.void',
            'grades.finalize',
            'exam.create',
            'exam.update',
            'exam.cancel',
            'students.view',
            'enrollment.view',
        ],
        'grades_teacher' => [
            'grades.view',
            'grades.create',
            'students.view',
            'enrollment.view',
        ],
        'grades_viewer' => [
            'grades.view',
            'students.view',
            'enrollment.view',
        ],
        'attendance_viewer' => [
            'attendance.view',
        ],
        'attendance_teacher' => [
            'attendance.view',
            'attendance.session.create',
            'attendance.mark',
            'attendance.session.close',
        ],
        'attendance_manager' => [
            'attendance.view',
            'attendance.session.create',
            'attendance.mark',
            'attendance.correct',
            'attendance.session.close',
            'attendance.session.cancel',
        ],
    ],

    'rate_limits' => [
        'api' => ['max_attempts' => 120, 'decay_minutes' => 1],
        'api_students' => ['max_attempts' => 60, 'decay_minutes' => 1],
        'api_search' => ['max_attempts' => 30, 'decay_minutes' => 1],
    ],

    'headers' => [
        'X-Content-Type-Options' => 'nosniff',
        'X-Frame-Options' => 'SAMEORIGIN',
        'Referrer-Policy' => 'strict-origin-when-cross-origin',
        'Permissions-Policy' => 'camera=(), microphone=(), geolocation=()',
    ],

    'public_api_routes' => [
        'api.health',
    ],

    'dependency_audit_enabled' => env('SECURITY_DEPENDENCY_AUDIT_ENABLED', true),

    'allow_implicit_single_school' => false,
];
