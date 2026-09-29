/**
 * Fixed vocational curriculum subjects by branch → department (اختصاص).
 * SSOT twin: database/seeders/Support/CurriculumSubjectCatalogReference.php — keep in sync.
 * Department names must match admission-branch-catalog / AdmissionCatalogReference.
 */

import {
    ADMISSION_DEPARTMENTS_BY_BRANCH,
    type AdmissionBranchName,
} from '@/components/admission/admission-branch-catalog';

export type CurriculumCatalogSubject = {
    name: string;
    credit_hours: number;
    /** 1=Core, 2=Elective, 3=Practical */
    subject_type: number;
};

export type CatalogCurriculumTableRow = {
    /** Stable client key: branch|specialization|class */
    key: string;
    branch_name: string;
    specialization_name: string;
    /** صف SSOT: الأول / الثاني / الثالث */
    class_name: string;
    subjects: Array<{
        name: string;
        subject_type: number;
        credit_hours: number;
        max_grade: number;
    }>;
};

/** صف SSOT twin of enrollment.classes / FoundationReference (الأول / الثاني / الثالث). */
export const CURRICULUM_CLASS_NAMES = ['الأول', 'الثاني', 'الثالث'] as const;

/** Default subjects table before the user applies page filters. */
export const CURRICULUM_DEFAULT_TABLE_VIEW = {
    branch: 'الصناعي',
    specialization: 'ميكانيك',
    className: 'الأول',
    yearToken: '2026',
} as const;

type BranchSubjectMap = Record<string, readonly CurriculumCatalogSubject[]>;

const DEFAULT_MAX_GRADE = 100;

export const DEFAULT_PASS_GRADE = 50;

export type CatalogSubjectTableRow = {
    name: string;
    subject_type: number;
    credit_hours: number;
    max_grade: number;
    pass_grade: number;
    status: number;
};

export const CURRICULUM_SUBJECTS_BY_BRANCH_DEPARTMENT: Record<
    Exclude<AdmissionBranchName, 'العلوم الرياضية'>,
    BranchSubjectMap
