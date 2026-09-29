import AppLogo from '@/components/app-logo';
import { SheetField, SheetSection } from '@/components/sis/admission-sheet';
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

export type PlacementHistorySegment = {
    id: number;
    status: number;
    effective_from: string;
    effective_to: string | null;
    branch_name?: string | null;
    department_name?: string | null;
    specialization_name?: string | null;
    class_name?: string | null;
    section_name?: string | null;
    enrollment_number?: string | null;
};

export type PlacementHistoryPayload = {
    student_id: number;
    student_full_name?: string | null;
    student_code?: string | null;
    academic_year_id: number | null;
    academic_year_name?: string | null;
    academic_year_code?: string | null;
    segments: PlacementHistorySegment[];
};

type Props = {
    histories: PlacementHistoryPayload[];
    onClose: () => void;
};

type TrackedFieldKey =
    | 'branch_name'
    | 'department_name'
    | 'specialization_name'
    | 'class_name'
    | 'section_name';

type FieldChange = {
    key: TrackedFieldKey;
    label: string;
    from: string;
    to: string;
};

type TimelineEntry =
    | {
          kind: 'original';
          id: number;
          date: string;
          fields: Array<{ key: TrackedFieldKey; label: string; value: string }>;
      }
    | {
          kind: 'change';
          id: number;
          date: string;
          fields: FieldChange[];
      };

const TRACKED_FIELDS: TrackedFieldKey[] = [
    'branch_name',
    'department_name',
    'specialization_name',
    'class_name',
    'section_name',
];

