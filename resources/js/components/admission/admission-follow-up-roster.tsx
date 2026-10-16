import { router } from '@inertiajs/react';
import {
    ArrowLeftRight,
    FilterX,
    GraduationCap,
    Hourglass,
    LogOut,
    MessagesSquare,
    Pencil,
    Save,
    SearchCheck,
    Send,
    Users,
    XCircle,
    XOctagon,
    type LucideIcon,
} from 'lucide-react';
import {
    Fragment,
    useCallback,
    useEffect,
    useMemo,
    useRef,
    useState,
    type KeyboardEvent as ReactKeyboardEvent,
    type PointerEvent as ReactPointerEvent,
} from 'react';
import {
    ADMISSION_STATUS_CONVERTED,
    ADMISSION_STATUS_INTERVIEW,
    ADMISSION_STATUS_REJECTED,
    ADMISSION_STATUS_SUBMITTED,
    ADMISSION_STATUS_UNDER_REVIEW,
    ADMISSION_STATUS_WAITLISTED,
    ADMISSION_STATUS_WITHDRAWN,
    type AdmissionAcceptedStudent,
} from '@/components/admission/admission-workspace';
import { usePageError } from '@/components/sis/page-error-context';
import {
    useRegisterPageRibbon,
    type PageRibbonCommand,
    type PageRibbonGroup,
} from '@/components/sis/page-ribbon-context';
import { useRegisterPageTitlebarSearch } from '@/components/sis/page-titlebar-search-context';
import { formatAcademicYearOptionLabel } from '@/components/sis/ops-year-filter';
import { RibbonFilterField } from '@/components/sis/ribbon-filter';
import { ariaSort, SortButton, sortRows, useSortState } from '@/components/sis/table-sort';
import { useFitTablePageSize } from '@/hooks/use-fit-table-page-size';
import { useResizableTableColumns } from '@/hooks/use-resizable-table-columns';
import { useSmoothVerticalScroll } from '@/hooks/use-smooth-vertical-scroll';
import { t } from '@/i18n';

type Props = {
    students: AdmissionAcceptedStudent[];
    defaultAcademicYearId?: number | null;
    canManage?: boolean;
    /** The user's schools — the roster lists the students of each school. */
    schools?: { id: number; name: string }[];
    /** Pre-selected school (current school context). */
    defaultSchoolId?: number | null;
};

/** 1 = academic→vocational transfer, 2 = vocational school intake */
const REQUEST_KIND_VOCATIONAL = 2;
const REQUEST_KIND_TRANSFER = 1;

type RosterEntry = {
    id: number;
    full_name: string;
    rejection_reason: string;
    withdrawal_reason: string;
    status: number;
};

type EditDraft = {
    full_name: string;
    rejection_reason: string;
    withdrawal_reason: string;
};

const CELL_SCROLL_STEP = 48;

