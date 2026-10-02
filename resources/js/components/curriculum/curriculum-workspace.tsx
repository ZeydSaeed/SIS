import { router, usePage } from '@inertiajs/react';
import {
    Eye,
    Filter,
    FilterX,
    Pencil,
    Trash2,
    XCircle,
} from 'lucide-react';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import {
    catalogAllSubjectTableRows,
    catalogBranches,
    catalogCurriculumTableRows,
    catalogDepartmentsForBranch,
    catalogSubjectsFor,
} from '@/components/curriculum/curriculum-subject-catalog';
import { CurriculumCreateSheetDialog } from '@/components/curriculum/curriculum-create-sheet';
import { CurriculumPlanSheetDialog } from '@/components/curriculum/curriculum-plan-sheet';
import { CurriculumSubjectSheetDialog } from '@/components/curriculum/curriculum-subject-sheet';
import {
    CURRICULUM_DISTRIBUTION_ACTIONS,
    CURRICULUM_STATUS_TABS,
    CURRICULUM_SUPERSEDED_ICON,
    editFilterEquals,
    matchesCurriculumEditFilter,
    type CurriculumEditFilter,
} from '@/components/curriculum/curriculum-edit-controls';
import { ConfirmDialog } from '@/components/sis/confirm-dialog';
import { OpsFormField, OpsTextInput } from '@/components/sis/ops-form-field';
import {
    formatAcademicYearOptionLabel,
    type YearOption,
} from '@/components/sis/ops-year-filter';
import { usePageError } from '@/components/sis/page-error-context';
import {
    useRegisterPageRibbon,
    type PageRibbonCommand,
    type PageRibbonGroup,
} from '@/components/sis/page-ribbon-context';
import { useRegisterPageTitlebarSearch } from '@/components/sis/page-titlebar-search-context';
import { SisListSelect } from '@/components/sis/sis-list-select';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { useFitTablePageSize } from '@/hooks/use-fit-table-page-size';
import { useResizableTableColumns } from '@/hooks/use-resizable-table-columns';
import { useSmoothVerticalScroll } from '@/hooks/use-smooth-vertical-scroll';
import { t } from '@/i18n';
import {
    SIS_OPEN_CREATE_CURRICULUM_EVENT,
    SIS_OPEN_CREATE_SUBJECT_EVENT,
} from '@/lib/curriculum-create-subject-event';
import {
    readStoredListPage,
    writeStoredListPage,
} from '@/lib/sis-list-page-storage';
import {
    resolveSisClassId,
    resolveSisClassKey,
    sisClassLabel,
    sisClassSelectOptions,
} from '@/lib/sis-class-section-options';

type CurriculumListView = 'subjects' | 'curricula';

export type CurriculumNestedSubject = {
    name: string;
    subject_type: number;
    credit_hours: number | null;
    max_grade: number | null;
};

export type CurriculumRow = {
    id: number;
    name: string;
    grade_level_id: number;
    grade_level_name: string | null;
    specialization_id: number | null;
    specialization_name: string | null;
    department_id: number | null;
    department_name: string | null;
    branch_id: number | null;
    branch_name: string | null;
    status: number;
    academic_year_id: number;
    school_id?: number;
    subjects?: CurriculumNestedSubject[];
};

export type SubjectRow = {
    id: number;
    code: string;
    name: string;
    name_en?: string | null;
    subject_type: number;
    credit_hours: number | null;
    max_grade: number;
    pass_grade: number;
    status: number;
    prerequisites?: string;
    prerequisite_subject_ids?: number[];
};

export type LinkedSubjectRow = {
    id: number;
    curriculum_id: number;
    subject_id: number;
    subject_code: string | null;
    subject_name: string | null;
    weekly_hours: number | null;
    is_required: boolean;
    subject_order: number;
    status: number;
};

export type CurriculumPagination = {
    page: number;
    per_page: number;
    total: number;
    last_page: number;
};

export type CurriculumFilterOptions = {
    classes: Array<{ id: number; code: string; name: string; grade_level_id: number }>;
    branches: Array<{ id: number; code: string; name: string }>;
    departments: Array<{ id: number; branch_id: number | null; code: string; name: string }>;
    specializations: Array<{
        id: number;
        department_id: number | null;
        code: string;
        name: string;
    }>;
};

export type CurriculumPageFilters = {
    academic_year_id: number | null;
    /** When true, curricula list spans every academic year (overview mode). */
    all_curricula: boolean;
    curriculum_id: number | null;
    q: string;
    status: number | null;
    class_id: number | null;
    grade_level_id: number | null;
    specialization_id: number | null;
    branch_id: number | null;
    /** Vocational catalog branch name (SSOT). */
    branch: string;
    /** Vocational catalog specialization / department name (SSOT). */
    specialization: string;
    page: number;
    per_page: number;
    subject_q: string;
    subject_status: number | null;
    subject_type: number | null;
    subject_page: number;
    subject_per_page: number;
};

export type CurriculumPageProps = {
    curricula: { data: CurriculumRow[]; pagination: CurriculumPagination };
    subjects: { data: SubjectRow[]; pagination: CurriculumPagination };
    linkedSubjects: LinkedSubjectRow[];
    filters: CurriculumPageFilters;
    filterOptions: CurriculumFilterOptions;
    authorization: {
        canView: boolean;
        canManage: boolean;
    };
};

type ConfirmState =
    | { kind: 'plan-deactivate'; id: number }
    | { kind: 'plan-reactivate'; id: number }
    | { kind: 'subject-deactivate'; id: number }
    | { kind: 'subject-reactivate'; id: number }
    | { kind: 'link-deactivate'; id: number }
    | { kind: 'link-reactivate'; id: number }
    | { kind: 'subjects-delete'; ids: number[]; count: number }
    | null;

export function newIdempotencyKey(prefix: string): string {
    if (typeof crypto !== 'undefined' && 'randomUUID' in crypto) {
        return `${prefix}-${crypto.randomUUID()}`;
    }

    return `${prefix}-${Date.now()}-${Math.random().toString(16).slice(2)}`;
}

export function postWithIdempotency(
    url: string,
    data: Record<string, string | number | boolean | null | undefined> = {},
): void {
    router.post(url, data, {
        preserveScroll: true,
        headers: { 'X-Idempotency-Key': newIdempotencyKey('curriculum') },
    });
}

export function patchWithIdempotency(
    url: string,
    data: Record<string, string | number | boolean | null | undefined>,
): void {
    router.patch(url, data, {
        preserveScroll: true,
        headers: { 'X-Idempotency-Key': newIdempotencyKey('curriculum') },
    });
}

export function statusLabel(
    status: number | string | null | undefined,
    active: string,
    inactive: string,
): string {
    return Number(status) === 1 ? active : inactive;
}

export function subjectTypeLabel(
    type: number,
    i18n: ReturnType<typeof t>['curriculum'],
): string {
    if (type === 2) {
        return i18n.subjectTypeSpecialization;
    }
    if (type === 3) {
        return i18n.subjectTypePractical;
    }

    return i18n.subjectTypeCore;
}

const CURRICULUM_FILTERS_STORAGE_KEY = 'sis.curriculum.pageFilters.v1';

type CurriculumStoredFilters = {
    year: string;
    branch: string;
    specialization: string;
    classKey: string;
};

function readCurriculumStoredFilters(): CurriculumStoredFilters | null {
    if (typeof window === 'undefined') {
        return null;
    }

    try {
        const raw = window.sessionStorage.getItem(CURRICULUM_FILTERS_STORAGE_KEY);
        if (!raw) {
            return null;
        }

        const parsed = JSON.parse(raw) as Partial<CurriculumStoredFilters>;

        return {
            year: typeof parsed.year === 'string' ? parsed.year : '',
            branch: typeof parsed.branch === 'string' ? parsed.branch : '',
            specialization:
                typeof parsed.specialization === 'string' ? parsed.specialization : '',
            classKey: typeof parsed.classKey === 'string' ? parsed.classKey : '',
        };
    } catch {
        return null;
    }
}

function writeCurriculumStoredFilters(next: CurriculumStoredFilters): void {
    if (typeof window === 'undefined') {
        return;
    }

    try {
        window.sessionStorage.setItem(CURRICULUM_FILTERS_STORAGE_KEY, JSON.stringify(next));
    } catch {
        // Ignore quota / private-mode failures — UI still works in-memory.
    }
}

function clearCurriculumStoredFilters(): void {
    if (typeof window === 'undefined') {
        return;
    }

    try {
        window.sessionStorage.removeItem(CURRICULUM_FILTERS_STORAGE_KEY);
    } catch {
        // Ignore storage failures.
    }
}

function omitEmpty(
    params: Record<string, string | number | null | undefined>,
): Record<string, string | number> {
    const out: Record<string, string | number> = {};
    for (const [key, value] of Object.entries(params)) {
        if (value === null || value === undefined || value === '') {
            continue;
        }
        out[key] = value;
    }

    return out;
}

