<?php

namespace Database\Seeders\Support;

/**
 * Stable reference codes for foundation seed data (Phase 3.2).
 * Used by seeders, tests, and sis:verify-database.
 */
final class FoundationReference
{
    public const MINISTRY_CODE = 'MOE';

    public const DIRECTORATE_CODE = 'DIR-DEMO';

    public const SCHOOL_CODE = 'SCH-DEMO';

    public const BRANCH_CODE = 'BR-MAIN';

    public const DEPARTMENT_CODE = 'DEP-VOC';

    public const ACADEMIC_YEAR_CODE = '2026-2027';

    public const TERM_ONE_CODE = 'T1';

    public const TERM_TWO_CODE = 'T2';

    /** @var list<array{code: string, name: string, level_order: int, education_stage: int}> */
    public const GRADE_LEVELS = [
        ['code' => 'G10', 'name' => 'Grade 10', 'level_order' => 10, 'education_stage' => 3],
        ['code' => 'G11', 'name' => 'Grade 11', 'level_order' => 11, 'education_stage' => 3],
        ['code' => 'G12', 'name' => 'Grade 12', 'level_order' => 12, 'education_stage' => 3],
    ];
}
