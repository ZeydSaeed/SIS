export type AdmissionPeriod = {
    id: number;
    academic_year_id: number;
    school_id: number;
    name: string;
    start_date: string;
    end_date: string;
    max_applications: number | null;
    status: number;
    created_at: string;
};

export type AdmissionApplication = {
    id: number;
    application_period_id: number;
    application_number: string;
    first_name: string;
    father_name: string | null;
    grandfather_name: string | null;
    great_grandfather_name: string | null;
    last_name: string;
    mother_name: string | null;
    maternal_father_name: string | null;
    maternal_grandfather_name: string | null;
    national_id: string | null;
    birth_date: string;
    birth_place: string | null;
    gender: number;
    target_school_id: number | null;
    grade_level_id: number | null;
    intended_grade_name: string | null;
    department_name: string | null;
    specialization_id: number | null;
    specialization_name: string | null;
    governorate: string | null;
    neighborhood: string | null;
    status: number;
    submitted_at: string | null;
    reviewed_by: number | null;
    reviewed_at: string | null;
    notes: string | null;
    student_id: number | null;
    created_at: string;
    updated_at: string;
    allowed_transitions?: number[];
    can_convert?: boolean;
};

export type AdmissionDocumentRow = {
    id: number;
    application_id: number;
    document_type: number;
    storage_key?: string;
    file_name?: string;
    file_hash?: string;
    created_at?: string;
};

export type AdmissionNamedOption = { id: number; name: string };

export type AdmissionWorkflowStep = { status: number; key: string };

export type AdmissionWorkflowStagePercent = { status: number; percent: number };

export type AdmissionWorkflowProgress = {
    overall_percent: number;
    stages: AdmissionWorkflowStagePercent[];
};

export type AdmissionActivePeriodSummary = {
    id: number;
    name: string;
    start_date?: string;
    max_applications: number | null;
    total_count: number;
    submitted_count: number;
    remaining: number | null;
};

export type AdmissionPagination = {
    page: number;
    per_page: number;
    total: number;
    total_pages: number;
};

export type AdmissionWorkspace = {
    periods: AdmissionPeriod[];
    applications: AdmissionApplication[];
    documents: AdmissionDocumentRow[];
    grade_levels: AdmissionNamedOption[];
    schools: AdmissionNamedOption[];
    departments: AdmissionNamedOption[];
    specializations: AdmissionNamedOption[];
    workflow_steps: AdmissionWorkflowStep[];
    workflow_progress?: AdmissionWorkflowProgress;
    active_periods?: AdmissionActivePeriodSummary[];
    selected_period_id?: number | null;
    pagination?: AdmissionPagination;
    /** Shared once — use instead of per-row allowed_transitions. */
    status_transitions?: Record<number, number[]>;
};

export type AdmissionPageAuthorization = {
    can_manage: boolean;
};

export const ADMISSION_STATUS_DRAFT = 1;
export const ADMISSION_STATUS_SUBMITTED = 2;
export const ADMISSION_STATUS_UNDER_REVIEW = 3;
export const ADMISSION_STATUS_INTERVIEW = 4;
export const ADMISSION_STATUS_WAITLISTED = 5;
export const ADMISSION_STATUS_ACCEPTED = 6;
export const ADMISSION_STATUS_CONVERTED = 9;
export const ADMISSION_STATUS_REQUEST = 0;

/** Stage workspace routes — draft/submitted + pipeline pages. */
export const ADMISSION_STAGE_PATHS: Record<number, string> = {
    [ADMISSION_STATUS_DRAFT]: '/admission/drafts',
    [ADMISSION_STATUS_SUBMITTED]: '/admission/submitted',
    [ADMISSION_STATUS_UNDER_REVIEW]: '/admission/under-review',
    [ADMISSION_STATUS_INTERVIEW]: '/admission/interview',
    [ADMISSION_STATUS_WAITLISTED]: '/admission/waitlisted',
    [ADMISSION_STATUS_ACCEPTED]: '/admission/accepted',
    [ADMISSION_STATUS_CONVERTED]: '/admission/converted',
};

export const ADMISSION_PERIOD_FILTER_ALL = 0;

export function admissionWorkspaceQuery(
    academicYearId: number | null | undefined,
    periodId?: number | null,
    page?: number | null,
    search?: string | null,
): string {
    const params = new URLSearchParams();
    if (academicYearId != null) {
        params.set('academic_year_id', String(academicYearId));
    }
    if (periodId != null && periodId > 0) {
        params.set('application_period_id', String(periodId));
    } else if (periodId === ADMISSION_PERIOD_FILTER_ALL) {
        params.set('application_period_id', String(ADMISSION_PERIOD_FILTER_ALL));
    }
    if (page != null && page > 1) {
        params.set('page', String(page));
    }
    const queryText = search?.trim() ?? '';
    if (queryText !== '') {
        params.set('q', queryText);
    }
    const query = params.toString();

    return query === '' ? '' : `?${query}`;
}

export function admissionApplicationFullName(app: AdmissionApplication): string {
    return [
        app.first_name,
        app.father_name,
        app.grandfather_name,
        app.great_grandfather_name,
        app.last_name,
    ]
        .map((part) => part?.trim() ?? '')
        .filter((part) => part !== '')
        .join(' ');
}

/** Split a name into plain / match segments for yellow search highlighting. */
export function admissionSearchSegments(
    text: string,
    query: string,
): Array<{ text: string; hit: boolean }> {
    const tokens = query
        .trim()
        .split(/\s+/)
        .map((token) => token.trim())
        .filter((token) => token.length > 0);

    if (text === '' || tokens.length === 0) {
        return [{ text, hit: false }];
    }

    const pattern = tokens
        .map((token) => token.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'))
        .join('|');
    const matcher = new RegExp(`(${pattern})`, 'giu');
    const parts = text.split(matcher);

    return parts
        .filter((part) => part !== '')
        .map((part) => ({
            text: part,
            hit: tokens.some((token) =>
                part.toLocaleLowerCase('ar').includes(token.toLocaleLowerCase('ar')),
            ),
        }));
}

export function lookupName(
    options: AdmissionNamedOption[],
    id: number | null,
    fallback: string | null = null,
): string {
    if (id !== null) {
        const match = options.find((option) => option.id === id);

        if (match) {
            return match.name;
        }
    }

    const text = fallback?.trim() ?? '';

    return text !== '' ? text : '—';
}

export function admissionAllowedTransitions(
    workspace: AdmissionWorkspace,
    app: AdmissionApplication,
): number[] {
    return (
        workspace.status_transitions?.[app.status] ??
        app.allowed_transitions ??
        []
    );
}

export function admissionCanConvert(app: AdmissionApplication): boolean {
    if (typeof app.can_convert === 'boolean') {
        return app.can_convert;
    }

    return app.status === ADMISSION_STATUS_ACCEPTED;
}
