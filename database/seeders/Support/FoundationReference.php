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

    /** Arabic grade labels aligned with SIS class SSOT (الأول / الثاني / الثالث). */
    /** @var list<array{code: string, name: string, level_order: int, education_stage: int}> */
    public const GRADE_LEVELS = [
        ['code' => 'G1', 'name' => 'الأول', 'level_order' => 1, 'education_stage' => 3],
        ['code' => 'G2', 'name' => 'الثاني', 'level_order' => 2, 'education_stage' => 3],
        ['code' => 'G3', 'name' => 'الثالث', 'level_order' => 3, 'education_stage' => 3],
    ];
}