> = {
    الصناعي: {
        '*': [
            { name: 'التربية الاسلامية', credit_hours: 2, subject_type: 1 },
            { name: 'اللغة العربية', credit_hours: 2, subject_type: 1 },
            { name: 'اللغة الانكليزية', credit_hours: 2, subject_type: 1 },
            { name: 'الرياضيات', credit_hours: 2, subject_type: 1 },
            { name: 'الطبيعيات', credit_hours: 2, subject_type: 1 },
            { name: 'الرسم الصناعي', credit_hours: 2, subject_type: 1 },
            { name: 'العلوم الصناعية', credit_hours: 2, subject_type: 1 },
            { name: 'التدريب العملي', credit_hours: 4, subject_type: 3 },
        ],
    },
    'الحاسوب وتقنية المعلومات': {
        'تجميع وصيانة الحاسوب': [
            { name: 'التربية الاسلامية', credit_hours: 2, subject_type: 1 },
            { name: 'اللغة العربية', credit_hours: 2, subject_type: 1 },
            { name: 'اللغة الانكليزية', credit_hours: 2, subject_type: 1 },
            { name: 'الرياضيات', credit_hours: 2, subject_type: 1 },
            { name: 'الطبيعيات', credit_hours: 2, subject_type: 1 },
            { name: 'المعالجات الدقيقة', credit_hours: 2, subject_type: 2 },
            { name: 'صيانة الحاسوب', credit_hours: 2, subject_type: 2 },
            { name: 'مختبر وشبكات الانترنت', credit_hours: 3, subject_type: 3 },
        ],
        'شبكات الحاسوب': [
            { name: 'التربية الاسلامية', credit_hours: 2, subject_type: 1 },
            { name: 'اللغة العربية', credit_hours: 2, subject_type: 1 },
            { name: 'اللغة الانكليزية', credit_hours: 2, subject_type: 1 },
            { name: 'الرياضيات', credit_hours: 2, subject_type: 1 },
            { name: 'الطبيعيات', credit_hours: 2, subject_type: 1 },
            { name: 'المعالجات الدقيقة', credit_hours: 2, subject_type: 2 },
            { name: 'تطبيقيات الاتصالات', credit_hours: 2, subject_type: 1 },
            { name: 'شبكات الحاسوب', credit_hours: 3, subject_type: 3 },
        ],
        'اجهزة الهاتف والحاسوب المحمولة': [
            { name: 'التربية الاسلامية', credit_hours: 2, subject_type: 1 },
            { name: 'اللغة العربية', credit_hours: 2, subject_type: 1 },
            { name: 'اللغة الانكليزية', credit_hours: 2, subject_type: 1 },
            { name: 'الرياضيات', credit_hours: 2, subject_type: 1 },
            { name: 'الطبيعيات', credit_hours: 2, subject_type: 1 },
            { name: 'الشبكات المتحسسة اللاسلكية', credit_hours: 2, subject_type: 1 },
            { name: 'تكنلوجيا المحمول والاجهزة اللوحية', credit_hours: 2, subject_type: 1 },
            { name: 'صيانة الاجهزة الخلوية', credit_hours: 3, subject_type: 3 },
        ],
    },
    الزراعي: {
        زراعي: [
            { name: 'التربية الاسلامية', credit_hours: 2, subject_type: 1 },
            { name: 'اللغة العربية', credit_hours: 2, subject_type: 1 },
            { name: 'اللغة الانكليزية', credit_hours: 2, subject_type: 1 },
            { name: 'الرياضيات', credit_hours: 2, subject_type: 1 },
            { name: 'تربية الدواجن', credit_hours: 2, subject_type: 1 },
            { name: 'وقاية المزروعات', credit_hours: 2, subject_type: 1 },
            { name: 'انتاج الفاكهة', credit_hours: 2, subject_type: 1 },
            { name: 'صناعة الالبان', credit_hours: 3, subject_type: 3 },
        ],
    },
    'الفنون التطبيقية': {
        'فن التربية الاسرية': [
            { name: 'التربية الاسلامية', credit_hours: 2, subject_type: 1 },
            { name: 'اللغة العربية', credit_hours: 2, subject_type: 1 },
            { name: 'اللغة الانكليزية', credit_hours: 2, subject_type: 1 },
            { name: 'الرياضيات', credit_hours: 2, subject_type: 1 },
            { name: 'فن التفصيل والخياطة', credit_hours: 3, subject_type: 3 },
            { name: 'التغذية', credit_hours: 2, subject_type: 1 },
            { name: 'تربية الطفل والعلاقات الاسرية', credit_hours: 2, subject_type: 1 },
            { name: 'فن الاناقة و الازياء', credit_hours: 2, subject_type: 3 },
        ],
        'فن الديكور': [
            { name: 'التربية الاسلامية', credit_hours: 2, subject_type: 1 },
            { name: 'اللغة العربية', credit_hours: 2, subject_type: 1 },
            { name: 'اللغة الانكليزية', credit_hours: 2, subject_type: 1 },
            { name: 'الرياضيات', credit_hours: 2, subject_type: 1 },
            { name: 'تقنيات الديكور', credit_hours: 2, subject_type: 1 },
            { name: 'اخراج واضهار', credit_hours: 2, subject_type: 3 },
            { name: 'صناعة الاثاث', credit_hours: 3, subject_type: 3 },
            { name: 'مكملات الديكور', credit_hours: 2, subject_type: 3 },
        ],
    },
    'السياحة والفندقة': {
        'الادارة السياحية': [
            { name: 'التربية الاسلامية', credit_hours: 2, subject_type: 1 },
            { name: 'اللغة العربية', credit_hours: 2, subject_type: 1 },
            { name: 'اللغة الانكليزية', credit_hours: 2, subject_type: 1 },
            { name: 'المحاسبة الفندقية', credit_hours: 2, subject_type: 1 },
            { name: 'ادارة السفر والحجز الالكتروني', credit_hours: 2, subject_type: 1 },
            { name: 'الارشاد السياحي', credit_hours: 2, subject_type: 1 },
            { name: 'التخطيط السياحي', credit_hours: 2, subject_type: 1 },
            { name: 'السياحة المستدامة', credit_hours: 2, subject_type: 1 },
        ],
        'الاسكان الفندقي': [
            { name: 'التربية الاسلامية', credit_hours: 2, subject_type: 1 },
            { name: 'اللغة العربية', credit_hours: 2, subject_type: 1 },
            { name: 'اللغة الانكليزية', credit_hours: 2, subject_type: 1 },
            { name: 'المحاسبة الفندقية', credit_hours: 2, subject_type: 1 },
            { name: 'ادارة السفر والحجز الالكتروني', credit_hours: 2, subject_type: 1 },
            { name: 'الارشاد السياحي', credit_hours: 2, subject_type: 1 },
            { name: 'المهارات العملية التدبير الفندقي', credit_hours: 3, subject_type: 3 },
            { name: 'المهارات العملية المكتب الامامي', credit_hours: 3, subject_type: 3 },
        ],
        'الضيافة وانتاج الاظعمة': [
            { name: 'التربية الاسلامية', credit_hours: 2, subject_type: 1 },
            { name: 'اللغة العربية', credit_hours: 2, subject_type: 1 },
            { name: 'اللغة الانكليزية', credit_hours: 2, subject_type: 1 },
            { name: 'المحاسبة الفندقية', credit_hours: 2, subject_type: 1 },
            { name: 'التغذية', credit_hours: 2, subject_type: 1 },
            { name: 'فن الضيافة وانتاج الاطعمة', credit_hours: 2, subject_type: 1 },
            { name: 'المهارات العملية فن الضيافة', credit_hours: 3, subject_type: 3 },
            { name: 'المهارات العملية انتاج الاطعمة', credit_hours: 3, subject_type: 3 },
        ],
    },
    التجاري: {
        الادارة: [
            { name: 'التربية الاسلامية', credit_hours: 2, subject_type: 1 },
            { name: 'اللغة العربية', credit_hours: 2, subject_type: 1 },
            { name: 'اللغة الانكليزية والمراسلات التجارية', credit_hours: 2, subject_type: 1 },
            { name: 'المحاسبة الادارية', credit_hours: 2, subject_type: 1 },
            { name: 'الادارة المالية', credit_hours: 2, subject_type: 1 },
            { name: 'ادارة الموارد البشرية', credit_hours: 2, subject_type: 1 },
            { name: 'ادارة الانتاج والعمليات', credit_hours: 2, subject_type: 1 },
            { name: 'الاقتصاد الكلي', credit_hours: 2, subject_type: 1 },
        ],
        المحاسبة: [
            { name: 'التربية الاسلامية', credit_hours: 2, subject_type: 1 },
            { name: 'اللغة العربية', credit_hours: 2, subject_type: 1 },
            { name: 'اللغة الانكليزية والمراسلات التجارية', credit_hours: 2, subject_type: 1 },
            { name: 'المحاسبة الادارية', credit_hours: 2, subject_type: 1 },
            { name: 'محاسبة الشركات', credit_hours: 2, subject_type: 1 },
            { name: 'محاسبة التكاليف', credit_hours: 2, subject_type: 1 },
            { name: 'محاسبة المنشات المالية', credit_hours: 2, subject_type: 1 },
            { name: 'الاقتصاد الكلي', credit_hours: 2, subject_type: 1 },
        ],
    },
};

