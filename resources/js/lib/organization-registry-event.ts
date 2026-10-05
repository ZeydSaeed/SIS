/**
 * Edit → تعديل المدرسة on admission pages opens the school chosen on the page
 * in «المديريات والمدارس».
 */

/** A school was picked on the admission page — the Edit tab edits that school. */
export const SIS_ADMISSION_SCHOOL_SELECTED_EVENT = 'sis:admission-school-selected';

export function dispatchAdmissionSchoolSelected(schoolId: number | null): void {
    window.dispatchEvent(
        new CustomEvent<number | null>(SIS_ADMISSION_SCHOOL_SELECTED_EVENT, { detail: schoolId }),
    );
}

export function directorateSchoolsUrl(schoolId: number | null, edit = false): string {
    if (schoolId === null) {
        return '/organization/directorates-schools';
    }

    return `/organization/directorates-schools?school=${schoolId}${edit ? '&edit=1' : ''}`;
}
