import { router } from '@inertiajs/react';
import {
    ArrowRightLeft,
    CheckCircle2,
    CircleSlash,
    Eye,
    PauseCircle,
    Pencil,
    UserPlus,
    Users,
    type LucideIcon,
} from 'lucide-react';
import { useCallback, useEffect, useMemo, useRef, useState, type ReactNode } from 'react';
import {
    FilterBranchIcon,
    FilterClassIcon,
    FilterDepartmentIcon,
    FilterGenderIcon,
    FilterSectionIcon,
    FilterSpecializationIcon,
    FilterYearIcon,
} from '@/components/enrollments/enrollment-filter-icons';
import { OpsYearFilter } from '@/components/sis/ops-year-filter';
import { SisListSelect } from '@/components/sis/sis-list-select';
import {
    selectTableRow,
    tableActionIds,
    toggleTableRowChecked,
    toggleTableSelectAll,
} from '@/components/sis/table-row-selection';
import {
    useRegisterPageRibbon,
    type PageRibbonGroup,
} from '@/components/sis/page-ribbon-context';
import { useRegisterPageTitlebarHome } from '@/components/sis/page-titlebar-home-context';
import { useRegisterPageTitlebarSearch } from '@/components/sis/page-titlebar-search-context';
import { t } from '@/i18n';

const ENROLLMENTS_PER_PAGE = 17;

const ENROLLMENT_STATUS_TABS: Array<{
    status: number | null;
    icon: LucideIcon;
    tone: 'light' | 'dark';
}> = [
    { status: null, icon: Users, tone: 'dark' },
    { status: 1, icon: CheckCircle2, tone: 'dark' },
    { status: 0, icon: PauseCircle, tone: 'light' },
    { status: 2, icon: CircleSlash, tone: 'light' },
    { status: 3, icon: ArrowRightLeft, tone: 'dark' },
];

const ENROLLMENT_STATUS_ACTIONS: Array<{
    status: number;
    icon: LucideIcon;
    tone: 'light' | 'dark';
}> = [
    { status: 1, icon: CheckCircle2, tone: 'dark' },
    { status: 0, icon: PauseCircle, tone: 'light' },
    { status: 2, icon: CircleSlash, tone: 'light' },
    { status: 3, icon: ArrowRightLeft, tone: 'dark' },
];

function clampPercent(value: number): number {
    if (value < 0) {
        return 0;
    }

    if (value > 100) {
        return 100;
    }

    return Math.round(value);
}

function percentForStatus(
    status: number | null,
    progress: EnrollmentsPayload['status_progress'],
): number {
    const match = progress?.stages.find((stage) => stage.status === status);

    return clampPercent(match?.percent ?? 0);
}

function countForStatus(
    status: number | null,
    progress: EnrollmentsPayload['status_progress'],
): number {
    const match = progress?.stages.find((stage) => stage.status === status);
    const count = match?.count ?? 0;

    return count < 0 ? 0 : Math.round(count);
}

export type EnrollmentListItem = {
    id: number;
    student_id: number;
    school_id: number;
    academic_year_id: number;
    class_id: number;
    section_id: number;
    enrollment_number: string;
    status: number;
    effective_from: string;
    effective_to: string | null;
    specialization_id?: number | null;
    branch_id?: number | null;
    department_id?: number | null;
    student_code?: string | null;
    student_full_name?: string | null;
    student_first_name?: string | null;
    student_father_name?: string | null;
    student_grandfather_name?: string | null;
    student_great_grandfather_name?: string | null;
    student_last_name?: string | null;
    student_gender?: number | null;
    class_code?: string | null;
    class_name?: string | null;
    section_code?: string | null;
    section_name?: string | null;
    specialization_code?: string | null;
    specialization_name?: string | null;
    branch_code?: string | null;
    branch_name?: string | null;
    grade_level_code?: string | null;
    grade_level_name?: string | null;
    department_name?: string | null;
    stage_name?: string | null;
};

export type EnrollmentsPayload = {
    data: EnrollmentListItem[];
    meta: {
        page: number;
        per_page: number;
        total: number;
        last_page: number;
    };
    status_progress?: {
        overall_percent: number;
        stages: Array<{ status: number | null; percent: number; count: number }>;
    };
};

export type EnrollmentAuthorization = {
    canView: boolean;
    canCreate: boolean;
    canUpdate: boolean;
    canCancel: boolean;
};

export type EnrollmentFilterOptions = {
    branches: Array<{ id: number; code: string; name: string }>;
    classes: Array<{ id: number; code: string; name: string }>;
    sections: Array<{ id: number; class_id: number; code: string; name: string }>;
    departments: Array<{ id: number; branch_id: number | null; code: string; name: string }>;
    specializations: Array<{
        id: number;
        department_id: number | null;
        code: string;
        name: string;
    }>;
};