/** Overflow cell text — hidden H-scroll via wheel, drag, and arrow keys. */
function AcceptedCellScroll({ text }: { text: string }) {
    const ref = useRef<HTMLDivElement | null>(null);
    const dragRef = useRef<{ pointerId: number; startX: number; startScroll: number } | null>(null);

    const canScroll = useCallback(() => {
        const node = ref.current;
        return node !== null && node.scrollWidth > node.clientWidth + 1;
    }, []);

    useEffect(() => {
        const node = ref.current;
        if (node === null) {
            return;
        }

        const onNativeWheel = (event: WheelEvent) => {
            if (node.scrollWidth <= node.clientWidth + 1) {
                return;
            }

            const delta = event.deltaX !== 0 ? event.deltaX : event.deltaY;
            if (delta === 0) {
                return;
            }

            event.preventDefault();
            event.stopPropagation();
            node.scrollLeft += delta;
        };

        node.addEventListener('wheel', onNativeWheel, { passive: false });

        return () => node.removeEventListener('wheel', onNativeWheel);
    }, []);

    const onKeyDown = useCallback(
        (event: ReactKeyboardEvent<HTMLDivElement>) => {
            if (!canScroll()) {
                return;
            }

            const node = ref.current;
            if (node === null) {
                return;
            }

            const rtl = getComputedStyle(node).direction === 'rtl';
            let next: number | null = null;

            switch (event.key) {
                case 'ArrowLeft':
                    next = node.scrollLeft + (rtl ? CELL_SCROLL_STEP : -CELL_SCROLL_STEP);
                    break;
                case 'ArrowRight':
                    next = node.scrollLeft + (rtl ? -CELL_SCROLL_STEP : CELL_SCROLL_STEP);
                    break;
                case 'Home':
                    next = rtl ? node.scrollWidth : 0;
                    break;
                case 'End':
                    next = rtl ? 0 : node.scrollWidth;
                    break;
                default:
                    return;
            }

            event.preventDefault();
            event.stopPropagation();
            node.scrollLeft = next;
        },
        [canScroll],
    );

    const onPointerDown = useCallback(
        (event: ReactPointerEvent<HTMLDivElement>) => {
            if (event.button !== 0 || !canScroll()) {
                return;
            }

            const node = ref.current;
            if (node === null) {
                return;
            }

            dragRef.current = {
                pointerId: event.pointerId,
                startX: event.clientX,
                startScroll: node.scrollLeft,
            };
            node.setPointerCapture(event.pointerId);
            node.dataset.dragging = 'true';
        },
        [canScroll],
    );

    const onPointerMove = useCallback((event: ReactPointerEvent<HTMLDivElement>) => {
        const drag = dragRef.current;
        const node = ref.current;
        if (drag === null || node === null || event.pointerId !== drag.pointerId) {
            return;
        }

        event.preventDefault();
        node.scrollLeft = drag.startScroll - (event.clientX - drag.startX);
    }, []);

    const endDrag = useCallback((event: ReactPointerEvent<HTMLDivElement>) => {
        const drag = dragRef.current;
        if (drag === null || event.pointerId !== drag.pointerId) {
            return;
        }

        dragRef.current = null;
        const node = ref.current;
        if (node !== null) {
            node.dataset.dragging = 'false';
            try {
                node.releasePointerCapture(event.pointerId);
            } catch {
                /* already released */
            }
        }
    }, []);

    return (
        <div
            ref={ref}
            className="sis-students-table__cell-scroll"
            tabIndex={0}
            role="text"
            title={text}
            onKeyDown={onKeyDown}
            onPointerDown={onPointerDown}
            onPointerMove={onPointerMove}
            onPointerUp={endDrag}
            onPointerCancel={endDrag}
        >
            {text}
        </div>
    );
}

/** Rows per page before the table has been measured (as on the students page). */
const FOLLOW_UP_PER_PAGE = 17;

type NameColumnKey =
    | 'vocational'
    | 'transfer'
    | 'submitted'
    | 'underReview'
    | 'interview'
    | 'waitlisted'
    | 'rejected'
    | 'withdrawn';

