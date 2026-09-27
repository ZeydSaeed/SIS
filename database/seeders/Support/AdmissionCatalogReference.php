<?php

namespace Database\Seeders\Support;

/**
 * Single source of truth for vocational branch → department catalog.
 * Must stay aligned with resources/js/components/admission/admission-branch-catalog.ts
 * and resources/js/lib/sis-class-section-options.ts (class/section labels).
 */
final class AdmissionCatalogReference
{
    /** @var list<string> */
    public const BRANCHES = [
        'الصناعي',
        'التجاري',
        'الزراعي',
        'الحاسوب وتقنية المعلومات',
        'الفنون التطبيقية',
        'السياحة والفندقة',
        'العلوم الرياضية',
    ];

    /**
     * @var array<string, list<string>>
     */
    public const DEPARTMENTS_BY_BRANCH = [
        'الصناعي' => [
            'كهرباء',
            'ميكانيك',
            'اللحام وتشكيل المعادن',
            'تكييف الهواء و التثليج',
            'النجارة',
            'السيارات',
            'الأجهزة الطبية',
            'الكترونيك وسيطرة',
            'البناء',
            'الصناعات البتروكيمياوية',
        ],
        'الحاسوب وتقنية المعلومات' => [
            'تجميع وصيانة الحاسوب',
            'شبكات الحاسوب',
            'اجهزة الهاتف والحاسوب المحمولة',
            'الامن السبراني',
        ],
        'الزراعي' => ['زراعي'],
        'التجاري' => ['الادارة', 'المحاسبة'],
        'الفنون التطبيقية' => ['فن التربية الاسرية', 'فن الديكور'],
        'السياحة والفندقة' => ['الاسكان الفندقي', 'الضيافة وانتاج الاظعمة', 'الادارة السياحية'],
        'العلوم الرياضية' => ['رياضة'],
    ];

    /** UI class keys → Arabic labels (sis-class-section-options). */
    public const CLASSES = [
        ['key' => '1', 'code' => 'CLS-1', 'name' => 'الأول', 'grade_code' => 'G1'],
        ['key' => '2', 'code' => 'CLS-2', 'name' => 'الثاني', 'grade_code' => 'G2'],
        ['key' => '3', 'code' => 'CLS-3', 'name' => 'الثالث', 'grade_code' => 'G3'],
    ];

    /** UI section codes → Arabic names. */
    public const SECTIONS = [
        ['code' => 'SEC-A', 'ui' => 'A', 'name' => 'شعبة أ'],
        ['code' => 'SEC-B', 'ui' => 'B', 'name' => 'ب'],
        ['code' => 'SEC-C', 'ui' => 'C', 'name' => 'شعبة ج'],
    ];

    public static function branchCode(string $branchName): string
    {
        return 'BR-'.strtoupper(substr(md5($branchName), 0, 6));
    }

    public static function departmentCode(string $branchName, string $departmentName): string
    {
        return 'DEP-'.strtoupper(substr(md5($branchName.'|'.$departmentName), 0, 8));
    }

    /**
     * Flat list of [branch, department] pairs for round-robin placement.
     *
     * @return list<array{branch: string, department: string}>
     */
    public static function placementPairs(): array
    {
        $pairs = [];
        foreach (self::DEPARTMENTS_BY_BRANCH as $branch => $departments) {
            foreach ($departments as $department) {
                $pairs[] = ['branch' => $branch, 'department' => $department];
            }
        }

        return $pairs;
    }
}