function withMaxGrade(
    subjects: readonly CurriculumCatalogSubject[],
): CatalogCurriculumTableRow['subjects'] {
    return subjects.map((subject) => ({
        name: subject.name,
        subject_type: subject.subject_type,
        credit_hours: subject.credit_hours,
        max_grade: DEFAULT_MAX_GRADE,
    }));
}

/**
 * Builds the operational curriculum matrix: one row per branch × اختصاص × صف
 * (industrial uses every admission specialty with the shared subject set).
 */
export function catalogCurriculumTableRows(): CatalogCurriculumTableRow[] {
    const rows: CatalogCurriculumTableRow[] = [];

    const pushForClasses = (
        branchName: string,
        specialization: string,
        subjects: readonly CurriculumCatalogSubject[],
    ): void => {
        for (const className of CURRICULUM_CLASS_NAMES) {
            rows.push({
                key: `${branchName}|${specialization}|${className}`,
                branch_name: branchName,
                specialization_name: specialization,
                class_name: className,
                subjects: withMaxGrade(subjects),
            });
        }
    };

    for (const [branchName, branchMap] of Object.entries(
        CURRICULUM_SUBJECTS_BY_BRANCH_DEPARTMENT,
    )) {
        if (branchMap['*']) {
            const specialties =
                ADMISSION_DEPARTMENTS_BY_BRANCH[branchName as AdmissionBranchName] ?? [];
            for (const specialization of specialties) {
                pushForClasses(branchName, specialization, branchMap['*']);
            }
            continue;
        }

        for (const [specialization, subjects] of Object.entries(branchMap)) {
            pushForClasses(branchName, specialization, subjects);
        }
    }

    return rows;
}