/** Rows that fill the table card — same density as students/enrollments. */
const CURRICULUM_PER_PAGE = 17;

const emptyPagination = (): CurriculumPagination => ({
    page: 1,
    per_page: CURRICULUM_PER_PAGE,
    total: 0,
    last_page: 1,
});

function visiblePages(current: number, totalPages: number): number[] {
    const windowSize = 5;
    if (totalPages <= windowSize) {
        return Array.from({ length: totalPages }, (_, index) => index + 1);
    }

    const half = Math.floor(windowSize / 2);
    let start = Math.max(1, current - half);
    let end = start + windowSize - 1;
    if (end > totalPages) {
        end = totalPages;
        start = Math.max(1, end - windowSize + 1);
    }

    return Array.from({ length: end - start + 1 }, (_, index) => start + index);
}

type CurriculumTableFilterContextLabels = {
    academicYear: string;
    branch: string;
    specialization: string;
    gradeLevel: string;
};

function CurriculumTableFilterContext({
    ariaLabel,
    labels,
    context,
}: {
    ariaLabel: string;
    labels: CurriculumTableFilterContextLabels;
    context: {
        year: string;
        branch: string;
        specialization: string;
        className: string;
    };
}): JSX.Element {
    return (
        <div className="sis-curriculum-table-context" dir="rtl" aria-label={ariaLabel}>
            <span className="sis-curriculum-table-context__item">
                <span className="sis-curriculum-table-context__label">{labels.academicYear}</span>
                <span className="sis-curriculum-table-context__value">{context.year}</span>
            </span>
            <span className="sis-curriculum-table-context__item">
                <span className="sis-curriculum-table-context__label">{labels.branch}</span>
                <span className="sis-curriculum-table-context__value">{context.branch}</span>
            </span>
            <span className="sis-curriculum-table-context__item">
                <span className="sis-curriculum-table-context__label">
                    {labels.specialization}
                </span>
                <span className="sis-curriculum-table-context__value">
                    {context.specialization}
                </span>
            </span>
            <span className="sis-curriculum-table-context__item">
                <span className="sis-curriculum-table-context__label">{labels.gradeLevel}</span>
                <span className="sis-curriculum-table-context__value">{context.className}</span>
            </span>
        </div>
    );
}

/** Accepts {data,pagination} or a legacy bare array so a stale build never blank-screens. */
function normalizePagedRows<T>(
    value: { data: T[]; pagination: CurriculumPagination } | T[] | null | undefined,
): { data: T[]; pagination: CurriculumPagination } {
    if (Array.isArray(value)) {
        return { data: value, pagination: emptyPagination() };
    }
    if (value && Array.isArray(value.data)) {
        return {
            data: value.data,
            pagination: value.pagination ?? emptyPagination(),
        };
    }

    return { data: [], pagination: emptyPagination() };
}