type EnrollmentListProps = {
    enrollments: EnrollmentsPayload;
    filters: {
        q: string;
        status: number | null;
        gender: number | null;
        class_id: number | null;
        section_id: number | null;
        department_name: string | null;
        specialization_id: number | null;
        branch_id: number | null;
        department_id: number | null;
        page: number;
        per_page: number;
        academic_year_id: number | null;
    };
    filterOptions: EnrollmentFilterOptions;
    authorization: EnrollmentAuthorization;
};

type VisitParams = {
    q?: string;
    status?: number | null;
    page?: number;
    academic_year_id?: number | null;
    gender?: number | null;
    class_id?: number | null;
    section_id?: number | null;
    department_name?: string | null;
    specialization_id?: number | null;
    branch_id?: number | null;
    department_id?: number | null;
    quiet?: boolean;
};

function textOrDash(value: string | number | null | undefined): string {
    if (value === null || value === undefined || value === '') {
        return '—';
    }

    return String(value);
}

function studentQuadName(row: EnrollmentListItem): string {
    const parts = [
        row.student_first_name,
        row.student_father_name,
        row.student_grandfather_name,
        row.student_great_grandfather_name,
        row.student_last_name,
    ]
        .map((part) => part?.trim() ?? '')
        .filter((part) => part !== '');

    if (parts.length > 0) {
        return parts.join(' ');
    }

    return row.student_full_name?.trim() || '—';
}

function formatCivilDate(value: string | null | undefined): string {
    if (value === null || value === undefined || value === '') {
        return '—';
    }

    const match = /^(\d{4})-(\d{2})-(\d{2})/.exec(value);

    if (!match) {
        return value;
    }

    return `${match[2]}/${match[3]}/${match[1]}`;
}

function genderLabelFor(gender: number | null | undefined, i18n: ReturnType<typeof t>): string {
    if (gender === 1) {
        return i18n.students.male;
    }

    if (gender === 2) {
        return i18n.students.female;
    }

    return '—';
}

function CellScroll({ children }: { children: ReactNode }) {
    return <div className="sis-students-table__cell-scroll">{children}</div>;
}

function HighlightedText({ text, query }: { text: string; query: string }) {
    return (
        <>
            {searchSegments(text, query).map((segment, segmentIndex) =>
                segment.hit ? (
                    <mark key={`hit-${segmentIndex}`} className="sis-admission-search-hit">
                        {segment.text}
                    </mark>
                ) : (
                    <span key={`plain-${segmentIndex}`}>{segment.text}</span>
                ),
            )}
        </>
    );
}

