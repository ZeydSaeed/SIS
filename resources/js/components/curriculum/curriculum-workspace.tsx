import { router, usePage } from '@inertiajs/react';
import {
    Eye,
    FilterX,
    Pencil,
    Plus,
    Users,
    XCircle,
} from 'lucide-react';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import {
    CURRICULUM_DEFAULT_TABLE_VIEW,
    catalogAllSubjectTableRows,
    catalogBranches,
    catalogCurriculumTableRows,
    catalogDepartmentsForBranch,
    catalogSubjectsFor,
} from '@/components/curriculum/curriculum-subject-catalog';
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
import { useResizableTableColumns } from '@/hooks/use-resizable-table-columns';
import { useSmoothVerticalScroll } from '@/hooks/use-smooth-vertical-scroll';
import { t } from '@/i18n';
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

function subjectCodeFromName(name: string): string {
    let hash = 0;
    for (let i = 0; i < name.length; i++) {
        hash = (hash << 5) - hash + name.charCodeAt(i);
        hash |= 0;
    }
    const hex = Math.abs(hash).toString(16).toUpperCase().padStart(8, '0').slice(0, 8);

    return `SUB-${hex}`;
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

const emptyPagination = (): CurriculumPagination => ({
    page: 1,
    per_page: 25,
    total: 0,
    last_page: 1,
});

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
            per_page: props.filters?.per_page ?? 25,
            subject_q: props.filters?.subject_q ?? '',
            subject_status: props.filters?.subject_status ?? null,
            subject_type: props.filters?.subject_type ?? null,
            subject_page: props.filters?.subject_page ?? 1,
            subject_per_page: props.filters?.subject_per_page ?? 25,
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
    const [planDialogOpen, setPlanDialogOpen] = useState(false);
    const [editingPlan, setEditingPlan] = useState<CurriculumRow | null>(null);
    const [subjectDialogOpen, setSubjectDialogOpen] = useState(false);
    const [editingSubject, setEditingSubject] = useState<SubjectRow | null>(null);
    const [linkDialogOpen, setLinkDialogOpen] = useState(false);
    const [confirm, setConfirm] = useState<ConfirmState>(null);
    const [confirmPending, setConfirmPending] = useState(false);
    const [activeView, setActiveView] = useState<CurriculumListView>('curricula');
    const [editFilter, setEditFilter] = useState<CurriculumEditFilter>({ kind: 'all' });
    const [selectedNames, setSelectedNames] = useState<string[]>([]);
    const canManage = props.authorization?.canManage === true;

    const { academicYears } = usePage().props as { academicYears?: YearOption[] };
    const yearCatalog = academicYears ?? [];

    // UI filters start empty; table uses progressive auto-filter from these values.
    // Year is local-only when empty so server-resolved current year does not fill the control.
    const [selectedYear, setSelectedYear] = useState('');
    const [selectedBranch, setSelectedBranch] = useState(filters.branch);
    const [selectedSpec, setSelectedSpec] = useState(filters.specialization);
    const [selectedClass, setSelectedClass] = useState(
        resolveSisClassKey(filters.class_id, filterOptions.classes),
    );

    useEffect(() => {
        setSelectedBranch(filters.branch);
        setSelectedSpec(filters.specialization);
        setSelectedClass(resolveSisClassKey(filters.class_id, filterOptions.classes));
    }, [filterOptions.classes, filters.branch, filters.class_id, filters.specialization]);

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
        // Keep session on current year unless the year control is explicitly set —
        // otherwise a one-off curriculum year pick empties students/enrollments/admission.
        const yearForRequest =
            selectedYear !== ''
                ? Number(selectedYear)
                : (overrides.academic_year_id ?? currentYearId);

        router.get(
            '/curriculum',
            {
                ...omitEmpty({
                    academic_year_id: yearForRequest ?? undefined,
                    curriculum_id: next.curriculum_id,
                    q: next.q,
                    status: next.status,
                    page: next.page,
                    per_page: next.per_page,
                    subject_q: next.subject_q,
                    subject_status: next.subject_status,
                    subject_type: next.subject_type,
                    subject_page: next.subject_page,
                    subject_per_page: next.subject_per_page,
                }),
                branch: next.branch,
                specialization: next.specialization,
                class_id: next.class_id ?? '',
            },
            {
                preserveState: true,
                preserveScroll: true,
                ...(only ? { only } : {}),
            },
        );
    };

    // Sticky empty years (e.g. 2025-2026) hide students/enrollments/admission — pin back to current.
    useEffect(() => {
        if (selectedYear !== '') {
            return;
        }

        if (currentYearId === null || currentYearId === undefined) {
            return;
        }

        if (filters.academic_year_id === currentYearId) {
            return;
        }

        visitIndex({ academic_year_id: currentYearId, page: 1 });
        // Mount / year-catalog sync only — visitIndex closes over latest filters.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [currentYearId, filters.academic_year_id, selectedYear]);

    const titlebarSearch = useMemo(
        () => ({
            committedQuery: filters.q,
            label: c.searchAria,
            placeholder: c.search,
            onCommit: (query: string) => {
                visitIndex({ q: query, page: 1 });
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

    const clearPageFilters = useCallback((): void => {
        setSelectedYear('');
        setSelectedBranch('');
        setSelectedSpec('');
        setSelectedClass('');
        visitIndex({
            academic_year_id: currentYearId ?? filters.academic_year_id,
            branch: '',
            specialization: '',
            class_id: null,
            grade_level_id: null,
            page: 1,
        });
        // visitIndex closes over latest filters/state when invoked
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [currentYearId, filters.academic_year_id]);

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
                                            if (next !== '') {
                                                visitIndex({
                                                    academic_year_id: Number(next),
                                                    page: 1,
                                                });
                                            } else if (
                                                currentYearId !== null &&
                                                currentYearId !== undefined
                                            ) {
                                                visitIndex({
                                                    academic_year_id: currentYearId,
                                                    page: 1,
                                                });
                                            }
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
                                                page: 1,
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
                                                page: 1,
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
                                                page: 1,
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
        selectedBranch,
        selectedClass,
        selectedSpec,
        selectedYear,
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
                prerequisites: '',
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
                prerequisites: '',
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
    }, [selectedBranch, selectedClass, selectedSpec]);

    /** Curricula tab: subjects of the filtered plan only (no branch/spec/class columns). */
    const curriculumPlanSubjectRows = useMemo(() => {
        let source = catalogCurriculumRows;
        if (
            selectedBranch === '' &&
            selectedSpec === '' &&
            selectedClass === ''
        ) {
            source = catalogCurriculumTableRows().filter(
                (row) =>
                    row.branch_name === CURRICULUM_DEFAULT_TABLE_VIEW.branch &&
                    row.specialization_name === CURRICULUM_DEFAULT_TABLE_VIEW.specialization &&
                    row.class_name === CURRICULUM_DEFAULT_TABLE_VIEW.className,
            );
        }

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
    }, [catalogCurriculumRows, selectedBranch, selectedClass, selectedSpec, subjectsByName]);

    const visiblePlanSubjectRows = useMemo(
        () =>
            curriculumPlanSubjectRows.filter((row) =>
                matchesCurriculumEditFilter(row, editFilter),
            ),
        [curriculumPlanSubjectRows, editFilter],
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
        const rows = activeView === 'subjects' ? visibleSubjectRows : visiblePlanSubjectRows;
        setSelectedNames(rows.map((row) => row.name));
    }, [activeView, visiblePlanSubjectRows, visibleSubjectRows]);

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

    const editRibbonGroups = useMemo((): PageRibbonGroup[] => {
        const actionCommands: PageRibbonCommand[] = [
            {
                id: 'view-subjects',
                label: c.view,
                icon: Eye,
                onSelect: () => {
                    setActiveView('subjects');
                },
            },
            {
                id: 'create-subject',
                label: c.createSubjectAction,
                icon: Plus,
                tone: 'edit',
                disabled: !canManage,
                onSelect: () => {
                    setEditingSubject(null);
                    setSubjectDialogOpen(true);
                },
            },
            {
                id: 'create-plan',
                label: c.createPlanAction,
                icon: Plus,
                tone: 'edit',
                disabled: !canManage,
                onSelect: () => {
                    setEditingPlan(null);
                    setPlanDialogOpen(true);
                },
            },
            {
                id: 'edit-selected-subject',
                label: i18n.common.edit,
                icon: Pencil,
                tone: 'edit',
                disabled: !canManage || selectedNames.length !== 1,
                title: i18n.common.edit,
                onSelect: () => {
                    const name = selectedNames[0];
                    const row = name ? subjectsByName.get(name) : undefined;
                    if (!row) {
                        return;
                    }
                    setEditingSubject(row);
                    setSubjectDialogOpen(true);
                },
            },
            {
                id: 'select-all-visible',
                label: c.selectAllVisible,
                icon: Users,
                onSelect: selectAllVisible,
            },
            {
                id: 'clear-selection',
                label: c.clearSelection,
                icon: XCircle,
                disabled: !hasSelection,
                onSelect: clearSelection,
            },
        ];

        const statusCommands: PageRibbonCommand[] = CURRICULUM_STATUS_TABS.map((tab) => {
            const label = c[tab.labelKey];

            return {
                id: tab.id,
                label,
                title: label,
                icon: tab.icon,
                pressed: editFilterEquals(editFilter, tab.filter),
                onSelect: () => {
                    setEditFilter(tab.filter);
                    setActiveView('subjects');
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
        const supersededCommand: PageRibbonCommand = {
            id: 'curriculum-superseded-action',
            label: supersededLabel,
            title: supersededLabel,
            icon: CURRICULUM_SUPERSEDED_ICON,
            pressed: editFilter.kind === 'superseded',
            onSelect: () => {
                setEditFilter({ kind: 'superseded' });
                setActiveView('subjects');
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
        applyDistribution,
        c,
        canManage,
        clearSelection,
        editFilter,
        hasSelection,
        i18n.common.edit,
        selectAllVisible,
        selectedNames,
        subjectsByName,
    ]);

    useRegisterPageRibbon('edit', editRibbonGroups);

    const tableRef = useRef<HTMLTableElement>(null);
    const scrollerRef = useRef<HTMLDivElement>(null);
    const tableRowCount =
        activeView === 'subjects'
            ? visibleSubjectRows.length
            : visiblePlanSubjectRows.length;

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
        }
    };

    const confirmCopy = useMemo(() => {
        if (!confirm) {
            return { title: '', description: '' };
        }
        switch (confirm.kind) {
            case 'plan-deactivate':
                return { title: c.deactivate, description: c.confirmDeactivatePlan };
            case 'plan-reactivate':
                return { title: c.reactivate, description: c.confirmReactivatePlan };
            case 'subject-deactivate':
                return { title: c.deactivate, description: c.confirmDeactivateSubject };
            case 'subject-reactivate':
                return { title: c.reactivate, description: c.confirmReactivateSubject };
            case 'link-deactivate':
                return { title: c.deactivate, description: c.confirmDeactivateLink };
            case 'link-reactivate':
                return { title: c.reactivate, description: c.confirmReactivateLink };
        }
    }, [c, confirm]);

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
                        catalogSubjectRows.length === 0 ? (
                            <p className="text-sm px-1 py-2">
                                {hasSubjectSearch ? c.emptySearch : c.subjectsEmptyDesc}
                            </p>
                        ) : visibleSubjectRows.length === 0 ? (
                            <p className="text-sm px-1 py-2">{c.editErrorEmpty}</p>
                        ) : (
                            <div className="sis-curriculum-subjects-table">
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
                                                            visibleSubjectRows.length > 0 &&
                                                            visibleSubjectRows.every((row) =>
                                                                selectedSet.has(row.name),
                                                            )
                                                        }
                                                        onChange={() => {
                                                            if (
                                                                visibleSubjectRows.every((row) =>
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
                                                <th>{c.subjectType}</th>
                                                <th>{c.creditHours}</th>
                                                <th>{c.maxGrade}</th>
                                                <th>{c.passGrade}</th>
                                                <th>{c.prerequisitesColumn}</th>
                                                <th>{c.subjectStatus}</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {visibleSubjectRows.map((row, index) => (
                                                <tr
                                                    key={`${row.name}-${index}`}
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
                                                        {index + 1}
                                                    </td>
                                                    <td className="sis-admission-drafts-table__name sis-students-table__nowrap">
                                                        <div className="sis-students-table__cell-scroll">
                                                            {row.name}
                                                        </div>
                                                    </td>
                                                    <td className="sis-admission-drafts-table__text">
                                                        {subjectTypeLabel(row.subject_type, c)}
                                                    </td>
                                                    <td className="sis-admission-drafts-table__text" dir="ltr">
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
                        )
                    ) : curriculumPlanSubjectRows.length === 0 ? (
                        <p className="text-sm px-1 py-2">
                            {hasSubjectSearch ? c.emptySearch : c.plansEmptyDesc}
                        </p>
                    ) : visiblePlanSubjectRows.length === 0 ? (
                        <p className="text-sm px-1 py-2">{c.editErrorEmpty}</p>
                    ) : (
                        <div className="sis-curriculum-subjects-table">
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
                                                        visiblePlanSubjectRows.length > 0 &&
                                                        visiblePlanSubjectRows.every((row) =>
                                                            selectedSet.has(row.name),
                                                        )
                                                    }
                                                    onChange={() => {
                                                        if (
                                                            visiblePlanSubjectRows.every((row) =>
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
                                            <th>{c.subjectType}</th>
                                            <th>{c.creditHours}</th>
                                            <th>{c.maxGrade}</th>
                                            <th>{c.subjectStatus}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {visiblePlanSubjectRows.map((subject, index) => (
                                            <tr
                                                key={`${subject.name}-${index}`}
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
                                                    {index + 1}
                                                </td>
                                                <td className="sis-admission-drafts-table__name sis-students-table__nowrap">
                                                    <div className="sis-students-table__cell-scroll">
                                                        {subject.name}
                                                    </div>
                                                </td>
                                                <td className="sis-admission-drafts-table__text">
                                                    {subjectTypeLabel(subject.subject_type, c)}
                                                </td>
                                                <td className="sis-admission-drafts-table__text" dir="ltr">
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
                    )}
                </section>
            </div>

            <CurriculumPlanDialog
                open={planDialogOpen}
                onOpenChange={setPlanDialogOpen}
                editing={editingPlan}
                academicYearId={filters.academic_year_id}
                filterOptions={filterOptions}
            />

            <SubjectDialog
                open={subjectDialogOpen}
                onOpenChange={setSubjectDialogOpen}
                editing={editingSubject}
            />

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
                confirmPending={confirmPending}
                tone={
                    confirm?.kind === 'plan-deactivate' ||
                    confirm?.kind === 'subject-deactivate' ||
                    confirm?.kind === 'link-deactivate'
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

export function CurriculumPlanDialog({
    open,
    onOpenChange,
    editing,
    academicYearId,
    filterOptions,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    editing: CurriculumRow | null;
    academicYearId: number | null;
    filterOptions: CurriculumFilterOptions;
}) {
    const i18n = t();
    const c = i18n.curriculum;
    const [name, setName] = useState('');
    const [gradeLevelId, setGradeLevelId] = useState('');
    const [branchId, setBranchId] = useState('');
    const [specializationId, setSpecializationId] = useState('');
    const [processing, setProcessing] = useState(false);

    const reset = (): void => {
        if (editing) {
            setName(editing.name);
            setGradeLevelId(String(editing.grade_level_id));
            setBranchId(editing.branch_id !== null ? String(editing.branch_id) : '');
            setSpecializationId(
                editing.specialization_id !== null ? String(editing.specialization_id) : '',
            );
        } else {
            setName('');
            const firstClass = filterOptions.classes[0];
            setGradeLevelId(firstClass ? String(firstClass.grade_level_id) : '');
            setBranchId('');
            setSpecializationId('');
        }
    };

    const branchDepartments = useMemo(() => {
        const bid = branchId === '' ? null : Number(branchId);
        if (bid === null) {
            return [];
        }

        return filterOptions.departments.filter((d) => d.branch_id === bid);
    }, [branchId, filterOptions.departments]);

    const specializationOptions = useMemo(() => {
        const deptIds = new Set(branchDepartments.map((d) => d.id));
        if (deptIds.size === 0) {
            return filterOptions.specializations;
        }

        return filterOptions.specializations.filter(
            (s) => s.department_id !== null && deptIds.has(s.department_id),
        );
    }, [branchDepartments, filterOptions.specializations]);

    const selectedClassId = useMemo(
        () => resolveSisClassKey(
            filterOptions.classes.find(
                (item) => String(item.grade_level_id) === gradeLevelId,
            )?.id ?? null,
            filterOptions.classes,
        ),
        [filterOptions.classes, gradeLevelId],
    );

    const suggestName = (
        nextBranchId: string,
        nextSpecId: string,
        nextGradeId: string,
    ): string => {
        const branch = filterOptions.branches.find((b) => String(b.id) === nextBranchId);
        const spec = filterOptions.specializations.find((s) => String(s.id) === nextSpecId);
        const gradeClass = filterOptions.classes.find(
            (item) => String(item.grade_level_id) === nextGradeId,
        );
        return [branch?.name, spec?.name, gradeClass?.name].filter(Boolean).join(' — ');
    };

    return (
        <Dialog
            open={open}
            onOpenChange={(next) => {
                if (next) {
                    reset();
                }
                onOpenChange(next);
            }}
        >
            <DialogContent className="sis-ops-hub max-h-[90vh] overflow-y-auto sm:max-w-lg" dir="rtl" lang="ar">
                <DialogHeader>
                    <DialogTitle>{editing ? c.editPlan : c.createPlan}</DialogTitle>
                    <DialogDescription>{c.description}</DialogDescription>
                </DialogHeader>
                <form
                    key={editing?.id ?? 'create-plan'}
                    className="flex flex-col gap-3"
                    onSubmit={(event) => {
                        event.preventDefault();
                        if (academicYearId === null && !editing) {
                            return;
                        }
                        const planName =
                            name.trim() !== ''
                                ? name.trim()
                                : suggestName(branchId, specializationId, gradeLevelId);
                        if (planName === '') {
                            return;
                        }
                        setProcessing(true);
                        const finish = (): void => setProcessing(false);
                        if (editing) {
                            patchWithIdempotency(`/curriculum/curricula/${editing.id}`, {
                                name: planName,
                                specialization_id:
                                    specializationId === '' ? null : Number(specializationId),
                            });
                            finish();
                            onOpenChange(false);
                            return;
                        }
                        postWithIdempotency('/curriculum/curricula', {
                            academic_year_id: academicYearId,
                            grade_level_id: Number(gradeLevelId),
                            name: planName,
                            specialization_id:
                                specializationId === '' ? null : Number(specializationId),
                        });
                        finish();
                        onOpenChange(false);
                    }}
                >
                    <OpsFormField label={c.planName} name="name">
                        <OpsTextInput
                            name="name"
                            required
                            dir="rtl"
                            value={name}
                            onChange={(e) => setName(e.target.value)}
                        />
                    </OpsFormField>
                    {!editing ? (
                        <OpsFormField label={c.gradeLevel} name="grade_level_id">
                            <SisListSelect
                                name="grade_level_id"
                                ariaLabel={c.gradeLevel}
                                value={selectedClassId}
                                required
                                onChange={(value) => {
                                    const classId = resolveSisClassId(
                                        value,
                                        filterOptions.classes,
                                    );
                                    const selected = filterOptions.classes.find(
                                        (item) => item.id === classId,
                                    );
                                    const nextGradeId = selected
                                        ? String(selected.grade_level_id)
                                        : '';
                                    setGradeLevelId(nextGradeId);
                                    if (name.trim() === '') {
                                        setName(
                                            suggestName(branchId, specializationId, nextGradeId),
                                        );
                                    }
                                }}
                                options={sisClassSelectOptions()}
                            />
                        </OpsFormField>
                    ) : null}
                    <OpsFormField label={c.branch} name="branch_id">
                        <SisListSelect
                            name="branch_id"
                            ariaLabel={c.branch}
                            value={branchId}
                            includeBlank
                            onChange={(value) => {
                                setBranchId(value);
                                setSpecializationId('');
                                if (name.trim() === '' || !editing) {
                                    setName(suggestName(value, '', gradeLevelId));
                                }
                            }}
                            options={filterOptions.branches.map((b) => ({
                                value: String(b.id),
                                label: b.name,
                            }))}
                        />
                    </OpsFormField>
                    <OpsFormField label={c.specialization} name="specialization_id">
                        <SisListSelect
                            name="specialization_id"
                            ariaLabel={c.specialization}
                            value={specializationId}
                            includeBlank
                            onChange={(value) => {
                                setSpecializationId(value);
                                setName(suggestName(branchId, value, gradeLevelId));
                            }}
                            options={specializationOptions.map((s) => ({
                                value: String(s.id),
                                label: s.name,
                            }))}
                        />
                    </OpsFormField>
                    {!editing ? (
                        <p className="text-muted-foreground text-xs">{c.autoLinkHint}</p>
                    ) : null}
                    <div className="flex justify-end gap-2 pt-2">
                        <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
                            {c.cancel}
                        </Button>
                        <Button
                            type="submit"
                            disabled={
                                processing ||
                                (name.trim() === '' &&
                                    suggestName(branchId, specializationId, gradeLevelId) === '')
                            }
                        >
                            {c.save}
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
}

export function SubjectDialog({
    open,
    onOpenChange,
    editing,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    editing: SubjectRow | null;
}) {
    const i18n = t();
    const c = i18n.curriculum;
    const [code, setCode] = useState('');
    const [name, setName] = useState('');
    const [subjectType, setSubjectType] = useState('1');
    const [creditHours, setCreditHours] = useState('2');
    const [maxGrade, setMaxGrade] = useState('100');
    const [passGrade, setPassGrade] = useState('50');

    return (
        <Dialog
            open={open}
            onOpenChange={(next) => {
                if (next) {
                    if (editing) {
                        setCode(editing.code);
                        setName(editing.name);
                        setSubjectType(String(editing.subject_type));
                        setCreditHours(
                            editing.credit_hours !== null ? String(editing.credit_hours) : '',
                        );
                        setMaxGrade(String(editing.max_grade));
                        setPassGrade(String(editing.pass_grade));
                    } else {
                        setCode('');
                        setName('');
                        setSubjectType('1');
                        setCreditHours('2');
                        setMaxGrade('100');
                        setPassGrade('50');
                    }
                }
                onOpenChange(next);
            }}
        >
            <DialogContent className="sis-ops-hub max-h-[90vh] overflow-y-auto sm:max-w-lg" dir="rtl" lang="ar">
                <DialogHeader>
                    <DialogTitle>{editing ? c.editSubject : c.createSubject}</DialogTitle>
                    <DialogDescription>{c.codeHint}</DialogDescription>
                </DialogHeader>
                <form
                    className="flex flex-col gap-3"
                    onSubmit={(event) => {
                        event.preventDefault();
                        if (editing) {
                            patchWithIdempotency(`/curriculum/subjects/${editing.id}`, {
                                name,
                                subject_type: Number(subjectType),
                                credit_hours: creditHours === '' ? null : Number(creditHours),
                                max_grade: Number(maxGrade),
                                pass_grade: Number(passGrade),
                            });
                        } else {
                            postWithIdempotency('/curriculum/subjects', {
                                code: code.trim() || subjectCodeFromName(name),
                                name,
                                subject_type: Number(subjectType),
                                credit_hours: creditHours === '' ? null : Number(creditHours),
                                max_grade: Number(maxGrade),
                                pass_grade: Number(passGrade),
                            });
                        }
                        onOpenChange(false);
                    }}
                >
                    {!editing ? (
                        <OpsFormField label={c.subjectCode} name="code" hint={c.codeHint}>
                            <OpsTextInput
                                name="code"
                                dir="ltr"
                                required
                                defaultValue=""
                                onChange={(e) => setCode(e.target.value)}
                            />
                        </OpsFormField>
                    ) : (
                        <OpsFormField label={c.subjectCode} name="code_ro">
                            <span className="sis-ops-hub__link min-h-11 px-3 py-2" dir="ltr">
                                {editing.code}
                            </span>
                        </OpsFormField>
                    )}
                    <OpsFormField label={c.subjectName} name="name">
                        <OpsTextInput
                            name="name"
                            required
                            dir="rtl"
                            defaultValue={editing?.name ?? ''}
                            onChange={(e) => setName(e.target.value)}
                        />
                    </OpsFormField>
                    <OpsFormField label={c.subjectType} name="subject_type">
                        <SisListSelect
                            name="subject_type"
                            ariaLabel={c.subjectType}
                            value={subjectType}
                            onChange={setSubjectType}
                            options={[
                                { value: '1', label: c.subjectTypeCore },
                                { value: '2', label: c.subjectTypeElective },
                                { value: '3', label: c.subjectTypePractical },
                            ]}
                        />
                    </OpsFormField>
                    <OpsFormField label={c.creditHours} name="credit_hours">
                        <OpsTextInput
                            name="credit_hours"
                            type="number"
                            min={0}
                            dir="ltr"
                            defaultValue={editing?.credit_hours ?? 2}
                            onChange={(e) => setCreditHours(e.target.value)}
                        />
                    </OpsFormField>
                    <OpsFormField label={c.maxGrade} name="max_grade">
                        <OpsTextInput
                            name="max_grade"
                            type="number"
                            min={1}
                            dir="ltr"
                            defaultValue={editing?.max_grade ?? 100}
                            onChange={(e) => setMaxGrade(e.target.value)}
                        />
                    </OpsFormField>
                    <OpsFormField label={c.passGrade} name="pass_grade">
                        <OpsTextInput
                            name="pass_grade"
                            type="number"
                            min={0}
                            dir="ltr"
                            defaultValue={editing?.pass_grade ?? 50}
                            onChange={(e) => setPassGrade(e.target.value)}
                        />
                    </OpsFormField>
                    <div className="flex justify-end gap-2 pt-2">
                        <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
                            {c.cancel}
                        </Button>
                        <Button type="submit" disabled={name.trim() === ''}>
                            {c.save}
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
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
