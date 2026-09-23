import { router, usePage } from '@inertiajs/react';
import {
    ArrowRightLeft,
    CheckCircle2,
    CircleSlash,
    FilterX,
    History,
    PauseCircle,
    UserMinus,
    Users,
    type LucideIcon,
} from 'lucide-react';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
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
import { usePageError } from '@/components/sis/page-error-context';
import { OpsYearFilter } from '@/components/sis/ops-year-filter';
import { SisListSelect } from '@/components/sis/sis-list-select';
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
import { useResizableTableColumns } from '@/hooks/use-resizable-table-columns';
import { useSmoothVerticalScroll } from '@/hooks/use-smooth-vertical-scroll';
import {
    clearEnrollmentHandoff,
    readEnrollmentHandoff,
    type EnrollmentHandoffStudent,
} from '@/lib/enrollment-handoff';
import { t } from '@/i18n';

const ENROLLMENTS_PER_PAGE = 17;

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
    { status: 0, icon: PauseCircle, tone: 'light' },
    { status: 2, icon: CircleSlash, tone: 'light' },
    { status: 4, icon: UserMinus, tone: 'light' },
    { status: 3, icon: ArrowRightLeft, tone: 'dark' },
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
    { status: 0, icon: PauseCircle, tone: 'light' },
    { status: 2, icon: CircleSlash, tone: 'light' },
    { status: 4, icon: UserMinus, tone: 'light' },
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
        4: i18n.status.dismissed,
        5: i18n.status.superseded,
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
    const { showError, showInertiaErrors } = usePageError();
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
    const [handoffStudents, setHandoffStudents] = useState<EnrollmentHandoffStudent[]>([]);
    const [handoffDraft, setHandoffDraft] = useState<HandoffPlacementDraft>(emptyHandoffDraft);
    const [handoffAcademicYearId, setHandoffAcademicYearId] = useState<number | null>(null);
    const [enrollingHandoff, setEnrollingHandoff] = useState(false);
    const [deleteTarget, setDeleteTarget] = useState<EnrollmentListItem | null>(null);
    const [deleting, setDeleting] = useState(false);
    const selectAllRef = useRef<HTMLInputElement>(null);
    const tableRef = useRef<HTMLTableElement>(null);
    const scrollerRef = useRef<HTMLDivElement>(null);
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

        // Handoff from students uses the structure bar — never open create dialog.
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

    useResizableTableColumns(tableRef, {
        storageKey: 'enrollments.list',
        columnSignature: canSelect ? 'select-v3' : 'readonly-v3',
        enabled: rows.length > 0,
    });
    useSmoothVerticalScroll(scrollerRef, rows.length > 0);

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
    const isHandoffMode = handoffStudents.length > 0 && authorization.canCreate;
    const setActiveRibbonTab = useSetActivePageRibbonTab();
    const canApplyStatus =
        canSelect && actionIds.length > 0 && !applyingStatus && !applyingPlacement && !editing && !isHandoffMode;
    const isEditMode =
        !isHandoffMode && authorization.canUpdate && actionIds.length > 0 && !editing;
    const isStructureEditMode = isEditMode || isHandoffMode;

    useEffect(() => {
        if (isHandoffMode) {
            setActiveRibbonTab('edit');
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
        && selectedRow.status !== 2;
    const hasActiveSelection = actionIds.length > 0 || editing;

    const filterSections = useMemo(() => {
        const activeClass = isHandoffMode ? handoffDraft.class_id : classValue;
        if (isEditMode || activeClass === '') {
            return filterOptions.sections;
        }

        return filterOptions.sections.filter((section) => String(section.class_id) === activeClass);
    }, [classValue, filterOptions.sections, handoffDraft.class_id, isEditMode, isHandoffMode]);

    const filterDepartments = useMemo(() => {
        const activeBranch = isHandoffMode ? handoffDraft.branch_id : branchValue;
        if (isEditMode || activeBranch === '') {
            return filterOptions.departments;
        }

        return filterOptions.departments.filter(
            (department) =>
                department.branch_id === null || String(department.branch_id) === activeBranch,
        );
    }, [branchValue, filterOptions.departments, handoffDraft.branch_id, isEditMode, isHandoffMode]);

    const filterSpecializations = useMemo(() => {
        const activeDepartment = isHandoffMode ? handoffDraft.department_id : departmentIdValue;
        if (isEditMode || activeDepartment === '') {
            return filterOptions.specializations;
        }

        return filterOptions.specializations.filter(
            (item) =>
                item.department_id === null || String(item.department_id) === activeDepartment,
        );
    }, [
        departmentIdValue,
        filterOptions.specializations,
        handoffDraft.department_id,
        isEditMode,
        isHandoffMode,
    ]);

    const handoffClassLabel =
        filterOptions.classes.find((item) => String(item.id) === handoffDraft.class_id)?.name
        ?? i18n.enrollments.allClasses;
    const handoffSectionLabel =
        filterOptions.sections.find((item) => String(item.id) === handoffDraft.section_id)?.name
        ?? i18n.enrollments.allSections;
    const handoffBranchLabel =
        filterOptions.branches.find((item) => String(item.id) === handoffDraft.branch_id)?.name
        ?? i18n.enrollments.allBranches;
    const handoffDepartmentLabel =
        filterOptions.departments.find((item) => String(item.id) === handoffDraft.department_id)
            ?.name ?? i18n.enrollments.allDepartments;
    const handoffSpecializationLabel =
        filterOptions.specializations.find(
            (item) => String(item.id) === handoffDraft.specialization_id,
        )?.name ?? i18n.enrollments.allSpecializations;

    const structureClassLabel = isHandoffMode ? handoffClassLabel : selectedClassLabel;
    const structureSectionLabel = isHandoffMode ? handoffSectionLabel : selectedSectionLabel;
    const structureBranchLabel = isHandoffMode ? handoffBranchLabel : selectedBranchLabel;
    const structureDepartmentLabel = isHandoffMode
        ? handoffDepartmentLabel
        : selectedDepartmentLabel;
    const structureSpecializationLabel = isHandoffMode
        ? handoffSpecializationLabel
        : selectedSpecializationLabel;

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
                    onError: (errors) => showInertiaErrors(errors, i18n.errors.statusFailed),
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
                showError(i18n.enrollments.handoffNeedsYear);

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
                    class_id: Number(draft.class_id),
                    section_id: Number(draft.section_id),
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
            class_id: null,
            section_id: null,
            branch_id: null,
            department_id: null,
            specialization_id: null,
            page: 1,
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
                onError: (errors) => showInertiaErrors(errors, i18n.errors.deleteFailed),
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

        const supersededLabel = statusTabLabel(ENROLLMENT_SUPERSEDED_TAB.status, i18n);
        const supersededCount = countForStatus(
            ENROLLMENT_SUPERSEDED_TAB.status,
            enrollments.status_progress,
        );
        const supersededCommand: PageRibbonCommand = {
            id: `status-tab-${ENROLLMENT_SUPERSEDED_TAB.status}`,
            label: supersededLabel,
            title: `${supersededLabel} (${supersededCount})`,
            icon: ENROLLMENT_SUPERSEDED_TAB.icon,
            count: supersededCount,
            pressed: filters.status === ENROLLMENT_SUPERSEDED_TAB.status,
            onSelect: () =>
                onStatusTabClick(
                    ENROLLMENT_SUPERSEDED_TAB.status,
                    filters.status === ENROLLMENT_SUPERSEDED_TAB.status,
                ),
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
                    onSelect: () => applyStatus(action.status),
                });
            }
        }

        const groups: PageRibbonGroup[] = [
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
                                                page: 1,
                                            });
                                        }}
                                        disabled={filtersBusy || isHandoffMode}
                                        triggerClassName="sis-ops-hub__link px-2 py-1 min-h-0 min-w-0 sis-admission-year-control"
                                        dir="rtl"
                                        ariaLabel={i18n.enrollments.filterByGender}
                                    />
                                </span>
                            </div>

                            <div className="sis-ribbon__filter-field" dir="rtl">
                                <span className="sis-admission-select-fit">
                                    <span className="sis-admission-select-fit__mirror" aria-hidden="true">
                                        {structureBranchLabel}
                                    </span>
                                    <SisListSelect
                                        key={isStructureEditMode ? 'branch-edit' : 'branch-filter'}
                                        value={
                                            isHandoffMode
                                                ? handoffDraft.branch_id
                                                : isEditMode
                                                  ? ''
                                                  : branchValue
                                        }
                                        options={[
                                            { value: '', label: i18n.enrollments.allBranches },
                                            ...filterOptions.branches.map((item) => ({
                                                value: String(item.id),
                                                label: item.name,
                                            })),
                                        ]}
                                        onChange={(next) => {
                                            if (isHandoffMode) {
                                                if (filtersBusy) {
                                                    return;
                                                }

                                                patchHandoffDraft({ branch_id: next });

                                                return;
                                            }

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

                            <div className="sis-ribbon__filter-field" dir="rtl">
                                <span className="sis-admission-select-fit">
                                    <span className="sis-admission-select-fit__mirror" aria-hidden="true">
                                        {structureDepartmentLabel}
                                    </span>
                                    <SisListSelect
                                        key={isStructureEditMode ? 'department-edit' : 'department-filter'}
                                        value={
                                            isHandoffMode
                                                ? handoffDraft.department_id
                                                : isEditMode
                                                  ? ''
                                                  : departmentIdValue
                                        }
                                        options={[
                                            { value: '', label: i18n.enrollments.allDepartments },
                                            ...filterDepartments.map((item) => ({
                                                value: String(item.id),
                                                label: item.name,
                                            })),
                                        ]}
                                        onChange={(next) => {
                                            if (isHandoffMode) {
                                                if (filtersBusy) {
                                                    return;
                                                }

                                                patchHandoffDraft({ department_id: next });

                                                return;
                                            }

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

                            <div className="sis-ribbon__filter-field" dir="rtl">
                                <span className="sis-admission-select-fit">
                                    <span className="sis-admission-select-fit__mirror" aria-hidden="true">
                                        {structureSpecializationLabel}
                                    </span>
                                    <SisListSelect
                                        key={
                                            isStructureEditMode
                                                ? 'specialization-edit'
                                                : 'specialization-filter'
                                        }
                                        value={
                                            isHandoffMode
                                                ? handoffDraft.specialization_id
                                                : isEditMode
                                                  ? ''
                                                  : specializationValue
                                        }
                                        options={[
                                            { value: '', label: i18n.enrollments.allSpecializations },
                                            ...filterSpecializations.map((item) => ({
                                                value: String(item.id),
                                                label: item.name,
                                            })),
                                        ]}
                                        onChange={(next) => {
                                            if (isHandoffMode) {
                                                if (filtersBusy) {
                                                    return;
                                                }

                                                patchHandoffDraft({ specialization_id: next });

                                                return;
                                            }

                                            if (isEditMode) {
                                                if (next === '' || filtersBusy) {
                                                    return;
                                                }

                                                applyPlacementPatch({
                                                    specialization_id: Number(next),
                                                });

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

                            <div className="sis-ribbon__filter-field" dir="rtl">
                                <span className="sis-admission-select-fit">
                                    <span className="sis-admission-select-fit__mirror" aria-hidden="true">
                                        {structureClassLabel}
                                    </span>
                                    <SisListSelect
                                        key={isStructureEditMode ? 'class-edit' : 'class-filter'}
                                        value={
                                            isHandoffMode
                                                ? handoffDraft.class_id
                                                : isEditMode
                                                  ? ''
                                                  : classValue
                                        }
                                        options={[
                                            { value: '', label: i18n.enrollments.allClasses },
                                            ...filterOptions.classes.map((item) => ({
                                                value: String(item.id),
                                                label: item.name,
                                            })),
                                        ]}
                                        onChange={(next) => {
                                            if (isHandoffMode) {
                                                if (filtersBusy) {
                                                    return;
                                                }

                                                patchHandoffDraft({ class_id: next });

                                                return;
                                            }

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

                            <div className="sis-ribbon__filter-field" dir="rtl">
                                <span className="sis-admission-select-fit">
                                    <span className="sis-admission-select-fit__mirror" aria-hidden="true">
                                        {structureSectionLabel}
                                    </span>
                                    <SisListSelect
                                        key={isStructureEditMode ? 'section-edit' : 'section-filter'}
                                        value={
                                            isHandoffMode
                                                ? handoffDraft.section_id
                                                : isEditMode
                                                  ? ''
                                                  : sectionValue
                                        }
                                        options={[
                                            { value: '', label: i18n.enrollments.allSections },
                                            ...filterSections.map((item) => ({
                                                value: String(item.id),
                                                label: item.name,
                                            })),
                                        ]}
                                        onChange={(next) => {
                                            if (isHandoffMode) {
                                                if (filtersBusy || next === '') {
                                                    return;
                                                }

                                                if (handoffDraft.class_id === '') {
                                                    showError(i18n.enrollments.handoffNeedsPlacement);

                                                    return;
                                                }

                                                patchHandoffDraft({ section_id: next });

                                                return;
                                            }

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
        applyPlacementPatch,
        applyStatus,
        authorization.canCancel,
        branchValue,
        canApplyStatus,
        canSelect,
        classValue,
        clearStructureFilters,
        departmentIdValue,
        enrollments.status_progress,
        filterDepartments,
        filterOptions.branches,
        filterOptions.classes,
        filterSections,
        filterSpecializations,
        filters.academic_year_id,
        filters.branch_id,
        filters.class_id,
        filters.department_id,
        filters.gender,
        filters.section_id,
        filters.specialization_id,
        filters.status,
        filtersBusy,
        genderFilterLabel,
        genderValue,
        handoffDraft.branch_id,
        handoffDraft.class_id,
        handoffDraft.department_id,
        handoffDraft.section_id,
        handoffDraft.specialization_id,
        i18n,
        isEditMode,
        isHandoffMode,
        isStructureEditMode,
        onStatusTabClick,
        overallPercent,
        patchHandoffDraft,
        sectionValue,
        showError,
        specializationValue,
        structureBranchLabel,
        structureClassLabel,
        structureDepartmentLabel,
        structureSectionLabel,
        structureSpecializationLabel,
        visitList,
    ]);

    useRegisterPageRibbon('edit', ribbonGroups);

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
                                                <th>{i18n.enrollments.stageName}</th>
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
