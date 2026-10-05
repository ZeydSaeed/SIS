/**
 * Chrome Add → مدرسة / مديرية and Edit → تعديل المدرسة open the organization
 * registry sheets on admission pages (instantly when already there; via query flag otherwise).
 */

export const SIS_OPEN_SCHOOL_REGISTRY_EVENT = 'sis:open-school-registry';
export const SIS_OPEN_DIRECTORATE_REGISTRY_EVENT = 'sis:open-directorate-registry';
/** A school was picked on the admission page — the Edit tab edits that school. */
export const SIS_ADMISSION_SCHOOL_SELECTED_EVENT = 'sis:admission-school-selected';

export const SCHOOL_REGISTRY_QUERY_FLAG = 'manage_schools';
export const DIRECTORATE_REGISTRY_QUERY_FLAG = 'manage_directorates';

export function dispatchOpenSchoolRegistry(): void {
    window.dispatchEvent(new CustomEvent(SIS_OPEN_SCHOOL_REGISTRY_EVENT));
}

export function dispatchOpenDirectorateRegistry(): void {
    window.dispatchEvent(new CustomEvent(SIS_OPEN_DIRECTORATE_REGISTRY_EVENT));
}

export function dispatchAdmissionSchoolSelected(schoolId: number | null): void {
    window.dispatchEvent(
        new CustomEvent<number | null>(SIS_ADMISSION_SCHOOL_SELECTED_EVENT, { detail: schoolId }),
    );
}

export function isAdmissionWorkspacePath(url: string): boolean {
    const path = url.split('?')[0] ?? url;

    return path === '/admission' || path.startsWith('/admission/');
}
