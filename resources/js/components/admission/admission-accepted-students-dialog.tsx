import {
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
import { t } from '@/i18n';

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    students: AdmissionAcceptedStudent[];
    defaultAcademicYearId?: number | null;
};

/** 1 = academic→vocational transfer, 2 = vocational school intake */
const REQUEST_KIND_VOCATIONAL = 2;
const REQUEST_KIND_TRANSFER = 1;

type RosterEntry = {
    id: number;
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
    };
}

function cellName(entry: RosterEntry | undefined): string {
    return entry?.full_name ?? '';
}

/** Application follow-up roster — category columns by status / admission channel. */
export function AdmissionAcceptedStudentsDialog({
    open,
    onOpenChange,
    students,
    defaultAcademicYearId = null,
}: Props) {
    const i18n = t();
    const admission = i18n.admission;
    const [yearId, setYearId] = useState<string>('');
    const [periodId, setPeriodId] = useState<string>('');
    const { contentRef, heroDragProps, bringToFront, resizeHandles } = useSmoothDialogDrag(open, {
        resizable: true,
        minSize: { width: 720, height: 360 },
    });

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
            return;
        }

        const preferred =
            defaultAcademicYearId != null
            && yearOptions.some((option) => option.value === String(defaultAcademicYearId))
                ? String(defaultAcademicYearId)
                : (yearOptions[0]?.value ?? '');
        setYearId(preferred);
        setPeriodId('');
    }, [open, defaultAcademicYearId, yearOptions]);

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
            if (yearId !== '' && student.academic_year_id !== Number(yearId)) {
                return false;
            }
            if (periodId !== '' && student.period_id !== Number(periodId)) {
                return false;
            }

            return true;
        });
    }, [students, yearId, periodId]);

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
            const vocational = vocationalStudents[index];
            const transfer = transferStudents[index];
            const submitted = submittedStudents[index];
            const underReview = underReviewStudents[index];
            const interview = interviewStudents[index];
            const waitlisted = waitlistedStudents[index];
            const rejected = rejectedStudents[index];
            const withdrawn = withdrawnStudents[index];

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
                vocationalName: cellName(vocational),
                transferName: cellName(transfer),
                submittedName: cellName(submitted),
                underReviewName: cellName(underReview),
                interviewName: cellName(interview),
                waitlistedName: cellName(waitlisted),
                rejectedName: cellName(rejected),
                rejectionReason: rejected?.rejection_reason ?? '',
                withdrawnName: cellName(withdrawn),
                withdrawalReason: withdrawn?.withdrawal_reason ?? '',
            };
        });
    }, [
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

    const columns = [
        { key: 'vocational', header: admission.acceptedStatsVocational, count: columnCounts.vocational, cell: (row: (typeof tableRows)[number]) => row.vocationalName },
        { key: 'transfer', header: admission.acceptedStatsTransfer, count: columnCounts.transfer, cell: (row: (typeof tableRows)[number]) => row.transferName },
        { key: 'submitted', header: admission.acceptedStatsSubmitted, count: columnCounts.submitted, cell: (row: (typeof tableRows)[number]) => row.submittedName },
        { key: 'underReview', header: admission.acceptedStatsUnderReview, count: columnCounts.underReview, cell: (row: (typeof tableRows)[number]) => row.underReviewName },
        { key: 'interview', header: admission.acceptedStatsInterview, count: columnCounts.interview, cell: (row: (typeof tableRows)[number]) => row.interviewName },
        { key: 'waitlisted', header: admission.acceptedStatsWaitlisted, count: columnCounts.waitlisted, cell: (row: (typeof tableRows)[number]) => row.waitlistedName },
        { key: 'rejected', header: admission.acceptedStatsRejected, count: columnCounts.rejected, cell: (row: (typeof tableRows)[number]) => row.rejectedName },
        { key: 'rejectionReason', header: admission.acceptedStatsRejectionReason, cell: (row: (typeof tableRows)[number]) => row.rejectionReason, notes: true },
        { key: 'withdrawn', header: admission.acceptedStatsWithdrawn, count: columnCounts.withdrawn, cell: (row: (typeof tableRows)[number]) => row.withdrawnName },
        { key: 'withdrawalReason', header: admission.acceptedStatsWithdrawalReason, cell: (row: (typeof tableRows)[number]) => row.withdrawalReason, notes: true },
    ] as const;

    return (
        <Dialog open={open} onOpenChange={onOpenChange} modal={false}>
            <DialogContent
                ref={contentRef}
                className="sis-admission-draft-dialog sis-admission-sheet-dialog sis-admission-accepted-dialog"
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
                {resizeHandles}

                <div className="sis-admission-sheet sis-admission-accepted-sheet">
                    <header className="sis-admission-sheet__hero" {...heroDragProps}>
                        <WindowControls
                            className="sis-admission-sheet__window-controls"
                            label={i18n.window.controls}
                            minimizeLabel={i18n.window.minimize}
                            maximizeLabel={i18n.window.maximize}
                            restoreLabel={i18n.window.restore}
                            closeLabel={i18n.window.close}
                            minimizable={false}
                            maximizable={false}
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
                        <div className="sis-admission-sheet__row sis-admission-sheet__row--2">
                            <SheetField label={admission.academicYear}>
                                <SisListSelect
                                    value={yearId}
                                    options={yearOptions}
                                    onChange={(next) => {
                                        setYearId(next);
                                        setPeriodId('');
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
                                    onChange={setPeriodId}
                                    dir="rtl"
                                    ariaLabel={admission.filterByPeriod}
                                    className={`sis-admission-sheet-list-select${periodId !== '' ? ' sis-admission-draft-field--filled' : ''}`}
                                    triggerClassName={`sis-admission-sheet__control sis-admission-draft-select${periodId !== '' ? ' sis-admission-draft-field--filled' : ''}`}
                                    menuClassName="sis-admission-sheet-list-select__menu"
                                />
                            </SheetField>
                        </div>
                    </SheetSection>

                    <div className="sis-admission-accepted-sheet__list-body">
                        {tableRows.length === 0 ? (
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
                                            {columns.map((column) => (
                                                <th
                                                    key={column.key}
                                                    scope="col"
                                                    className={
                                                        'notes' in column && column.notes
                                                            ? 'sis-admission-accepted-sheet__notes'
                                                            : 'sis-admission-accepted-sheet__channel'
                                                    }
                                                >
                                                    <span className="sis-admission-accepted-sheet__col-label">
                                                        {column.header}
                                                    </span>
                                                    {'count' in column ? (
                                                        <span
                                                            className="sis-admission-accepted-sheet__col-count"
                                                            dir="ltr"
                                                            aria-label={`${column.header}: ${column.count}`}
                                                        >
                                                            {column.count}
                                                        </span>
                                                    ) : null}
                                                </th>
                                            ))}
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {tableRows.map((row, index) => (
                                            <tr key={row.key}>
                                                <td className="sis-admission-accepted-sheet__num" dir="ltr">
                                                    {index + 1}
                                                </td>
                                                {columns.map((column) => {
                                                    const value = column.cell(row);
                                                    const isNotes = 'notes' in column && column.notes;

                                                    return (
                                                        <td
                                                            key={column.key}
                                                            className={
                                                                isNotes
                                                                    ? 'sis-admission-accepted-sheet__notes'
                                                                    : 'sis-admission-accepted-sheet__channel'
                                                            }
                                                            title={value}
                                                        >
                                                            {value !== '' ? (
                                                                <AcceptedCellScroll text={value} />
                                                            ) : null}
                                                        </td>
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