function statusLabel(status: number, i18n: ReturnType<typeof t>): string {
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

function formatDate(value: string | null | undefined): string {
    if (value === null || value === undefined || value === '') {
        return '—';
    }

    return value.slice(0, 10);
}

function displayValue(value: string | null | undefined): string {
    const text = value?.trim() ?? '';

    return text === '' ? '—' : text;
}

function fieldLabel(key: TrackedFieldKey, i18n: ReturnType<typeof t>): string {
    const map: Record<TrackedFieldKey, string> = {
        branch_name: i18n.enrollments.branchName,
        department_name: i18n.enrollments.departmentName,
        specialization_name: i18n.enrollments.specialization,
        class_name: i18n.enrollments.className,
        section_name: i18n.enrollments.sectionName,
    };

    return map[key];
}

function fieldValue(segment: PlacementHistorySegment, key: TrackedFieldKey): string {
    return displayValue(segment[key]);
}

function buildTimeline(
    segments: PlacementHistorySegment[],
    i18n: ReturnType<typeof t>,
): TimelineEntry[] {
    if (segments.length === 0) {
        return [];
    }

    const ordered = [...segments].sort((a, b) => {
        const dateCmp = formatDate(a.effective_from).localeCompare(formatDate(b.effective_from));
        if (dateCmp !== 0) {
            return dateCmp;
        }

        return a.id - b.id;
    });

    const [first, ...rest] = ordered;
    const entries: TimelineEntry[] = [
        {
            kind: 'original',
            id: first.id,
            date: formatDate(first.effective_from),
            fields: TRACKED_FIELDS.map((key) => ({
                key,
                label: fieldLabel(key, i18n),
                value: fieldValue(first, key),
            })),
        },
    ];

    let previous = first;
    for (const segment of rest) {
        const changes: FieldChange[] = [];
        for (const key of TRACKED_FIELDS) {
            const from = fieldValue(previous, key);
            const to = fieldValue(segment, key);
            if (from !== to) {
                changes.push({
                    key,
                    label: fieldLabel(key, i18n),
                    from,
                    to,
                });
            }
        }

        if (changes.length > 0) {
            entries.push({
                kind: 'change',
                id: segment.id,
                date: formatDate(segment.effective_from),
                fields: changes,
            });
        }

        previous = segment;
    }

    return entries;
}

function currentStatus(segments: PlacementHistorySegment[], i18n: ReturnType<typeof t>): string {
    if (segments.length === 0) {
        return '—';
    }

    const current =
        segments.find((segment) => segment.effective_to === null && segment.status !== 5) ??
        [...segments].sort((a, b) => b.id - a.id)[0];

    return statusLabel(current.status, i18n);
}

function filledControlClass(filled: boolean): string {
    return `sis-admission-sheet__control sis-admission-draft-readonly${filled ? ' sis-admission-draft-field--filled' : ''}`;
}

function SheetReadonly({
    value,
    dir = 'rtl',
}: {
    value: string;
    dir?: 'rtl' | 'ltr';
}) {
    return (
        <div className={filledControlClass(value !== '—')} dir={dir} aria-readonly="true">
            {value}
        </div>
    );
}

function StudentHistoryPanel({ history }: { history: PlacementHistoryPayload }) {
    const i18n = t();
    const studentTitle = displayValue(
        history.student_full_name?.trim() || String(history.student_id),
    );
    const yearTitle = displayValue(
        history.academic_year_code?.trim() || history.academic_year_name?.trim() || '',
    );
    const statusTitle = currentStatus(history.segments, i18n);
    const timeline = buildTimeline(history.segments, i18n);
    const hasChanges = timeline.some((entry) => entry.kind === 'change');

    return (
        <div className="sis-placement-history-sheet__student">
            <SheetSection
                id={`placement-history-student-${history.student_id}`}
                title={i18n.enrollments.student}
            >
                <div className="sis-admission-sheet__row sis-admission-sheet__row--track5 sis-placement-history-sheet__summary-row">
                    <SheetField
                        label={i18n.enrollments.quadName}
                        className="sis-placement-history-sheet__field--name"
                    >
                        <SheetReadonly value={studentTitle} />
                    </SheetField>
                    <SheetField
                        label={i18n.enrollments.academicYear}
                        className="sis-placement-history-sheet__field--year"
                    >
                        <SheetReadonly value={yearTitle} dir="ltr" />
                    </SheetField>
                    <SheetField
                        label={i18n.common.status}
                        className="sis-placement-history-sheet__field--status"
                    >
                        <SheetReadonly value={statusTitle} />
                    </SheetField>
                </div>
            </SheetSection>

            {timeline.length === 0 ? (
                <SheetSection
                    id={`placement-history-empty-${history.student_id}`}
                    title={i18n.enrollments.placementHistoryTitle}
                >
                    <p className="sis-admission-sheet__empty">{i18n.enrollments.placementHistoryEmpty}</p>
                </SheetSection>
            ) : (
                timeline.map((entry) => (
                    <SheetSection
                        key={`${history.student_id}-${entry.kind}-${entry.id}`}
                        id={`placement-history-${history.student_id}-${entry.kind}-${entry.id}`}
                        title={`${
                            entry.kind === 'original'
                                ? i18n.enrollments.placementHistoryOriginal
                                : i18n.enrollments.placementHistoryChange
                        } — ${entry.date}`}
                    >
                        {entry.kind === 'original' ? (
                            entry.fields.length === 0 ? (
                                <p className="sis-admission-sheet__empty">
                                    {i18n.enrollments.placementHistoryEmpty}
                                </p>
                            ) : (
                                <div className="sis-admission-sheet__row sis-admission-sheet__row--track5 sis-placement-history-sheet__fields-row">
                                    {entry.fields.map((field) => (
                                        <SheetField
                                            key={field.key}
                                            label={field.label}
                                            className={`sis-placement-history-sheet__field sis-placement-history-sheet__field--${field.key}`}
                                        >
                                            <SheetReadonly value={field.value} />
                                        </SheetField>
                                    ))}
                                </div>
                            )
                        ) : (
                            <div className="sis-admission-sheet__row sis-admission-sheet__row--track5 sis-placement-history-sheet__fields-row">
                                {entry.fields.map((field) => (
                                    <SheetField
                                        key={field.key}
                                        label={field.label}
                                        className={`sis-placement-history-sheet__field sis-placement-history-sheet__field--${field.key}`}
                                    >
                                        <div
                                            className={`${filledControlClass(true)} sis-placement-history-sheet__change`}
                                            dir="ltr"
                                            aria-readonly="true"
                                        >
                                            <span>{field.from}</span>
                                            <span aria-hidden="true">→</span>
                                            <span>{field.to}</span>
                                        </div>
                                    </SheetField>
                                ))}
                            </div>
                        )}
                    </SheetSection>
                ))
            )}

            {!hasChanges && timeline.length > 0 ? (
                <p className="sis-placement-history-sheet__hint">
                    {i18n.enrollments.placementHistoryNoChanges}
                </p>
            ) : null}
        </div>
    );
}

/** Placement history sheet — same chrome SSOT as enrollment / students / admission forms. */
export function EnrollmentPlacementHistoryDialog({ histories, onClose }: Props) {
    const i18n = t();
    const title =
        histories.length > 1
            ? `${i18n.enrollments.placementHistoryTitle} (${histories.length})`
            : i18n.enrollments.placementHistoryTitle;
    const { contentRef, heroDragProps, bringToFront, resizeHandles } = useSmoothDialogDrag(true, {
        resizable: true,
        minSize: { width: 720, height: 320 },
    });
    const { maximized, toggleMaximize, maximizeClassName } = useSheetMaximize(contentRef);

    return (
        <Dialog
            open
            modal={false}
            onOpenChange={(open) => {
                if (!open) {
                    onClose();
                }
            }}
        >
            <DialogContent
                ref={contentRef}
                className={`sis-admission-draft-dialog sis-admission-sheet-dialog sis-placement-history-dialog${maximizeClassName}`}
                overlayClassName="sis-admission-sheet-dialog__overlay sis-placement-history-dialog__overlay"
                dir="rtl"
                lang="ar"
                onOpenAutoFocus={(event) => event.preventDefault()}
                onCloseAutoFocus={(event) => event.preventDefault()}
                onPointerDownOutside={(event) => event.preventDefault()}
                onInteractOutside={(event) => event.preventDefault()}
                onPointerDownCapture={bringToFront}
            >
                <DialogTitle className="sr-only">{title}</DialogTitle>
                {maximized ? null : resizeHandles}

                <div className="sis-admission-sheet sis-placement-history-sheet">
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
                            onClose={onClose}
                        />
                        <div className="sis-admission-sheet__hero-copy">
                            <p className="sis-admission-sheet__hero-title">{title}</p>
                        </div>
                        <div className="sis-admission-sheet__hero-logo">
                            <AppLogo tone="on-dark" className="sis-admission-sheet__logo" />
                        </div>
                    </header>

                    {histories.length === 0 ? (
                        <SheetSection
                            id="placement-history-empty"
                            title={i18n.enrollments.placementHistoryTitle}
                        >
                            <p className="sis-admission-sheet__empty">
                                {i18n.enrollments.placementHistoryEmpty}
                            </p>
                        </SheetSection>
                    ) : (
                        <div className="sis-placement-history-sheet__students">
                            {histories.map((history) => (
                                <StudentHistoryPanel key={history.student_id} history={history} />
                            ))}
                        </div>
                    )}

                    <div className="sis-admission-sheet__actions">
                        <Button type="button" variant="outline" onClick={onClose}>
                            {i18n.dialog.cancel}
                        </Button>
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    );
}
