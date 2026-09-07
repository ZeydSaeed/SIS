<?php

return [
    'permissions' => [
        'students.view' => 'View student records',
        'students.create' => 'Create student records',
        'students.update' => 'Update student records',
        'students.view_pii' => 'View student PII (national ID)',
        'enrollment.view' => 'View enrollment records',
        'enrollment.create' => 'Create enrollment records',
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
            'students.view',
        ],
        'enrollment_viewer' => [
            'enrollment.view',
            'students.view',
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
