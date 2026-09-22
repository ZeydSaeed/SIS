/**
 * Placement completeness for enrollment list rows.
 * Mirrors EnrollStudentCommand required fields: year + class + section + effective_from.
 * Branch / department / specialization remain optional.
 */
export type EnrollmentRegistrationFields = {
    academic_year_id: number | string | null | undefined;
    class_id: number | string | null | undefined;
    section_id: number | string | null | undefined;
    effective_from: string | null | undefined;
};

function hasPositiveId(value: number | string | null | undefined): boolean {
    if (value === null || value === undefined || value === '') {
        return false;
    }

    const numeric = typeof value === 'number' ? value : Number(value);

    return Number.isFinite(numeric) && numeric > 0;
}

export function isEnrollmentRegistrationComplete(
    fields: EnrollmentRegistrationFields,
): boolean {
    const effectiveFrom =
        typeof fields.effective_from === 'string' ? fields.effective_from.trim() : '';

    return (
        hasPositiveId(fields.academic_year_id)
        && hasPositiveId(fields.class_id)
        && hasPositiveId(fields.section_id)
        && effectiveFrom !== ''
    );
}