/** Ribbon «حالة» tabs: one per column of the roster (like the students page status tabs). */
const COLUMN_ICONS: Record<NameColumnKey, LucideIcon> = {
    vocational: GraduationCap,
    transfer: ArrowLeftRight,
    submitted: Send,
    underReview: SearchCheck,
    interview: MessagesSquare,
    waitlisted: Hourglass,
    rejected: XOctagon,
    withdrawn: LogOut,
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

function matchesQuery(student: AdmissionAcceptedStudent, query: string): boolean {
    if (query === '') {
        return true;
    }
    const needle = query.toLocaleLowerCase('ar');

    return [student.full_name, student.rejection_reason, student.withdrawal_reason].some(
        (value) => (value ?? '').toLocaleLowerCase('ar').includes(needle),
    );
}

function yearOptionsOf(students: AdmissionAcceptedStudent[]): Array<{ value: string; label: string }> {
    const map = new Map<number, string>();
    for (const student of students) {
        if (!map.has(student.academic_year_id)) {
            map.set(student.academic_year_id, student.academic_year_name);
        }
    }

    return [...map.entries()]
        .sort((left, right) => left[1].localeCompare(right[1], 'ar'))
        .map(([id, name]) => ({
            value: String(id),
            label: formatAcademicYearOptionLabel(name, ''),
        }));
}

function channelOf(student: AdmissionAcceptedStudent): number {
    return student.request_kind ?? REQUEST_KIND_VOCATIONAL;
}

function toEntry(student: AdmissionAcceptedStudent): RosterEntry {
    return {
        id: student.id,
        full_name: student.full_name,
        rejection_reason: student.rejection_reason?.trim() ?? '',
        withdrawal_reason: student.withdrawal_reason?.trim() ?? '',
        status: student.status,
    };
}

function cellName(entry: RosterEntry | undefined): string {
    return entry?.full_name ?? '';
}

function EditableCell({
    editing,
    value,
    onChange,
}: {
    editing: boolean;
    value: string;
    onChange: (next: string) => void;
}) {
    if (!editing) {
        return value !== '' ? <AcceptedCellScroll text={value} /> : null;
    }

    return (
        <input
            type="text"
            className="sis-students-table__edit-input"
            value={value}
            dir="rtl"
            onChange={(event) => onChange(event.target.value)}
        />
    );
}

/**
 * «متابعة طلبات التقديم» page body — category columns by status / admission channel. The filters sit in the
 * «الصفحة الرئيسية» ribbon, «تعديل» / «حفظ» / «إلغاء» in the «تحرير» ribbon.
 */
export function AdmissionFollowUpRoster({
    students,
    defaultAcademicYearId = null,
    canManage = false,
    schools = [],
    defaultSchoolId = null,
}: Props) {
    const i18n = t();
    const admission = i18n.admission;
    const { showInertiaErrors } = usePageError();
    const yearOptions = useMemo(() => yearOptionsOf(students), [students]);
    // Chosen once when the page opens (the current school and year); a save keeps the user's filters.
    const [schoolId, setSchoolId] = useState<string>(() =>
        defaultSchoolId != null && schools.some((school) => school.id === defaultSchoolId)
            ? String(defaultSchoolId)
            : '',
    );
    const [yearId, setYearId] = useState<string>(() => {
        const options = yearOptionsOf(students);

        return defaultAcademicYearId != null
            && options.some((option) => option.value === String(defaultAcademicYearId))
            ? String(defaultAcademicYearId)
            : (options[0]?.value ?? '');
    });
    const [periodId, setPeriodId] = useState<string>('');
    const [editing, setEditing] = useState(false);
    const [saving, setSaving] = useState(false);
    const [drafts, setDrafts] = useState<Record<number, EditDraft>>({});
    const [query, setQuery] = useState('');
    const searchDraftRef = useRef('');
    /** The ribbon «حالة» tab: one column of the roster, or all of them. */
    const [shownColumn, setShownColumn] = useState<NameColumnKey | null>(null);
    const scrollerRef = useRef<HTMLDivElement>(null);
    const tableRef = useRef<HTMLTableElement>(null);

    const schoolOptions = useMemo(
        () => [
            { value: '', label: admission.allSchools },
            ...schools.map((school) => ({ value: String(school.id), label: school.name })),
        ],
        [schools, admission.allSchools],
    );

    const periodOptions = useMemo(() => {
        const map = new Map<number, string>();
        for (const student of students) {
            if (yearId !== '' && student.academic_year_id !== Number(yearId)) {
                continue;
            }
            if (!map.has(student.period_id)) {
                map.set(student.period_id, student.period_name);
            }
        }

        return [
            { value: '', label: admission.allPeriods },
            ...[...map.entries()]
                .sort((left, right) => left[1].localeCompare(right[1], 'ar'))
                .map(([id, name]) => ({ value: String(id), label: name })),
        ];
    }, [students, yearId, admission.allPeriods]);

    const filteredByYearPeriod = useMemo(() => {
        return students.filter((student) => {
            if (schoolId !== '' && student.school_id !== Number(schoolId)) {
                return false;
            }
            if (yearId !== '' && student.academic_year_id !== Number(yearId)) {
                return false;
            }
            if (periodId !== '' && student.period_id !== Number(periodId)) {
                return false;
            }

            return matchesQuery(student, query);
        });
    }, [students, schoolId, yearId, periodId, query]);

    const beginEdit = useCallback(() => {
        const next: Record<number, EditDraft> = {};
        for (const student of filteredByYearPeriod) {
            next[student.id] = {
                full_name: student.full_name,
                rejection_reason: student.rejection_reason?.trim() ?? '',
                withdrawal_reason: student.withdrawal_reason?.trim() ?? '',
            };
        }
        setDrafts(next);
        setEditing(true);
    }, [filteredByYearPeriod]);

    const displayEntry = useCallback(
        (entry: RosterEntry | undefined): RosterEntry | undefined => {
            if (entry === undefined) {
                return undefined;
            }
            const draft = drafts[entry.id];
            if (!editing || draft === undefined) {
                return entry;
            }

            return {
                ...entry,
                full_name: draft.full_name,
                rejection_reason: draft.rejection_reason,
                withdrawal_reason: draft.withdrawal_reason,
            };
        },
        [drafts, editing],
    );

    const patchDraft = useCallback((applicationId: number, patch: Partial<EditDraft>) => {
        setDrafts((current) => {
            const prior = current[applicationId] ?? {
                full_name: '',
                rejection_reason: '',
                withdrawal_reason: '',
            };

            return {
                ...current,
                [applicationId]: { ...prior, ...patch },
            };
        });
    }, []);

    const saveEdits = useCallback(() => {
        if (!editing || saving) {
            return;
        }

        const updates: Array<{
            application_id: number;
            full_name: string;
            rejection_reason: string | null;
            withdrawal_reason: string | null;
            update_rejection_reason: boolean;
            update_withdrawal_reason: boolean;
        }> = [];

        for (const student of filteredByYearPeriod) {
            const draft = drafts[student.id];
            if (draft === undefined) {
                continue;
            }

            const originalName = student.full_name;
            const originalRejection = student.rejection_reason?.trim() ?? '';
            const originalWithdrawal = student.withdrawal_reason?.trim() ?? '';
            const nameChanged = draft.full_name.trim() !== originalName.trim();
            const rejectionChanged =
                student.status === ADMISSION_STATUS_REJECTED
                && draft.rejection_reason.trim() !== originalRejection;
            const withdrawalChanged =
                student.status === ADMISSION_STATUS_WITHDRAWN
                && draft.withdrawal_reason.trim() !== originalWithdrawal;

            if (!nameChanged && !rejectionChanged && !withdrawalChanged) {
                continue;
            }

            if (draft.full_name.trim() === '') {
                showInertiaErrors({ full_name: i18n.errors.saveFailed }, i18n.errors.saveFailed);
                return;
            }

            if (rejectionChanged && draft.rejection_reason.trim() === '') {
                showInertiaErrors(
                    { rejection_reason: admission.reasonRequiredHint },
                    admission.reasonRequiredHint,
                );
                return;
            }

            if (withdrawalChanged && draft.withdrawal_reason.trim() === '') {
                showInertiaErrors(
                    { withdrawal_reason: admission.reasonRequiredHint },
                    admission.reasonRequiredHint,
                );
                return;
            }

            updates.push({
                application_id: student.id,
                full_name: draft.full_name.trim(),
                rejection_reason: rejectionChanged ? draft.rejection_reason.trim() : null,
                withdrawal_reason: withdrawalChanged ? draft.withdrawal_reason.trim() : null,
                update_rejection_reason: rejectionChanged,
                update_withdrawal_reason: withdrawalChanged,
            });
        }

        if (updates.length === 0) {
            setEditing(false);
            setDrafts({});
            return;
        }

        setSaving(true);
        router.put(
            '/admission/applications/follow-up',
            { updates },
            {
                // Saved inside the selected school's context (editing needs one school).
                headers: { 'X-School-Id': schoolId },
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => {
                    setEditing(false);
                    setDrafts({});
                },
                onError: (errors) => showInertiaErrors(errors, i18n.errors.saveFailed),
                onFinish: () => setSaving(false),
            },
        );
    }, [
        admission.reasonRequiredHint,
        drafts,
        editing,
        filteredByYearPeriod,
        i18n.errors.saveFailed,
        saving,
        schoolId,
        showInertiaErrors,
    ]);

    const vocationalStudents = useMemo(
        () =>
            filteredByYearPeriod
                .filter(
                    (student) =>
                        student.status === ADMISSION_STATUS_CONVERTED
                        && channelOf(student) === REQUEST_KIND_VOCATIONAL,
                )
                .map(toEntry),
        [filteredByYearPeriod],
    );

    const transferStudents = useMemo(
        () =>
            filteredByYearPeriod
                .filter(
                    (student) =>
                        student.status === ADMISSION_STATUS_CONVERTED
                        && channelOf(student) === REQUEST_KIND_TRANSFER,
                )
                .map(toEntry),
        [filteredByYearPeriod],
    );

    const submittedStudents = useMemo(
        () =>
            filteredByYearPeriod
                .filter((student) => student.status === ADMISSION_STATUS_SUBMITTED)
                .map(toEntry),
        [filteredByYearPeriod],
    );
    const underReviewStudents = useMemo(
        () =>
            filteredByYearPeriod
                .filter((student) => student.status === ADMISSION_STATUS_UNDER_REVIEW)
                .map(toEntry),
        [filteredByYearPeriod],
    );
    const interviewStudents = useMemo(
        () =>
            filteredByYearPeriod
                .filter((student) => student.status === ADMISSION_STATUS_INTERVIEW)
                .map(toEntry),
        [filteredByYearPeriod],
    );
    const waitlistedStudents = useMemo(
        () =>
            filteredByYearPeriod
                .filter((student) => student.status === ADMISSION_STATUS_WAITLISTED)
                .map(toEntry),
        [filteredByYearPeriod],
    );
    const rejectedStudents = useMemo(
        () =>
            filteredByYearPeriod
                .filter((student) => student.status === ADMISSION_STATUS_REJECTED)
                .map(toEntry),
        [filteredByYearPeriod],
    );
    const withdrawnStudents = useMemo(
        () =>
            filteredByYearPeriod
                .filter((student) => student.status === ADMISSION_STATUS_WITHDRAWN)
                .map(toEntry),
        [filteredByYearPeriod],
    );

    // Each column is its own list: a header click sorts that list only (the reason columns sort their list by reason).
    const columnSort = useSortState();
    const tableRows = useMemo(() => {
        const ordered = (list: RosterEntry[], nameKey: string, reasonKey?: 'rejection' | 'withdrawal'): RosterEntry[] =>
            columnSort.key === nameKey
                ? sortRows(list, (entry) => entry.full_name, columnSort.direction)
                : reasonKey !== undefined && columnSort.key === reasonKey
                  ? sortRows(
                        list,
                        (entry) => (reasonKey === 'rejection' ? entry.rejection_reason : entry.withdrawal_reason),
                        columnSort.direction,
                    )
                  : list;
        const vocationalList = ordered(vocationalStudents, 'vocational');
        const transferList = ordered(transferStudents, 'transfer');
        const submittedList = ordered(submittedStudents, 'submitted');
        const underReviewList = ordered(underReviewStudents, 'underReview');
        const interviewList = ordered(interviewStudents, 'interview');
        const waitlistedList = ordered(waitlistedStudents, 'waitlisted');
        const rejectedList = ordered(rejectedStudents, 'rejected', 'rejection');
        const withdrawnList = ordered(withdrawnStudents, 'withdrawn', 'withdrawal');
        const lengths: Record<NameColumnKey, number> = {
            vocational: vocationalStudents.length,
            transfer: transferStudents.length,
            submitted: submittedStudents.length,
            underReview: underReviewStudents.length,
            interview: interviewStudents.length,
            waitlisted: waitlistedStudents.length,
            rejected: rejectedStudents.length,
            withdrawn: withdrawnStudents.length,
        };
        const rowCount = Math.max(
            0,
            ...(shownColumn === null
                ? Object.values(lengths)
                : [lengths[shownColumn]]),
        );

        return Array.from({ length: rowCount }, (_, index) => {
            const vocational = displayEntry(vocationalList[index]);
            const transfer = displayEntry(transferList[index]);
            const submitted = displayEntry(submittedList[index]);
            const underReview = displayEntry(underReviewList[index]);
            const interview = displayEntry(interviewList[index]);
            const waitlisted = displayEntry(waitlistedList[index]);
            const rejected = displayEntry(rejectedList[index]);
            const withdrawn = displayEntry(withdrawnList[index]);

            return {
                key: [
                    vocational?.id ?? 'v0',
                    transfer?.id ?? 't0',
                    submitted?.id ?? 's0',
                    underReview?.id ?? 'u0',
                    interview?.id ?? 'i0',
                    waitlisted?.id ?? 'l0',
                    rejected?.id ?? 'r0',
                    withdrawn?.id ?? 'w0',
                    index,
                ].join('-'),
                vocational,
                transfer,
                submitted,
                underReview,
                interview,
                waitlisted,
                rejected,
                withdrawn,
            };
        });
    }, [
        columnSort.key,
        columnSort.direction,
        displayEntry,
        shownColumn,
        vocationalStudents,
        transferStudents,
        submittedStudents,
        underReviewStudents,
        interviewStudents,
        waitlistedStudents,
        rejectedStudents,
        withdrawnStudents,
    ]);

    const columnCounts = {
        vocational: vocationalStudents.length,
        transfer: transferStudents.length,
        submitted: submittedStudents.length,
        underReview: underReviewStudents.length,
        interview: interviewStudents.length,
        waitlisted: waitlistedStudents.length,
        rejected: rejectedStudents.length,
        withdrawn: withdrawnStudents.length,
    } as const;

    // Memoised: the «حالة» ribbon tabs are built from it, and the ribbon re-registers when it changes.
    const nameColumns = useMemo(
        (): Array<{
            key: NameColumnKey;
            header: string;
            count: number;
            after?: 'rejection' | 'withdrawal';
        }> => [
            { key: 'vocational', header: admission.acceptedStatsVocational, count: vocationalStudents.length },
            { key: 'transfer', header: admission.acceptedStatsTransfer, count: transferStudents.length },
            { key: 'submitted', header: admission.acceptedStatsSubmitted, count: submittedStudents.length },
            { key: 'underReview', header: admission.acceptedStatsUnderReview, count: underReviewStudents.length },
            { key: 'interview', header: admission.acceptedStatsInterview, count: interviewStudents.length },
            { key: 'waitlisted', header: admission.acceptedStatsWaitlisted, count: waitlistedStudents.length },
            {
                key: 'rejected',
                header: admission.acceptedStatsRejected,
                count: rejectedStudents.length,
                after: 'rejection',
            },
            {
                key: 'withdrawn',
                header: admission.acceptedStatsWithdrawn,
                count: withdrawnStudents.length,
                after: 'withdrawal',
            },
        ],
        [
            admission,
            vocationalStudents.length,
            transferStudents.length,
            submittedStudents.length,
            underReviewStudents.length,
            interviewStudents.length,
            waitlistedStudents.length,
            rejectedStudents.length,
            withdrawnStudents.length,
        ],
    );

    const shownColumns =
        shownColumn === null ? nameColumns : nameColumns.filter((column) => column.key === shownColumn);
    const totalApplications = Object.values(columnCounts).reduce((sum, count) => sum + count, 0);

    // Paged on screen like the students list: as many rows as the table fits, page numbers under it.
    const fitPageSize = useFitTablePageSize(scrollerRef, {
        fallbackRows: FOLLOW_UP_PER_PAGE,
        enabled: true,
    });
    const pageKey = `${schoolId}|${yearId}|${periodId}|${query}|${shownColumn ?? ''}`;
    const [pageState, setPageState] = useState({ key: pageKey, page: 1 });
    const lastPage = Math.max(1, Math.ceil(tableRows.length / Math.max(1, fitPageSize)));
    const currentPage = pageState.key === pageKey ? Math.min(pageState.page, lastPage) : 1;
    const rowOffset = (currentPage - 1) * fitPageSize;
    const pageRows = tableRows.slice(rowOffset, rowOffset + fitPageSize);
    const goPage = useCallback(
        (next: number) => {
            if (next >= 1 && next <= lastPage) {
                setPageState({ key: pageKey, page: next });
            }
        },
        [lastPage, pageKey],
    );

    useResizableTableColumns(tableRef, {
        storageKey: 'admission.follow-up',
        columnSignature: `${shownColumn ?? 'all'}:${canManage ? 'manage' : 'read'}`,
        enabled: pageRows.length > 0,
    });
    useSmoothVerticalScroll(scrollerRef, pageRows.length > 0);

    const resetEdits = useCallback(() => {
        setEditing(false);
        setDrafts({});
    }, []);

    const clearFilters = useCallback(() => {
        searchDraftRef.current = '';
        setQuery('');
        setSchoolId('');
        setPeriodId('');
        setShownColumn(null);
        resetEdits();
    }, [resetEdits]);

    const homeRibbonGroups = useMemo(
        (): PageRibbonGroup[] => [
            {
                id: 'follow-up-filters',
                label: admission.sheetAcceptedFilters,
                commands: [],
                custom: (
                    <div className="sis-ribbon__filters" aria-label={admission.sheetAcceptedFilters}>
                        <div className="sis-ribbon__filters-stack sis-ribbon__filters-stack--students">
                            <RibbonFilterField
                                spec={{
                                    key: 'school',
                                    type: 'single',
                                    label: admission.filterBySchool,
                                    disabled: saving,
                                    value: schoolId,
                                    options: schoolOptions,
                                    onChange: (next) => {
                                        setSchoolId(next);
                                        setPeriodId('');
                                        resetEdits();
                                    },
                                }}
                            />
                            <RibbonFilterField
                                spec={{
                                    key: 'academic-year',
                                    type: 'single',
                                    label: admission.academicYear,
                                    disabled: saving,
                                    value: yearId,
                                    options: yearOptions,
                                    onChange: (next) => {
                                        setYearId(next);
                                        setPeriodId('');
                                        resetEdits();
                                    },
                                }}
                            />
                            <RibbonFilterField
                                spec={{
                                    key: 'period',
                                    type: 'single',
                                    label: admission.filterByPeriod,
                                    disabled: saving,
                                    value: periodId,
                                    options: periodOptions,
                                    onChange: (next) => {
                                        setPeriodId(next);
                                        resetEdits();
                                    },
                                }}
                            />
                        </div>
                        <button
                            type="button"
                            className="sis-ribbon__item sis-ribbon__item--filter-clear"
                            disabled={saving}
                            aria-label={i18n.students.clearFiltersAria}
                            title={i18n.students.clearFiltersAria}
                            onClick={clearFilters}
                        >
                            <FilterX className="sis-ribbon__icon" aria-hidden />
                            <span className="sis-ribbon__label">{i18n.students.clearFilters}</span>
                        </button>
                    </div>
                ),
            },
        ],
        [
            admission.academicYear,
            admission.filterByPeriod,
            admission.filterBySchool,
            admission.sheetAcceptedFilters,
            clearFilters,
            i18n.students.clearFilters,
            i18n.students.clearFiltersAria,
            periodId,
            periodOptions,
            resetEdits,
            saving,
            schoolId,
            schoolOptions,
            yearId,
            yearOptions,
        ],
    );

    const editRibbonGroups = useMemo((): PageRibbonGroup[] => {
        const statusCommands: PageRibbonCommand[] = [
            {
                id: 'status-tab-all',
                label: i18n.students.allStatuses,
                title: `${i18n.students.allStatuses} (${totalApplications})`,
                icon: Users,
                count: totalApplications,
                pressed: shownColumn === null,
                onSelect: () => setShownColumn(null),
            },
            ...nameColumns.map((column) => ({
                id: `status-tab-${column.key}`,
                label: column.header,
                title: `${column.header} (${column.count})`,
                icon: COLUMN_ICONS[column.key],
                count: column.count,
                pressed: shownColumn === column.key,
                onSelect: () =>
                    setShownColumn((current) => (current === column.key ? null : column.key)),
            })),
        ];
        const groups: PageRibbonGroup[] = [];

        if (canManage) {
            groups.push({
                id: 'follow-up-actions',
                label: i18n.common.actions,
                commands: [
                    {
                        id: 'edit-follow-up',
                        label: i18n.common.edit,
                        icon: Pencil,
                        tone: 'edit',
                        // Editing saves inside one school's context.
                        disabled: editing || tableRows.length === 0 || saving || schoolId === '',
                        title: schoolId === '' ? admission.filterBySchool : undefined,
                        onSelect: beginEdit,
                    },
                    {
                        id: 'save-follow-up',
                        label: saving ? i18n.common.saving : i18n.common.save,
                        icon: Save,
                        tone: 'save',
                        disabled: !editing || saving,
                        onSelect: saveEdits,
                    },
                    {
                        id: 'cancel-follow-up',
                        label: i18n.common.cancel,
                        icon: XCircle,
                        disabled: !editing || saving,
                        onSelect: resetEdits,
                    },
                ],
            });
        }
        groups.push({
            id: 'follow-up-status-tabs',
            label: i18n.students.ribbonStatus,
            commands: statusCommands,
        });

        return groups;
    }, [
        admission.filterBySchool,
        beginEdit,
        canManage,
        editing,
        i18n.common.actions,
        i18n.common.cancel,
        i18n.common.edit,
        i18n.common.save,
        i18n.common.saving,
        i18n.students.allStatuses,
        i18n.students.ribbonStatus,
        nameColumns,
        resetEdits,
        saveEdits,
        saving,
        schoolId,
        shownColumn,
        tableRows.length,
        totalApplications,
    ]);

    useRegisterPageRibbon('home', homeRibbonGroups);
    useRegisterPageRibbon('edit', editRibbonGroups);

    // Title-bar search: names and reasons of the roster (filtered on screen, the roster is already loaded).
    const titlebarSearch = useMemo(
        () => ({
            committedQuery: query,
            label: admission.acceptedStudentsDialogTitle,
            placeholder: i18n.chrome.search,
            onDraftChange: (draft: string) => {
                searchDraftRef.current = draft;
            },
            onCommit: (next: string) => {
                searchDraftRef.current = next;
                setQuery(next.trim());
                resetEdits();
            },
        }),
        [admission.acceptedStudentsDialogTitle, i18n.chrome.search, query, resetEdits],
    );

    useRegisterPageTitlebarSearch(titlebarSearch);

    const renderCell = (entry: RosterEntry | undefined, value: string, patch: keyof EditDraft) =>
        entry ? (
            <EditableCell
                editing={editing}
                value={value}
                onChange={(next) => patchDraft(entry.id, { [patch]: next })}
            />
        ) : null;

    return (
        <div className="sis-admission-page-body">
            <section aria-label={admission.acceptedStudentsDialogTitle} className="flex min-h-0 flex-1 flex-col">
                {tableRows.length === 0 ? (
                    <p className="text-sm">{admission.emptyAcceptedStudents}</p>
                ) : (
                    <>
                        <div className="sis-admission-periods-table sis-admission-drafts-table">
                            <div className="sis-admission-drafts-table__scroller" ref={scrollerRef}>
                                <table ref={tableRef}>
                                    <thead>
                                        <tr>
                                            <th className="sis-admission-drafts-table__num">{i18n.students.seq}</th>
                                            {shownColumns.map((column) => (
                                                <Fragment key={column.key}>
                                                    <th aria-sort={ariaSort(columnSort, column.key)}>
                                                        <SortButton sort={columnSort} column={column.key}>
                                                            {column.header}
                                                        </SortButton>
                                                    </th>
                                                    {column.after === 'rejection' ? (
                                                        <th aria-sort={ariaSort(columnSort, 'rejection')}>
                                                            <SortButton sort={columnSort} column="rejection">
                                                                {admission.acceptedStatsRejectionReason}
                                                            </SortButton>
                                                        </th>
                                                    ) : null}
                                                    {column.after === 'withdrawal' ? (
                                                        <th aria-sort={ariaSort(columnSort, 'withdrawal')}>
                                                            <SortButton sort={columnSort} column="withdrawal">
                                                                {admission.acceptedStatsWithdrawalReason}
                                                            </SortButton>
                                                        </th>
                                                    ) : null}
                                                </Fragment>
                                            ))}
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {pageRows.map((row, index) => (
                                            <tr key={row.key}>
                                                <td className="sis-admission-drafts-table__num" dir="ltr">
                                                    {rowOffset + index + 1}
                                                </td>
                                                {shownColumns.map((column) => {
                                                    const entry = row[column.key];

                                                    return (
                                                        <Fragment key={column.key}>
                                                            <td className="sis-admission-drafts-table__text" title={cellName(entry)}>
                                                                {renderCell(entry, cellName(entry), 'full_name')}
                                                            </td>
                                                            {column.after === 'rejection' ? (
                                                                <td
                                                                    className="sis-admission-drafts-table__text sis-admission-drafts-table__text--wide"
                                                                    title={row.rejected?.rejection_reason ?? ''}
                                                                >
                                                                    {renderCell(
                                                                        row.rejected,
                                                                        row.rejected?.rejection_reason ?? '',
                                                                        'rejection_reason',
                                                                    )}
                                                                </td>
                                                            ) : null}
                                                            {column.after === 'withdrawal' ? (
                                                                <td
                                                                    className="sis-admission-drafts-table__text sis-admission-drafts-table__text--wide"
                                                                    title={row.withdrawn?.withdrawal_reason ?? ''}
                                                                >
                                                                    {renderCell(
                                                                        row.withdrawn,
                                                                        row.withdrawn?.withdrawal_reason ?? '',
                                                                        'withdrawal_reason',
                                                                    )}
                                                                </td>
                                                            ) : null}
                                                        </Fragment>
                                                    );
                                                })}
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <nav className="sis-admission-drafts-pagination" aria-label={i18n.common.page}>
                            <ul className="sis-admission-pagination" dir="ltr">
                                <li className="sis-admission-pagination__item">
                                    <button
                                        type="button"
                                        className="sis-admission-pagination__link"
                                        aria-label={i18n.common.previous}
                                        disabled={currentPage <= 1}
                                        onClick={() => goPage(currentPage - 1)}
                                    >
                                        <span aria-hidden="true">&laquo;</span>
                                    </button>
                                </li>
                                {visiblePages(currentPage, lastPage).map((pageNum) => (
                                    <li key={pageNum} className="sis-admission-pagination__item">
                                        <button
                                            type="button"
                                            className={
                                                pageNum === currentPage
                                                    ? 'sis-admission-pagination__link sis-admission-pagination__link--active'
                                                    : 'sis-admission-pagination__link'
                                            }
                                            aria-label={`${i18n.common.page} ${pageNum}`}
                                            aria-current={pageNum === currentPage ? 'page' : undefined}
                                            onClick={() => goPage(pageNum)}
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
                                        disabled={currentPage >= lastPage}
                                        onClick={() => goPage(currentPage + 1)}
                                    >
                                        <span aria-hidden="true">&raquo;</span>
                                    </button>
                                </li>
                            </ul>
                        </nav>
                    </>
                )}
            </section>
        </div>
    );
}