function toSubjectTableRows(
    subjects: readonly CurriculumCatalogSubject[],
): CatalogSubjectTableRow[] {
    return subjects.map((subject) => ({
        name: subject.name,
        subject_type: subject.subject_type,
        credit_hours: subject.credit_hours,
        max_grade: DEFAULT_MAX_GRADE,
        pass_grade: DEFAULT_PASS_GRADE,
        status: 1,
    }));
}

function uniqueByName(rows: CatalogSubjectTableRow[]): CatalogSubjectTableRow[] {
    const seen = new Set<string>();
    const out: CatalogSubjectTableRow[] = [];
    for (const row of rows) {
        if (seen.has(row.name)) {
            continue;
        }
        seen.add(row.name);
        out.push(row);
    }

    return out;
}

/** Subject rows for the flat curriculum table (branch + اختصاص; صف is a scope filter only). */
export function catalogSubjectTableRowsFor(
    branchName: string,
    specializationName: string,
): CatalogSubjectTableRow[] {
    return toSubjectTableRows(catalogSubjectsFor(branchName, specializationName));
}

/** All subjects under one branch (union of its اختصاص sets). */
export function catalogSubjectTableRowsForBranch(branchName: string): CatalogSubjectTableRow[] {
    const departments = catalogDepartmentsForBranch(branchName);
    if (departments.length === 0) {
        return catalogSubjectTableRowsFor(branchName, '');
    }

    return uniqueByName(
        departments.flatMap((department) => catalogSubjectTableRowsFor(branchName, department)),
    );
}

/** Full vocational catalog (all branches × اختصاص). */
export function catalogAllSubjectTableRows(): CatalogSubjectTableRow[] {
    return uniqueByName(
        catalogBranches().flatMap((branch) => catalogSubjectTableRowsForBranch(branch)),
    );
}

export function catalogSubjectsFor(
    branchName: string,
    departmentName: string,
): readonly CurriculumCatalogSubject[] {
    const branchMap =
        CURRICULUM_SUBJECTS_BY_BRANCH_DEPARTMENT[
            branchName as keyof typeof CURRICULUM_SUBJECTS_BY_BRANCH_DEPARTMENT
        ];
    if (!branchMap) {
        return [];
    }

    if (branchMap['*']) {
        return branchMap['*'];
    }

    return branchMap[departmentName] ?? [];
}

export function catalogBranches(): readonly string[] {
    return Object.keys(CURRICULUM_SUBJECTS_BY_BRANCH_DEPARTMENT);
}

export function catalogDepartmentsForBranch(branchName: string): readonly string[] {
    if (branchName in ADMISSION_DEPARTMENTS_BY_BRANCH) {
        const departments = ADMISSION_DEPARTMENTS_BY_BRANCH[branchName as AdmissionBranchName];
        const branchMap =
            CURRICULUM_SUBJECTS_BY_BRANCH_DEPARTMENT[
                branchName as keyof typeof CURRICULUM_SUBJECTS_BY_BRANCH_DEPARTMENT
            ];
        if (!branchMap) {
            return departments;
        }
        if (branchMap['*']) {
            return departments;
        }

        return departments.filter((name) => name in branchMap);
    }

    return [];
}
