/** Chrome Add actions open curriculum sheets instantly when already on /curriculum. */

export const SIS_OPEN_CREATE_SUBJECT_EVENT = 'sis:open-create-subject';
export const SIS_OPEN_CREATE_CURRICULUM_EVENT = 'sis:open-create-curriculum';

export function dispatchOpenCreateSubject(): void {
    window.dispatchEvent(new CustomEvent(SIS_OPEN_CREATE_SUBJECT_EVENT));
}

export function dispatchOpenCreateCurriculum(): void {
    window.dispatchEvent(new CustomEvent(SIS_OPEN_CREATE_CURRICULUM_EVENT));
}

export function isCurriculumWorkspacePath(url: string): boolean {
    const path = url.split('?')[0] ?? url;

    return path === '/curriculum';
}
