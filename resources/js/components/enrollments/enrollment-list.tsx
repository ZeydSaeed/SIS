import { router, usePage } from '@inertiajs/react';
import {
    ArrowRightLeft,
    CheckCircle2,
    CircleSlash,
    Eye,
    PauseCircle,
    Pencil,
    Save,
    Trash2,
    UserPlus,
    Users,
    XCircle,
    type LucideIcon,
} from 'lucide-react';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import {
    FilterBranchIcon,
    FilterClassIcon,
    FilterDepartmentIcon,
    FilterGenderIcon,
    FilterSectionIcon,
    FilterSpecializationIcon,
    FilterYearIcon,
} from '@/components/enrollments/enrollment-filter-icons';
import {
    EnrollmentCreateDialog,
    EnrollmentViewDialog,
} from '@/components/enrollments/enrollment-record-form';
import {
    EnrollmentEditorRow,
    type EnrollmentRowHandle,
} from '@/components/enrollments/enrollment-editor-row';
import type {
    EnrollmentFilterOptions,
    EnrollmentListItem,
} from '@/components/enrollments/enrollment-types';
import { ConfirmDialog } from '@/components/sis/confirm-dialog';
import { OpsYearFilter } from '@/components/sis/ops-year-filter';
import { SisListSelect } from '@/components/sis/sis-list-select';
import {
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
import { useResizableTableColumns } from '@/hooks/use-resizable-table-columns';
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

export type { EnrollmentFilterOptions, EnrollmentListItem };

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
    const [editing, setEditing] = useState(false);
    const [editingIds, setEditingIds] = useState<number[]>([]);
    const [savingRows, setSavingRows] = useState(false);
    const [applyingStatus, setApplyingStatus] = useState(false);
    const [applyingPlacement, setApplyingPlacement] = useState(false);
    const [viewingEnrollments, setViewingEnrollments] = useState<EnrollmentListItem[] | null>(null);
    const [viewDialogEditing, setViewDialogEditing] = useState(false);
    const [creatingEnrollment, setCreatingEnrollment] = useState(false);
    const [createStudentId, setCreateStudentId] = useState<number | null>(null);
    const [deleteTarget, setDeleteTarget] = useState<EnrollmentListItem | null>(null);
    const [deleting, setDeleting] = useState(false);
    const selectAllRef = useRef<HTMLInputElement>(null);
    const tableRef = useRef<HTMLTableElement>(null);
    const filtersRef = useRef(filters);
    const searchDraftRef = useRef(filters.q);
    const checkedIdsRef = useRef(checkedIds);
    const selectedIdRef = useRef(selectedId);
    const applyingPlacementRef = useRef(false);
    const applyingStatusRef = useRef(false);
    const createIntentHandledRef = useRef(false);
    const rowRefs = useRef(new Map<number, EnrollmentRowHandle>());
    const rowSaveFns = useRef(new Map<number, () => Promise<void>>());
    const editingIdsRef = useRef<number[]>([]);
    filtersRef.current = filters;
    checkedIdsRef.current = checkedIds;
    selectedIdRef.current = selectedId;
    editingIdsRef.current = editingIds;
    const page = usePage();
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

    useEffect(() => {
        const query = page.url.includes('?') ? page.url.slice(page.url.indexOf('?') + 1) : '';
        const params = new URLSearchParams(query);
        if (params.get('create') !== '1') {
            createIntentHandledRef.current = false;

            return;
        }

        if (createIntentHandledRef.current) {
            return;
        }

        createIntentHandledRef.current = true;

        const studentIdRaw = params.get('student_id');
        const studentId =
            studentIdRaw !== null && studentIdRaw !== '' && Number(studentIdRaw) > 0
                ? Number(studentIdRaw)
                : null;

        if (authorization.canCreate) {
            setCreateStudentId(studentId);
            setCreatingEnrollment(true);
        }

        params.delete('create');
        params.delete('student_id');
        const next = params.toString();
        router.get(next === '' ? '/enrollments' : `/enrollments?${next}`, {}, {
            replace: true,
            preserveState: true,
            preserveScroll: true,
        });
    }, [authorization.canCreate, page.url]);

    useResizableTableColumns(tableRef, {
        storageKey: 'enrollments.list',
        columnSignature: canSelect ? 'select' : 'readonly',
        enabled: rows.length > 0,
    });

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
    const canApplyStatus = canSelect && actionIds.length > 0 && !applyingStatus && !applyingPlacement && !editing;
    const isEditMode = authorization.canUpdate && actionIds.length > 0 && !editing;
    const filtersBusy = applyingPlacement || applyingStatus || savingRows;
    const selectedRow = rows.find((row) => row.id === selectedId) ?? null;
    const viewTargets = useMemo(() => {
        const selected = new Set(actionIds);

        return rows.filter((row) => selected.has(row.id));
    }, [actionIds, rows]);
    const hasViewTargets = viewTargets.length > 0;
    const editTargetIds = actionIds;
    const hasEditTargets = authorization.canUpdate && editTargetIds.length > 0;
    const canDelete =
        authorization.canCancel
        && selectedRow !== null
        && selectedRow.status !== 2;
    const hasActiveSelection = actionIds.length > 0 || editing;

    const filterSections = useMemo(() => {
        if (isEditMode || classValue === '') {
            return filterOptions.sections;
        }

        return filterOptions.sections.filter((section) => String(section.class_id) === classValue);
    }, [classValue, filterOptions.sections, isEditMode]);

    const filterDepartments = useMemo(() => {
        if (isEditMode || branchValue === '') {
            return filterOptions.departments;
        }

        return filterOptions.departments.filter(
            (department) =>
                department.branch_id === null || String(department.branch_id) === branchValue,
        );
    }, [branchValue, filterOptions.departments, isEditMode]);

    const filterSpecializations = useMemo(() => {
        if (isEditMode || departmentIdValue === '') {
            return filterOptions.specializations;
        }

        return filterOptions.specializations.filter(
            (item) =>
                item.department_id === null || String(item.department_id) === departmentIdValue,
        );
    }, [departmentIdValue, filterOptions.specializations, isEditMode]);

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
        setSelectedId(enrollmentId);
        setCheckedIds((previous) => {
            if (previous.length > 1 && previous.includes(enrollmentId)) {
                return previous;
            }

            return [enrollmentId];
        });
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

    const resolveActionIds = useCallback((): number[] => {
        // Prefer the same resolved list the toolbar uses; fall back to refs
        // so a click in the same tick as select-all still sees the new ids.
        const fromUi = tableActionIds(checkedIdsRef.current, selectedIdRef.current);
        if (fromUi.length > 0) {
            return fromUi;
        }

        return actionIds;
    }, [actionIds]);

    const applyStatus = useCallback(
        (status: number) => {
            const enrollmentIds = resolveActionIds();
            if (enrollmentIds.length === 0 || applyingStatusRef.current) {
                return;
            }

            const targetStatus = Number(status);
            if ((targetStatus === 0 || targetStatus === 2 || targetStatus === 3) && !authorization.canCancel) {
                return;
            }

            if (targetStatus === 1 && !authorization.canUpdate && !authorization.canCancel) {
                return;
            }

            applyingStatusRef.current = true;
            setApplyingStatus(true);
            const today = (() => {
                const now = new Date();
                const year = now.getFullYear();
                const month = String(now.getMonth() + 1).padStart(2, '0');
                const day = String(now.getDate()).padStart(2, '0');

                return `${year}-${month}-${day}`;
            })();
            router.post(
                '/enrollments/bulk-status',
                {
                    enrollment_ids: enrollmentIds.map((id) => Number(id)),
                    // Keep numeric 0 (inactive) — never coerce via || / ?? falsy checks.
                    status: targetStatus,
                    effective_to: today,
                },
                {
                    preserveScroll: true,
                    preserveState: true,
                    only: ['enrollments', 'filters', 'filterOptions', 'authorization'],
                    onFinish: () => {
                        applyingStatusRef.current = false;
                        setApplyingStatus(false);
                    },
                },
            );
        },
        [authorization.canCancel, authorization.canUpdate, resolveActionIds],
    );

    const applyPlacementPatch = useCallback(
        (payload: {
            class_id?: number;
            section_id?: number;
            branch_id?: number;
            department_id?: number;
            specialization_id?: number;
            gender?: number;
        }) => {
            const enrollmentIds = resolveActionIds();
            if (!authorization.canUpdate || enrollmentIds.length === 0 || applyingPlacementRef.current) {
                return;
            }

            applyingPlacementRef.current = true;
            setApplyingPlacement(true);
            router.post(
                '/enrollments/bulk-placement',
                {
                    enrollment_ids: enrollmentIds.map((id) => Number(id)),
                    ...payload,
                },
                {
                    preserveScroll: true,
                    preserveState: true,
                    only: ['enrollments', 'filters', 'filterOptions', 'authorization'],
                    onFinish: () => {
                        applyingPlacementRef.current = false;
                        setApplyingPlacement(false);
                    },
                },
            );
        },
        [authorization.canUpdate, resolveActionIds],
    );

    const clearStructureFilters = useCallback(() => {
        searchDraftRef.current = '';
        visitList({
            q: '',
            gender: null,
            class_id: null,
            section_id: null,
            branch_id: null,
            department_id: null,
            specialization_id: null,
            page: 1,
        });
    }, [visitList]);

    const clearSelection = useCallback(() => {
        setCheckedIds([]);
        setSelectedId(null);
        setEditing(false);
        setEditingIds([]);
        editingIdsRef.current = [];
        setViewDialogEditing(false);
    }, []);

    const registerRowSave = useCallback(
        (enrollmentId: number, save: (() => Promise<void>) | null) => {
            if (save === null) {
                rowSaveFns.current.delete(enrollmentId);

                return;
            }

            rowSaveFns.current.set(enrollmentId, save);
        },
        [],
    );

    const startEditing = useCallback(() => {
        if (!authorization.canUpdate || editTargetIds.length === 0) {
            return;
        }

        setEditingIds(editTargetIds);
        editingIdsRef.current = editTargetIds;
        setEditing(true);
    }, [authorization.canUpdate, editTargetIds]);

    const saveEditingRows = useCallback(() => {
        if (savingRows) {
            return;
        }

        const ids =
            editingIdsRef.current.length > 0 ? [...editingIdsRef.current] : [...editingIds];

        if (ids.length === 0) {
            return;
        }

        void (async () => {
            setSavingRows(true);
            try {
                for (const id of ids) {
                    const saveFn = rowSaveFns.current.get(id) ?? rowRefs.current.get(id)?.save;
                    if (!saveFn) {
                        throw new Error(`enrollment-row-missing:${id}`);
                    }
                    await saveFn();
                }
                setEditing(false);
                setEditingIds([]);
                editingIdsRef.current = [];
            } catch {
                // Stay in edit mode so the user can correct validation errors.
            } finally {
                setSavingRows(false);
            }
        })();
    }, [editingIds, savingRows]);

    const openViewDialog = useCallback(
        (dialogEditing = false) => {
            if (viewTargets.length === 0) {
                return;
            }

            setViewDialogEditing(dialogEditing);
            setViewingEnrollments(viewTargets);
        },
        [viewTargets],
    );

    const confirmDelete = useCallback(() => {
        if (deleteTarget === null) {
            return;
        }

        setDeleting(true);
        const today = (() => {
            const now = new Date();
            const year = now.getFullYear();
            const month = String(now.getMonth() + 1).padStart(2, '0');
            const day = String(now.getDate()).padStart(2, '0');

            return `${year}-${month}-${day}`;
        })();
        router.post(
            '/enrollments/bulk-status',
            {
                enrollment_ids: [deleteTarget.id],
                status: 2,
                effective_to: today,
            },
            {
                preserveScroll: true,
                preserveState: true,
                only: ['enrollments', 'filters', 'filterOptions', 'authorization'],
                onSuccess: () => {
                    if (selectedId === deleteTarget.id) {
                        setSelectedId(null);
                    }
                    setCheckedIds((current) => current.filter((id) => id !== deleteTarget.id));
                },
                onFinish: () => {
                    setDeleting(false);
                    setDeleteTarget(null);
                },
            },
        );
    }, [deleteTarget, selectedId]);

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
                disabled: !hasViewTargets || !authorization.canView,
                onSelect: () => openViewDialog(false),
            },
        ];

        if (authorization.canCreate) {
            commands.push({
                id: 'create-enrollment',
                label: i18n.enrollments.enrollStudent,
                icon: UserPlus,
                onSelect: () => {
                    setCreateStudentId(null);
                    setCreatingEnrollment(true);
                },
            });
        }

        if (authorization.canUpdate || authorization.canCancel) {
            commands.push(
                {
                    id: 'edit-enrollment',
                    label: i18n.common.edit,
                    icon: Pencil,
                    tone: 'edit',
                    disabled: !hasEditTargets || applyingPlacement || applyingStatus || savingRows,
                    onSelect: startEditing,
                },
                {
                    id: 'save-enrollment',
                    label: i18n.common.save,
                    icon: Save,
                    tone: 'save',
                    disabled: !editing || savingRows,
                    onSelect: saveEditingRows,
                },
                {
                    id: 'cancel-enrollment-selection',
                    label: i18n.common.cancel,
                    icon: XCircle,
                    disabled: !hasActiveSelection || applyingPlacement || applyingStatus || savingRows,
                    onSelect: clearSelection,
                },
                {
                    id: 'delete-enrollment',
                    label: i18n.common.delete,
                    icon: Trash2,
                    tone: 'delete',
                    disabled: !canDelete || applyingPlacement || applyingStatus || savingRows || editing,
                    onSelect: () => {
                        if (selectedRow !== null) {
                            setDeleteTarget(selectedRow);
                        }
                    },
                },
            );
        }

        return [
            {
                id: 'enrollment-list-actions',
                label: i18n.common.actions,
                commands,
            },
        ];
    }, [
        applyingPlacement,
        applyingStatus,
        authorization.canCancel,
        authorization.canCreate,
        authorization.canUpdate,
        authorization.canView,
        canDelete,
        clearSelection,
        editing,
        hasActiveSelection,
        hasEditTargets,
        hasViewTargets,
        i18n.common.actions,
        i18n.common.cancel,
        i18n.common.delete,
        i18n.common.edit,
        i18n.common.save,
        i18n.common.view,
        i18n.enrollments.enrollStudent,
        openViewDialog,
        saveEditingRows,
        savingRows,
        selectedRow,
        startEditing,
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
                <div
                    className={
                        isEditMode
                            ? 'sis-enrollments-filters-bar sis-enrollments-filters-bar--edit'
                            : 'sis-enrollments-filters-bar sis-enrollments-filters-bar--filter'
                    }
                    role="search"
                    aria-label={i18n.enrollments.structureFiltersTitle}
                    aria-busy={filtersBusy || undefined}
                >
                <div className="sis-enrollments-filter-chip sis-enrollments-filter-chip--year" dir="rtl">
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
                            onYearChange={(yearId) => {
                                if (filtersBusy) {
                                    return;
                                }

                                visitList({
                                    academic_year_id: yearId,
                                    page: 1,
                                });
                            }}
                            label={i18n.enrollments.academicYear}
                            showLabel={false}
                            compact
                            showCurrentBadge={false}
                            disabled={filtersBusy}
                            controlClassName="sis-admission-year-control"
                        />
                    </span>
                </div>

                <div className="sis-enrollments-filter-chip sis-enrollments-filter-chip--gender"
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
                            key={isEditMode ? 'gender-edit' : 'gender-filter'}
                            value={isEditMode ? '' : genderValue}
                            options={[
                                { value: '', label: i18n.enrollments.gender },
                                { value: '1', label: i18n.students.male },
                                { value: '2', label: i18n.students.female },
                            ]}
                            onChange={(next) => {
                                if (isEditMode) {
                                    if (next === '' || filtersBusy) {
                                        return;
                                    }

                                    applyPlacementPatch({ gender: Number(next) });

                                    return;
                                }

                                visitList({
                                    gender: next === '1' || next === '2' ? Number(next) : null,
                                    page: 1,
                                });
                            }}
                            disabled={filtersBusy}
                            triggerClassName="sis-ops-hub__link px-2 py-1 min-h-0 min-w-0 sis-admission-year-control"
                            dir="rtl"
                            ariaLabel={i18n.enrollments.filterByGender}
                        />
                    </span>
                </div>

                <div className="sis-enrollments-filter-chip sis-enrollments-filter-chip--branch"
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
                            key={isEditMode ? 'branch-edit' : 'branch-filter'}
                            value={isEditMode ? '' : branchValue}
                            options={[
                                { value: '', label: i18n.enrollments.allBranches },
                                ...filterOptions.branches.map((item) => ({
                                    value: String(item.id),
                                    label: item.name,
                                })),
                            ]}
                            onChange={(next) => {
                                if (isEditMode) {
                                    if (next === '' || filtersBusy) {
                                        return;
                                    }

                                    applyPlacementPatch({ branch_id: Number(next) });

                                    return;
                                }

                                visitList({
                                    branch_id: next === '' ? null : Number(next),
                                    department_id: null,
                                    specialization_id: null,
                                    page: 1,
                                });
                            }}
                            disabled={filtersBusy}
                            triggerClassName="sis-ops-hub__link px-2 py-1 min-h-0 min-w-0 sis-admission-year-control"
                            dir="rtl"
                            ariaLabel={i18n.enrollments.filterByBranch}
                        />
                    </span>
                </div>

                <div className="sis-enrollments-filter-chip sis-enrollments-filter-chip--department"
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
                            key={isEditMode ? 'department-edit' : 'department-filter'}
                            value={isEditMode ? '' : departmentIdValue}
                            options={[
                                { value: '', label: i18n.enrollments.allDepartments },
                                ...filterDepartments.map((item) => ({
                                    value: String(item.id),
                                    label: item.name,
                                })),
                            ]}
                            onChange={(next) => {
                                if (isEditMode) {
                                    if (next === '' || filtersBusy) {
                                        return;
                                    }

                                    applyPlacementPatch({ department_id: Number(next) });

                                    return;
                                }

                                visitList({
                                    department_id: next === '' ? null : Number(next),
                                    specialization_id: null,
                                    page: 1,
                                });
                            }}
                            disabled={filtersBusy}
                            triggerClassName="sis-ops-hub__link px-2 py-1 min-h-0 min-w-0 sis-admission-year-control"
                            dir="rtl"
                            ariaLabel={i18n.enrollments.filterByDepartment}
                        />
                    </span>
                </div>

                <div className="sis-enrollments-filter-chip sis-enrollments-filter-chip--specialization"
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
                            key={isEditMode ? 'specialization-edit' : 'specialization-filter'}
                            value={isEditMode ? '' : specializationValue}
                            options={[
                                { value: '', label: i18n.enrollments.allSpecializations },
                                ...filterSpecializations.map((item) => ({
                                    value: String(item.id),
                                    label: item.name,
                                })),
                            ]}
                            onChange={(next) => {
                                if (isEditMode) {
                                    if (next === '' || filtersBusy) {
                                        return;
                                    }

                                    applyPlacementPatch({ specialization_id: Number(next) });

                                    return;
                                }

                                visitList({
                                    specialization_id: next === '' ? null : Number(next),
                                    page: 1,
                                });
                            }}
                            disabled={filtersBusy}
                            triggerClassName="sis-ops-hub__link px-2 py-1 min-h-0 min-w-0 sis-admission-year-control"
                            dir="rtl"
                            ariaLabel={i18n.enrollments.filterBySpecialization}
                        />
                    </span>
                </div>

                <div className="sis-enrollments-filter-chip sis-enrollments-filter-chip--class"
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
                            key={isEditMode ? 'class-edit' : 'class-filter'}
                            value={isEditMode ? '' : classValue}
                            options={[
                                { value: '', label: i18n.enrollments.allClasses },
                                ...filterOptions.classes.map((item) => ({
                                    value: String(item.id),
                                    label: item.name,
                                })),
                            ]}
                            onChange={(next) => {
                                if (isEditMode) {
                                    if (next === '' || filtersBusy) {
                                        return;
                                    }

                                    applyPlacementPatch({ class_id: Number(next) });

                                    return;
                                }

                                visitList({
                                    class_id: next === '' ? null : Number(next),
                                    section_id: null,
                                    page: 1,
                                });
                            }}
                            disabled={filtersBusy}
                            triggerClassName="sis-ops-hub__link px-2 py-1 min-h-0 min-w-0 sis-admission-year-control"
                            dir="rtl"
                            ariaLabel={i18n.enrollments.filterByClass}
                        />
                    </span>
                </div>

                <div className="sis-enrollments-filter-chip sis-enrollments-filter-chip--section"
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
                            key={isEditMode ? 'section-edit' : 'section-filter'}
                            value={isEditMode ? '' : sectionValue}
                            options={[
                                { value: '', label: i18n.enrollments.allSections },
                                ...filterSections.map((item) => ({
                                    value: String(item.id),
                                    label: item.name,
                                })),
                            ]}
                            onChange={(next) => {
                                if (isEditMode) {
                                    if (next === '' || filtersBusy) {
                                        return;
                                    }

                                    applyPlacementPatch({ section_id: Number(next) });

                                    return;
                                }

                                visitList({
                                    section_id: next === '' ? null : Number(next),
                                    page: 1,
                                });
                            }}
                            disabled={filtersBusy}
                            triggerClassName="sis-ops-hub__link px-2 py-1 min-h-0 min-w-0 sis-admission-year-control"
                            dir="rtl"
                            ariaLabel={i18n.enrollments.filterBySection}
                        />
                    </span>
                </div>

                <button
                    type="button"
                    className="sis-enrollments-filter-clear"
                    onClick={clearStructureFilters}
                    disabled={filtersBusy}
                    aria-label={i18n.enrollments.clearFiltersAria}
                    title={i18n.enrollments.clearFiltersAria}
                >
                    {i18n.enrollments.clearFilters}
                </button>
                {actionIds.length > 0 ? (
                    <button
                        type="button"
                        className="sis-enrollments-filter-clear"
                        onClick={clearSelection}
                        disabled={filtersBusy}
                        aria-label={i18n.enrollments.clearSelectionAria}
                        title={i18n.enrollments.clearSelectionAria}
                    >
                        {i18n.enrollments.clearSelection}
                    </button>
                ) : null}
                </div>

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
                                    <table ref={tableRef}>
                                        <thead>
                                            <tr>
                                                {canSelect ? (
                                                    <th className="sis-admission-drafts-table__select">
                                                        <input
                                                            ref={selectAllRef}
                                                            type="checkbox"
                                                            checked={allChecked}
                                                            disabled={applyingStatus || applyingPlacement}
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
                                                <th>{i18n.enrollments.academicYear}</th>
                                                <th>{i18n.enrollments.className}</th>
                                                <th>{i18n.enrollments.sectionName}</th>
                                                <th>{i18n.enrollments.branchName}</th>
                                                <th>{i18n.enrollments.departmentName}</th>
                                                <th>{i18n.enrollments.specialization}</th>
                                                <th>{i18n.enrollments.gradeLevel}</th>
                                                <th>{i18n.enrollments.stageName}</th>
                                                <th>{i18n.enrollments.enrollmentNumber}</th>
                                                <th>{i18n.enrollments.effectiveFrom}</th>
                                                <th>{i18n.enrollments.effectiveTo}</th>
                                                <th>{i18n.enrollments.statusTabsTitle}</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {rows.map((row, index) => {
                                                const selected = selectedId === row.id;
                                                const checked = checkedIds.includes(row.id);
                                                const rowEditing =
                                                    editing && editingIds.includes(row.id);

                                                return (
                                                    <EnrollmentEditorRow
                                                        key={row.id}
                                                        ref={(handle) => {
                                                            if (handle) {
                                                                rowRefs.current.set(row.id, handle);
                                                            } else {
                                                                rowRefs.current.delete(row.id);
                                                            }
                                                        }}
                                                        row={row}
                                                        rowNumber={rowOffset + index + 1}
                                                        canSelect={canSelect}
                                                        selected={selected}
                                                        checked={checked}
                                                        editing={rowEditing}
                                                        busy={
                                                            applyingStatus
                                                            || applyingPlacement
                                                            || savingRows
                                                        }
                                                        search={filters.q}
                                                        filterOptions={filterOptions}
                                                        onSelect={selectRow}
                                                        onToggleChecked={toggleChecked}
                                                        onRegisterSave={registerRowSave}
                                                    />
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

            {viewingEnrollments !== null && viewingEnrollments.length > 0 ? (
                <EnrollmentViewDialog
                    enrollments={viewingEnrollments}
                    canUpdate={authorization.canUpdate}
                    filterOptions={filterOptions}
                    initialEditing={viewDialogEditing}
                    onClose={() => {
                        setViewingEnrollments(null);
                        setViewDialogEditing(false);
                    }}
                    onSaved={(updated) => {
                        setViewingEnrollments((current) =>
                            current === null
                                ? current
                                : current.map((row) =>
                                      row.id === updated.id ? { ...row, ...updated } : row,
                                  ),
                        );
                    }}
                />
            ) : null}

            {creatingEnrollment ? (
                <EnrollmentCreateDialog
                    academicYearId={filters.academic_year_id}
                    filterOptions={filterOptions}
                    initialStudentId={createStudentId}
                    onClose={() => {
                        setCreatingEnrollment(false);
                        setCreateStudentId(null);
                    }}
                    onCreated={() => {
                        const studentQuery =
                            createStudentId !== null && createStudentId > 0
                                ? String(createStudentId)
                                : undefined;
                        setCreateStudentId(null);
                        visitList({ page: 1, q: studentQuery });
                    }}
                />
            ) : null}

            <ConfirmDialog
                open={deleteTarget !== null}
                title={i18n.enrollments.deleteTitle}
                description={i18n.enrollments.deleteConfirm}
                confirmLabel={i18n.common.delete}
                tone="danger"
                confirmPending={deleting}
                onConfirm={confirmDelete}
                onOpenChange={(open) => {
                    if (!open && !deleting) {
                        setDeleteTarget(null);
                    }
                }}
            />
        </div>
    );
}
