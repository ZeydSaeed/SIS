import { router, usePage } from '@inertiajs/react';
import {
    CalendarClock,
    ClipboardCheck,
    ClockAlert,
    ExternalLink,
    ShieldCheck,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import {
    AttendancePage,
    AttendanceTable,
    AttendanceWindow,
    WindowFact,
    selectionGroup,
    useAttendanceEditRibbon,
    useAttendanceHomeFilters,
    useAttendanceMessages,
    useAttendanceSelection,
    useRowViewer,
    useYearFilterSpec,
    visitWith,
    type AttendanceColumn,
    type AttendanceMessage,
    type SectionKey,
} from '@/components/attendance/attendance-chrome';
import {
    SHEET_STATUSES,
    StatusLabel,
    countsText,
    dateTime,
    newKey,
    sheetLabel,
    sheetStatusMeta,
    statusCommands,
    toggleIn,
    type AttendanceAbilities,
    type StatusCounts,
} from '@/components/attendance/attendance-ui';
import type { ContextMenuItem } from '@/components/sis/context-menu';
import { usePageError } from '@/components/sis/page-error-context';
import type { PageRibbonGroup } from '@/components/sis/page-ribbon-context';
import type { FilterSpec } from '@/components/sis/ribbon-filter';
import { Button } from '@/components/ui/button';
import { t } from '@/i18n';

export type BoardRow = {
    id: number;
    sheet_status: number;
    version: number;
    lesson_type: number | null;
    session_date: string;
    start_time: string | null;
    period_number: number | null;
    subject_name: string | null;
    section_name: string | null;
    class_name: string | null;
    teacher_name: string | null;
    teacher_id: number;
    submitted_at: string | null;
    submitted_late: boolean;
    submission_count: number;
    counts: StatusCounts;
};

type MissingLesson = {
    schedule_id: number;
    start_time: string;
    end_time: string;
    subject_name: string;
    class_name: string;
    section_name: string;
    teacher_name: string;
    session_id: number | null;
    sheet_status: number | null;
};

export type BoardFilters = {
    date_from?: string;
    date_to?: string;
    sheet_status: number[];
    teacher_id: number[];
    subject_id: number[];
    section_id: number[];
    class_id: number[];
    lesson_type: number[];
    origin: number[];
    late?: boolean;
    page: number;
    per_page?: number;
    academic_year_id: number | null;
};

export type BoardProps = {
    board: {
        data: BoardRow[];
        meta: {
            page: number;
            per_page: number;
            total: number;
            last_page: number;
        };
        totals: Record<number, number>;
        missing: MissingLesson[];
        single_day: boolean;
    };
    filters: BoardFilters;
    lookups: {
        classes: Array<{
            id: number;
            name: string;
            sections: Array<{ id: number; name: string }>;
        }>;
        subjects: Array<{ id: number; name: string }>;
        teachers: Array<{ id: number; full_name: string }>;
    };
    abilities: AttendanceAbilities;
    mode: 'review' | 'sessions';
};

const reviewable = (row: BoardRow) =>
    row.sheet_status === 2 || row.sheet_status === 3;

/** «مراجعة الكشوف» (review mode) and «كل الجلسات» (sessions mode) — the same server-filtered board. */
export function SheetsBoard({
    board,
    filters,
    lookups,
    abilities,
    mode,
}: BoardProps) {
    const a = t().attendanceSystem;
    const r = a.review;
    const c = a.chrome;
    const path = mode === 'review' ? '/attendance/review' : '/attendance';
    const current: SectionKey = mode === 'review' ? 'review' : 'sessions';
    const { showInertiaErrors } = usePageError();
    const flash = (
        usePage().props as {
            flash?: {
                attendanceResult?: {
                    action?: string;
                    failed?: Array<{ session_id: number; code: string }>;
                } | null;
            };
        }
    ).flash;
    const selection = useAttendanceSelection(board.data, (row) => row.id);
    const [confirmBulk, setConfirmBulk] = useState<BoardRow[] | null>(null);
    const [missingOpen, setMissingOpen] = useState(false);
    const [busy, setBusy] = useState(false);
    const canReview = abilities.review_sheet;
    const targets = selection.targets;
    const single = targets.length === 1 ? targets[0] : null;
    const bulkRows = targets.filter(reviewable);

    const visit = (next: Partial<BoardFilters>) => {
        const merged = { ...filters, ...next, page: next.page ?? 1 };
        visitWith(path, {
            ...merged,
            sheet_status:
                merged.sheet_status.length === 0 ? [''] : merged.sheet_status,
        });
    };
    const openSheet = (row: BoardRow) =>
        router.visit(`/attendance/sheets/${row.id}`);
    const teacherDay = (row: BoardRow) =>
        router.visit(
            `/attendance/my?date=${row.session_date}&teacher_id=${row.teacher_id}`,
        );

    const bulkApprove = () => {
        if (confirmBulk === null) return;
        setBusy(true);
        router.post(
            '/attendance/review/bulk-approve',
            {
                items: confirmBulk.map((row) => ({
                    session_id: row.id,
                    version: row.version,
                })),
            },
            {
                preserveScroll: true,
                headers: { 'X-Idempotency-Key': newKey('bulk-approve') },
                onSuccess: () => {
                    setConfirmBulk(null);
                },
                onError: (errors) => showInertiaErrors(errors),
                onFinish: () => setBusy(false),
            },
        );
    };

    const columns: AttendanceColumn<BoardRow>[] = [
        {
            id: 'date',
            header: r.date,
            kind: 'text',
            cell: (row) => (
                <span dir="ltr">{`${row.session_date} ${row.start_time ?? ''}`}</span>
            ),
        },
        {
            id: 'period',
            header: a.myDay.period,
            kind: 'num',
            cell: (row) => <span dir="ltr">{row.period_number ?? '—'}</span>,
        },
        {
            id: 'subject',
            header: r.subject,
            kind: 'name',
            cell: (row) => row.subject_name ?? '—',
        },
        {
            id: 'type',
            header: r.lessonType,
            kind: 'text',
            cell: (row) =>
                row.lesson_type ? a.lessonType[row.lesson_type] : '—',
        },
        {
            id: 'class',
            header: r.section,
            kind: 'text',
            cell: (row) =>
                `${row.class_name ?? '—'} / ${row.section_name ?? '—'}`,
        },
        {
            id: 'teacher',
            header: r.teacher,
            kind: 'text',
            cell: (row) => row.teacher_name ?? '—',
        },
        {
            id: 'counts',
            header: a.sheet.recorded,
            kind: 'text',
            cell: (row) => countsText(row.counts),
        },
        {
            id: 'status',
            header: r.status,
            kind: 'status',
            tone: (row) => sheetStatusMeta(row.sheet_status)?.tone ?? 0,
            cell: (row) => (
                <StatusLabel
                    meta={sheetStatusMeta(row.sheet_status)}
                    label={`${sheetLabel(row.sheet_status)}${row.submission_count > 1 ? ` ×${row.submission_count}` : ''}${row.submitted_late ? ` · ${a.sheet.lateSubmission}` : ''}`}
                />
            ),
        },
        {
            id: 'submitted',
            header: r.submitted,
            kind: 'text',
            cell: (row) => dateTime(row.submitted_at),
        },
    ];
    const viewer = useRowViewer(
        columns,
        (row) => `${a.sheet.sheetNo} ${row.id}`,
        (row) => (
            <div className="sis-admission-sheet__field sis-branches-field--wide">
                <Button
                    type="button"
                    variant="outline"
                    onClick={() => openSheet(row)}
                >
                    <ExternalLink aria-hidden />
                    {r.open}
                </Button>
            </div>
        ),
    );

    /** «تحرير»: one sheet opens for marking / review; several reviewable sheets go to «اعتماد المحدد». */
    const edit = (rows: BoardRow[]) =>
        rows.length === 1
            ? openSheet(rows[0])
            : setConfirmBulk(rows.filter(reviewable));

    const editGroups = useMemo((): PageRibbonGroup[] => {
        const multiEditable =
            targets.length > 1 && (!canReview || bulkRows.length === 0);

        return [
            selectionGroup(
                'attendance-board-selection',
                targets,
                viewer.open,
                edit,
                {
                    editLabel: targets.length > 1 ? r.bulkApprove : c.edit,
                    editDisabled: multiEditable,
                    editTitle: multiEditable ? r.selectHint : undefined,
                },
            ),
            {
                id: 'attendance-board-actions',
                label: c.actions,
                commands: [
                    {
                        id: 'attendance-board-open',
                        label: r.open,
                        icon: ExternalLink,
                        iconTone: 'education',
                        disabled: single === null,
                        title: single === null ? c.needsSelection : undefined,
                        onSelect: () => single && openSheet(single),
                    },
                    {
                        id: 'attendance-board-day',
                        label: a.myDay.chooseTeacher,
                        icon: CalendarClock,
                        iconTone: 'growth',
                        disabled: single === null || !canReview,
                        onSelect: () => single && teacherDay(single),
                    },
                    ...(canReview
                        ? [
                              {
                                  id: 'attendance-board-bulk',
                                  label: r.bulkApprove,
                                  icon: ShieldCheck,
                                  iconTone: 'ok' as const,
                                  count: bulkRows.length,
                                  disabled: bulkRows.length === 0 || busy,
                                  title:
                                      bulkRows.length === 0
                                          ? r.selectHint
                                          : undefined,
                                  onSelect: () => setConfirmBulk(bulkRows),
                              },
                              {
                                  id: 'attendance-board-review',
                                  label: a.sheet.startReview,
                                  icon: ClipboardCheck,
                                  iconTone: 'amber' as const,
                                  disabled:
                                      single === null || !reviewable(single),
                                  onSelect: () => single && openSheet(single),
                              },
                          ]
                        : []),
                    ...(mode === 'review'
                        ? [
                              {
                                  id: 'attendance-board-missing',
                                  label: r.missing,
                                  icon: ClockAlert,
                                  iconTone: 'warning' as const,
                                  count: board.missing.length,
                                  disabled: !board.single_day,
                                  title: board.single_day
                                      ? r.missing
                                      : r.missingHint,
                                  onSelect: () => setMissingOpen(true),
                              },
                          ]
                        : []),
                ],
            },
            {
                id: 'attendance-board-status',
                label: c.statuses,
                commands: statusCommands(
                    'attendance-board-status',
                    SHEET_STATUSES,
                    a.sheetStatus,
                    board.totals,
                    filters.sheet_status,
                    (v) =>
                        visit({
                            sheet_status: toggleIn(filters.sheet_status, v),
                        }),
                ),
            },
        ];
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [
        targets,
        busy,
        board.totals,
        board.missing.length,
        board.single_day,
        filters,
        canReview,
    ]);
    useAttendanceEditRibbon(current, abilities, editGroups);

    const multi = (
        key: keyof BoardFilters,
        label: string,
        allLabel: string,
        options: Array<{ value: string; label: string }>,
    ): FilterSpec => ({
        key,
        type: 'multi',
        label,
        allLabel,
        values: ((filters[key] as number[]) ?? []).map(String),
        options,
        onChange: (values: string[]) => visit({ [key]: values.map(Number) }),
    });
    const sectionOptions = lookups.classes
        .filter(
            (cls) =>
                filters.class_id.length === 0 ||
                filters.class_id.includes(cls.id),
        )
        .flatMap((cls) =>
            cls.sections.map((s) => ({
                value: String(s.id),
                label: `${cls.name} / ${s.name}`,
            })),
        );
    const yearSpec = useYearFilterSpec(filters.academic_year_id, (year) =>
        visitWith(path, { academic_year_id: year }),
    );
    useAttendanceHomeFilters(
        [
            yearSpec,
            {
                key: 'date_from',
                date: true,
                label: r.dateFrom,
                value: filters.date_from ?? '',
                onChange: (date_from: string) => visit({ date_from }),
            },
            {
                key: 'date_to',
                date: true,
                label: r.dateTo,
                value: filters.date_to ?? '',
                onChange: (date_to: string) => visit({ date_to }),
            },
            multi(
                'teacher_id',
                r.teacher,
                c.allTeachers,
                lookups.teachers.map((x) => ({
                    value: String(x.id),
                    label: x.full_name,
                })),
            ),
            multi(
                'subject_id',
                r.subject,
                c.allSubjects,
                lookups.subjects.map((x) => ({
                    value: String(x.id),
                    label: x.name,
                })),
            ),
            multi(
                'class_id',
                r.classLabel,
                c.allClasses,
                lookups.classes.map((x) => ({
                    value: String(x.id),
                    label: x.name,
                })),
            ),
            multi('section_id', r.section, c.allSections, sectionOptions),
            multi(
                'lesson_type',
                r.lessonType,
                c.allTypes,
                [1, 2].map((v) => ({
                    value: String(v),
                    label: a.lessonType[v],
                })),
            ),
            multi(
                'origin',
                c.origin,
                c.allOrigins,
                [1, 2, 3].map((v) => ({
                    value: String(v),
                    label: c.origins[v],
                })),
            ),
            {
                key: 'late',
                type: 'single',
                label: r.lateOnly,
                value: filters.late ? '1' : '',
                options: [
                    { value: '', label: c.allSubmissions },
                    { value: '1', label: r.lateOnly },
                ],
                onChange: (value: string) => visit({ late: value === '1' }),
            },
        ],
        () => visitWith(path, { academic_year_id: filters.academic_year_id }),
        [filters, lookups, yearSpec.options.length],
    );

    const failed =
        flash?.attendanceResult?.action === 'bulk_approve'
            ? (flash.attendanceResult.failed ?? [])
            : [];
    const messages: AttendanceMessage[] =
        failed.length > 0
            ? [
                  {
                      key: `failed:${failed.map((f) => f.session_id).join(',')}`,
                      tone: 'warning' as const,
                      text: `${r.bulkFailed}: ${failed.map((f) => `#${f.session_id} (${(t().errors.codes as Record<string, string>)[f.code] ?? f.code})`).join('، ')}`,
                  },
              ]
            : [];
    useAttendanceMessages(messages);

    const menuItems = (row: BoardRow): ContextMenuItem[] => [
        {
            id: 'open',
            label: r.open,
            icon: ExternalLink,
            onSelect: () => openSheet(row),
        },
        {
            id: 'view',
            label: c.view,
            icon: ClipboardCheck,
            onSelect: () => viewer.open([row]),
        },
        ...(canReview
            ? [
                  {
                      id: 'day',
                      label: a.myDay.chooseTeacher,
                      icon: CalendarClock,
                      onSelect: () => teacherDay(row),
                  },
              ]
            : []),
    ];

    const missingColumns: AttendanceColumn<MissingLesson>[] = [
        {
            id: 'time',
            header: a.myDay.time,
            kind: 'text',
            cell: (m) => (
                <span dir="ltr">{`${m.start_time}–${m.end_time}`}</span>
            ),
        },
        {
            id: 'subject',
            header: r.subject,
            kind: 'name',
            cell: (m) => m.subject_name,
        },
        {
            id: 'class',
            header: r.section,
            kind: 'text',
            cell: (m) => `${m.class_name} / ${m.section_name}`,
        },
        {
            id: 'teacher',
            header: r.teacher,
            kind: 'text',
            cell: (m) => m.teacher_name,
        },
        {
            id: 'status',
            header: r.status,
            kind: 'status',
            tone: (m) => sheetStatusMeta(m.sheet_status)?.tone ?? 0,
            cell: (m) => (
                <StatusLabel
                    meta={sheetStatusMeta(m.sheet_status)}
                    label={sheetLabel(m.sheet_status)}
                />
            ),
        },
    ];

    return (
        <AttendancePage title={mode === 'review' ? r.title : a.nav.sessions}>
            <AttendanceTable
                caption={mode === 'review' ? r.title : a.nav.sessions}
                storageKey={`board.${mode}`}
                rows={board.data}
                rowKey={(row) => row.id}
                columns={columns}
                emptyText={r.empty}
                selection={selection}
                onOpen={openSheet}
                menuItems={menuItems}
                server={{
                    page: board.meta.page,
                    perPage: board.meta.per_page,
                    total: board.meta.total,
                    lastPage: board.meta.last_page,
                    onPage: (page, per_page) => visit({ page, per_page }),
                }}
            />

            {viewer.element}

            {missingOpen ? (
                <AttendanceWindow
                    wide
                    title={r.missing}
                    onClose={() => setMissingOpen(false)}
                    sections={[
                        {
                            id: 'att-missing',
                            title: `${r.missing} (${board.missing.length})`,
                            plain: true,
                            content: (
                                <div className="sis-branches-field--wide">
                                    <AttendanceTable
                                        caption={r.missing}
                                        storageKey="board.missing"
                                        rows={board.missing}
                                        rowKey={(m) => m.schedule_id}
                                        columns={missingColumns}
                                        emptyText={r.noMissing}
                                        fit={false}
                                        emptyAsDialog={false}
                                        onOpen={(m) =>
                                            m.session_id !== null &&
                                            router.visit(
                                                `/attendance/sheets/${m.session_id}`,
                                            )
                                        }
                                    />
                                </div>
                            ),
                        },
                    ]}
                />
            ) : null}

            {confirmBulk !== null ? (
                <AttendanceWindow
                    title={r.bulkTitle.replace(
                        ':n',
                        String(confirmBulk.length),
                    )}
                    pending={busy}
                    confirmLabel={r.bulkApprove}
                    confirmDisabled={confirmBulk.length === 0}
                    onConfirm={bulkApprove}
                    onClose={() => setConfirmBulk(null)}
                    columns={2}
                    sections={[
                        {
                            id: 'att-bulk-desc',
                            title: r.bulkApprove,
                            content: (
                                <WindowFact
                                    wide
                                    label={c.note}
                                    value={r.bulkDesc}
                                />
                            ),
                        },
                        {
                            id: 'att-bulk-list',
                            title: `${c.scope} (${confirmBulk.length})`,
                            content: (
                                <ul className="sis-branches-checklist sis-branches-field--wide">
                                    {confirmBulk.map((row) => (
                                        <li key={row.id}>
                                            <span className="sis-branches-checklist__item">
                                                <ShieldCheck
                                                    aria-hidden
                                                    className="sis-branches-table__icon"
                                                />
                                                <span className="sis-branches-checklist__text">
                                                    <span className="sis-branches-checklist__name">{`#${row.id} · ${row.subject_name ?? ''} · ${row.class_name ?? ''} / ${row.section_name ?? ''}`}</span>
                                                    <span className="sis-branches-checklist__desc">{`${row.session_date} · ${row.teacher_name ?? ''} · ${countsText(row.counts)}`}</span>
                                                </span>
                                            </span>
                                        </li>
                                    ))}
                                </ul>
                            ),
                        },
                    ]}
                />
            ) : null}
        </AttendancePage>
    );
}
