<?php

namespace Database\Seeders\Support;

/**
 * SSOT for vocational curriculum subjects by branch → department (اختصاص).
 * Must stay aligned with resources/js/components/curriculum/curriculum-subject-catalog.ts
 * and AdmissionCatalogReference department names.
 *
 * subject_type: 1=Core, 2=Elective, 3=Practical
 *
 * @phpstan-type SubjectDef array{name: string, credit_hours: int, subject_type: int}
 */
final class CurriculumSubjectCatalogReference
{
    /**
     * Branch → department → subjects.
     * Use '*' as department key when the subject set applies to every specialization in the branch.
     *
     * @var array<string, array<string, list<SubjectDef>>>
     */
    public const SUBJECTS_BY_BRANCH_DEPARTMENT = [
        'الصناعي' => [
            '*' => [
                ['name' => 'التربية الاسلامية', 'credit_hours' => 2, 'subject_type' => 1],
                ['name' => 'اللغة العربية', 'credit_hours' => 2, 'subject_type' => 1],
                ['name' => 'اللغة الانكليزية', 'credit_hours' => 2, 'subject_type' => 1],
                ['name' => 'الرياضيات', 'credit_hours' => 2, 'subject_type' => 1],
                ['name' => 'الطبيعيات', 'credit_hours' => 2, 'subject_type' => 1],
                ['name' => 'الرسم الصناعي', 'credit_hours' => 2, 'subject_type' => 1],
                ['name' => 'العلوم الصناعية', 'credit_hours' => 2, 'subject_type' => 1],
                ['name' => 'التدريب العملي', 'credit_hours' => 4, 'subject_type' => 3],
            ],
        ],
        'الحاسوب وتقنية المعلومات' => [
            'تجميع وصيانة الحاسوب' => [
                ['name' => 'التربية الاسلامية', 'credit_hours' => 2, 'subject_type' => 1],
                ['name' => 'اللغة العربية', 'credit_hours' => 2, 'subject_type' => 1],
                ['name' => 'اللغة الانكليزية', 'credit_hours' => 2, 'subject_type' => 1],
                ['name' => 'الرياضيات', 'credit_hours' => 2, 'subject_type' => 1],
                ['name' => 'الطبيعيات', 'credit_hours' => 2, 'subject_type' => 1],
                ['name' => 'المعالجات الدقيقة', 'credit_hours' => 2, 'subject_type' => 2],
                ['name' => 'صيانة الحاسوب', 'credit_hours' => 2, 'subject_type' => 2],
                ['name' => 'مختبر وشبكات الانترنت', 'credit_hours' => 3, 'subject_type' => 3],
            ],
            'شبكات الحاسوب' => [
                ['name' => 'التربية الاسلامية', 'credit_hours' => 2, 'subject_type' => 1],
                ['name' => 'اللغة العربية', 'credit_hours' => 2, 'subject_type' => 1],
                ['name' => 'اللغة الانكليزية', 'credit_hours' => 2, 'subject_type' => 1],
                ['name' => 'الرياضيات', 'credit_hours' => 2, 'subject_type' => 1],
                ['name' => 'الطبيعيات', 'credit_hours' => 2, 'subject_type' => 1],
                ['name' => 'المعالجات الدقيقة', 'credit_hours' => 2, 'subject_type' => 2],
                ['name' => 'تطبيقيات الاتصالات', 'credit_hours' => 2, 'subject_type' => 1],
                ['name' => 'شبكات الحاسوب', 'credit_hours' => 3, 'subject_type' => 3],
            ],
            'اجهزة الهاتف والحاسوب المحمولة' => [
                ['name' => 'التربية الاسلامية', 'credit_hours' => 2, 'subject_type' => 1],
                ['name' => 'اللغة العربية', 'credit_hours' => 2, 'subject_type' => 1],
                ['name' => 'اللغة الانكليزية', 'credit_hours' => 2, 'subject_type' => 1],
                ['name' => 'الرياضيات', 'credit_hours' => 2, 'subject_type' => 1],
                ['name' => 'الطبيعيات', 'credit_hours' => 2, 'subject_type' => 1],
                ['name' => 'الشبكات المتحسسة اللاسلكية', 'credit_hours' => 2, 'subject_type' => 1],
                ['name' => 'تكنلوجيا المحمول والاجهزة اللوحية', 'credit_hours' => 2, 'subject_type' => 1],
                ['name' => 'صيانة الاجهزة الخلوية', 'credit_hours' => 3, 'subject_type' => 3],
            ],
        ],
        'الزراعي' => [
            'زراعي' => [
                ['name' => 'التربية الاسلامية', 'credit_hours' => 2, 'subject_type' => 1],
                ['name' => 'اللغة العربية', 'credit_hours' => 2, 'subject_type' => 1],
                ['name' => 'اللغة الانكليزية', 'credit_hours' => 2, 'subject_type' => 1],
                ['name' => 'الرياضيات', 'credit_hours' => 2, 'subject_type' => 1],
                ['name' => 'تربية الدواجن', 'credit_hours' => 2, 'subject_type' => 1],
                ['name' => 'وقاية المزروعات', 'credit_hours' => 2, 'subject_type' => 1],
                ['name' => 'انتاج الفاكهة', 'credit_hours' => 2, 'subject_type' => 1],
                ['name' => 'صناعة الالبان', 'credit_hours' => 3, 'subject_type' => 3],
            ],
        ],
        'الفنون التطبيقية' => [
            'فن التربية الاسرية' => [
                ['name' => 'التربية الاسلامية', 'credit_hours' => 2, 'subject_type' => 1],
                ['name' => 'اللغة العربية', 'credit_hours' => 2, 'subject_type' => 1],
                ['name' => 'اللغة الانكليزية', 'credit_hours' => 2, 'subject_type' => 1],
                ['name' => 'الرياضيات', 'credit_hours' => 2, 'subject_type' => 1],
                ['name' => 'فن التفصيل والخياطة', 'credit_hours' => 3, 'subject_type' => 3],
                ['name' => 'التغذية', 'credit_hours' => 2, 'subject_type' => 1],
                ['name' => 'تربية الطفل والعلاقات الاسرية', 'credit_hours' => 2, 'subject_type' => 1],
                ['name' => 'فن الاناقة و الازياء', 'credit_hours' => 2, 'subject_type' => 3],
            ],
            'فن الديكور' => [
                ['name' => 'التربية الاسلامية', 'credit_hours' => 2, 'subject_type' => 1],
                ['name' => 'اللغة العربية', 'credit_hours' => 2, 'subject_type' => 1],
                ['name' => 'اللغة الانكليزية', 'credit_hours' => 2, 'subject_type' => 1],
                ['name' => 'الرياضيات', 'credit_hours' => 2, 'subject_type' => 1],
                ['name' => 'تقنيات الديكور', 'credit_hours' => 2, 'subject_type' => 1],
                ['name' => 'اخراج واضهار', 'credit_hours' => 2, 'subject_type' => 3],
                ['name' => 'صناعة الاثاث', 'credit_hours' => 3, 'subject_type' => 3],
                ['name' => 'مكملات الديكور', 'credit_hours' => 2, 'subject_type' => 3],
            ],
        ],
        'السياحة والفندقة' => [
            'الادارة السياحية' => [
                ['name' => 'التربية الاسلامية', 'credit_hours' => 2, 'subject_type' => 1],
                ['name' => 'اللغة العربية', 'credit_hours' => 2, 'subject_type' => 1],
                ['name' => 'اللغة الانكليزية', 'credit_hours' => 2, 'subject_type' => 1],
                ['name' => 'المحاسبة الفندقية', 'credit_hours' => 2, 'subject_type' => 1],
                ['name' => 'ادارة السفر والحجز الالكتروني', 'credit_hours' => 2, 'subject_type' => 1],
                ['name' => 'الارشاد السياحي', 'credit_hours' => 2, 'subject_type' => 1],
                ['name' => 'التخطيط السياحي', 'credit_hours' => 2, 'subject_type' => 1],
                ['name' => 'السياحة المستدامة', 'credit_hours' => 2, 'subject_type' => 1],
            ],
            'الاسكان الفندقي' => [
                ['name' => 'التربية الاسلامية', 'credit_hours' => 2, 'subject_type' => 1],
                ['name' => 'اللغة العربية', 'credit_hours' => 2, 'subject_type' => 1],
                ['name' => 'اللغة الانكليزية', 'credit_hours' => 2, 'subject_type' => 1],
                ['name' => 'المحاسبة الفندقية', 'credit_hours' => 2, 'subject_type' => 1],
                ['name' => 'ادارة السفر والحجز الالكتروني', 'credit_hours' => 2, 'subject_type' => 1],
                ['name' => 'الارشاد السياحي', 'credit_hours' => 2, 'subject_type' => 1],
                ['name' => 'المهارات العملية التدبير الفندقي', 'credit_hours' => 3, 'subject_type' => 3],
                ['name' => 'المهارات العملية المكتب الامامي', 'credit_hours' => 3, 'subject_type' => 3],
            ],
            'الضيافة وانتاج الاظعمة' => [
                ['name' => 'التربية الاسلامية', 'credit_hours' => 2, 'subject_type' => 1],
                ['name' => 'اللغة العربية', 'credit_hours' => 2, 'subject_type' => 1],
                ['name' => 'اللغة الانكليزية', 'credit_hours' => 2, 'subject_type' => 1],
                ['name' => 'المحاسبة الفندقية', 'credit_hours' => 2, 'subject_type' => 1],
                ['name' => 'التغذية', 'credit_hours' => 2, 'subject_type' => 1],
                ['name' => 'فن الضيافة وانتاج الاطعمة', 'credit_hours' => 2, 'subject_type' => 1],
                ['name' => 'المهارات العملية فن الضيافة', 'credit_hours' => 3, 'subject_type' => 3],
                ['name' => 'المهارات العملية انتاج الاطعمة', 'credit_hours' => 3, 'subject_type' => 3],
            ],
        ],
        'التجاري' => [
            'الادارة' => [
                ['name' => 'التربية الاسلامية', 'credit_hours' => 2, 'subject_type' => 1],
                ['name' => 'اللغة العربية', 'credit_hours' => 2, 'subject_type' => 1],
                ['name' => 'اللغة الانكليزية والمراسلات التجارية', 'credit_hours' => 2, 'subject_type' => 1],
                ['name' => 'المحاسبة الادارية', 'credit_hours' => 2, 'subject_type' => 1],
                ['name' => 'الادارة المالية', 'credit_hours' => 2, 'subject_type' => 1],
                ['name' => 'ادارة الموارد البشرية', 'credit_hours' => 2, 'subject_type' => 1],
                ['name' => 'ادارة الانتاج والعمليات', 'credit_hours' => 2, 'subject_type' => 1],
                ['name' => 'الاقتصاد الكلي', 'credit_hours' => 2, 'subject_type' => 1],
            ],
            'المحاسبة' => [
                ['name' => 'التربية الاسلامية', 'credit_hours' => 2, 'subject_type' => 1],
                ['name' => 'اللغة العربية', 'credit_hours' => 2, 'subject_type' => 1],
                ['name' => 'اللغة الانكليزية والمراسلات التجارية', 'credit_hours' => 2, 'subject_type' => 1],
                ['name' => 'المحاسبة الادارية', 'credit_hours' => 2, 'subject_type' => 1],
                ['name' => 'محاسبة الشركات', 'credit_hours' => 2, 'subject_type' => 1],
                ['name' => 'محاسبة التكاليف', 'credit_hours' => 2, 'subject_type' => 1],
                ['name' => 'محاسبة المنشات المالية', 'credit_hours' => 2, 'subject_type' => 1],
                ['name' => 'الاقتصاد الكلي', 'credit_hours' => 2, 'subject_type' => 1],
            ],
        ],
    ];

    public static function subjectCode(string $name): string
    {
        return 'SUB-'.strtoupper(substr(md5($name), 0, 8));
    }

    /**
     * Unique subject definitions across the catalog (first occurrence wins for hours/type).
     *
     * @return list<SubjectDef>
     */
    public static function uniqueSubjects(): array
    {
        $byName = [];
        foreach (self::SUBJECTS_BY_BRANCH_DEPARTMENT as $departments) {
            foreach ($departments as $subjects) {
                foreach ($subjects as $subject) {
                    $byName[$subject['name']] ??= $subject;
                }
            }
        }

        return array_values($byName);
    }

    /**
     * @return list<SubjectDef>
     */
    public static function subjectsFor(string $branchName, string $departmentName): array
    {
        $byDept = self::SUBJECTS_BY_BRANCH_DEPARTMENT[$branchName] ?? null;
        if ($byDept === null) {
            return [];
        }

        if (isset($byDept['*'])) {
            return $byDept['*'];
        }

        return $byDept[$departmentName] ?? [];
    }
}