function searchSegments(
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

function statusTabLabel(status: number | null, i18n: ReturnType<typeof t>): string {
    if (status === null) {
        return i18n.enrollments.allStatuses;
    }

    const labels: Record<number, string> = {
        0: i18n.status.inactive,
        1: i18n.status.active,
        2: i18n.status.cancelled,
        3: i18n.status.transferred,
    };

    return labels[status] ?? String(status);
}

function statusTone(status: number): 'light' | 'dark' {
    return status === 0 || status === 2 ? 'light' : 'dark';
}

export function EnrollmentList({
    enrollments,
    filters,
    filterOptions,
    authorization,
}: EnrollmentListProps) {
    const i18n = t();
    const genderValue = filters.gender === 1 || filters.gender === 2 ? String(filters.gender) : '';
    const genderFilterLabel =
        genderValue === '1'
            ? i18n.students.male
            : genderValue === '2'
              ? i18n.students.female
              : i18n.enrollments.gender;
    const classValue = filters.class_id ? String(filters.class_id) : '';
    const sectionValue = filters.section_id ? String(filters.section_id) : '';
    const branchValue = filters.branch_id ? String(filters.branch_id) : '';
    const departmentIdValue = filters.department_id ? String(filters.department_id) : '';
    const specializationValue = filters.specialization_id
        ? String(filters.specialization_id)
        : '';
    const selectedClassLabel =
        filterOptions.classes.find((item) => String(item.id) === classValue)?.name
        ?? i18n.enrollments.allClasses;
    const selectedSectionLabel =
        filterOptions.sections.find((item) => String(item.id) === sectionValue)?.name
        ?? i18n.enrollments.allSections;
    const selectedBranchLabel =
        filterOptions.branches.find((item) => String(item.id) === branchValue)?.name
        ?? i18n.enrollments.allBranches;
    const selectedDepartmentLabel =
        filterOptions.departments.find((item) => String(item.id) === departmentIdValue)?.name
        ?? i18n.enrollments.allDepartments;
    const selectedSpecializationLabel =
        filterOptions.specializations.find((item) => String(item.id) === specializationValue)?.name
        ?? i18n.enrollments.allSpecializations;

    const [selectedId, setSelectedId] = useState<number | null>(null);
    const [checkedIds, setCheckedIds] = useState<number[]>([]);
    const [applyingStatus, setApplyingStatus] = useState(false);
    const selectAllRef = useRef<HTMLInputElement>(null);
    const filtersRef = useRef(filters);
    const searchDraftRef = useRef(filters.q);
    filtersRef.current = filters;
    const rows = enrollments?.data ?? [];
    const pagination = enrollments?.meta ?? {
        page: filters.page,
        per_page: filters.per_page,
        total: 0,
        last_page: 1,
    };
    const rowOffset = (pagination.page - 1) * pagination.per_page;
    const overallPercent = clampPercent(enrollments.status_progress?.overall_percent ?? 0);
    const canSelect = authorization.canUpdate || authorization.canCancel;
    const rowIds = useMemo(() => rows.map((row) => row.id), [rows]);
    const visibleCheckedIds = useMemo(
        () => checkedIds.filter((id) => rowIds.includes(id)),
        [checkedIds, rowIds],
    );
    const actionIds = useMemo(
        () => tableActionIds(visibleCheckedIds, selectedId),
        [selectedId, visibleCheckedIds],
    );
    const allChecked = rowIds.length > 0 && visibleCheckedIds.length === rowIds.length;
    const someChecked = visibleCheckedIds.length > 0 && !allChecked;
    const canApplyStatus = canSelect && actionIds.length > 0 && !applyingStatus;
    const selectedRow = rows.find((row) => row.id === selectedId) ?? null;
    const canViewSelected = authorization.canView && selectedRow !== null;
    const canEditSelected = authorization.canUpdate && selectedRow !== null;

    const filterSections = useMemo(() => {
        if (classValue === '') {
            return filterOptions.sections;
        }

        return filterOptions.sections.filter((section) => String(section.class_id) === classValue);
    }, [classValue, filterOptions.sections]);

    const filterDepartments = useMemo(() => {
        if (branchValue === '') {
            return filterOptions.departments;
        }

        return filterOptions.departments.filter(
            (department) =>
                department.branch_id === null || String(department.branch_id) === branchValue,
        );
    }, [branchValue, filterOptions.departments]);

    const filterSpecializations = useMemo(() => {
        if (departmentIdValue === '') {
            return filterOptions.specializations;
        }

        return filterOptions.specializations.filter(
            (item) =>
                item.department_id === null || String(item.department_id) === departmentIdValue,
        );
    }, [departmentIdValue, filterOptions.specializations]);

    useEffect(() => {
        if (selectAllRef.current) {
            selectAllRef.current.indeterminate = someChecked;
        }
    }, [someChecked]);

    useRegisterPageTitlebarHome({
        href: '/enrollments',
        ariaLabel: i18n.enrollments.backToEnrollments,
    });

    const visitList = useCallback((params: VisitParams) => {
        const current = filtersRef.current;
        const nextStatus = 'status' in params ? params.status : current.status;
        const nextQuery = ('q' in params ? params.q : current.q) ?? '';
        const nextYear =
            'academic_year_id' in params ? params.academic_year_id : current.academic_year_id;
        const nextGender = 'gender' in params ? params.gender : current.gender;
        const nextClassId = 'class_id' in params ? params.class_id : current.class_id;
        const nextSectionId = 'section_id' in params ? params.section_id : current.section_id;
        const nextBranchId = 'branch_id' in params ? params.branch_id : current.branch_id;
        const nextDepartmentId =
            'department_id' in params ? params.department_id : current.department_id;
        const nextSpecialization =
            'specialization_id' in params
                ? params.specialization_id
                : current.specialization_id;

        router.get(
            '/enrollments',
            {
                q: nextQuery.trim() || undefined,
                page: params.page ?? current.page,
                per_page: ENROLLMENTS_PER_PAGE,
                status: nextStatus ?? undefined,
                academic_year_id: nextYear ?? undefined,
                gender: nextGender ?? undefined,
                class_id: nextClassId ?? undefined,
                section_id: nextSectionId ?? undefined,
                branch_id: nextBranchId ?? undefined,
                department_id: nextDepartmentId ?? undefined,
                specialization_id: nextSpecialization ?? undefined,
            },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
                only: ['enrollments', 'filters', 'filterOptions', 'authorization'],
                showProgress: params.quiet !== true,
            },
        );
    }, []);

    const commitSearch = useCallback(
        (query: string) => {
            visitList({ q: query, page: 1, quiet: true });
        },
        [visitList],
    );

    const selectRow = useCallback((enrollmentId: number) => {
        const next = selectTableRow(enrollmentId);
        setSelectedId(next.selectedId);
        setCheckedIds(next.checkedIds);
    }, []);

    const toggleChecked = useCallback(
        (enrollmentId: number) => {
            const next = toggleTableRowChecked(checkedIds, enrollmentId);
            setCheckedIds(next.checkedIds);
            setSelectedId(next.selectedId);
        },
        [checkedIds],
    );

    const toggleAll = useCallback(() => {
        const next = toggleTableSelectAll(checkedIds, rowIds, selectedId);
        setCheckedIds(next.checkedIds);
        setSelectedId(next.selectedId);
    }, [checkedIds, rowIds, selectedId]);

    const applyStatus = useCallback(
        (status: number) => {
            if (!canApplyStatus) {
                return;
            }

            if ((status === 0 || status === 2 || status === 3) && !authorization.canCancel) {
                return;
            }

            if (status === 1 && !authorization.canUpdate && !authorization.canCancel) {
                return;
            }

            setApplyingStatus(true);
            router.post(
                '/enrollments/bulk-status',
                {
                    enrollment_ids: actionIds,
                    status,
                    effective_to: new Date().toISOString().slice(0, 10),
                },
                {
                    preserveScroll: true,
                    preserveState: true,
                    onSuccess: () => {
                        setCheckedIds([]);
                        setSelectedId(null);
                    },
                    onFinish: () => setApplyingStatus(false),
                },
            );
        },
        [actionIds, authorization.canCancel, authorization.canUpdate, canApplyStatus],
    );

    const onStatusTabClick = useCallback(
        (status: number | null, isActive: boolean) => {
            if (isActive) {
                return;
            }

            visitList({
                status,
                page: 1,
            });
        },
        [visitList],
    );

    const goPage = useCallback(
        (page: number) => {
            visitList({ page });
        },
        [visitList],
    );

    const ribbonGroups = useMemo((): PageRibbonGroup[] => {
        const commands: PageRibbonGroup['commands'] = [
            {
                id: 'view-enrollment',
                label: i18n.common.view,
                icon: Eye,
                disabled: !canViewSelected,
                onSelect: () => {
                    if (selectedRow !== null) {
                        router.visit(`/enrollments/${selectedRow.id}`);
                    }
                },
            },
        ];

        if (authorization.canCreate) {
            commands.push({
                id: 'create-enrollment',
                label: i18n.enrollments.enrollStudent,
                icon: UserPlus,
                onSelect: () => {
                    router.visit('/enrollments/create', {
                        data: {
                            academic_year_id: filters.academic_year_id ?? undefined,
                        },
                    });
                },
            });
        }

        if (authorization.canUpdate) {
            commands.push({
                id: 'edit-enrollment',
                label: i18n.enrollments.editPlacement,
                icon: Pencil,
                disabled: !canEditSelected,
                onSelect: () => {
                    if (selectedRow !== null) {
                        router.visit(`/enrollments/${selectedRow.id}/edit`);
                    }
                },
            });
        }

        return [
            {
                id: 'enrollment-list-actions',
                label: i18n.common.actions,
                commands,
            },
        ];
    }, [
        authorization.canCreate,
        authorization.canUpdate,
        canEditSelected,
        canViewSelected,
        filters.academic_year_id,
        i18n.common.actions,
        i18n.common.view,
        i18n.enrollments.editPlacement,
        i18n.enrollments.enrollStudent,
        selectedRow,
    ]);

    useRegisterPageRibbon('home', ribbonGroups);

    const titlebarSearch = useMemo(
        () => ({
            committedQuery: filters.q,
            label: i18n.enrollments.searchAria,
            placeholder: i18n.enrollments.search,
            onDraftChange: (query: string) => {
                searchDraftRef.current = query;
            },
            onCommit: commitSearch,
        }),
        [commitSearch, filters.q, i18n.enrollments.search, i18n.enrollments.searchAria],
    );

    useRegisterPageTitlebarSearch(titlebarSearch);

    const emptyMessage = filters.q ? i18n.enrollments.emptySearch : i18n.enrollments.emptyDesc;

    return (
        <div
            className="sis-ops-hub sis-admission-page sis-students-page sis-enrollments-page flex h-full min-h-0 flex-col overflow-hidden pb-4"
            dir="rtl"
            lang="ar"
        >
            <div className="sis-enrollments-control-strip">
                <form
                    className="sis-enrollments-filters-bar"
                    onSubmit={(event) => event.preventDefault()}
                    aria-label={i18n.enrollments.structureFiltersTitle}
                >
                <label className="sis-enrollments-filter-chip sis-enrollments-filter-chip--year" dir="rtl">
                    <span className="sis-enrollments-filter-chip__icon" aria-hidden="true">
                        <FilterYearIcon />
                    </span>
                    <span className="sis-enrollments-filter-chip__control">
                        <OpsYearFilter
                            action="/enrollments"
                            academicYearId={filters.academic_year_id}
                            extraParams={{
                                get q() {
                                    const value = searchDraftRef.current.trim();

                                    return value === '' ? undefined : value;
                                },
                                per_page: ENROLLMENTS_PER_PAGE,
                                status: filters.status ?? undefined,
                                gender: filters.gender ?? undefined,
                                class_id: filters.class_id ?? undefined,
                                section_id: filters.section_id ?? undefined,
                                branch_id: filters.branch_id ?? undefined,
                                department_id: filters.department_id ?? undefined,
                                specialization_id: filters.specialization_id ?? undefined,
                            }}
                            label={i18n.enrollments.academicYear}
                            showLabel={false}
                            compact
                            showCurrentBadge={false}
                            controlClassName="sis-admission-year-control"
                        />
                    </span>
                </label>

                <label
                    className="sis-enrollments-filter-chip sis-enrollments-filter-chip--gender"
                    dir="rtl"
                >
                    <span className="sis-enrollments-filter-chip__icon" aria-hidden="true">
                        <FilterGenderIcon />
                    </span>
                    <span className="sis-admission-select-fit">
                        <span className="sis-admission-select-fit__mirror" aria-hidden="true">
                            {genderFilterLabel}
                        </span>
                        <SisListSelect
                            value={genderValue}
                            options={[
                                { value: '', label: i18n.enrollments.gender },
                                { value: '1', label: i18n.students.male },
                                { value: '2', label: i18n.students.female },
                            ]}
                            onChange={(next) => {
                                visitList({
                                    gender: next === '1' || next === '2' ? Number(next) : null,
                                    page: 1,
                                });
                            }}
                            triggerClassName="sis-ops-hub__link px-2 py-1 min-h-0 min-w-0 sis-admission-year-control"
                            dir="rtl"
                            ariaLabel={i18n.enrollments.filterByGender}
                        />
                    </span>
                </label>

                <label
                    className="sis-enrollments-filter-chip sis-enrollments-filter-chip--branch"
                    dir="rtl"
                >
                    <span className="sis-enrollments-filter-chip__icon" aria-hidden="true">
                        <FilterBranchIcon />
                    </span>
                    <span className="sis-admission-select-fit">
                        <span className="sis-admission-select-fit__mirror" aria-hidden="true">
                            {selectedBranchLabel}
                        </span>
                        <SisListSelect
                            value={branchValue}
                            options={[
                                { value: '', label: i18n.enrollments.allBranches },
                                ...filterOptions.branches.map((item) => ({
                                    value: String(item.id),
                                    label: item.name,
                                })),
                            ]}
                            onChange={(next) => {
                                visitList({
                                    branch_id: next === '' ? null : Number(next),
                                    department_id: null,
                                    specialization_id: null,
                                    page: 1,
                                });
                            }}
                            triggerClassName="sis-ops-hub__link px-2 py-1 min-h-0 min-w-0 sis-admission-year-control"
                            dir="rtl"
                            ariaLabel={i18n.enrollments.filterByBranch}
                        />
                    </span>
                </label>

                <label
                    className="sis-enrollments-filter-chip sis-enrollments-filter-chip--department"
                    dir="rtl"
                >
                    <span className="sis-enrollments-filter-chip__icon" aria-hidden="true">
                        <FilterDepartmentIcon />
                    </span>
                    <span className="sis-admission-select-fit">
                        <span className="sis-admission-select-fit__mirror" aria-hidden="true">
                            {selectedDepartmentLabel}
                        </span>
                        <SisListSelect
                            value={departmentIdValue}
                            options={[
                                { value: '', label: i18n.enrollments.allDepartments },
                                ...filterDepartments.map((item) => ({
                                    value: String(item.id),
                                    label: item.name,
                                })),
                            ]}
                            onChange={(next) => {
                                visitList({
                                    department_id: next === '' ? null : Number(next),
                                    specialization_id: null,
                                    page: 1,
                                });
                            }}
                            triggerClassName="sis-ops-hub__link px-2 py-1 min-h-0 min-w-0 sis-admission-year-control"
                            dir="rtl"
                            ariaLabel={i18n.enrollments.filterByDepartment}
                        />
                    </span>
                </label>

                <label
                    className="sis-enrollments-filter-chip sis-enrollments-filter-chip--specialization"
                    dir="rtl"
                >
                    <span className="sis-enrollments-filter-chip__icon" aria-hidden="true">
                        <FilterSpecializationIcon />
                    </span>
                    <span className="sis-admission-select-fit">
                        <span className="sis-admission-select-fit__mirror" aria-hidden="true">
                            {selectedSpecializationLabel}
                        </span>
                        <SisListSelect
                            value={specializationValue}
                            options={[
                                { value: '', label: i18n.enrollments.allSpecializations },
                                ...filterSpecializations.map((item) => ({
                                    value: String(item.id),
                                    label: item.name,
                                })),
                            ]}
                            onChange={(next) => {
                                visitList({
                                    specialization_id: next === '' ? null : Number(next),
                                    page: 1,
                                });
                            }}
                            triggerClassName="sis-ops-hub__link px-2 py-1 min-h-0 min-w-0 sis-admission-year-control"
                            dir="rtl"
                            ariaLabel={i18n.enrollments.filterBySpecialization}
                        />
                    </span>
                </label>

                <label
                    className="sis-enrollments-filter-chip sis-enrollments-filter-chip--class"
                    dir="rtl"
                >
                    <span className="sis-enrollments-filter-chip__icon" aria-hidden="true">
                        <FilterClassIcon />
                    </span>
                    <span className="sis-admission-select-fit">
                        <span className="sis-admission-select-fit__mirror" aria-hidden="true">
                            {selectedClassLabel}
                        </span>
                        <SisListSelect
                            value={classValue}
                            options={[
                                { value: '', label: i18n.enrollments.allClasses },
                                ...filterOptions.classes.map((item) => ({
                                    value: String(item.id),
                                    label: item.name,
                                })),
                            ]}
                            onChange={(next) => {
                                visitList({
                                    class_id: next === '' ? null : Number(next),
                                    section_id: null,
                                    page: 1,
                                });
                            }}
                            triggerClassName="sis-ops-hub__link px-2 py-1 min-h-0 min-w-0 sis-admission-year-control"
                            dir="rtl"
                            ariaLabel={i18n.enrollments.filterByClass}
                        />
                    </span>
                </label>

                <label
                    className="sis-enrollments-filter-chip sis-enrollments-filter-chip--section"
                    dir="rtl"
                >
                    <span className="sis-enrollments-filter-chip__icon" aria-hidden="true">
                        <FilterSectionIcon />
                    </span>
                    <span className="sis-admission-select-fit">
                        <span className="sis-admission-select-fit__mirror" aria-hidden="true">
                            {selectedSectionLabel}
                        </span>
                        <SisListSelect
                            value={sectionValue}
                            options={[
                                { value: '', label: i18n.enrollments.allSections },
                                ...filterSections.map((item) => ({
                                    value: String(item.id),
                                    label: item.name,
                                })),
                            ]}
                            onChange={(next) => {
                                visitList({
                                    section_id: next === '' ? null : Number(next),
                                    page: 1,
                                });
                            }}
                            triggerClassName="sis-ops-hub__link px-2 py-1 min-h-0 min-w-0 sis-admission-year-control"
                            dir="rtl"
                            ariaLabel={i18n.enrollments.filterBySection}
                        />
                    </span>
                </label>
                </form>

                {canSelect ? (
                    <div
                        className="sis-admission-drafts-transitions sis-enrollments-status-actions"
                        role="toolbar"
                        aria-label={i18n.enrollments.statusActionsTitle}
                    >
                    <div className="sis-admission-drafts-table__transitions">
                        {ENROLLMENT_STATUS_ACTIONS.map((action) => {
                            const Icon = action.icon;
                            const actionLabel = statusTabLabel(action.status, i18n);

                            return (
                                <button
                                    key={action.status}
                                    type="button"
                                    className={`sis-admission-drafts-table__transition sis-admission-drafts-table__transition--tone-${action.tone} sis-enrollments-status-action`}
                                    data-status={action.status}
                                    disabled={
                                        !canApplyStatus
                                        || ((action.status === 0
                                            || action.status === 2
                                            || action.status === 3)
                                            && !authorization.canCancel)
                                    }
                                    aria-label={actionLabel}
                                    title={
                                        canApplyStatus
                                            ? actionLabel
                                            : i18n.enrollments.statusNeedsSelection
                                    }
                                    onClick={() => applyStatus(action.status)}
                                >
                                    <Icon className="sis-enrollments-status-action__icon" aria-hidden="true" />
                                    <span className="sis-enrollments-status-action__label">{actionLabel}</span>
                                </button>
                            );
                        })}
                    </div>
                    </div>
                ) : null}
            </div>

            <section
                aria-label={i18n.enrollments.statusTabsTitle}
                className="sis-admission-progress sis-students-tabs sis-enrollments-status-tabs"
            >
                <ol className="sis-admission-progress__track" dir="rtl" role="tablist">
                    {ENROLLMENT_STATUS_TABS.map((tab) => {
                        const Icon = tab.icon;
                        const isActive = filters.status === tab.status;
                        const label = statusTabLabel(tab.status, i18n);
                        const percent = percentForStatus(tab.status, enrollments.status_progress);
                        const count = countForStatus(tab.status, enrollments.status_progress);
                        const statusClass =
                            tab.status === null
                                ? 'sis-admission-progress__segment--status-all'
                                : `sis-admission-progress__segment--status-${tab.status}`;

                        return (
                            <li key={tab.status ?? 'all'} className="sis-admission-progress__item">
                                <button
                                    type="button"
                                    role="tab"
                                    className={`sis-admission-progress__segment ${statusClass} sis-admission-progress__segment--tone-${tab.tone}${isActive ? ' sis-admission-progress__segment--active' : ''}`}
                                    aria-label={`${label} ${count}`}
                                    title={label}
                                    aria-selected={isActive}
                                    aria-pressed={isActive}
                                    aria-current={isActive ? 'true' : undefined}
                                    data-active={isActive ? 'true' : undefined}
                                    onClick={() => onStatusTabClick(tab.status, isActive)}
                                >
                                    <span
                                        className="sis-admission-progress__fill"
                                        style={{ width: `${percent}%` }}
                                        aria-hidden="true"
                                    />
                                    <span className="sis-admission-progress__content">
                                        <Icon className="sis-admission-progress__icon" aria-hidden="true" />
                                        <span className="sis-admission-progress__label">{label}</span>
                                        <span className="sis-admission-progress__count" dir="ltr">
                                            {count}
                                        </span>
                                    </span>
                                </button>
                            </li>
                        );
                    })}
                </ol>
            </section>

            <div
                className="sis-admission-progress__overall-block sis-enrollments-progress"
                role="group"
                aria-label={i18n.enrollments.overallProgress}
            >
                <div
                    className="sis-admission-progress__overall-track"
                    role="progressbar"
                    aria-label={i18n.enrollments.overallProgress}
                    aria-valuemin={0}
                    aria-valuemax={100}
                    aria-valuenow={overallPercent}
                    data-contrast={overallPercent >= 45 ? 'light' : 'dark'}
                >
                    <span
                        className="sis-admission-progress__overall-fill"
                        style={{ width: `${overallPercent}%` }}
                    />
                    <span className="sis-admission-progress__overall-value" dir="ltr">
                        {overallPercent}%
                    </span>
                </div>
            </div>

            <div className="sis-admission-page-body">
                <section aria-label={i18n.enrollments.tableCaption} className="flex min-h-0 flex-1 flex-col">
                    {rows.length === 0 ? (
                        <p className="text-sm">{emptyMessage}</p>
                    ) : (
                        <>
                            <div className="sis-admission-periods-table sis-admission-drafts-table">
                                <div className="sis-admission-drafts-table__scroller">
                                    <table>
                                        <thead>
                                            <tr>
                                                {canSelect ? (
                                                    <th className="sis-admission-drafts-table__select">
                                                        <input
                                                            ref={selectAllRef}
                                                            type="checkbox"
                                                            checked={allChecked}
                                                            disabled={applyingStatus}
                                                            aria-label={i18n.enrollments.selectAllEnrollments}
                                                            onChange={toggleAll}
                                                        />
                                                    </th>
                                                ) : null}
                                                <th className="sis-admission-drafts-table__num">
                                                    {i18n.enrollments.seq}
                                                </th>
                                                <th className="sis-admission-drafts-table__name-head">
                                                    {i18n.enrollments.quadName}
                                                </th>
                                                <th>{i18n.enrollments.studentCode}</th>
                                                <th>{i18n.enrollments.gender}</th>
                                                <th>{i18n.enrollments.className}</th>
                                                <th>{i18n.enrollments.sectionName}</th>
                                                <th>{i18n.enrollments.branchName}</th>
                                                <th>{i18n.enrollments.departmentName}</th>
                                                <th>{i18n.enrollments.specialization}</th>
                                                <th>{i18n.enrollments.gradeLevel}</th>
                                                <th>{i18n.enrollments.enrollmentNumber}</th>
                                                <th>{i18n.enrollments.effectiveFrom}</th>
                                                <th>{i18n.enrollments.effectiveTo}</th>
                                                <th>{i18n.enrollments.statusTabsTitle}</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {rows.map((row, index) => {
                                                const name = studentQuadName(row);
                                                const selected = selectedId === row.id;
                                                const checked = checkedIds.includes(row.id);

                                                return (
                                                    <tr
                                                        key={row.id}
                                                        className={
                                                            selected || checked
                                                                ? 'sis-admission-periods-table__row--selected'
                                                                : undefined
                                                        }
                                                        aria-selected={selected || checked}
                                                        onClick={() => selectRow(row.id)}
                                                    >
                                                        {canSelect ? (
                                                            <td className="sis-admission-drafts-table__select">
                                                                <input
                                                                    type="checkbox"
                                                                    checked={checked}
                                                                    disabled={applyingStatus}
                                                                    aria-label={`${i18n.enrollments.selectEnrollment}: ${name}`}
                                                                    onClick={(event) =>
                                                                        event.stopPropagation()
                                                                    }
                                                                    onChange={() =>
                                                                        toggleChecked(row.id)
                                                                    }
                                                                />
                                                            </td>
                                                        ) : null}
                                                        <td className="sis-admission-drafts-table__num">
                                                            <span dir="ltr">{rowOffset + index + 1}</span>
                                                        </td>
                                                        <td className="sis-admission-drafts-table__name">
                                                            <CellScroll>
                                                                <HighlightedText text={name} query={filters.q} />
                                                            </CellScroll>
                                                        </td>
                                                        <td className="sis-admission-drafts-table__text sis-students-table__nowrap">
                                                            <span dir="ltr">
                                                                {textOrDash(row.student_code)}
                                                            </span>
                                                        </td>
                                                        <td className="sis-admission-drafts-table__text">
                                                            {genderLabelFor(row.student_gender, i18n)}
                                                        </td>
                                                        <td className="sis-admission-drafts-table__text">
                                                            <CellScroll>
                                                                {textOrDash(row.class_name ?? row.class_code)}
                                                            </CellScroll>
                                                        </td>
                                                        <td className="sis-admission-drafts-table__text">
                                                            <CellScroll>
                                                                {textOrDash(row.section_name ?? row.section_code)}
                                                            </CellScroll>
                                                        </td>
                                                        <td className="sis-admission-drafts-table__text">
                                                            <CellScroll>
                                                                {textOrDash(row.branch_name ?? row.branch_code)}
                                                            </CellScroll>
                                                        </td>
                                                        <td className="sis-admission-drafts-table__text">
                                                            <CellScroll>
                                                                {textOrDash(row.department_name)}
                                                            </CellScroll>
                                                        </td>
                                                        <td className="sis-admission-drafts-table__text">
                                                            <CellScroll>
                                                                {textOrDash(row.specialization_name)}
                                                            </CellScroll>
                                                        </td>
                                                        <td className="sis-admission-drafts-table__text">
                                                            <CellScroll>
                                                                {textOrDash(
                                                                    row.grade_level_name ?? row.grade_level_code,
                                                                )}
                                                            </CellScroll>
                                                        </td>
                                                        <td className="sis-admission-drafts-table__text sis-students-table__nowrap">
                                                            <span dir="ltr">{row.enrollment_number}</span>
                                                        </td>
                                                        <td className="sis-admission-drafts-table__text sis-students-table__nowrap">
                                                            <span dir="ltr">
                                                                {formatCivilDate(row.effective_from)}
                                                            </span>
                                                        </td>
                                                        <td className="sis-admission-drafts-table__text sis-students-table__nowrap">
                                                            <span dir="ltr">
                                                                {formatCivilDate(row.effective_to)}
                                                            </span>
                                                        </td>
                                                        <td
                                                            className={`sis-students-table__status sis-students-table__status--tone-${statusTone(row.status)}`}
                                                            data-status={row.status}
                                                        >
                                                            {statusTabLabel(row.status, i18n)}
                                                        </td>
                                                    </tr>
                                                );
                                            })}
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            {pagination.total > 0 ? (
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
                                                disabled={pagination.page <= 1}
                                                onClick={() => goPage(pagination.page - 1)}
                                            >
                                                <span aria-hidden="true">&laquo;</span>
                                            </button>
                                        </li>
                                        {visiblePages(pagination.page, pagination.last_page).map(
                                            (pageNum) => (
                                                <li
                                                    key={pageNum}
                                                    className="sis-admission-pagination__item"
                                                >
                                                    <button
                                                        type="button"
                                                        className={
                                                            pageNum === pagination.page
                                                                ? 'sis-admission-pagination__link sis-admission-pagination__link--active'
                                                                : 'sis-admission-pagination__link'
                                                        }
                                                        aria-label={`${i18n.common.page} ${pageNum}`}
                                                        aria-current={
                                                            pageNum === pagination.page
                                                                ? 'page'
                                                                : undefined
                                                        }
                                                        onClick={() => goPage(pageNum)}
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
                                                disabled={pagination.page >= pagination.last_page}
                                                onClick={() => goPage(pagination.page + 1)}
                                            >
                                                <span aria-hidden="true">&raquo;</span>
                                            </button>
                                        </li>
                                    </ul>
                                </nav>
                            ) : null}
                        </>
                    )}
                </section>
            </div>
        </div>
    );
}
