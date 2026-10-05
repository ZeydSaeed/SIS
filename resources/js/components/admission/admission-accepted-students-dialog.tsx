import { router } from '@inertiajs/react';
import {
    Fragment,
    useCallback,
    useEffect,
    useMemo,
    useRef,
    useState,
    type KeyboardEvent as ReactKeyboardEvent,
    type PointerEvent as ReactPointerEvent,
    type ReactNode,
} from 'react';
import AppLogo from '@/components/app-logo';
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
import { formatAcademicYearOptionLabel } from '@/components/sis/ops-year-filter';
import { SisListSelect } from '@/components/sis/sis-list-select';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogTitle,
} from '@/components/ui/dialog';
import { WindowControls } from '@/components/window-controls';
import { useSmoothDialogDrag } from '@/hooks/use-smooth-dialog-drag';
import { useSheetMaximize } from '@/hooks/use-sheet-maximize';
import { t } from '@/i18n';

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    students: AdmissionAcceptedStudent[];
    loading?: boolean;
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
            className="sis-admission-accepted-sheet__cell-scroll"
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

function SheetSection({
    title,
    children,
}: {
    title: string;
    children: ReactNode;
}) {
    return (
        <section className="sis-admission-sheet__section">
            <h3 className="sis-admission-sheet__banner sis-admission-sheet__banner--accent">{title}</h3>
            <div className="sis-admission-sheet__body">{children}</div>
        </section>
    );
}

function SheetField({
    label,
    children,
}: {
    label: string;
    children: ReactNode;
}) {
    return (
        <label className="sis-admission-sheet__field">
            <span className="sis-admission-sheet__label">{label}</span>
            {children}
        </label>
    );
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
            className="sis-admission-accepted-sheet__edit-input"
            value={value}
            dir="rtl"
            onChange={(event) => onChange(event.target.value)}
        />
    );
}

