import { router, usePage } from '@inertiajs/react';
import {
    CheckCircle2,
    CircleSlash,
    Eye,
    FilterX,
    GraduationCap,
    History,
    PauseCircle,
    Pencil,
    Save,
    Trash2,
    UserMinus,
    Users,
    XCircle,
    type LucideIcon,
} from 'lucide-react';
import { useCallback, useEffect, useMemo, useRef, useState, lazy, Suspense } from 'react';
import {
    EnrollmentPlacementHistoryDialog,
    type PlacementHistoryPayload,
} from '@/components/enrollments/enrollment-placement-history-dialog';
import {
    EnrollmentEditorRow,
    type EnrollmentRowHandle,
    genderLabelFor,
    statusLabel,
    studentQuadName,
} from '@/components/enrollments/enrollment-editor-row';
import { ariaSort, SortButton, useTableSort } from '@/components/sis/table-sort';
import { sisListPageClass } from '@/components/sis/page-theme';
import { useOverviewHome, type OverviewCard } from '@/components/sis/module-overview';
import { STUDENT_STATUS_TONES } from '@/components/sis/table-status-badge';
import type {
    EnrollmentFilterOptions,
    EnrollmentListItem,
} from '@/components/enrollments/enrollment-types';
import { ConfirmDialog } from '@/components/sis/confirm-dialog';
import { useFitTablePageSize } from '@/hooks/use-fit-table-page-size';
import {
    clipRowsToFitPageSize,
    fitAwareLastPage,
    useDebouncedFitPageSync,
} from '@/lib/sis-ribbon-layout';
import { writeStoredListPage } from '@/lib/sis-list-page-storage';
import { usePageError } from '@/components/sis/page-error-context';
import { OpsYearFilter } from '@/components/sis/ops-year-filter';
import { RibbonFilterField } from '@/components/sis/ribbon-filter';
import { encodeIds, sameIds } from '@/lib/filter-values';
import { SisListSelect } from '@/components/sis/sis-list-select';
import {
    resolveSisClassId,
    resolveSisClassKey,
    resolveSisSectionCode,
    resolveSisSectionId,
    sisClassSelectOptions,
    sisSectionSelectOptions,
} from '@/lib/sis-class-section-options';
import {
    tableActionIds,
    toggleTableRowChecked,
    toggleTableSelectAll,
} from '@/components/sis/table-row-selection';
import {
    useRegisterPageRibbon,
    useSetActivePageRibbonTab,
    type PageRibbonCommand,
    type PageRibbonGroup,
} from '@/components/sis/page-ribbon-context';
import { useRegisterPageTitlebarHome } from '@/components/sis/page-titlebar-home-context';
import { useRegisterPageTitlebarSearch } from '@/components/sis/page-titlebar-search-context';
import { useAutoFitTableColumns } from '@/hooks/use-auto-fit-table-columns';
import { useSmoothVerticalScroll } from '@/hooks/use-smooth-vertical-scroll';
import {
    clearEnrollmentHandoff,
    readEnrollmentHandoff,
    type EnrollmentHandoffStudent,
} from '@/lib/enrollment-handoff';
import {
    publishStudentStatusSync,
    subscribeStudentStatusSync,
} from '@/lib/student-status-sync';
import { sisSmoothMutation } from '@/lib/sis-ui-perf';
import { t } from '@/i18n';

const ENROLLMENTS_PER_PAGE = 17;

const EnrollmentViewDialog = lazy(async () => {
    const mod = await import('@/components/enrollments/enrollment-record-form');

    return { default: mod.EnrollmentViewDialog };
});
const EnrollmentCreateDialog = lazy(async () => {
    const mod = await import('@/components/enrollments/enrollment-record-form');

    return { default: mod.EnrollmentCreateDialog };
});

type HandoffPlacementDraft = {
    branch_id: string;
    department_id: string;
    specialization_id: string;
    class_id: string;
    section_id: string;
};

function emptyHandoffDraft(): HandoffPlacementDraft {
    return {
        branch_id: '',
        department_id: '',
        specialization_id: '',
        class_id: '',
        section_id: '',
    };
}

function todayIsoDate(): string {
    const now = new Date();
    const year = now.getFullYear();
    const month = String(now.getMonth() + 1).padStart(2, '0');
    const day = String(now.getDate()).padStart(2, '0');

    return `${year}-${month}-${day}`;
}

const ENROLLMENT_STATUS_TABS: Array<{
    status: number | null;
    icon: LucideIcon;
    tone: 'light' | 'dark';
}> = [
    { status: null, icon: Users, tone: 'dark' },
    { status: 1, icon: CheckCircle2, tone: 'dark' },
    { status: 0, icon: CircleSlash, tone: 'light' },
    { status: 2, icon: PauseCircle, tone: 'dark' },
    { status: 3, icon: GraduationCap, tone: 'dark' },
    { status: 4, icon: UserMinus, tone: 'light' },
];

const ENROLLMENT_SUPERSEDED_TAB: {
    status: number;
    icon: LucideIcon;
    tone: 'light' | 'dark';
} = { status: 5, icon: History, tone: 'light' };

const ENROLLMENT_STATUS_ACTIONS: Array<{
    status: number;
    icon: LucideIcon;
    tone: 'light' | 'dark';
}> = [
    { status: 1, icon: CheckCircle2, tone: 'dark' },
    { status: 0, icon: CircleSlash, tone: 'light' },
    { status: 2, icon: PauseCircle, tone: 'dark' },
    { status: 3, icon: GraduationCap, tone: 'dark' },
    { status: 4, icon: UserMinus, tone: 'light' },
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
        /** The ribbon filters accept several choices: ids of the chosen classes / sections / branches / departments. */
        class_id: number[];
        section_id: number[];
        department_name: string | null;
        specialization_id: number | null;
        branch_id: number[];
        department_id: number[];
        page: number;
        per_page: number;
        academic_year_id: number | null;
    };
    filterOptions: EnrollmentFilterOptions;
    authorization: EnrollmentAuthorization;
    placementHistory?: PlacementHistoryPayload[] | null;
};