export function CurriculumWorkspace(props: CurriculumPageProps) {
    const curricula = useMemo(
        () => normalizePagedRows<CurriculumRow>(props.curricula),
        [props.curricula],
    );
    const subjects = useMemo(
        () => normalizePagedRows<SubjectRow>(props.subjects),
        [props.subjects],
    );
    const linkedSubjects = Array.isArray(props.linkedSubjects) ? props.linkedSubjects : [];
    const filters = useMemo<CurriculumPageFilters>(
        () => ({
            academic_year_id: props.filters?.academic_year_id ?? null,
            all_curricula: Boolean(props.filters?.all_curricula),
            curriculum_id: props.filters?.curriculum_id ?? null,
            q: props.filters?.q ?? '',
            status: props.filters?.status ?? null,
            class_id: props.filters?.class_id ?? null,
            grade_level_id: props.filters?.grade_level_id ?? null,
            specialization_id: props.filters?.specialization_id ?? null,
            branch_id: props.filters?.branch_id ?? null,
            branch: props.filters?.branch ?? '',
            specialization: props.filters?.specialization ?? '',
            page: props.filters?.page ?? 1,
            per_page: props.filters?.per_page ?? CURRICULUM_PER_PAGE,
            subject_q: props.filters?.subject_q ?? '',
            subject_status: props.filters?.subject_status ?? null,
            subject_type: props.filters?.subject_type ?? null,
            subject_page: props.filters?.subject_page ?? 1,
            subject_per_page: props.filters?.subject_per_page ?? CURRICULUM_PER_PAGE,
        }),
        [props.filters],
    );
    const filterOptions = useMemo<CurriculumFilterOptions>(
        () => ({
            classes: props.filterOptions?.classes ?? [],
            branches: props.filterOptions?.branches ?? [],
            departments: props.filterOptions?.departments ?? [],
            specializations: props.filterOptions?.specializations ?? [],
        }),
        [props.filterOptions],
    );
    const i18n = t();
    const c = i18n.curriculum;
    const { showWarning } = usePageError();
    const [planCreateOpen, setPlanCreateOpen] = useState(false);
    const [planSheetPlan, setPlanSheetPlan] = useState<CurriculumRow | null>(null);
    const [planSheetEditing, setPlanSheetEditing] = useState(false);
    const [subjectCreateOpen, setSubjectCreateOpen] = useState(false);
    const [subjectSheetSubjects, setSubjectSheetSubjects] = useState<SubjectRow[]>([]);
    const [subjectSheetEditing, setSubjectSheetEditing] = useState(false);
    const [linkDialogOpen, setLinkDialogOpen] = useState(false);
    const [confirm, setConfirm] = useState<ConfirmState>(null);
    const [confirmPending, setConfirmPending] = useState(false);
    const [activeView, setActiveView] = useState<CurriculumListView>('subjects');
    const [editFilter, setEditFilter] = useState<CurriculumEditFilter>({ kind: 'all' });
    const [selectedNames, setSelectedNames] = useState<string[]>([]);
    const [listPage, setListPage] = useState(() => readStoredListPage('curriculum', 1));
    const scrollerRef = useRef<HTMLDivElement>(null);
    const canManage = props.authorization?.canManage === true;
    const pageUrl = usePage().url;

    useEffect(() => {
        if (!canManage) {
            return;
        }

        const onOpenCreateSubject = (): void => {
            setActiveView('subjects');
            setSubjectCreateOpen(true);
        };

        const onOpenCreateCurriculum = (): void => {
            setActiveView('curricula');
            setPlanSheetPlan(null);
            setPlanSheetEditing(false);
            setPlanCreateOpen(true);
        };

        window.addEventListener(SIS_OPEN_CREATE_SUBJECT_EVENT, onOpenCreateSubject);
        window.addEventListener(SIS_OPEN_CREATE_CURRICULUM_EVENT, onOpenCreateCurriculum);

        return () => {
            window.removeEventListener(SIS_OPEN_CREATE_SUBJECT_EVENT, onOpenCreateSubject);
            window.removeEventListener(SIS_OPEN_CREATE_CURRICULUM_EVENT, onOpenCreateCurriculum);
        };
    }, [canManage]);

    useEffect(() => {
        const [path, query = ''] = pageUrl.split('?');
        const params = new URLSearchParams(query);
        const createSubject = params.get('create_subject') === '1';
        const createCurriculum = params.get('create_curriculum') === '1';
        if ((!createSubject && !createCurriculum) || !canManage) {
            return;
        }

        if (createSubject) {
            setActiveView('subjects');
            setSubjectCreateOpen(true);
            params.delete('create_subject');
        }
        if (createCurriculum) {
            setActiveView('curricula');
            setPlanCreateOpen(true);
            params.delete('create_curriculum');
        }
        const next = params.toString();
        router.visit(next === '' ? path : `${path}?${next}`, {
            replace: true,
            preserveState: true,
            preserveScroll: true,
            showProgress: false,
        });
    }, [canManage, pageUrl]);

    const fitPageSize = useFitTablePageSize(scrollerRef, {
        fallbackRows: CURRICULUM_PER_PAGE,
        enabled: true,
        remountKey: activeView,
    });

    const { academicYears } = usePage().props as { academicYears?: YearOption[] };
    const yearCatalog = academicYears ?? [];

    // Filters persist in session until the user changes or clears them.
    const [selectedYear, setSelectedYear] = useState(() => readCurriculumStoredFilters()?.year ?? '');
    const [selectedBranch, setSelectedBranch] = useState(
        () => readCurriculumStoredFilters()?.branch ?? filters.branch,
    );
    const [selectedSpec, setSelectedSpec] = useState(
        () => readCurriculumStoredFilters()?.specialization ?? filters.specialization,
    );
    const [selectedClass, setSelectedClass] = useState(() => {
        const stored = readCurriculumStoredFilters();
        if (stored?.classKey) {
            return stored.classKey;
        }

        return resolveSisClassKey(filters.class_id, filterOptions.classes);
    });
    const filtersHydratedRef = useRef(false);

    const selectedPlan = useMemo(
        () => curricula.data.find((row) => row.id === filters.curriculum_id) ?? null,
        [curricula.data, filters.curriculum_id],
    );

    const catalogBranchOptions = useMemo(() => catalogBranches(), []);
    const catalogSpecOptions = useMemo(
        () =>
            selectedBranch === ''
                ? catalogBranchOptions.flatMap((branch) =>
                      catalogDepartmentsForBranch(branch).map((name) => ({
                          branch,
                          name,
                      })),
                  )
                : catalogDepartmentsForBranch(selectedBranch).map((name) => ({
                      branch: selectedBranch,
                      name,
                  })),
        [catalogBranchOptions, selectedBranch],
    );
    const classSelectOptions = useMemo(() => sisClassSelectOptions(), []);

    const currentYearId = useMemo(
        () => yearCatalog.find((year) => year.is_current)?.id ?? filters.academic_year_id,
        [filters.academic_year_id, yearCatalog],
    );

    const visitIndex = (
        overrides: Partial<CurriculumPageFilters> = {},
        only?: string[],
    ): void => {
        const next: CurriculumPageFilters = { ...filters, ...overrides };
        const showAll =
            Object.prototype.hasOwnProperty.call(overrides, 'all_curricula')
                ? Boolean(overrides.all_curricula)
                : next.all_curricula;
        // Explicit academic_year_id in overrides always wins — setState is async, so
        // reading selectedYear here would re-send the previous year on every change.
        const yearForRequest = showAll
            ? null
            : Object.prototype.hasOwnProperty.call(overrides, 'academic_year_id')
              ? (overrides.academic_year_id ?? currentYearId)
              : selectedYear !== ''
                ? Number(selectedYear)
                : (currentYearId ?? filters.academic_year_id);

        router.get(
            '/curriculum',
            {
                ...omitEmpty({
                    academic_year_id: yearForRequest ?? undefined,
                    all_curricula: showAll ? 1 : undefined,
                    curriculum_id: next.curriculum_id,
                    q: next.q,
                    status: next.status,
                    page: next.page,
                    per_page: showAll ? 100 : next.per_page,
                    subject_q: next.subject_q,
                    subject_status: next.subject_status,
                    subject_type: next.subject_type,
                    subject_page: next.subject_page,
                    subject_per_page: next.subject_per_page,
                }),
                branch: showAll ? '' : next.branch,
                specialization: showAll ? '' : next.specialization,
                class_id: showAll ? '' : (next.class_id ?? ''),
            },
            {
                preserveState: true,
                preserveScroll: true,
                ...(only ? { only } : {}),
            },
        );
    };

    // Persist UI filters so they stay until the user changes or clears them.
    useEffect(() => {
        writeCurriculumStoredFilters({
            year: selectedYear,
            branch: selectedBranch,
            specialization: selectedSpec,
            classKey: selectedClass,
        });
    }, [selectedBranch, selectedClass, selectedSpec, selectedYear]);

    // On first mount, re-apply stored filters to the request if the URL lagged behind.
    useEffect(() => {
        if (filtersHydratedRef.current) {
            return;
        }
        filtersHydratedRef.current = true;

        const stored = readCurriculumStoredFilters();
        if (!stored) {
            return;
        }

        const hasStored =
            stored.year !== '' ||
            stored.branch !== '' ||
            stored.specialization !== '' ||
            stored.classKey !== '';
        if (!hasStored) {
            return;
        }

        const classId =
            stored.classKey === ''
                ? null
                : resolveSisClassId(stored.classKey, filterOptions.classes);
        const yearId =
            stored.year !== ''
                ? Number(stored.year)
                : (currentYearId ?? filters.academic_year_id);

        const urlMatches =
            (stored.branch === (filters.branch ?? '')) &&
            (stored.specialization === (filters.specialization ?? '')) &&
            ((classId ?? null) === (filters.class_id ?? null)) &&
            (stored.year === '' || Number(stored.year) === filters.academic_year_id);

        if (urlMatches) {
            return;
        }

        visitIndex({
            academic_year_id: yearId,
            branch: stored.branch,
            specialization: stored.specialization,
            class_id: classId,
            grade_level_id: null,
        });
        // One-shot hydration only.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    // Sticky empty years (e.g. 2025-2026) hide students/enrollments/admission — pin back to current.
    useEffect(() => {
        if (filters.all_curricula) {
            return;
        }

        if (selectedYear !== '') {
            return;
        }

        if (currentYearId === null || currentYearId === undefined) {
            return;
        }

        if (filters.academic_year_id === currentYearId) {
            return;
        }

        visitIndex({ academic_year_id: currentYearId, all_curricula: false });
        // Mount / year-catalog sync only — visitIndex closes over latest filters.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [currentYearId, filters.academic_year_id, filters.all_curricula, selectedYear]);

    const titlebarSearch = useMemo(
        () => ({
            committedQuery: filters.q,
            label: c.searchAria,
            placeholder: c.search,
            onCommit: (query: string) => {
                visitIndex({ q: query });
            },
        }),
        // visitIndex reads latest filters via closure when commit fires
        // eslint-disable-next-line react-hooks/exhaustive-deps
        [c.search, c.searchAria, filters.q],
    );

    useRegisterPageTitlebarSearch(titlebarSearch);

    const yearFilterLabel = useMemo(() => {
        if (selectedYear === '') {
            return c.academicYear;
        }
        const match = yearCatalog.find((item) => item.id.toString() === selectedYear);

        return match
            ? formatAcademicYearOptionLabel(match.name, match.code)
            : c.academicYear;
    }, [c.academicYear, selectedYear, yearCatalog]);

    const classFilterLabel = useMemo(() => {
        if (selectedClass === '') {
            return c.gradeLevel;
        }

        return (
            classSelectOptions.find((item) => item.value === selectedClass)?.label ?? c.gradeLevel
        );
    }, [c.gradeLevel, classSelectOptions, selectedClass]);

    const tableFilterContext = useMemo(() => {
        const yearLabel =
            selectedYear === ''
                ? c.filterDefault
                : (() => {
                      const match = yearCatalog.find((item) => item.id.toString() === selectedYear);
                      return match
                          ? formatAcademicYearOptionLabel(match.name, match.code)
                          : c.filterDefault;
                  })();
        const branchLabel = selectedBranch === '' ? c.filterDefault : selectedBranch;
        const specLabel = selectedSpec === '' ? c.filterDefault : selectedSpec;
        const classLabel =
            selectedClass === ''
                ? c.filterDefault
                : (classSelectOptions.find((item) => item.value === selectedClass)?.label ??
                  c.filterDefault);

        return {
            year: yearLabel,
            branch: branchLabel,
            specialization: specLabel,
            className: classLabel,
        };
    }, [
        c.filterDefault,
        classSelectOptions,
        selectedBranch,
        selectedClass,
        selectedSpec,
        selectedYear,
        yearCatalog,
    ]);

    const clearPageFilters = useCallback((): void => {
        setSelectedYear('');
        setSelectedBranch('');
        setSelectedSpec('');
        setSelectedClass('');
        clearCurriculumStoredFilters();
        visitIndex({
            academic_year_id: currentYearId ?? filters.academic_year_id,
            all_curricula: false,
            branch: '',
            specialization: '',
            class_id: null,
            grade_level_id: null,
        });
        // visitIndex closes over latest filters/state when invoked
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [currentYearId, filters.academic_year_id]);

    const showAllCurricula = useCallback((): void => {
        setSelectedYear('');
        setSelectedBranch('');
        setSelectedSpec('');
        setSelectedClass('');
        clearCurriculumStoredFilters();
        setActiveView('curricula');
        visitIndex({
            academic_year_id: null,
            all_curricula: true,
            branch: '',
            specialization: '',
            class_id: null,
            grade_level_id: null,
            specialization_id: null,
            branch_id: null,
            per_page: 100,
        });
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    const homeRibbonGroups = useMemo((): PageRibbonGroup[] => {
        return [
            {
                id: 'curriculum-filters',
                label: c.ribbonFilters,
                commands: [],
                custom: (
                    <div className="sis-ribbon__filters" aria-label={c.filtersTitle} dir="rtl">
                        <div className="sis-ribbon__filters-stack sis-ribbon__filters-stack--curriculum">
                            <div className="sis-ribbon__filter-field" dir="rtl">
                                <span className="sis-admission-select-fit">
                                    <span className="sis-admission-select-fit__mirror" aria-hidden="true">
                                        {yearFilterLabel}
                                    </span>
                                    <SisListSelect
                                        value={selectedYear}
                                        options={[
                                            { value: '', label: c.academicYear },
                                            ...yearCatalog.map((item) => ({
                                                value: item.id.toString(),
                                                label: formatAcademicYearOptionLabel(
                                                    item.name,
                                                    item.code,
                                                ),
                                            })),
                                        ]}
                                        onChange={(next) => {
                                            setSelectedYear(next);
                                            const yearId =
                                                next !== ''
                                                    ? Number(next)
                                                    : currentYearId;
                                            if (yearId === null || yearId === undefined) {
                                                return;
                                            }
                                            visitIndex({
                                                academic_year_id: yearId,
                                                all_curricula: false,
                                            });
                                        }}
                                        triggerClassName="sis-ops-hub__link px-2 py-1 min-h-0 min-w-0 sis-admission-year-control"
                                        dir="rtl"
                                        ariaLabel={c.academicYear}
                                    />
                                </span>
                            </div>
                            <div className="sis-ribbon__filter-field" dir="rtl">
                                <span className="sis-admission-select-fit">
                                    <span className="sis-admission-select-fit__mirror" aria-hidden="true">
                                        {classFilterLabel}
                                    </span>
                                    <SisListSelect
                                        value={selectedClass}
                                        options={[
                                            { value: '', label: c.gradeLevel },
                                            ...classSelectOptions,
                                        ]}
                                        onChange={(next) => {
                                            setSelectedClass(next);
                                            const classId =
                                                next === ''
                                                    ? null
                                                    : resolveSisClassId(next, filterOptions.classes);
                                            visitIndex({
                                                class_id: classId,
                                                grade_level_id: null,
                                                all_curricula: false,
                                            });
                                        }}
                                        triggerClassName="sis-ops-hub__link px-2 py-1 min-h-0 min-w-0 sis-admission-year-control"
                                        dir="rtl"
                                        ariaLabel={c.gradeLevel}
                                    />
                                </span>
                            </div>
                            <div className="sis-ribbon__filter-field" dir="rtl">
                                <span className="sis-admission-select-fit">
                                    <span className="sis-admission-select-fit__mirror" aria-hidden="true">
                                        {selectedBranch === '' ? c.branch : selectedBranch}
                                    </span>
                                    <SisListSelect
                                        value={selectedBranch}
                                        options={[
                                            { value: '', label: c.branch },
                                            ...catalogBranchOptions.map((name) => ({
                                                value: name,
                                                label: name,
                                            })),
                                        ]}
                                        onChange={(next) => {
                                            setSelectedBranch(next);
                                            setSelectedSpec('');
                                            visitIndex({
                                                branch: next,
                                                specialization: '',
                                                all_curricula: false,
                                            });
                                        }}
                                        triggerClassName="sis-ops-hub__link px-2 py-1 min-h-0 min-w-0 sis-admission-year-control"
                                        dir="rtl"
                                        ariaLabel={c.branch}
                                    />
                                </span>
                            </div>
                            <div className="sis-ribbon__filter-field" dir="rtl">
                                <span className="sis-admission-select-fit">
                                    <span className="sis-admission-select-fit__mirror" aria-hidden="true">
                                        {selectedSpec === '' ? c.specialization : selectedSpec}
                                    </span>
                                    <SisListSelect
                                        value={selectedSpec}
                                        options={[
                                            { value: '', label: c.specialization },
                                            ...catalogSpecOptions.map((item) => ({
                                                value: item.name,
                                                label: item.name,
                                            })),
                                        ]}
                                        onChange={(next) => {
                                            setSelectedSpec(next);
                                            visitIndex({
                                                specialization: next,
                                                all_curricula: false,
                                            });
                                        }}
                                        triggerClassName="sis-ops-hub__link px-2 py-1 min-h-0 min-w-0 sis-admission-year-control"
                                        dir="rtl"
                                        ariaLabel={c.specialization}
                                    />
                                </span>
                            </div>
                        </div>
                        <button
                            type="button"
                            className="sis-ribbon__item sis-ribbon__item--filter-clear"
                            aria-label={c.clearFiltersAria}
                            title={c.clearFiltersAria}
                            onClick={clearPageFilters}
                        >
                            <FilterX className="sis-ribbon__icon" aria-hidden />
                            <span className="sis-ribbon__label">{c.clearFilters}</span>
                        </button>
                        <button
                            type="button"
                            className={
                                filters.all_curricula
                                    ? 'sis-ribbon__item sis-ribbon__item--filter-clear is-active'
                                    : 'sis-ribbon__item sis-ribbon__item--filter-clear'
                            }
                            aria-label={c.showAllCurriculaAria}
                            title={c.showAllCurriculaAria}
                            aria-pressed={filters.all_curricula}
                            onClick={showAllCurricula}
                        >
                            <Filter className="sis-ribbon__icon" aria-hidden />
                            <span className="sis-ribbon__label">{c.showAllCurricula}</span>
                        </button>
                    </div>
                ),
            },
        ];
        // visitIndex / setters close over latest values when commands fire
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [
        c,
        catalogBranchOptions,
        catalogSpecOptions,
        classFilterLabel,
        classSelectOptions,
        clearPageFilters,
        currentYearId,
        filterOptions.classes,
        filters.all_curricula,
        selectedBranch,
        selectedClass,
        selectedSpec,
        selectedYear,
        showAllCurricula,
        yearCatalog,
        yearFilterLabel,
    ]);

    useRegisterPageRibbon('home', homeRibbonGroups);

    const subjectsByName = useMemo(() => {
        const map = new Map<string, SubjectRow>();
        for (const row of subjects.data) {
            if (!map.has(row.name)) {
                map.set(row.name, row);
            }
        }

        return map;
    }, [subjects.data]);

    const catalogSubjectRows = useMemo(() => {
        const query = filters.q.trim().toLowerCase();
        const catalogNames = new Set<string>();
        const rows: Array<{
            id: number | null;
            name: string;
            subject_type: number;
            credit_hours: number | null;
            max_grade: number;
            pass_grade: number;
            status: number;
            prerequisites: string;
        }> = catalogAllSubjectTableRows().map((row) => {
            const fromDb = subjectsByName.get(row.name);
            catalogNames.add(row.name);

            return {
                id: fromDb?.id ?? null,
                name: row.name,
                subject_type: fromDb?.subject_type ?? row.subject_type,
                credit_hours: fromDb?.credit_hours ?? row.credit_hours,
                max_grade: fromDb?.max_grade ?? row.max_grade,
                pass_grade: fromDb?.pass_grade ?? row.pass_grade,
                status: fromDb?.status ?? row.status,
                prerequisites: fromDb?.prerequisites?.trim() || '',
            };
        });

        for (const fromDb of subjects.data) {
            if (catalogNames.has(fromDb.name)) {
                continue;
            }
            rows.push({
                id: fromDb.id,
                name: fromDb.name,
                subject_type: fromDb.subject_type,
                credit_hours: fromDb.credit_hours,
                max_grade: fromDb.max_grade,
                pass_grade: fromDb.pass_grade,
                status: fromDb.status,
                prerequisites: fromDb.prerequisites?.trim() || '',
            });
        }

        return rows.filter((row) => {
            if (query === '') {
                return true;
            }

            return row.name.toLowerCase().includes(query);
        });
    }, [filters.q, subjects.data, subjectsByName]);

    const visibleSubjectRows = useMemo(
        () =>
            catalogSubjectRows.filter((row) =>
                matchesCurriculumEditFilter(row, editFilter),
            ),
        [catalogSubjectRows, editFilter],
    );

    const catalogCurriculumRows = useMemo(() => {
        const classLabel = selectedClass === '' ? '' : sisClassLabel(selectedClass);

        // Overview: every curriculum across years (DB) or full catalog matrix.
        if (filters.all_curricula) {
            if (curricula.data.length > 0) {
                return curricula.data.map((row) => ({
                    key: `db-${row.id}`,
                    branch_name: row.branch_name ?? '',
                    specialization_name: row.specialization_name ?? '',
                    class_name: row.grade_level_name ?? '',
                    subjects: (row.subjects ?? []).map((subject) => ({
                        name: subject.name,
                        subject_type: subject.subject_type,
                        credit_hours: subject.credit_hours ?? 0,
                        max_grade: subject.max_grade ?? 0,
                    })),
                }));
            }

            return catalogCurriculumTableRows();
        }

        const yearId =
            selectedYear !== ''
                ? Number(selectedYear)
                : (filters.academic_year_id ?? null);

        // Prefer DB curricula for the active academic year so the year filter is visible.
        const dbMatches = curricula.data.filter((row) => {
            if (yearId !== null && row.academic_year_id !== yearId) {
                return false;
            }
            if (selectedBranch !== '' && (row.branch_name ?? '') !== selectedBranch) {
                return false;
            }
            if (selectedSpec !== '' && (row.specialization_name ?? '') !== selectedSpec) {
                return false;
            }
            if (classLabel !== '') {
                const classOk =
                    (row.grade_level_name ?? '') === classLabel
                    || filterOptions.classes.some(
                        (item) =>
                            item.grade_level_id === row.grade_level_id
                            && (item.name === classLabel || String(item.id) === selectedClass),
                    );
                if (!classOk) {
                    return false;
                }
            }

            return true;
        });

        if (dbMatches.length > 0) {
            return dbMatches.map((row) => ({
                key: `db-${row.id}`,
                branch_name: row.branch_name ?? '',
                specialization_name: row.specialization_name ?? '',
                class_name: row.grade_level_name ?? classLabel,
                subjects: (row.subjects ?? []).map((subject) => ({
                    name: subject.name,
                    subject_type: subject.subject_type,
                    credit_hours: subject.credit_hours ?? 0,
                    max_grade: subject.max_grade ?? 0,
                })),
            }));
        }

        // Explicit year with no DB curricula → empty list (year filter applied).
        if (selectedYear !== '') {
            return [];
        }

        // No explicit year yet — catalog matrix for planning until curricula exist.
        let rows = catalogCurriculumTableRows();

        if (selectedBranch !== '') {
            rows = rows.filter((row) => row.branch_name === selectedBranch);
        }
        if (selectedSpec !== '') {
            rows = rows.filter((row) => row.specialization_name === selectedSpec);
        }
        if (classLabel !== '') {
            rows = rows.filter((row) => row.class_name === classLabel);
        }

        return rows;
    }, [
        curricula.data,
        filterOptions.classes,
        filters.academic_year_id,
        filters.all_curricula,
        selectedBranch,
        selectedClass,
        selectedSpec,
        selectedYear,
    ]);

    /** Curricula tab: subjects of filtered plans (all plans when no filters). */
    const curriculumPlanSubjectRows = useMemo(() => {
        const source = catalogCurriculumRows;
        const seen = new Set<string>();
        const out: Array<{
            id: number | null;
            name: string;
            subject_type: number;
            credit_hours: number;
            max_grade: number;
            status: number;
        }> = [];

        for (const row of source) {
            for (const subject of row.subjects) {
                if (seen.has(subject.name)) {
                    continue;
                }
                seen.add(subject.name);
                const fromDb = subjectsByName.get(subject.name);
                out.push({
                    ...subject,
                    id: fromDb?.id ?? null,
                    status: fromDb?.status ?? 1,
                });
            }
        }

        return out;
    }, [catalogCurriculumRows, subjectsByName]);

    const visiblePlanSubjectRows = useMemo(
        () =>
            curriculumPlanSubjectRows.filter((row) =>
                matchesCurriculumEditFilter(row, editFilter),
            ),
        [curriculumPlanSubjectRows, editFilter],
    );

    const subjectLastPage = Math.max(
        1,
        Math.ceil(visibleSubjectRows.length / fitPageSize),
    );
    const planLastPage = Math.max(
        1,
        Math.ceil(visiblePlanSubjectRows.length / fitPageSize),
    );
    const subjectPage = Math.min(listPage, subjectLastPage);
    const planPage = Math.min(listPage, planLastPage);
    const subjectRowOffset = (subjectPage - 1) * fitPageSize;
    const planRowOffset = (planPage - 1) * fitPageSize;

    const pagedSubjectRows = useMemo(
        () =>
            visibleSubjectRows.slice(
                subjectRowOffset,
                subjectRowOffset + fitPageSize,
            ),
        [fitPageSize, subjectRowOffset, visibleSubjectRows],
    );
    const pagedPlanSubjectRows = useMemo(
        () =>
            visiblePlanSubjectRows.slice(
                planRowOffset,
                planRowOffset + fitPageSize,
            ),
        [fitPageSize, planRowOffset, visiblePlanSubjectRows],
    );

    const hasSubjectSearch = filters.q.trim() !== '';

    const selectedSet = useMemo(() => new Set(selectedNames), [selectedNames]);
    const hasSelection = selectedNames.length > 0;

    const toggleSelectedName = useCallback((name: string): void => {
        setSelectedNames((prev) =>
            prev.includes(name) ? prev.filter((item) => item !== name) : [...prev, name],
        );
    }, []);

    const clearSelection = useCallback((): void => {
        setSelectedNames([]);
    }, []);

    const selectAllVisible = useCallback((): void => {
        const rows = activeView === 'subjects' ? pagedSubjectRows : pagedPlanSubjectRows;
        setSelectedNames(rows.map((row) => row.name));
    }, [activeView, pagedPlanSubjectRows, pagedSubjectRows]);

    const goListPage = useCallback(
        (page: number, lastPage: number): void => {
            if (page < 1 || page > lastPage || page === listPage) {
                return;
            }
            writeStoredListPage('curriculum', page);
            setListPage(page);
        },
        [listPage],
    );

    const applyDistribution = useCallback(
        (apply: { kind: 'status'; value: 1 | 2 } | { kind: 'type'; value: 1 | 2 | 3 }): void => {
            if (!canManage || !hasSelection) {
                return;
            }

            const targets = selectedNames
                .map((name) => subjectsByName.get(name))
                .filter((row): row is SubjectRow => row !== undefined);

            if (targets.length === 0) {
                return;
            }

            for (const target of targets) {
                if (apply.kind === 'status') {
                    if (apply.value === 1 && target.status !== 1) {
                        postWithIdempotency(`/curriculum/subjects/${target.id}/reactivate`);
                    } else if (apply.value === 2 && target.status === 1) {
                        postWithIdempotency(`/curriculum/subjects/${target.id}/deactivate`);
                    }
                } else if (target.subject_type !== apply.value) {
                    patchWithIdempotency(`/curriculum/subjects/${target.id}`, {
                        name: target.name,
                        subject_type: apply.value,
                        credit_hours: target.credit_hours,
                        max_grade: target.max_grade,
                        pass_grade: target.pass_grade,
                    });
                }
            }

            clearSelection();
        },
        [canManage, clearSelection, hasSelection, selectedNames, subjectsByName],
    );

    const openSelectedSubjectsSheet = useCallback(
        (startEditing: boolean): void => {
            const rows = selectedNames
                .map((name) => subjectsByName.get(name))
                .filter((row): row is SubjectRow => row !== undefined);

            if (rows.length === 0) {
                showWarning(c.subjectNotInDatabase);

                return;
            }

            setSubjectSheetSubjects(rows);
            setSubjectSheetEditing(startEditing);
        },
        [c.subjectNotInDatabase, selectedNames, showWarning, subjectsByName],
    );

    const requestDeleteSelected = useCallback((): void => {
        if (!canManage || !hasSelection) {
            return;
        }

        const activeTargets = selectedNames
            .map((name) => subjectsByName.get(name))
            .filter(
                (row): row is SubjectRow =>
                    row !== undefined && Number(row.status) === 1,
            );

        if (activeTargets.length === 0) {
            const anyInDatabase = selectedNames.some((name) => subjectsByName.has(name));
            showWarning(anyInDatabase ? c.deleteAlreadyInactive : c.subjectNotInDatabase);

            return;
        }

        setConfirm({
            kind: 'subjects-delete',
            ids: activeTargets.map((row) => row.id),
            count: activeTargets.length,
        });
    }, [
        c.deleteAlreadyInactive,
        c.subjectNotInDatabase,
        canManage,
        hasSelection,
        selectedNames,
        showWarning,
        subjectsByName,
    ]);

    const editRibbonGroups = useMemo((): PageRibbonGroup[] => {
        const actionCommands: PageRibbonCommand[] = [
            {
                id: 'view-subjects',
                label: c.view,
                icon: Eye,
                disabled: !hasSelection,
                title: c.viewSubjectTitle,
                onSelect: () => openSelectedSubjectsSheet(false),
            },
            {
                id: 'edit-selected-subject',
                label: i18n.common.edit,
                icon: Pencil,
                tone: 'edit',
                disabled: !canManage || !hasSelection,
                title: i18n.common.edit,
                onSelect: () => openSelectedSubjectsSheet(true),
            },
            {
                id: 'clear-selection',
                label: c.clearSelection,
                icon: XCircle,
                disabled: !hasSelection,
                onSelect: clearSelection,
            },
            {
                id: 'delete-subject',
                label: i18n.common.delete,
                icon: Trash2,
                tone: 'delete',
                disabled: !canManage || !hasSelection,
                title: i18n.common.delete,
                onSelect: requestDeleteSelected,
            },
        ];

        const statusCountSource =
            activeView === 'subjects' ? catalogSubjectRows : curriculumPlanSubjectRows;

        const statusCommands: PageRibbonCommand[] = CURRICULUM_STATUS_TABS.map((tab) => {
            const label = c[tab.labelKey];
            const count = statusCountSource.filter((row) =>
                matchesCurriculumEditFilter(row, tab.filter),
            ).length;

            return {
                id: tab.id,
                label,
                title: `${label} (${count})`,
                icon: tab.icon,
                count,
                pressed: editFilterEquals(editFilter, tab.filter),
                onSelect: () => {
                    setEditFilter(tab.filter);
                },
            };
        });

        const distributionCommands: PageRibbonCommand[] = CURRICULUM_DISTRIBUTION_ACTIONS.map(
            (action) => {
                const label = c[action.labelKey];

                return {
                    id: action.id,
                    label,
                    title: label,
                    icon: action.icon,
                    disabled: !canManage,
                    onSelect: () => applyDistribution(action.apply),
                };
            },
        );

        const supersededLabel = c.ribbonSuperseded;
        const supersededCount = statusCountSource.filter((row) =>
            matchesCurriculumEditFilter(row, { kind: 'superseded' }),
        ).length;
        const supersededCommand: PageRibbonCommand = {
            id: 'curriculum-superseded-action',
            label: supersededLabel,
            title: `${supersededLabel} (${supersededCount})`,
            icon: CURRICULUM_SUPERSEDED_ICON,
            count: supersededCount,
            pressed: editFilter.kind === 'superseded',
            onSelect: () => {
                setEditFilter({ kind: 'superseded' });
            },
        };

        return [
            {
                id: 'curriculum-actions',
                label: c.ribbonActions,
                commands: actionCommands,
            },
            {
                id: 'curriculum-status-tabs',
                label: c.ribbonStatus,
                commands: statusCommands,
            },
            {
                id: 'curriculum-distribution',
                label: c.ribbonDistribution,
                commands: distributionCommands,
            },
            {
                id: 'curriculum-superseded',
                label: c.ribbonSuperseded,
                commands: [supersededCommand],
            },
        ];
    }, [
        activeView,
        applyDistribution,
        c,
        canManage,
        catalogSubjectRows,
        clearSelection,
        curriculumPlanSubjectRows,
        editFilter,
        hasSelection,
        i18n.common.delete,
        i18n.common.edit,
        openSelectedSubjectsSheet,
        requestDeleteSelected,
    ]);

    useRegisterPageRibbon('edit', editRibbonGroups);

    const tableRef = useRef<HTMLTableElement>(null);
    const tableRowCount =
        activeView === 'subjects'
            ? pagedSubjectRows.length
            : pagedPlanSubjectRows.length;

    useResizableTableColumns(tableRef, {
        storageKey: activeView === 'subjects' ? 'curriculum.subjects' : 'curriculum.plans',
        columnSignature:
            activeView === 'subjects'
                ? 'select:seq:name:type:hours:max:pass:prereq:status:v3'
                : 'select:seq:name:type:hours:max:status:v3',
        enabled: tableRowCount > 0,
    });
    useSmoothVerticalScroll(scrollerRef, tableRowCount > 0);

    const runConfirm = (): void => {
        if (!confirm) {
            return;
        }

        setConfirmPending(true);
        const done = (): void => {
            setConfirmPending(false);
            setConfirm(null);
        };

        const headers = { 'X-Idempotency-Key': newIdempotencyKey('curriculum') };

        switch (confirm.kind) {
            case 'plan-deactivate':
                router.post(`/curriculum/curricula/${confirm.id}/deactivate`, {}, { preserveScroll: true, headers, onFinish: done });
                break;
            case 'plan-reactivate':
                router.post(`/curriculum/curricula/${confirm.id}/reactivate`, {}, { preserveScroll: true, headers, onFinish: done });
                break;
            case 'subject-deactivate':
                router.post(`/curriculum/subjects/${confirm.id}/deactivate`, {}, { preserveScroll: true, headers, onFinish: done });
                break;
            case 'subject-reactivate':
                router.post(`/curriculum/subjects/${confirm.id}/reactivate`, {}, { preserveScroll: true, headers, onFinish: done });
                break;
            case 'link-deactivate':
                router.post(`/curriculum/curriculum-subjects/${confirm.id}/deactivate`, {}, { preserveScroll: true, headers, onFinish: done });
                break;
            case 'link-reactivate':
                router.post(`/curriculum/curriculum-subjects/${confirm.id}/reactivate`, {}, { preserveScroll: true, headers, onFinish: done });
                break;
            case 'subjects-delete': {
                const ids = confirm.ids;
                let remaining = ids.length;
                const finishOne = (): void => {
                    remaining -= 1;
                    if (remaining <= 0) {
                        clearSelection();
                        done();
                    }
                };

                for (const id of ids) {
                    router.post(
                        `/curriculum/subjects/${id}/deactivate`,
                        {},
                        {
                            preserveScroll: true,
                            headers: { 'X-Idempotency-Key': newIdempotencyKey('curriculum') },
                            onFinish: finishOne,
                        },
                    );
                }
                break;
            }
        }
    };

    const confirmCopy = useMemo(() => {
        if (!confirm) {
            return { title: '', description: '', confirmLabel: undefined as string | undefined };
        }
        switch (confirm.kind) {
            case 'plan-deactivate':
                return { title: c.deactivate, description: c.confirmDeactivatePlan, confirmLabel: undefined };
            case 'plan-reactivate':
                return { title: c.reactivate, description: c.confirmReactivatePlan, confirmLabel: undefined };
            case 'subject-deactivate':
                return { title: c.deactivate, description: c.confirmDeactivateSubject, confirmLabel: undefined };
            case 'subject-reactivate':
                return { title: c.reactivate, description: c.confirmReactivateSubject, confirmLabel: undefined };
            case 'link-deactivate':
                return { title: c.deactivate, description: c.confirmDeactivateLink, confirmLabel: undefined };
            case 'link-reactivate':
                return { title: c.reactivate, description: c.confirmReactivateLink, confirmLabel: undefined };
            case 'subjects-delete':
                return {
                    title: confirm.count > 1 ? c.deleteTitleMany : c.deleteTitle,
                    description:
                        confirm.count > 1
                            ? c.deleteConfirmMany.replace(':count', String(confirm.count))
                            : c.deleteConfirm,
                    confirmLabel: i18n.common.delete,
                };
        }
    }, [c, confirm, i18n.common.delete]);

    return (
        <div
            className="sis-ops-hub sis-admission-page sis-students-page sis-curriculum-page flex h-full min-h-0 flex-col overflow-hidden pb-4"
            dir="rtl"
            lang="ar"
        >
            <div className="sis-admission-page-body">
                <div
                    className="sis-curriculum-view-tabs"
                    role="tablist"
                    aria-label={c.title}
                >
                    <button
                        type="button"
                        role="tab"
                        aria-selected={activeView === 'subjects'}
                        className={`sis-curriculum-view-tabs__btn${activeView === 'subjects' ? ' is-active' : ''}`}
                        onClick={() => setActiveView('subjects')}
                    >
                        {c.viewSubjects}
                    </button>
                    <button
                        type="button"
                        role="tab"
                        aria-selected={activeView === 'curricula'}
                        className={`sis-curriculum-view-tabs__btn${activeView === 'curricula' ? ' is-active' : ''}`}
                        onClick={() => setActiveView('curricula')}
                    >
                        {c.viewCurricula}
                    </button>
                </div>

                <section
                    aria-label={
                        activeView === 'subjects' ? c.subjectsCaption : c.plansCaption
                    }
                    className="sis-curriculum-table-stage"
                >
                    {activeView === 'subjects' ? (
                        <>
                            {catalogSubjectRows.length === 0 ? (
                            <p className="text-sm px-1 py-2">
                                {hasSubjectSearch ? c.emptySearch : c.subjectsEmptyDesc}
                            </p>
                        ) : visibleSubjectRows.length === 0 ? (
                            <p className="text-sm px-1 py-2">{c.editErrorEmpty}</p>
                        ) : (
                            <>
                            <div className="sis-admission-periods-table sis-admission-drafts-table">
                                <div
                                    className="sis-admission-drafts-table__scroller"
                                    ref={scrollerRef}
                                >
                                    <table ref={tableRef}>
                                        <thead>
                                            <tr>
                                                <th className="sis-admission-drafts-table__select">
                                                    <input
                                                        type="checkbox"
                                                        checked={
                                                            pagedSubjectRows.length > 0 &&
                                                            pagedSubjectRows.every((row) =>
                                                                selectedSet.has(row.name),
                                                            )
                                                        }
                                                        onChange={() => {
                                                            if (
                                                                pagedSubjectRows.every((row) =>
                                                                    selectedSet.has(row.name),
                                                                )
                                                            ) {
                                                                clearSelection();
                                                            } else {
                                                                selectAllVisible();
                                                            }
                                                        }}
                                                        aria-label={c.selectAllVisible}
                                                    />
                                                </th>
                                                <th className="sis-admission-drafts-table__num">
                                                    {c.seq}
                                                </th>
                                                <th className="sis-admission-drafts-table__name-head">
                                                    {c.subjectName}
                                                </th>
                                                <th className="sis-curriculum-type-col">{c.subjectType}</th>
                                                <th className="sis-curriculum-hours-col">{c.creditHours}</th>
                                                <th>{c.maxGrade}</th>
                                                <th>{c.passGrade}</th>
                                                <th className="sis-curriculum-prereq-col">{c.prerequisitesColumn}</th>
                                                <th className="sis-curriculum-subject-status">
                                                    {c.subjectStatus}
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {pagedSubjectRows.map((row, index) => (
                                                <tr
                                                    key={`${row.name}-${subjectRowOffset + index}`}
                                                    className={
                                                        selectedSet.has(row.name)
                                                            ? 'sis-admission-periods-table__row--selected'
                                                            : undefined
                                                    }
                                                >
                                                    <td className="sis-admission-drafts-table__select">
                                                        <input
                                                            type="checkbox"
                                                            checked={selectedSet.has(row.name)}
                                                            onChange={() =>
                                                                toggleSelectedName(row.name)
                                                            }
                                                            aria-label={row.name}
                                                        />
                                                    </td>
                                                    <td className="sis-admission-drafts-table__num">
                                                        {subjectRowOffset + index + 1}
                                                    </td>
                                                    <td className="sis-admission-drafts-table__name sis-students-table__nowrap">
                                                        <div className="sis-students-table__cell-scroll">
                                                            {row.name}
                                                        </div>
                                                    </td>
                                                    <td className="sis-admission-drafts-table__text sis-curriculum-type-col">
                                                        {subjectTypeLabel(row.subject_type, c)}
                                                    </td>
                                                    <td className="sis-admission-drafts-table__text sis-curriculum-hours-col" dir="ltr">
                                                        {row.credit_hours ?? '—'}
                                                    </td>
                                                    <td className="sis-admission-drafts-table__text" dir="ltr">
                                                        {row.max_grade}
                                                    </td>
                                                    <td className="sis-admission-drafts-table__text" dir="ltr">
                                                        {row.pass_grade}
                                                    </td>
                                                    <td className="sis-admission-drafts-table__text sis-curriculum-prereq-col">
                                                        {row.prerequisites.trim() !== ''
                                                            ? row.prerequisites
                                                            : c.nonePrerequisites}
                                                    </td>
                                                    <td
                                                        className="sis-admission-drafts-table__text sis-curriculum-subject-status"
                                                        data-status={Number(row.status) === 1 ? '1' : '2'}
                                                    >
                                                        {Number(row.status) === 1
                                                            ? i18n.status.active
                                                            : i18n.status.inactive}
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            {visibleSubjectRows.length > 0 ? (
                                <nav
                                    className="sis-admission-drafts-pagination"
                                    aria-label={i18n.common.page}
                                >
                                    <ul className="sis-admission-pagination" dir="ltr">
                                        <li className="sis-admission-pagination__item">
                                            <button
                                                type="button"
                                                className="sis-admission-pagination__link"
                                                aria-label={i18n.common.previous}
                                                disabled={subjectPage <= 1}
                                                onClick={() =>
                                                    goListPage(subjectPage - 1, subjectLastPage)
                                                }
                                            >
                                                <span aria-hidden="true">&laquo;</span>
                                            </button>
                                        </li>
                                        {visiblePages(subjectPage, subjectLastPage).map(
                                            (pageNum) => (
                                                <li
                                                    key={pageNum}
                                                    className="sis-admission-pagination__item"
                                                >
                                                    <button
                                                        type="button"
                                                        className={
                                                            pageNum === subjectPage
                                                                ? 'sis-admission-pagination__link sis-admission-pagination__link--active'
                                                                : 'sis-admission-pagination__link'
                                                        }
                                                        aria-label={`${i18n.common.page} ${pageNum}`}
                                                        aria-current={
                                                            pageNum === subjectPage
                                                                ? 'page'
                                                                : undefined
                                                        }
                                                        onClick={() =>
                                                            goListPage(pageNum, subjectLastPage)
                                                        }
                                                    >
                                                        {pageNum}
                                                    </button>
                                                </li>
                                            ),
                                        )}
                                        <li className="sis-admission-pagination__item">
                                            <button
                                                type="button"
                                                className="sis-admission-pagination__link"
                                                aria-label={i18n.common.next}
                                                disabled={subjectPage >= subjectLastPage}
                                                onClick={() =>
                                                    goListPage(subjectPage + 1, subjectLastPage)
                                                }
                                            >
                                                <span aria-hidden="true">&raquo;</span>
                                            </button>
                                        </li>
                                    </ul>
                                </nav>
                            ) : null}
                            </>
                        )}
                        </>
                    ) : (
                        <>
                            <CurriculumTableFilterContext
                                ariaLabel={c.tableContextAria}
                                labels={{
                                    academicYear: c.academicYear,
                                    branch: c.branch,
                                    specialization: c.specialization,
                                    gradeLevel: c.gradeLevel,
                                }}
                                context={tableFilterContext}
                            />
                        {curriculumPlanSubjectRows.length === 0 ? (
                        <p className="text-sm px-1 py-2">
                            {hasSubjectSearch ? c.emptySearch : c.plansEmptyDesc}
                        </p>
                    ) : visiblePlanSubjectRows.length === 0 ? (
                        <p className="text-sm px-1 py-2">{c.editErrorEmpty}</p>
                    ) : (
                        <>
                        <div className="sis-admission-periods-table sis-admission-drafts-table">
                            <div
                                className="sis-admission-drafts-table__scroller"
                                ref={scrollerRef}
                            >
                                <table ref={tableRef}>
                                    <thead>
                                        <tr>
                                            <th className="sis-admission-drafts-table__select">
                                                <input
                                                    type="checkbox"
                                                    checked={
                                                        pagedPlanSubjectRows.length > 0 &&
                                                        pagedPlanSubjectRows.every((row) =>
                                                            selectedSet.has(row.name),
                                                        )
                                                    }
                                                    onChange={() => {
                                                        if (
                                                            pagedPlanSubjectRows.every((row) =>
                                                                selectedSet.has(row.name),
                                                            )
                                                        ) {
                                                            clearSelection();
                                                        } else {
                                                            selectAllVisible();
                                                        }
                                                    }}
                                                    aria-label={c.selectAllVisible}
                                                />
                                            </th>
                                            <th className="sis-admission-drafts-table__num">
                                                {c.seq}
                                            </th>
                                            <th className="sis-admission-drafts-table__name-head">
                                                {c.subjectName}
                                            </th>
                                            <th className="sis-curriculum-type-col">{c.subjectType}</th>
                                            <th className="sis-curriculum-hours-col">{c.creditHours}</th>
                                            <th>{c.maxGrade}</th>
                                            <th className="sis-curriculum-subject-status">
                                                {c.subjectStatus}
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {pagedPlanSubjectRows.map((subject, index) => (
                                            <tr
                                                key={`${subject.name}-${planRowOffset + index}`}
                                                className={
                                                    selectedSet.has(subject.name)
                                                        ? 'sis-admission-periods-table__row--selected'
                                                        : undefined
                                                }
                                            >
                                                <td className="sis-admission-drafts-table__select">
                                                    <input
                                                        type="checkbox"
                                                        checked={selectedSet.has(subject.name)}
                                                        onChange={() =>
                                                            toggleSelectedName(subject.name)
                                                        }
                                                        aria-label={subject.name}
                                                    />
                                                </td>
                                                <td className="sis-admission-drafts-table__num">
                                                    {planRowOffset + index + 1}
                                                </td>
                                                <td className="sis-admission-drafts-table__name sis-students-table__nowrap">
                                                    <div className="sis-students-table__cell-scroll">
                                                        {subject.name}
                                                    </div>
                                                </td>
                                                <td className="sis-admission-drafts-table__text sis-curriculum-type-col">
                                                    {subjectTypeLabel(subject.subject_type, c)}
                                                </td>
                                                <td className="sis-admission-drafts-table__text sis-curriculum-hours-col" dir="ltr">
                                                    {subject.credit_hours}
                                                </td>
                                                <td className="sis-admission-drafts-table__text" dir="ltr">
                                                    {subject.max_grade}
                                                </td>
                                                <td
                                                    className="sis-admission-drafts-table__text sis-curriculum-subject-status"
                                                    data-status={subject.status === 1 ? '1' : '2'}
                                                >
                                                    {statusLabel(
                                                        subject.status,
                                                        i18n.status.active,
                                                        i18n.status.inactive,
                                                    )}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        {visiblePlanSubjectRows.length > 0 ? (
                            <nav
                                className="sis-admission-drafts-pagination"
                                aria-label={i18n.common.page}
                            >
                                <ul className="sis-admission-pagination" dir="ltr">
                                    <li className="sis-admission-pagination__item">
                                        <button
                                            type="button"
                                            className="sis-admission-pagination__link"
                                            aria-label={i18n.common.previous}
                                            disabled={planPage <= 1}
                                            onClick={() => goListPage(planPage - 1, planLastPage)}
                                        >
                                            <span aria-hidden="true">&laquo;</span>
                                        </button>
                                    </li>
                                    {visiblePages(planPage, planLastPage).map((pageNum) => (
                                        <li
                                            key={pageNum}
                                            className="sis-admission-pagination__item"
                                        >
                                            <button
                                                type="button"
                                                className={
                                                    pageNum === planPage
                                                        ? 'sis-admission-pagination__link sis-admission-pagination__link--active'
                                                        : 'sis-admission-pagination__link'
                                                }
                                                aria-label={`${i18n.common.page} ${pageNum}`}
                                                aria-current={
                                                    pageNum === planPage ? 'page' : undefined
                                                }
                                                onClick={() => goListPage(pageNum, planLastPage)}
                                            >
                                                {pageNum}
                                            </button>
                                        </li>
                                    ))}
                                    <li className="sis-admission-pagination__item">
                                        <button
                                            type="button"
                                            className="sis-admission-pagination__link"
                                            aria-label={i18n.common.next}
                                            disabled={planPage >= planLastPage}
                                            onClick={() => goListPage(planPage + 1, planLastPage)}
                                        >
                                            <span aria-hidden="true">&raquo;</span>
                                        </button>
                                    </li>
                                </ul>
                            </nav>
                        ) : null}
                        </>
                    )}
                        </>
                    )}
                </section>
            </div>

            {planCreateOpen ? (
                <CurriculumCreateSheetDialog
                    canManage={canManage}
                    filterOptions={filterOptions}
                    defaultAcademicYearId={filters.academic_year_id}
                    subjects={subjects.data}
                    onClose={() => setPlanCreateOpen(false)}
                />
            ) : null}

            {planSheetPlan !== null ? (
                <CurriculumPlanSheetDialog
                    mode="edit"
                    plan={planSheetPlan}
                    canManage={canManage}
                    initialEditing={planSheetEditing}
                    filterOptions={filterOptions}
                    defaultAcademicYearId={filters.academic_year_id}
                    onClose={() => {
                        setPlanSheetPlan(null);
                        setPlanSheetEditing(false);
                    }}
                />
            ) : null}

            {subjectCreateOpen ? (
                <CurriculumSubjectSheetDialog
                    mode="create"
                    canManage={canManage}
                    prerequisiteOptions={subjects.data
                        .filter((row) => Number(row.status) === 1)
                        .map((row) => ({ id: row.id, name: row.name }))}
                    onClose={() => setSubjectCreateOpen(false)}
                />
            ) : null}

            {subjectSheetSubjects.length > 0 ? (
                <CurriculumSubjectSheetDialog
                    subjects={subjectSheetSubjects}
                    canManage={canManage}
                    initialEditing={subjectSheetEditing}
                    prerequisiteOptions={subjects.data
                        .filter((row) => Number(row.status) === 1)
                        .map((row) => ({ id: row.id, name: row.name }))}
                    onClose={() => {
                        setSubjectSheetSubjects([]);
                        setSubjectSheetEditing(false);
                    }}
                />
            ) : null}

            {selectedPlan ? (
                <LinkSubjectDialog
                    open={linkDialogOpen}
                    onOpenChange={setLinkDialogOpen}
                    curriculum={selectedPlan}
                    subjects={subjects.data.filter((s) => s.status === 1)}
                    existingSubjectIds={new Set(linkedSubjects.map((l) => l.subject_id))}
                />
            ) : null}

            <ConfirmDialog
                open={confirm !== null}
                title={confirmCopy.title}
                description={confirmCopy.description}
                confirmLabel={confirmCopy.confirmLabel}
                confirmPending={confirmPending}
                tone={
                    confirm?.kind === 'plan-deactivate' ||
                    confirm?.kind === 'subject-deactivate' ||
                    confirm?.kind === 'link-deactivate' ||
                    confirm?.kind === 'subjects-delete'
                        ? 'danger'
                        : 'default'
                }
                onConfirm={runConfirm}
                onOpenChange={(open) => {
                    if (!open) {
                        setConfirm(null);
                    }
                }}
            />
        </div>
    );
}

export function LinkSubjectDialog({
    open,
    onOpenChange,
    curriculum,
    subjects,
    existingSubjectIds,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    curriculum: CurriculumRow;
    subjects: SubjectRow[];
    existingSubjectIds: Set<number>;
}) {
    const i18n = t();
    const c = i18n.curriculum;
    const [subjectId, setSubjectId] = useState('');
    const [weeklyHours, setWeeklyHours] = useState('2');
    const [isRequired, setIsRequired] = useState('1');
    const [subjectOrder, setSubjectOrder] = useState('0');

    const availableSubjects = subjects.filter((s) => !existingSubjectIds.has(s.id));

    const applyCatalog = (): void => {
        const branchName = curriculum.branch_name;
        const deptName = curriculum.department_name ?? curriculum.specialization_name;
        if (!branchName || !deptName) {
            return;
        }
        const catalog = catalogSubjectsFor(branchName, deptName);
        const byName = new Map(subjects.map((s) => [s.name, s]));
        const queue: Array<{ subject_id: number; weekly_hours: number; subject_order: number }> = [];
        let order = Number(subjectOrder) || 0;
        for (const item of catalog) {
            const match = byName.get(item.name);
            if (!match || existingSubjectIds.has(match.id)) {
                continue;
            }
            queue.push({
                subject_id: match.id,
                weekly_hours: item.credit_hours,
                subject_order: order,
            });
            order += 1;
        }

        const runNext = (index: number): void => {
            if (index >= queue.length) {
                onOpenChange(false);
                return;
            }
            const item = queue[index];
            router.post(
                `/curriculum/curricula/${curriculum.id}/subjects`,
                {
                    subject_id: item.subject_id,
                    weekly_hours: item.weekly_hours,
                    is_required: true,
                    subject_order: item.subject_order,
                },
                {
                    preserveScroll: true,
                    headers: { 'X-Idempotency-Key': newIdempotencyKey('curriculum-catalog') },
                    onFinish: () => runNext(index + 1),
                },
            );
        };

        if (queue.length === 0) {
            onOpenChange(false);
            return;
        }

        runNext(0);
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sis-ops-hub max-h-[90vh] overflow-y-auto sm:max-w-lg" dir="rtl" lang="ar">
                <DialogHeader>
                    <DialogTitle>{c.addLinkedSubject}</DialogTitle>
                    <DialogDescription>{curriculum.name}</DialogDescription>
                </DialogHeader>
                <div className="flex flex-col gap-3">
                    {curriculum.branch_name &&
                    (curriculum.department_name || curriculum.specialization_name) ? (
                        <Button type="button" variant="outline" onClick={applyCatalog}>
                            {c.suggestFromCatalog}
                        </Button>
                    ) : null}
                    <form
                        className="flex flex-col gap-3"
                        onSubmit={(event) => {
                            event.preventDefault();
                            if (subjectId === '') {
                                return;
                            }
                            postWithIdempotency(`/curriculum/curricula/${curriculum.id}/subjects`, {
                                subject_id: Number(subjectId),
                                weekly_hours: weeklyHours === '' ? null : Number(weeklyHours),
                                is_required: isRequired === '1',
                                subject_order: Number(subjectOrder) || 0,
                            });
                            onOpenChange(false);
                        }}
                    >
                        <OpsFormField label={c.subjectName} name="subject_id">
                            <SisListSelect
                                name="subject_id"
                                ariaLabel={c.subjectName}
                                value={subjectId}
                                required
                                onChange={(value) => {
                                    setSubjectId(value);
                                    const subject = availableSubjects.find((s) => String(s.id) === value);
                                    if (subject?.credit_hours != null) {
                                        setWeeklyHours(String(subject.credit_hours));
                                    }
                                }}
                                options={availableSubjects.map((s) => ({
                                    value: String(s.id),
                                    label: `${s.name} (${s.code})`,
                                }))}
                            />
                        </OpsFormField>
                        <OpsFormField label={c.weeklyHours} name="weekly_hours">
                            <OpsTextInput
                                name="weekly_hours"
                                type="number"
                                min={0}
                                dir="ltr"
                                defaultValue={2}
                                onChange={(e) => setWeeklyHours(e.target.value)}
                            />
                        </OpsFormField>
                        <OpsFormField label={c.isRequired} name="is_required">
                            <SisListSelect
                                name="is_required"
                                ariaLabel={c.isRequired}
                                value={isRequired}
                                onChange={setIsRequired}
                                options={[
                                    { value: '1', label: c.requiredYes },
                                    { value: '0', label: c.requiredNo },
                                ]}
                            />
                        </OpsFormField>
                        <OpsFormField label={c.subjectOrder} name="subject_order">
                            <OpsTextInput
                                name="subject_order"
                                type="number"
                                min={0}
                                dir="ltr"
                                defaultValue={0}
                                onChange={(e) => setSubjectOrder(e.target.value)}
                            />
                        </OpsFormField>
                        <div className="flex justify-end gap-2 pt-2">
                            <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
                                {c.cancel}
                            </Button>
                            <Button type="submit" disabled={subjectId === ''}>
                                {c.save}
                            </Button>
                        </div>
                    </form>
                </div>
            </DialogContent>
        </Dialog>
    );
}