/** Application follow-up roster — category columns by status / admission channel. */
export function AdmissionAcceptedStudentsDialog({
    open,
    onOpenChange,
    students,
    loading = false,
    defaultAcademicYearId = null,
    canManage = false,
    schools = [],
    defaultSchoolId = null,
}: Props) {
    const i18n = t();
    const admission = i18n.admission;
    const { showInertiaErrors } = usePageError();
    const [schoolId, setSchoolId] = useState<string>('');
    const [yearId, setYearId] = useState<string>('');
    const [periodId, setPeriodId] = useState<string>('');
    const [editing, setEditing] = useState(false);
    const [saving, setSaving] = useState(false);
    const [drafts, setDrafts] = useState<Record<number, EditDraft>>({});
    const { contentRef, heroDragProps, bringToFront, resizeHandles } = useSmoothDialogDrag(open, {
        resizable: true,
        minSize: { width: 720, height: 360 },
    });
    const { maximized, toggleMaximize, maximizeClassName } = useSheetMaximize(contentRef);

    const yearOptions = useMemo(() => {
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
    }, [students]);

    useEffect(() => {
        if (!open) {
            setEditing(false);
            setDrafts({});
            setSaving(false);
            return;
        }

        const preferred =
            defaultAcademicYearId != null
            && yearOptions.some((option) => option.value === String(defaultAcademicYearId))
                ? String(defaultAcademicYearId)
                : (yearOptions[0]?.value ?? '');
        setYearId(preferred);
        setPeriodId('');
        setSchoolId(
            defaultSchoolId != null && schools.some((school) => school.id === defaultSchoolId)
                ? String(defaultSchoolId)
                : '',
        );
        setEditing(false);
        setDrafts({});
    }, [open, defaultAcademicYearId, yearOptions, defaultSchoolId, schools]);

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

            return true;
        });
    }, [students, schoolId, yearId, periodId]);

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

    const tableRows = useMemo(() => {
        const rowCount = Math.max(
            vocationalStudents.length,
            transferStudents.length,
            submittedStudents.length,
            underReviewStudents.length,
            interviewStudents.length,
            waitlistedStudents.length,
            rejectedStudents.length,
            withdrawnStudents.length,
            0,
        );

        return Array.from({ length: rowCount }, (_, index) => {
            const vocational = displayEntry(vocationalStudents[index]);
            const transfer = displayEntry(transferStudents[index]);
            const submitted = displayEntry(submittedStudents[index]);
            const underReview = displayEntry(underReviewStudents[index]);
            const interview = displayEntry(interviewStudents[index]);
            const waitlisted = displayEntry(waitlistedStudents[index]);
            const rejected = displayEntry(rejectedStudents[index]);
            const withdrawn = displayEntry(withdrawnStudents[index]);

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
        displayEntry,
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

    type NameColumnKey =
        | 'vocational'
        | 'transfer'
        | 'submitted'
        | 'underReview'
        | 'interview'
        | 'waitlisted'
        | 'rejected'
        | 'withdrawn';

    const nameColumns: Array<{
        key: NameColumnKey;
        header: string;
        count: number;
        after?: 'rejection' | 'withdrawal';
    }> = [
        { key: 'vocational', header: admission.acceptedStatsVocational, count: columnCounts.vocational },
        { key: 'transfer', header: admission.acceptedStatsTransfer, count: columnCounts.transfer },
        { key: 'submitted', header: admission.acceptedStatsSubmitted, count: columnCounts.submitted },
        { key: 'underReview', header: admission.acceptedStatsUnderReview, count: columnCounts.underReview },
        { key: 'interview', header: admission.acceptedStatsInterview, count: columnCounts.interview },
        { key: 'waitlisted', header: admission.acceptedStatsWaitlisted, count: columnCounts.waitlisted },
        {
            key: 'rejected',
            header: admission.acceptedStatsRejected,
            count: columnCounts.rejected,
            after: 'rejection',
        },
        {
            key: 'withdrawn',
            header: admission.acceptedStatsWithdrawn,
            count: columnCounts.withdrawn,
            after: 'withdrawal',
        },
    ];

    return (
        <Dialog open={open} onOpenChange={onOpenChange} modal={false}>
            <DialogContent
                ref={contentRef}
                className={`sis-admission-draft-dialog sis-admission-sheet-dialog sis-admission-accepted-dialog${maximizeClassName}`}
                overlayClassName="sis-admission-sheet-dialog__overlay"
                dir="rtl"
                lang="ar"
                onOpenAutoFocus={(event) => event.preventDefault()}
                onCloseAutoFocus={(event) => event.preventDefault()}
                onInteractOutside={(event) => event.preventDefault()}
                onPointerDownOutside={(event) => event.preventDefault()}
                onPointerDownCapture={bringToFront}
            >
                <DialogTitle className="sr-only">{admission.acceptedStudentsDialogTitle}</DialogTitle>
                {maximized ? null : resizeHandles}

                <div className="sis-admission-sheet sis-admission-accepted-sheet">
                    <header className="sis-admission-sheet__hero" {...(maximized ? {} : heroDragProps)}>
                        <WindowControls
                            className="sis-admission-sheet__window-controls"
                            label={i18n.window.controls}
                            minimizeLabel={i18n.window.minimize}
                            maximizeLabel={i18n.window.maximize}
                            restoreLabel={i18n.window.restore}
                            closeLabel={i18n.window.close}
                            minimizable={false}
                            maximizable
                            maximized={maximized}
                            onMaximize={toggleMaximize}
                            onClose={() => onOpenChange(false)}
                        />
                        <div className="sis-admission-sheet__hero-copy">
                            <p className="sis-admission-sheet__hero-title">
                                {admission.acceptedStudentsDialogTitle}
                            </p>
                        </div>
                        <div className="sis-admission-sheet__hero-logo">
                            <AppLogo tone="on-dark" className="sis-admission-sheet__logo" />
                        </div>
                    </header>

                    <SheetSection title={admission.sheetAcceptedFilters}>
                        <div className="sis-admission-sheet__row sis-admission-accepted-sheet__filters-row sis-admission-accepted-sheet__filters-row--with-school">
                            <SheetField label={admission.filterBySchool}>
                                <SisListSelect
                                    value={schoolId}
                                    options={schoolOptions}
                                    onChange={(next) => {
                                        setSchoolId(next);
                                        setPeriodId('');
                                        setEditing(false);
                                        setDrafts({});
                                    }}
                                    dir="rtl"
                                    ariaLabel={admission.filterBySchool}
                                    className={`sis-admission-sheet-list-select${schoolId !== '' ? ' sis-admission-draft-field--filled' : ''}`}
                                    triggerClassName={`sis-admission-sheet__control sis-admission-draft-select${schoolId !== '' ? ' sis-admission-draft-field--filled' : ''}`}
                                    menuClassName="sis-admission-sheet-list-select__menu"
                                />
                            </SheetField>
                            <SheetField label={admission.academicYear}>
                                <SisListSelect
                                    value={yearId}
                                    options={yearOptions}
                                    onChange={(next) => {
                                        setYearId(next);
                                        setPeriodId('');
                                        setEditing(false);
                                        setDrafts({});
                                    }}
                                    dir="ltr"
                                    ariaLabel={admission.academicYear}
                                    className={`sis-admission-sheet-list-select${yearId !== '' ? ' sis-admission-draft-field--filled' : ''}`}
                                    triggerClassName={`sis-admission-sheet__control sis-admission-draft-select${yearId !== '' ? ' sis-admission-draft-field--filled' : ''}`}
                                    menuClassName="sis-admission-sheet-list-select__menu"
                                />
                            </SheetField>
                            <SheetField label={admission.filterByPeriod}>
                                <SisListSelect
                                    value={periodId}
                                    options={periodOptions}
                                    onChange={(next) => {
                                        setPeriodId(next);
                                        setEditing(false);
                                        setDrafts({});
                                    }}
                                    dir="rtl"
                                    ariaLabel={admission.filterByPeriod}
                                    className={`sis-admission-sheet-list-select${periodId !== '' ? ' sis-admission-draft-field--filled' : ''}`}
                                    triggerClassName={`sis-admission-sheet__control sis-admission-draft-select${periodId !== '' ? ' sis-admission-draft-field--filled' : ''}`}
                                    menuClassName="sis-admission-sheet-list-select__menu"
                                />
                            </SheetField>
                            {canManage ? (
                                <div
                                    className="sis-admission-accepted-sheet__filter-actions"
                                    role="group"
                                    aria-label={i18n.common.actions}
                                >
                                    <Button
                                        type="button"
                                        disabled={editing || tableRows.length === 0 || saving || schoolId === ''}
                                        onClick={beginEdit}
                                    >
                                        {i18n.common.edit}
                                    </Button>
                                    <Button
                                        type="button"
                                        disabled={!editing || saving}
                                        onClick={saveEdits}
                                    >
                                        {saving ? i18n.common.saving : i18n.common.save}
                                    </Button>
                                </div>
                            ) : null}
                        </div>
                    </SheetSection>

                    <div className="sis-admission-accepted-sheet__list-body">
                        {loading ? (
                            <p className="sis-admission-sheet__empty sis-admission-accepted-sheet__empty" aria-busy="true">
                                {i18n.common.loading}
                            </p>
                        ) : tableRows.length === 0 ? (
                            <p className="sis-admission-sheet__empty sis-admission-accepted-sheet__empty">
                                {admission.emptyAcceptedStudents}
                            </p>
                        ) : (
                            <div className="sis-admission-accepted-sheet__scroller">
                                <table className="sis-admission-accepted-sheet__table">
                                    <thead>
                                        <tr>
                                            <th scope="col" className="sis-admission-accepted-sheet__num">
                                                #
                                            </th>
                                            {nameColumns.map((column) => (
                                                <Fragment key={column.key}>
                                                    <th
                                                        scope="col"
                                                        className="sis-admission-accepted-sheet__channel"
                                                    >
                                                        <span className="sis-admission-accepted-sheet__col-label">
                                                            {column.header}
                                                        </span>
                                                        <span
                                                            className="sis-admission-accepted-sheet__col-count"
                                                            dir="ltr"
                                                            aria-label={`${column.header}: ${column.count}`}
                                                        >
                                                            {column.count}
                                                        </span>
                                                    </th>
                                                    {column.after === 'rejection' ? (
                                                        <th
                                                            scope="col"
                                                            className="sis-admission-accepted-sheet__notes"
                                                        >
                                                            <span className="sis-admission-accepted-sheet__col-label">
                                                                {admission.acceptedStatsRejectionReason}
                                                            </span>
                                                        </th>
                                                    ) : null}
                                                    {column.after === 'withdrawal' ? (
                                                        <th
                                                            scope="col"
                                                            className="sis-admission-accepted-sheet__notes"
                                                        >
                                                            <span className="sis-admission-accepted-sheet__col-label">
                                                                {admission.acceptedStatsWithdrawalReason}
                                                            </span>
                                                        </th>
                                                    ) : null}
                                                </Fragment>
                                            ))}
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {tableRows.map((row, index) => (
                                            <tr key={row.key}>
                                                <td className="sis-admission-accepted-sheet__num" dir="ltr">
                                                    {index + 1}
                                                </td>
                                                {nameColumns.map((column) => {
                                                    const entry = row[column.key];
                                                    const value = cellName(entry);

                                                    return (
                                                        <Fragment key={column.key}>
                                                            <td
                                                                className="sis-admission-accepted-sheet__channel"
                                                                title={value}
                                                            >
                                                                {entry ? (
                                                                    <EditableCell
                                                                        editing={editing}
                                                                        value={value}
                                                                        onChange={(next) =>
                                                                            patchDraft(entry.id, {
                                                                                full_name: next,
                                                                            })
                                                                        }
                                                                    />
                                                                ) : null}
                                                            </td>
                                                            {column.after === 'rejection' ? (
                                                                <td
                                                                    className="sis-admission-accepted-sheet__notes"
                                                                    title={row.rejected?.rejection_reason ?? ''}
                                                                >
                                                                    {row.rejected ? (
                                                                        <EditableCell
                                                                            editing={editing}
                                                                            value={row.rejected.rejection_reason}
                                                                            onChange={(next) =>
                                                                                patchDraft(row.rejected!.id, {
                                                                                    rejection_reason: next,
                                                                                })
                                                                            }
                                                                        />
                                                                    ) : null}
                                                                </td>
                                                            ) : null}
                                                            {column.after === 'withdrawal' ? (
                                                                <td
                                                                    className="sis-admission-accepted-sheet__notes"
                                                                    title={row.withdrawn?.withdrawal_reason ?? ''}
                                                                >
                                                                    {row.withdrawn ? (
                                                                        <EditableCell
                                                                            editing={editing}
                                                                            value={row.withdrawn.withdrawal_reason}
                                                                            onChange={(next) =>
                                                                                patchDraft(row.withdrawn!.id, {
                                                                                    withdrawal_reason: next,
                                                                                })
                                                                            }
                                                                        />
                                                                    ) : null}
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
                        )}
                    </div>

                    <div className="sis-admission-sheet__actions">
                        <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
                            {i18n.dialog.cancel}
                        </Button>
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    );
}
