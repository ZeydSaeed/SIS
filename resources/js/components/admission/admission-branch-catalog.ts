/** Fixed vocational branch → department catalog for admission request form (RTL Arabic). */
export const ADMISSION_BRANCH_OPTIONS = [
    'الصناعي',
    'التجاري',
    'الزراعي',
    'الحاسوب وتقنية المعلومات',
    'الفنون التطبيقية',
    'السياحة والفندقة',
    'العلوم الرياضية',
] as const;

export type AdmissionBranchName = (typeof ADMISSION_BRANCH_OPTIONS)[number];

export const ADMISSION_DEPARTMENTS_BY_BRANCH: Record<AdmissionBranchName, readonly string[]> = {
    الصناعي: [
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
    'الحاسوب وتقنية المعلومات': [
        'تجميع وصيانة الحاسوب',
        'شبكات الحاسوب',
        'اجهزة الهاتف والحاسوب المحمولة',
        'الامن السبراني',
    ],
    الزراعي: ['زراعي'],
    التجاري: ['الادارة', 'المحاسبة'],
    'الفنون التطبيقية': ['فن التربية الاسرية', 'فن الديكور'],
    'السياحة والفندقة': ['الاسكان الفندقي', 'الضيافة وانتاج الاظعمة', 'الادارة السياحية'],
    'العلوم الرياضية': ['رياضة'],
};

export function departmentsForBranch(branchName: string): readonly string[] {
    if (branchName in ADMISSION_DEPARTMENTS_BY_BRANCH) {
        return ADMISSION_DEPARTMENTS_BY_BRANCH[branchName as AdmissionBranchName];
    }

    return [];
}