type VisitParams = {
    q?: string;
    status?: number | null;
    page?: number;
    per_page?: number;
    academic_year_id?: number | null;
    gender?: number | null;
    class_id?: number[];
    section_id?: number[];
    department_name?: string | null;
    specialization_id?: number | null;
    branch_id?: number[];
    department_id?: number[];
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
        2: i18n.status.suspended,
        3: i18n.status.graduated,
        4: i18n.status.withdrawn,
    };

    return labels[status] ?? String(status);
}

/** The cards of the module's overview page: this page's status tabs, each opening the list on its status. */
export function enrollmentsOverviewCards(
    enrollments: EnrollmentsPayload,
    i18n: ReturnType<typeof t>,
): OverviewCard[] {
    return ENROLLMENT_STATUS_TABS.map((tab) => ({
        key: tab.status === null ? 'all' : String(tab.status),
        label: statusTabLabel(tab.status, i18n),
        icon: tab.icon,
        count: countForStatus(tab.status, enrollments.status_progress),
        tone: tab.status === null ? 'interview' : (STUDENT_STATUS_TONES[tab.status] ?? 'withdrawn'),
        href: tab.status === null ? '/enrollments' : `/enrollments?status=${tab.status}`,
    }));
}

export function EnrollmentList({
    enrollments,
    filters,
    filterOptions,
    authorization,
    placementHistory = null,
}: EnrollmentListProps) {
    useOverviewHome('/enrollments');
    const i18n = t();
    const { showError, showWarning, showInertiaErrors } = usePageError();
    const genderValue = filters.gender === 1 || filters.gender === 2 ? String(filters.gender) : '';
    const genderFilterLabel =
        genderValue === '1'
            ? i18n.students.male
            : genderValue === '2'
              ? i18n.students.female
              : i18n.enrollments.gender;
    // Several choices per filter: the class keys / section codes (A, B, C) the chosen ids stand for. The first one
    // stays the «current» value for the single-choice modes (placement edit, hand-off) and the cascades.
    const classKeys = [...new Set(filters.class_id.map((id) => resolveSisClassKey(id, filterOptions.classes)).filter((key) => key !== ''))];
    const sectionCodes = [...new Set(filters.section_id.map((id) => resolveSisSectionCode(id, filterOptions.sections)).filter((code) => code !== ''))];
    const branchValues = filters.branch_id.map(String);
    const departmentValues = filters.department_id.map(String);
    const classValue = classKeys[0] ?? '';
    const sectionValue = sectionCodes[0] ?? '';
    const branchValue = branchValues[0] ?? '';
    const departmentIdValue = departmentValues[0] ?? '';

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
    const [handoffStudents, setHandoffStudents] = useState<EnrollmentHandoffStudent[]>([]);
    const [handoffDraft, setHandoffDraft] = useState<HandoffPlacementDraft>(emptyHandoffDraft);
    const [handoffAcademicYearId, setHandoffAcademicYearId] = useState<number | null>(null);
    const [enrollingHandoff, setEnrollingHandoff] = useState(false);
    const [deleteTarget, setDeleteTarget] = useState<EnrollmentListItem | null>(null);
    /** Cancelling / suspending / withdrawing the selected enrollments waits for a confirmation. */
    const [statusConfirm, setStatusConfirm] = useState<number | null>(null);
    const [deleting, setDeleting] = useState(false);
    const selectAllRef = useRef<HTMLInputElement>(null);
    const tableRef = useRef<HTMLTableElement>(null);
    const scrollerRef = useRef<HTMLDivElement>(null);
    const fitPageSizeRef = useRef(ENROLLMENTS_PER_PAGE);
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
    const [statusOverrides, setStatusOverrides] = useState<Record<number, number>>({});
    const serverRows = enrollments?.data ?? [];
    const rows = useMemo(() => {
        const mapped = serverRows.map((row) => {
            const override = statusOverrides[row.student_id];
            if (override === undefined) {
                return row;
            }

            return { ...row, student_status: override };
        });

        if (filters.status === null) {
            return mapped;
        }

        return mapped.filter((row) => (row.student_status ?? null) === filters.status);
    }, [filters.status, serverRows, statusOverrides]);
    const pagination = enrollments?.meta ?? {
        page: filters.page,
        per_page: filters.per_page,
        total: 0,
        last_page: 1,
    };
    const rowOffset = (pagination.page - 1) * pagination.per_page;
    const fitPageSize = useFitTablePageSize(scrollerRef, {
        fallbackRows: ENROLLMENTS_PER_PAGE,
        enabled: true,
    });
    fitPageSizeRef.current = fitPageSize;
    const pageRows = useMemo(
        () => clipRowsToFitPageSize(rows, fitPageSize),
        [fitPageSize, rows],
    );
    // Header click sorts the page on screen (the list itself is paged on the server).
    const { rows: displayRows, sort } = useTableSort(pageRows, (row, key) => {
        switch (key) {
            case 'name':
                return studentQuadName(row);
            case 'gender':
                return genderLabelFor(row.student_gender, i18n);
            case 'year':
                return row.academic_year_name ?? row.academic_year_code;
            case 'class':
                return row.class_name ?? row.class_code;
            case 'section':
                return row.section_code ?? row.section_name;
            case 'branch':
                return row.branch_name ?? row.branch_code;
            case 'department':
                return row.department_name;
            case 'stage':
                return row.stage_name;
            case 'from':
                return row.effective_from;
            case 'to':
                return row.effective_to;
            case 'status':
                return statusLabel(row.student_status ?? 1, i18n);
            default:
                return null;
        }
    });
    const fitLastPage = fitAwareLastPage(
        pagination.total,
        fitPageSize,
        pagination.last_page,
    );
    const overallPercent = clampPercent(enrollments.status_progress?.overall_percent ?? 0);
    const canSelect = authorization.canUpdate || authorization.canCancel;

    const serverRowsRef = useRef(serverRows);
    serverRowsRef.current = serverRows;

    useEffect(() => {
        return subscribeStudentStatusSync((payload) => {
            setStatusOverrides((current) => {
                const next = { ...current };
                for (const studentId of payload.studentIds) {
                    next[studentId] = payload.status;
                }

                return next;
            });
        });
    }, []);

    useEffect(() => {
        setStatusOverrides((current) => {
            if (Object.keys(current).length === 0) {
                return current;
            }

            let changed = false;
            const next = { ...current };
            for (const row of serverRows) {
                const override = next[row.student_id];
                if (override !== undefined && row.student_status === override) {
                    delete next[row.student_id];
                    changed = true;
                }
            }

            return changed ? next : current;
        });
    }, [serverRows]);

    useEffect(() => {
        const handoff = readEnrollmentHandoff();
        if (handoff === null || handoff.students.length === 0) {
            return;
        }

        setHandoffStudents(handoff.students);
        setHandoffDraft(emptyHandoffDraft());
        setHandoffAcademicYearId(handoff.academic_year_id);

        if (
            handoff.academic_year_id !== null
            && handoff.academic_year_id > 0
            && handoff.academic_year_id !== filtersRef.current.academic_year_id
        ) {
            router.get(
                '/enrollments',
                {
                    academic_year_id: handoff.academic_year_id,
                    handoff: 1,
                },
                {
                    replace: true,
                    preserveState: true,
                    preserveScroll: true,
                },
            );
        }
    }, []);

    useEffect(() => {
        const query = page.url.includes('?') ? page.url.slice(page.url.indexOf('?') + 1) : '';
        const params = new URLSearchParams(query);
        const wantsHandoff = params.get('handoff') === '1';
        const wantsCreate = params.get('create') === '1';

        if (!wantsHandoff && !wantsCreate) {
            createIntentHandledRef.current = false;

            return;
        }

        if (createIntentHandledRef.current) {
            return;
        }

        createIntentHandledRef.current = true;

        const handoff = readEnrollmentHandoff();
        if (handoff !== null && handoff.students.length > 0) {
            setHandoffStudents(handoff.students);
            setHandoffDraft(emptyHandoffDraft());
            setHandoffAcademicYearId(handoff.academic_year_id);

            if (
                handoff.academic_year_id !== null
                && handoff.academic_year_id > 0
            ) {
                params.set('academic_year_id', String(handoff.academic_year_id));
            }
        }

        // Handoff from students uses the structure bar - never open create dialog.
        // Legacy ?create=1 without handoff still opens the dialog for ribbon/deep links.
        if (wantsCreate && authorization.canCreate && (handoff === null || handoff.students.length === 0)) {
            const studentIdRaw = params.get('student_id');
            const studentId =
                studentIdRaw !== null && studentIdRaw !== '' && Number(studentIdRaw) > 0
                    ? Number(studentIdRaw)
                    : null;
            setCreateStudentId(studentId);
            setCreatingEnrollment(true);
        }

        params.delete('handoff');
        params.delete('create');
        params.delete('student_id');
        const next = params.toString();
        router.get(next === '' ? '/enrollments' : `/enrollments?${next}`, {}, {
            replace: true,
            preserveState: true,
            preserveScroll: true,
        });
    }, [authorization.canCreate, page.url]);

    useAutoFitTableColumns(tableRef, scrollerRef, {
        storageKey: 'enrollments.list.v5',
        columnSignature: canSelect ? 'select-v5' : 'readonly-v5',
        enabled: displayRows.length > 0,
        rowsKey: displayRows.map((row) => row.id).join(','),
    });
    useSmoothVerticalScroll(scrollerRef, displayRows.length > 0);

    const rowIds = useMemo(() => displayRows.map((row) => row.id), [displayRows]);
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
    const isHandoffMode = handoffStudents.length > 0 && authorization.canCreate;
    const setActiveRibbonTab = useSetActivePageRibbonTab();
    const canApplyStatus =
        canSelect && actionIds.length > 0 && !applyingStatus && !applyingPlacement && !editing && !isHandoffMode;
    const isEditMode =
        !isHandoffMode && authorization.canUpdate && actionIds.length > 0 && !editing;
    const isStructureEditMode = isEditMode || isHandoffMode;

    useEffect(() => {
        if (isHandoffMode) {
            setActiveRibbonTab('home');
        }
    }, [isHandoffMode, setActiveRibbonTab]);

    const filtersBusy = applyingPlacement || applyingStatus || savingRows || enrollingHandoff;
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
        && selectedRow.student_status !== 4;
    const hasActiveSelection = actionIds.length > 0 || editing;

    const openPlacementHistory = useCallback(() => {
        const targets = viewTargets;
        if (targets.length === 0) {
            showWarning(i18n.enrollments.historyNeedsSelection);

            return;
        }

        const studentIds = [...new Set(targets.map((row) => row.student_id))];
        const current = filtersRef.current;
        router.get(
            '/enrollments',
            {
                q: current.q.trim() || undefined,
                page: current.page,
                per_page: fitPageSizeRef.current,
                status: current.status ?? undefined,
                academic_year_id: current.academic_year_id ?? undefined,
                gender: current.gender ?? undefined,
                class_id: encodeIds(current.class_id),
                section_id: encodeIds(current.section_id),
                branch_id: encodeIds(current.branch_id),
                department_id: encodeIds(current.department_id),
                specialization_id: current.specialization_id ?? undefined,
                history_student_ids: studentIds,
            },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
                only: ['placementHistory'],
            },
        );
    }, [i18n.enrollments.historyNeedsSelection, showWarning, viewTargets]);

    const closePlacementHistory = useCallback(() => {
        const current = filtersRef.current;
        router.get(
            '/enrollments',
            {
                q: current.q.trim() || undefined,
                page: current.page,
                per_page: fitPageSizeRef.current,
                status: current.status ?? undefined,
                academic_year_id: current.academic_year_id ?? undefined,
                gender: current.gender ?? undefined,
                class_id: encodeIds(current.class_id),
                section_id: encodeIds(current.section_id),
                branch_id: encodeIds(current.branch_id),
                department_id: encodeIds(current.department_id),
                specialization_id: current.specialization_id ?? undefined,
            },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
                only: ['placementHistory'],
            },
        );
    }, []);

    const sectionUiOptions = useMemo(
        () => [
            { value: '', label: i18n.enrollments.allSections },
            ...sisSectionSelectOptions(),
        ],
        [i18n.enrollments.allSections],
    );

    const filterDepartments = useMemo(() => {
        const activeBranches = isHandoffMode ? (handoffDraft.branch_id === '' ? [] : [handoffDraft.branch_id]) : branchValues;
        if (isEditMode || activeBranches.length === 0) {
            return filterOptions.departments;
        }

        return filterOptions.departments.filter(
            (department) =>
                department.branch_id === null || activeBranches.includes(String(department.branch_id)),
        );
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [filters.branch_id, filterOptions.departments, handoffDraft.branch_id, isEditMode, isHandoffMode]);

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

        const structureChanged =
            ('academic_year_id' in params && params.academic_year_id !== current.academic_year_id)
            || ('class_id' in params && !sameIds(params.class_id, current.class_id))
            || ('branch_id' in params && !sameIds(params.branch_id, current.branch_id))
            || ('department_id' in params && !sameIds(params.department_id, current.department_id))
            || ('specialization_id' in params
                && params.specialization_id !== current.specialization_id);

        const onlyProps = structureChanged
            ? (['enrollments', 'filters', 'filterOptions', 'authorization'] as const)
            : (['enrollments', 'filters', 'authorization'] as const);

        router.get(
            '/enrollments',
            {
                q: nextQuery.trim() || undefined,
                page: params.page ?? current.page,
                per_page: params.per_page ?? fitPageSizeRef.current,
                status:
                    nextStatus === 5 || nextStatus === null || nextStatus === undefined
                        ? undefined
                        : nextStatus,
                academic_year_id: nextYear ?? undefined,
                gender: nextGender ?? undefined,
                class_id: encodeIds(nextClassId ?? []),
                section_id: encodeIds(nextSectionId ?? []),
                branch_id: encodeIds(nextBranchId ?? []),
                department_id: encodeIds(nextDepartmentId ?? []),
                specialization_id: nextSpecialization ?? undefined,
            },
            {
                preserveState: true,
                preserveScroll: true,
                async: true,
                replace: true,
                only: [...onlyProps],
                showProgress: params.quiet !== true,
            },
        );
    }, []);

    const syncFitPageSize = useCallback(
        (nextPerPage: number) => {
            visitList({ per_page: nextPerPage, quiet: true });
        },
        [visitList],
    );
    useDebouncedFitPageSync(fitPageSize, filters.per_page, syncFitPageSize);

    const commitSearch = useCallback(
        (query: string) => {
            visitList({ q: query, quiet: true });
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
            if ((targetStatus === 0 || targetStatus === 2 || targetStatus === 3 || targetStatus === 4) && !authorization.canCancel) {
                return;
            }

            if (targetStatus === 1 && !authorization.canUpdate && !authorization.canCancel) {
                return;
            }

            applyingStatusRef.current = true;
            setApplyingStatus(true);

            const studentIds = [
                ...new Set(
                    enrollmentIds
                        .map((id) => serverRowsRef.current.find((row) => row.id === id)?.student_id ?? 0)
                        .filter((id) => id > 0),
                ),
            ];
            if (studentIds.length === 0) {
                applyingStatusRef.current = false;
                setApplyingStatus(false);

                return;
            }
            setStatusOverrides((current) => {
                const next = { ...current };
                for (const studentId of studentIds) {
                    next[studentId] = targetStatus;
                }

                return next;
            });
            publishStudentStatusSync(studentIds, targetStatus, 'enrollments');

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
                    // Keep numeric 0 (inactive) - never coerce via || / ?? falsy checks.
                    status: targetStatus,
                    effective_to: today,
                },
                {
                    ...sisSmoothMutation(['enrollments', 'filters', 'authorization']),
                    onSuccess: () => {
                        publishStudentStatusSync(studentIds, targetStatus, 'enrollments');
                    },
                    onError: (errors) => {
                        setStatusOverrides((current) => {
                            const next = { ...current };
                            for (const studentId of studentIds) {
                                delete next[studentId];
                            }

                            return next;
                        });
                        showInertiaErrors(errors, i18n.errors.statusFailed);
                    },
                    onFinish: () => {
                        applyingStatusRef.current = false;
                        setApplyingStatus(false);
                    },
                },
            );
        },
        [
            authorization.canCancel,
            authorization.canUpdate,
            i18n.errors.statusFailed,
            resolveActionIds,
            showInertiaErrors,
        ],
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
                    onError: (errors) => showInertiaErrors(errors, i18n.errors.placementFailed),
                    onFinish: () => {
                        applyingPlacementRef.current = false;
                        setApplyingPlacement(false);
                    },
                },
            );
        },
        [
            authorization.canUpdate,
            i18n.errors.placementFailed,
            resolveActionIds,
            showInertiaErrors,
        ],
    );

    const clearHandoff = useCallback(() => {
        clearEnrollmentHandoff();
        setHandoffStudents([]);
        setHandoffDraft(emptyHandoffDraft());
        setHandoffAcademicYearId(null);
        setEnrollingHandoff(false);
    }, []);

    const commitHandoffEnrollments = useCallback(
        async (draft: HandoffPlacementDraft) => {
            if (
                !authorization.canCreate
                || handoffStudents.length === 0
                || enrollingHandoff
                || draft.class_id === ''
                || draft.section_id === ''
            ) {
                return;
            }

            const academicYearId =
                handoffAcademicYearId !== null && handoffAcademicYearId > 0
                    ? handoffAcademicYearId
                    : filters.academic_year_id;

            if (academicYearId === null || academicYearId < 1) {
                showWarning(i18n.enrollments.handoffNeedsYear);

                return;
            }

            setEnrollingHandoff(true);
            const effectiveFrom = todayIsoDate();

            try {
                for (const student of handoffStudents) {
                    const idempotencyKey =
                        typeof crypto !== 'undefined' && 'randomUUID' in crypto
                            ? crypto.randomUUID()
                            : `enroll-handoff-${student.id}-${Date.now()}`;

                    await new Promise<void>((resolve, reject) => {
                        router.post(
                            '/enrollments',
                            {
                                student_id: student.id,
                                academic_year_id: academicYearId,
                                class_id: Number(draft.class_id),
                                section_id: Number(draft.section_id),
                                effective_from: effectiveFrom,
                                ...(draft.branch_id === ''
                                    ? {}
                                    : { branch_id: Number(draft.branch_id) }),
                                ...(draft.department_id === ''
                                    ? {}
                                    : { department_id: Number(draft.department_id) }),
                                ...(draft.specialization_id === ''
                                    ? {}
                                    : { specialization_id: Number(draft.specialization_id) }),
                            },
                            {
                                headers: { 'X-Idempotency-Key': idempotencyKey },
                                preserveScroll: true,
                                preserveState: true,
                                onSuccess: () => resolve(),
                                onError: (errors) => {
                                    showInertiaErrors(errors, i18n.errors.createFailed);
                                    reject(errors);
                                },
                            },
                        );
                    });
                }

                clearHandoff();
                visitList({
                    page: 1,
                    academic_year_id: academicYearId,
                    class_id: [Number(draft.class_id)],
                    section_id: [Number(draft.section_id)],
                });
            } catch {
                // Errors already surfaced via showInertiaErrors.
            } finally {
                setEnrollingHandoff(false);
            }
        },
        [
            authorization.canCreate,
            clearHandoff,
            enrollingHandoff,
            filters.academic_year_id,
            handoffAcademicYearId,
            handoffStudents,
            i18n.enrollments.handoffNeedsYear,
            i18n.errors.createFailed,
            showError,
            showWarning,
            showInertiaErrors,
            visitList,
        ],
    );

    const patchHandoffDraft = useCallback(
        (patch: Partial<HandoffPlacementDraft>) => {
            setHandoffDraft((current) => {
                const next: HandoffPlacementDraft = {
                    ...current,
                    ...patch,
                };

                if ('class_id' in patch && patch.class_id !== current.class_id) {
                    next.section_id = '';
                }

                if ('branch_id' in patch && patch.branch_id !== current.branch_id) {
                    next.department_id = '';
                    next.specialization_id = '';
                }

                if ('department_id' in patch && patch.department_id !== current.department_id) {
                    next.specialization_id = '';
                }

                if (next.class_id !== '' && next.section_id !== '') {
                    queueMicrotask(() => {
                        void commitHandoffEnrollments(next);
                    });
                }

                return next;
            });
        },
        [commitHandoffEnrollments],
    );

    const clearStructureFilters = useCallback(() => {
        if (isHandoffMode) {
            clearHandoff();
        }

        searchDraftRef.current = '';
        visitList({
            q: '',
            gender: null,
            class_id: [],
            section_id: [],
            branch_id: [],
            department_id: [],
            specialization_id: null,
        });
    }, [clearHandoff, isHandoffMode, visitList]);

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
            } catch (error) {
                // Stay in edit mode; row save or local guard already reported when possible.
                if (error instanceof Error && error.message.startsWith('enrollment-row-missing:')) {
                    showError(i18n.errors.saveFailed);
                }
            } finally {
                setSavingRows(false);
            }
        })();
    }, [editingIds, i18n.errors.saveFailed, savingRows, showError]);

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
        setStatusOverrides((current) => ({
            ...current,
            [deleteTarget.student_id]: 4,
        }));
        publishStudentStatusSync([deleteTarget.student_id], 4, 'enrollments');
        router.post(
            '/enrollments/bulk-status',
            {
                enrollment_ids: [deleteTarget.id],
                status: 4,
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
                onError: (errors) => {
                    setStatusOverrides((current) => {
                        const next = { ...current };
                        delete next[deleteTarget.student_id];

                        return next;
                    });
                    showInertiaErrors(errors, i18n.errors.deleteFailed);
                },
                onFinish: () => {
                    setDeleting(false);
                    setDeleteTarget(null);
                },
            },
        );
    }, [deleteTarget, i18n.errors.deleteFailed, selectedId, showInertiaErrors]);

    const onStatusTabClick = useCallback(
        (status: number | null, isActive: boolean) => {
            if (isActive) {
                return;
            }

            visitList({
                status,
            });
        },
        [visitList],
    );

    const goPage = useCallback(
        (page: number) => {
            visitList({ page });
            writeStoredListPage('enrollments', page);
        },
        [visitList],
    );

    const homeRibbonGroups = useMemo((): PageRibbonGroup[] => {
        return [
            {
                id: 'enrollment-filters',
                label: i18n.enrollments.ribbonFilters,
                commands: [],
                custom: (
                    <div
                        className="sis-ribbon__filters"
                        aria-label={i18n.enrollments.structureFiltersTitle}
                        aria-busy={filtersBusy || undefined}
                    >
                        <div className="sis-ribbon__filters-stack sis-ribbon__filters-stack--enrollments">
                            <div className="sis-ribbon__filter-field" dir="rtl">
                                <OpsYearFilter
                                    action="/enrollments"
                                    academicYearId={filters.academic_year_id}
                                    extraParams={{
                                        get q() {
                                            const value = searchDraftRef.current.trim();

                                            return value === '' ? undefined : value;
                                        },
                                        per_page: filters.per_page || ENROLLMENTS_PER_PAGE,
                                        status: filters.status ?? undefined,
                                        gender: filters.gender ?? undefined,
                                        class_id: encodeIds(filters.class_id),
                                        section_id: encodeIds(filters.section_id),
                                        branch_id: encodeIds(filters.branch_id),
                                        department_id: encodeIds(filters.department_id),
                                        specialization_id: filters.specialization_id ?? undefined,
                                    }}
                                    onYearChange={(yearId) => {
                                        if (filtersBusy) {
                                            return;
                                        }

                                        visitList({
                                            academic_year_id: yearId,
                                        });
                                    }}
                                    label={i18n.enrollments.academicYear}
                                    showLabel={false}
                                    compact
                                    showCurrentBadge={false}
                                    disabled={filtersBusy}
                                    controlClassName="sis-admission-year-control"
                                />
                            </div>

                            <div className="sis-ribbon__filter-field" dir="rtl">
                                <span className="sis-admission-select-fit">
                                    <span className="sis-admission-select-fit__mirror" aria-hidden="true">
                                        {genderFilterLabel}
                                    </span>
                                    <SisListSelect
                                        key={isStructureEditMode ? 'gender-edit' : 'gender-filter'}
                                        value={isStructureEditMode ? '' : genderValue}
                                        options={[
                                            { value: '', label: i18n.enrollments.gender },
                                            { value: '1', label: i18n.students.male },
                                            { value: '2', label: i18n.students.female },
                                        ]}
                                        onChange={(next) => {
                                            if (isHandoffMode) {
                                                return;
                                            }

                                            if (isEditMode) {
                                                if (next === '' || filtersBusy) {
                                                    return;
                                                }

                                                applyPlacementPatch({ gender: Number(next) });

                                                return;
                                            }

                                            visitList({
                                                gender: next === '1' || next === '2' ? Number(next) : null,
                                            });
                                        }}
                                        disabled={filtersBusy || isHandoffMode}
                                        triggerClassName="sis-ops-hub__link px-2 py-1 min-h-0 min-w-0 sis-admission-year-control"
                                        dir="rtl"
                                        ariaLabel={i18n.enrollments.filterByGender}
                                    />
                                </span>
                            </div>

                            {/* The structure filters take several choices at once (shared field: components/sis/ribbon-filter);
                                while a placement is being edited / handed off they stay single-choice assignments. */}
                            <RibbonFilterField
                                key={isStructureEditMode ? 'branch-edit' : 'branch-filter'}
                                spec={
                                    isHandoffMode || isEditMode
                                        ? {
                                              key: 'branch',
                                              type: 'single',
                                              label: i18n.enrollments.filterByBranch,
                                              disabled: filtersBusy,
                                              value: isHandoffMode ? handoffDraft.branch_id : '',
                                              options: [
                                                  { value: '', label: i18n.enrollments.allBranches },
                                                  ...filterOptions.branches.map((item) => ({ value: String(item.id), label: item.name })),
                                              ],
                                              onChange: (next) => {
                                                  if (filtersBusy) {
                                                      return;
                                                  }
                                                  if (isHandoffMode) {
                                                      patchHandoffDraft({ branch_id: next });

                                                      return;
                                                  }
                                                  if (next !== '') {
                                                      applyPlacementPatch({ branch_id: Number(next) });
                                                  }
                                              },
                                          }
                                        : {
                                              key: 'branch',
                                              type: 'multi',
                                              label: i18n.enrollments.filterByBranch,
                                              allLabel: i18n.enrollments.allBranches,
                                              disabled: filtersBusy,
                                              values: branchValues,
                                              options: filterOptions.branches.map((item) => ({ value: String(item.id), label: item.name })),
                                              onChange: (values) =>
                                                  visitList({
                                                      branch_id: values.map(Number),
                                                      department_id: [],
                                                      specialization_id: null,
                                                  }),
                                          }
                                }
                            />

                            <RibbonFilterField
                                key={isStructureEditMode ? 'department-edit' : 'department-filter'}
                                spec={
                                    isHandoffMode || isEditMode
                                        ? {
                                              key: 'department',
                                              type: 'single',
                                              label: i18n.enrollments.filterByDepartment,
                                              disabled: filtersBusy,
                                              value: isHandoffMode ? handoffDraft.department_id : '',
                                              options: [
                                                  { value: '', label: i18n.enrollments.allDepartments },
                                                  ...filterDepartments.map((item) => ({ value: String(item.id), label: item.name })),
                                              ],
                                              onChange: (next) => {
                                                  if (filtersBusy) {
                                                      return;
                                                  }
                                                  if (isHandoffMode) {
                                                      patchHandoffDraft({ department_id: next });

                                                      return;
                                                  }
                                                  if (next !== '') {
                                                      applyPlacementPatch({ department_id: Number(next) });
                                                  }
                                              },
                                          }
                                        : {
                                              key: 'department',
                                              type: 'multi',
                                              label: i18n.enrollments.filterByDepartment,
                                              allLabel: i18n.enrollments.allDepartments,
                                              disabled: filtersBusy,
                                              values: departmentValues,
                                              options: filterDepartments.map((item) => ({ value: String(item.id), label: item.name })),
                                              onChange: (values) =>
                                                  visitList({
                                                      department_id: values.map(Number),
                                                      specialization_id: null,
                                                  }),
                                          }
                                }
                            />

                            <RibbonFilterField
                                key={isStructureEditMode ? 'class-edit' : 'class-filter'}
                                spec={
                                    isHandoffMode || isEditMode
                                        ? {
                                              key: 'class',
                                              type: 'single',
                                              label: i18n.enrollments.filterByClass,
                                              disabled: filtersBusy,
                                              value: isHandoffMode
                                                  ? resolveSisClassKey(
                                                        handoffDraft.class_id === '' ? null : Number(handoffDraft.class_id),
                                                        filterOptions.classes,
                                                    )
                                                  : '',
                                              options: [{ value: '', label: i18n.enrollments.allClasses }, ...sisClassSelectOptions()],
                                              onChange: (next) => {
                                                  if (filtersBusy) {
                                                      return;
                                                  }
                                                  const classId = next === '' ? null : resolveSisClassId(next, filterOptions.classes);
                                                  if (isHandoffMode) {
                                                      patchHandoffDraft({
                                                          class_id: classId === null ? '' : String(classId),
                                                          section_id: '',
                                                      });

                                                      return;
                                                  }
                                                  if (classId !== null) {
                                                      applyPlacementPatch({ class_id: classId });
                                                  }
                                              },
                                          }
                                        : {
                                              key: 'class',
                                              type: 'multi',
                                              label: i18n.enrollments.filterByClass,
                                              allLabel: i18n.enrollments.allClasses,
                                              disabled: filtersBusy,
                                              values: classKeys,
                                              options: sisClassSelectOptions(),
                                              onChange: (values) =>
                                                  visitList({
                                                      class_id: values
                                                          .map((key) => resolveSisClassId(key, filterOptions.classes))
                                                          .filter((id): id is number => id !== null),
                                                      section_id: [],
                                                  }),
                                          }
                                }
                            />

                            <RibbonFilterField
                                key={isStructureEditMode ? 'section-edit' : 'section-filter'}
                                spec={
                                    isHandoffMode || isEditMode
                                        ? {
                                              key: 'section',
                                              type: 'single',
                                              label: i18n.enrollments.filterBySection,
                                              disabled: filtersBusy,
                                              value: isHandoffMode
                                                  ? resolveSisSectionCode(
                                                        handoffDraft.section_id === '' ? null : Number(handoffDraft.section_id),
                                                        filterOptions.sections,
                                                    )
                                                  : '',
                                              options: sectionUiOptions,
                                              onChange: (next) => {
                                                  if (filtersBusy || next === '') {
                                                      return;
                                                  }
                                                  const classId = isHandoffMode
                                                      ? handoffDraft.class_id === ''
                                                          ? null
                                                          : Number(handoffDraft.class_id)
                                                      : classValue === ''
                                                        ? null
                                                        : resolveSisClassId(classValue, filterOptions.classes);
                                                  if (classId === null) {
                                                      showWarning(i18n.enrollments.handoffNeedsPlacement);

                                                      return;
                                                  }
                                                  const sectionId = resolveSisSectionId(next, classId, filterOptions.sections);
                                                  if (sectionId === null) {
                                                      showWarning(i18n.students.enrollSectionNotFound);

                                                      return;
                                                  }
                                                  if (isHandoffMode) {
                                                      patchHandoffDraft({ section_id: String(sectionId) });

                                                      return;
                                                  }
                                                  applyPlacementPatch({ section_id: sectionId });
                                              },
                                          }
                                        : {
                                              key: 'section',
                                              type: 'multi',
                                              label: i18n.enrollments.filterBySection,
                                              allLabel: i18n.enrollments.allSections,
                                              disabled: filtersBusy,
                                              values: sectionCodes,
                                              options: sisSectionSelectOptions(),
                                              onChange: (codes) => {
                                                  // A shared code (A / B / C) stands for one section of every chosen class.
                                                  const ids = filterOptions.sections
                                                      .filter(
                                                          (section) =>
                                                              codes.includes(resolveSisSectionCode(section.id, filterOptions.sections))
                                                              && (filters.class_id.length === 0 || filters.class_id.includes(section.class_id)),
                                                      )
                                                      .map((section) => section.id);
                                                  if (codes.length > 0 && ids.length === 0) {
                                                      showWarning(i18n.students.enrollSectionNotFound);

                                                      return;
                                                  }
                                                  visitList({ section_id: ids });
                                              },
                                          }
                                }
                            />
                        </div>
                        <button
                            type="button"
                            className="sis-ribbon__item sis-ribbon__item--filter-clear"
                            disabled={filtersBusy}
                            aria-label={i18n.enrollments.clearFiltersAria}
                            title={i18n.enrollments.clearFiltersAria}
                            onClick={clearStructureFilters}
                        >
                            <FilterX className="sis-ribbon__icon" aria-hidden />
                            <span className="sis-ribbon__label">{i18n.enrollments.clearFilters}</span>
                        </button>
                    </div>
                ),
            },
        ];
    }, [
        applyPlacementPatch,
        branchValue,
        classValue,
        clearStructureFilters,
        departmentIdValue,
        filterDepartments,
        filterOptions.branches,
        filterOptions.classes,
        filterOptions.sections,
        sectionUiOptions,
        filters.academic_year_id,
        filters.branch_id,
        filters.class_id,
        filters.department_id,
        filters.gender,
        filters.section_id,
        filters.status,
        filtersBusy,
        genderFilterLabel,
        genderValue,
        handoffDraft.branch_id,
        handoffDraft.class_id,
        handoffDraft.department_id,
        handoffDraft.section_id,
        i18n,
        isEditMode,
        isHandoffMode,
        isStructureEditMode,
        patchHandoffDraft,
        sectionValue,
        showWarning,
        visitList,
    ]);

    const editRibbonGroups = useMemo((): PageRibbonGroup[] => {
        const actionCommands: PageRibbonCommand[] = [
            {
                id: 'view-enrollment',
                label: i18n.common.view,
                icon: Eye,
                disabled: !hasViewTargets,
                onSelect: () => openViewDialog(false),
            },
        ];

        if (canSelect) {
            actionCommands.push(
                {
                    id: 'edit-enrollment',
                    label: i18n.common.edit,
                    icon: Pencil,
                    tone: 'edit',
                    disabled: !hasEditTargets || savingRows,
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
                    disabled: !hasActiveSelection || savingRows,
                    onSelect: clearSelection,
                },
                {
                    id: 'delete-enrollment',
                    label: i18n.common.delete,
                    icon: Trash2,
                    tone: 'delete',
                    disabled: !canDelete || savingRows,
                    onSelect: () => {
                        if (selectedRow !== null) {
                            setDeleteTarget(selectedRow);
                        }
                    },
                },
            );
        }

        const statusCommands: PageRibbonCommand[] = ENROLLMENT_STATUS_TABS.map((tab) => {
            const full = statusTabLabel(tab.status, i18n);
            const count = countForStatus(tab.status, enrollments.status_progress);

            return {
                id: tab.status === null ? 'status-tab-all' : `status-tab-${tab.status}`,
                label: full,
                title: `${full} (${count})`,
                icon: tab.icon,
                count,
                pressed: filters.status === tab.status,
                onSelect: () => onStatusTabClick(tab.status, filters.status === tab.status),
            };
        });

        const supersededLabel = i18n.enrollments.ribbonSuperseded;
        const supersededCommand: PageRibbonCommand = {
            id: 'placement-history',
            label: supersededLabel,
            title: hasViewTargets
                ? supersededLabel
                : i18n.enrollments.historyNeedsSelection,
            icon: ENROLLMENT_SUPERSEDED_TAB.icon,
            disabled: !hasViewTargets,
            onSelect: openPlacementHistory,
        };

        const distributionCommands: PageRibbonCommand[] = [];
        if (canSelect) {
            for (const action of ENROLLMENT_STATUS_ACTIONS) {
                const full = statusTabLabel(action.status, i18n);
                const needsCancel =
                    action.status === 0
                    || action.status === 2
                    || action.status === 3
                    || action.status === 4;

                distributionCommands.push({
                    id: `action-status-${action.status}`,
                    label: full,
                    title: canApplyStatus ? full : i18n.enrollments.statusNeedsSelection,
                    icon: action.icon,
                    disabled: !canApplyStatus || (needsCancel && !authorization.canCancel),
                    onSelect: () => (needsCancel ? setStatusConfirm(action.status) : applyStatus(action.status)),
                });
            }
        }

        const groups: PageRibbonGroup[] = [
            {
                id: 'enrollment-actions',
                label: i18n.common.actions,
                commands: actionCommands,
            },
            {
                id: 'enrollment-status-tabs',
                label: i18n.enrollments.ribbonStatus,
                commands: statusCommands,
            },
        ];

        if (distributionCommands.length > 0) {
            groups.push({
                id: 'enrollment-distribution',
                label: i18n.enrollments.ribbonDistribution,
                commands: distributionCommands,
            });
        }

        groups.push({
            id: 'enrollment-superseded',
            label: i18n.enrollments.ribbonSuperseded,
            commands: [supersededCommand],
        });

        groups.push({
            id: 'enrollment-progress',
            label: i18n.enrollments.ribbonProgress,
            commands: [],
            custom: (
                <div
                    className="sis-ribbon__progress-track"
                    role="progressbar"
                    aria-label={i18n.enrollments.overallProgress}
                    aria-valuemin={0}
                    aria-valuemax={100}
                    aria-valuenow={overallPercent}
                    data-contrast={overallPercent >= 45 ? 'light' : 'dark'}
                    dir="rtl"
                    title={`${i18n.enrollments.overallProgress}: ${overallPercent}%`}
                >
                    <span
                        className="sis-ribbon__progress-fill"
                        style={{ width: `${overallPercent}%` }}
                    />
                    <span className="sis-ribbon__progress-value" dir="ltr">
                        {overallPercent}%
                    </span>
                </div>
            ),
        });

        return groups;
    }, [
        applyStatus,
        authorization.canCancel,
        canApplyStatus,
        canDelete,
        canSelect,
        clearSelection,
        editing,
        enrollments.status_progress,
        filters.status,
        hasActiveSelection,
        hasEditTargets,
        hasViewTargets,
        i18n,
        onStatusTabClick,
        openPlacementHistory,
        openViewDialog,
        overallPercent,
        saveEditingRows,
        savingRows,
        selectedRow,
        startEditing,
    ]);

    useRegisterPageRibbon('home', homeRibbonGroups);
    useRegisterPageRibbon('edit', editRibbonGroups);

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
            className={sisListPageClass()}
            dir="rtl"
            lang="ar"
        >
            <div className="sis-admission-page-body">
                <section aria-label={i18n.enrollments.tableCaption} className="flex min-h-0 flex-1 flex-col">
                    {rows.length === 0 ? (
                        <p className="text-sm">{emptyMessage}</p>
                    ) : (
                        <>
                            <div className="sis-admission-periods-table sis-admission-drafts-table">
                                <div className="sis-admission-drafts-table__scroller" ref={scrollerRef}>
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
                                                <th className="sis-admission-drafts-table__name-head" aria-sort={ariaSort(sort, 'name')}>
                                                    <SortButton sort={sort} column="name">
                                                        {i18n.enrollments.quadName}
                                                    </SortButton>
                                                </th>
                                                <th aria-sort={ariaSort(sort, 'class')}><SortButton sort={sort} column="class">{i18n.enrollments.className}</SortButton></th>
                                                <th aria-sort={ariaSort(sort, 'section')}><SortButton sort={sort} column="section">{i18n.enrollments.sectionName}</SortButton></th>
                                                <th aria-sort={ariaSort(sort, 'branch')}><SortButton sort={sort} column="branch">{i18n.enrollments.branchName}</SortButton></th>
                                                <th aria-sort={ariaSort(sort, 'department')}><SortButton sort={sort} column="department">{i18n.enrollments.departmentName}</SortButton></th>
                                                <th aria-sort={ariaSort(sort, 'from')}><SortButton sort={sort} column="from">{i18n.enrollments.effectiveFrom}</SortButton></th>
                                                <th aria-sort={ariaSort(sort, 'to')}><SortButton sort={sort} column="to">{i18n.enrollments.effectiveTo}</SortButton></th>
                                                <th aria-sort={ariaSort(sort, 'status')}><SortButton sort={sort} column="status">{i18n.enrollments.statusTabsTitle}</SortButton></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {displayRows.map((row, index) => {
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
                                        {visiblePages(pagination.page, fitLastPage).map(
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
                                                disabled={pagination.page >= fitLastPage}
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
                <Suspense fallback={null}>
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
                            setViewingEnrollments((current) => {
                                if (current === null) {
                                    return current;
                                }

                                const serverMatch = serverRowsRef.current.find(
                                    (row) =>
                                        row.student_id === updated.student_id
                                        && row.status !== 5,
                                );

                                if (serverMatch !== undefined) {
                                    return current.map((row) =>
                                        row.student_id === updated.student_id
                                            ? { ...row, ...serverMatch }
                                            : row,
                                    );
                                }

                                return current.map((row) =>
                                    row.id === updated.id || row.student_id === updated.student_id
                                        ? { ...row, ...updated }
                                        : row,
                                );
                            });
                        }}
                    />
                </Suspense>
            ) : null}

            {placementHistory !== null && placementHistory.length > 0 ? (
                <EnrollmentPlacementHistoryDialog
                    histories={placementHistory}
                    onClose={closePlacementHistory}
                />
            ) : null}

            {creatingEnrollment ? (
                <Suspense fallback={null}>
                    <EnrollmentCreateDialog
                        key={createStudentId ?? 'new-enrollment'}
                        academicYearId={filters.academic_year_id}
                        filterOptions={filterOptions}
                        initialStudentId={createStudentId}
                        onClose={() => {
                        setCreatingEnrollment(false);
                        setCreateStudentId(null);
                    }}
                    onCreated={(completedId) => {
                        setCreatingEnrollment(false);
                        setCreateStudentId(null);
                        visitList({
                            page: 1,
                            q: completedId > 0 ? String(completedId) : undefined,
                        });
                    }}
                />
                </Suspense>
            ) : null}

            <ConfirmDialog
                open={statusConfirm !== null}
                title={i18n.enrollments.statusConfirmTitle}
                description={
                    statusConfirm === null
                        ? ''
                        : i18n.enrollments.statusConfirmBody.replace('{count}', String(resolveActionIds().length)).replace('{status}', statusTabLabel(statusConfirm, i18n))
                }
                confirmLabel={i18n.enrollments.statusConfirmAction}
                tone="danger"
                onConfirm={() => {
                    if (statusConfirm !== null) {
                        applyStatus(statusConfirm);
                    }
                    setStatusConfirm(null);
                }}
                onOpenChange={(open) => {
                    if (!open) {
                        setStatusConfirm(null);
                    }
                }}
            />
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
