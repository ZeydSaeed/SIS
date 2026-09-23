import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { OpsFormField } from '@/components/sis/ops-form-field';
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
    | 'section_name'
    | 'enrollment_number';

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
    'enrollment_number',
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
        enrollment_number: i18n.enrollments.enrollmentNumber,
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
            })).filter((field) => field.value !== '—'),
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

function ReadonlyBox({
    value,
    dir = 'rtl',
}: {
    value: string;
    dir?: 'rtl' | 'ltr';
}) {
    const filled = value !== '—';

    return (
        <div
            className={
                filled
                    ? 'sis-ops-hub__link sis-admission-draft-control sis-admission-draft-readonly sis-admission-draft-field--filled'
                    : 'sis-ops-hub__link sis-admission-draft-control sis-admission-draft-readonly'
            }
            dir={dir}
            aria-live="polite"
        >
            {value}
        </div>
    );
}

function StudentHistoryPanel({
    history,
    showDivider,
}: {
    history: PlacementHistoryPayload;
    showDivider: boolean;
}) {
    const i18n = t();
    const studentTitle = displayValue(
        history.student_full_name?.trim() || history.student_code || String(history.student_id),
    );
    const yearTitle = displayValue(
        history.academic_year_code?.trim() || history.academic_year_name?.trim() || '',
    );
    const statusTitle = currentStatus(history.segments, i18n);
    const timeline = buildTimeline(history.segments, i18n);
    const hasChanges = timeline.some((entry) => entry.kind === 'change');

    return (
        <article
            className={
                showDivider
                    ? 'sis-placement-history-student sis-placement-history-student--divided'
                    : 'sis-placement-history-student'
            }
        >
            <div className="sis-admission-draft-rows">
                <div className="sis-admission-draft-row sis-admission-draft-row--3">
                    <OpsFormField
                        label={i18n.enrollments.quadName}
                        name={`history_student_${history.student_id}`}
                    >
                        <ReadonlyBox value={studentTitle} />
                    </OpsFormField>
                    <OpsFormField
                        label={i18n.enrollments.academicYear}
                        name={`history_year_${history.student_id}`}
                    >
                        <ReadonlyBox value={yearTitle} dir="ltr" />
                    </OpsFormField>
                    <OpsFormField
                        label={i18n.enrollments.statusTabsTitle}
                        name={`history_status_${history.student_id}`}
                    >
                        <ReadonlyBox value={statusTitle} />
                    </OpsFormField>
                </div>
            </div>

            {timeline.length === 0 ? (
                <p className="text-muted-foreground text-sm">
                    {i18n.enrollments.placementHistoryEmpty}
                </p>
            ) : (
                <div className="sis-placement-history-timeline">
                    {timeline.map((entry) => (
                        <section
                            key={`${history.student_id}-${entry.kind}-${entry.id}`}
                            className={
                                entry.kind === 'original'
                                    ? 'sis-placement-history-block sis-placement-history-block--original'
                                    : 'sis-placement-history-block sis-placement-history-block--change'
                            }
                        >
                            <header className="sis-placement-history-block__head">
                                <span className="sis-placement-history-block__kind">
                                    {entry.kind === 'original'
                                        ? i18n.enrollments.placementHistoryOriginal
                                        : i18n.enrollments.placementHistoryChange}
                                </span>
                                <span className="sis-placement-history-block__date" dir="ltr">
                                    {entry.date}
                                </span>
                            </header>

                            {entry.kind === 'original' ? (
                                entry.fields.length === 0 ? (
                                    <p className="sis-placement-history-block__empty">
                                        {i18n.enrollments.placementHistoryEmpty}
                                    </p>
                                ) : (
                                    <div className="sis-admission-draft-row sis-admission-draft-row--3">
                                        {entry.fields.map((field) => (
                                            <OpsFormField
                                                key={field.key}
                                                label={field.label}
                                                name={`history_original_${history.student_id}_${entry.id}_${field.key}`}
                                            >
                                                <ReadonlyBox value={field.value} />
                                            </OpsFormField>
                                        ))}
                                    </div>
                                )
                            ) : (
                                <ul className="sis-placement-history-changes">
                                    {entry.fields.map((field) => (
                                        <li
                                            key={field.key}
                                            className="sis-placement-history-change"
                                        >
                                            <span className="sis-placement-history-change__label">
                                                {field.label}
                                            </span>
                                            <span
                                                className="sis-placement-history-change__values"
                                                dir="ltr"
                                            >
                                                <span className="sis-placement-history-change__from">
                                                    {field.from}
                                                </span>
                                                <span
                                                    className="sis-placement-history-change__arrow"
                                                    aria-hidden
                                                >
                                                    →
                                                </span>
                                                <span className="sis-placement-history-change__to">
                                                    {field.to}
                                                </span>
                                            </span>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </section>
                    ))}

                    {!hasChanges && timeline.length > 0 ? (
                        <p className="text-muted-foreground text-sm">
                            {i18n.enrollments.placementHistoryNoChanges}
                        </p>
                    ) : null}
                </div>
            )}
        </article>
    );
}

export function EnrollmentPlacementHistoryDialog({ histories, onClose }: Props) {
    const i18n = t();
    const title =
        histories.length > 1
            ? `${i18n.enrollments.placementHistoryTitle} (${histories.length})`
            : i18n.enrollments.placementHistoryTitle;

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
                className="sis-admission-draft-dialog sis-placement-history-dialog max-h-[90vh] overflow-y-auto sm:max-w-3xl"
                overlayClassName="sis-placement-history-dialog__overlay"
                dir="rtl"
                lang="ar"
                onOpenAutoFocus={(event) => event.preventDefault()}
                onCloseAutoFocus={(event) => event.preventDefault()}
                onPointerDownOutside={(event) => event.preventDefault()}
                onInteractOutside={(event) => event.preventDefault()}
            >
                <DialogHeader>
                    <DialogTitle>{title}</DialogTitle>
                </DialogHeader>

                <div className="sis-admission-draft-form sis-placement-history-form">
                    {histories.length === 0 ? (
                        <p className="text-muted-foreground text-sm">
                            {i18n.enrollments.placementHistoryEmpty}
                        </p>
                    ) : (
                        <div className="sis-placement-history-students">
                            {histories.map((history, index) => (
                                <StudentHistoryPanel
                                    key={history.student_id}
                                    history={history}
                                    showDivider={index > 0}
                                />
                            ))}
                        </div>
                    )}

                    <div className="sis-admission-draft-actions">
                        <Button type="button" variant="outline" onClick={onClose}>
                            {i18n.dialog.cancel}
                        </Button>
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    );
}
