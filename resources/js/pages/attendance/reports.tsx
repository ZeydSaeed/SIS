import { router } from '@inertiajs/react';
import {
    Activity,
    BookOpen,
    CalendarDays,
    Clock4,
    Download,
    FileSpreadsheet,
    FileText,
    FlaskConical,
    FolderDown,
    History,
    Info,
    LayoutGrid,
    Printer,
    ShieldAlert,
    User,
    UserCheck,
    Users,
    type LucideIcon,
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
    type IconTone,
} from '@/components/attendance/attendance-chrome';
import {
    RECORD_STATUSES,
    StatusLabel,
    dateTime,
    newKey,
    recordStatusMeta,
    sheetStatusMeta,
    type AttendanceAbilities,
} from '@/components/attendance/attendance-ui';
import { usePageError } from '@/components/sis/page-error-context';
import type { PageRibbonGroup } from '@/components/sis/page-ribbon-context';
import type { FilterSpec } from '@/components/sis/ribbon-filter';
import { dayOfWeekLabel } from '@/components/sis/status-chip';
import { t } from '@/i18n';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

type Row = Record<string, string | number | null | boolean> & { __i: number };

type ListKey =
    | 'class_id'
    | 'section_id'
    | 'subject_id'
    | 'teacher_id'
    | 'student_id'
    | 'status'
    | 'lesson_type';

type Filters = Record<ListKey, number[]> & {
    type: string;
    date_from: string;
    date_to: string;
    approved_only: boolean;
    page: number;
    per_page?: number;
    academic_year_id: number;
};

type ExportRow = {
    id: number;
    report_type: string;
    format: number;
    status: number;
    row_count: number | null;
    truncated: boolean;
    error: string | null;
    created_at: string;
    finished_at: string | null;
};

type Props = {
    report: {
        type: string;
        rows: Array<Record<string, string | number | null | boolean>>;
        meta: {
            page: number;
            per_page: number;
            total: number;
            last_page: number;
        };
        truncated: boolean;
        formula: string;
        generated_at: string;
    };
    filters: Filters;
    types: string[];
    lookups: {
        classes: Array<{
            id: number;
            name: string;
            sections: Array<{ id: number; name: string }>;
        }>;
        subjects: Array<{ id: number; name: string }>;
        teachers: Array<{ id: number; full_name: string }>;
    };
    exports: ExportRow[];
    abilities: AttendanceAbilities;
};

/** Internal ids are not report columns. */
const HIDDEN = new Set([
    'student_id',
    'section_id',
    'subject_id',
    'teacher_id',
    'school_id',
    'student_code',
    '__i',
]);

/** One icon shape + colour per report. */
const TYPE_ICONS: Record<string, { icon: LucideIcon; tone: IconTone }> = {
    students: { icon: Users, tone: 'education' },
    student_detail: { icon: User, tone: 'sky' },
    sections: { icon: LayoutGrid, tone: 'growth' },
    subjects: { icon: BookOpen, tone: 'forest' },
    teachers: { icon: FileText, tone: 'authority' },
    daily: { icon: CalendarDays, tone: 'steel' },
    staff: { icon: UserCheck, tone: 'forest' },
    lesson_types: { icon: FlaskConical, tone: 'amber' },
    periods: { icon: Clock4, tone: 'warning' },
    excuses: { icon: FileText, tone: 'rose' },
    risk: { icon: ShieldAlert, tone: 'danger' },
};

/** report_exports.status — 1 queued · 2 running · 3 ready · 4 failed. */
const EXPORT_TONES: Record<number, number> = { 1: 0, 2: 2, 3: 1, 4: 4 };

export default function AttendanceReports(props: Props) {
    const a = t().attendanceSystem;
    const breadcrumbs: BreadcrumbItem[] = [
        { title: t().attendance.title, href: '/attendance' },
        { title: a.reports.title, href: '/attendance/reports' },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <ReportsPage {...props} />
        </AppLayout>
    );
}

/** The report criteria as query parameters (also the payload of a queued export). */
function criteria(filters: Filters): Record<string, unknown> {
    const {
        page: _page,
        per_page: _perPage,
        academic_year_id: _year,
        type: _type,
        ...rest
    } = filters;

    return rest;
}

/** «rate:23 · trend:12 …» → explained reasons. */
function reasonText(reasons: string): string {
    const map = t().attendanceSystem.reports.riskReasons as Record<
        string,
        string
    >;

    return reasons
        .split(' · ')
        .filter((r) => r !== '')
        .map((r) => {
            const [key, value] = r.split(':');

            return (map[key] ?? key).replace(':v', value ?? '');
        })
        .join(' · ');
}

function ReportsPage({
    report,
    filters,
    types,
    lookups,
    exports,
    abilities,
}: Props) {
    const a = t().attendanceSystem;
    const rp = a.reports;
    const c = a.chrome;
    const { showInertiaErrors } = usePageError();
    const [panel, setPanel] = useState<'info' | 'exports' | null>(null);
    const rows: Row[] = useMemo(
        () => report.rows.map((row, i) => ({ ...row, __i: i })),
        [report.rows],
    );
    const selection = useAttendanceSelection(rows, (row) => row.__i);
    const single = selection.targets.length === 1 ? selection.targets[0] : null;

    const visit = (next: Partial<Filters>) =>
        visitWith('/attendance/reports', {
            ...filters,
            ...next,
            page: next.page ?? 1,
        });
    const exportQuery = new URLSearchParams();
    Object.entries({ type: filters.type, ...criteria(filters) } as Record<
        string,
        unknown
    >).forEach(([key, value]) => {
        if (Array.isArray(value))
            value.forEach((v) => exportQuery.append(`${key}[]`, String(v)));
        else if (
            value !== null &&
            value !== undefined &&
            value !== '' &&
            value !== false
        )
            exportQuery.append(
                key,
                value === true ? '1' : String(value as string | number),
            );
    });

    const queueExport = (format: 1 | 2) =>
        router.post(
            '/attendance/reports/exports',
            { type: filters.type, format, filters: criteria(filters) } as never,
            {
                preserveScroll: true,
                headers: { 'X-Idempotency-Key': newKey('report-export') },
                onError: (errors) => showInertiaErrors(errors),
            },
        );
    const studentDetail = (row: Row) =>
        typeof row.student_id === 'number' &&
        visit({ type: 'student_detail', student_id: [row.student_id] });

    const cell = (key: string, value: Row[string]) => {
        if (value === null || value === undefined) return '—';
        if (key === 'status' && report.type === 'student_detail')
            return (
                <StatusLabel
                    meta={recordStatusMeta(Number(value))}
                    label={a.recordStatus[Number(value)] ?? String(value)}
                />
            );
        if (key === 'status' && report.type === 'excuses')
            return a.excuses.statuses[Number(value)] ?? value;
        if (key === 'sheet_status')
            return (
                <StatusLabel
                    meta={sheetStatusMeta(Number(value))}
                    label={a.sheetStatus[Number(value)] ?? String(value)}
                />
            );
        if (key === 'lesson_type') return a.lessonType[Number(value)] ?? value;
        if (key === 'excuse_type')
            return a.excuses.types[Number(value)] ?? value;
        if (key === 'weekday') return dayOfWeekLabel(Number(value));
        if (key === 'band') return rp.bands[String(value)] ?? value;
        if (key === 'reasons') return reasonText(String(value));
        if (typeof value === 'boolean')
            return value ? a.settings.yes : a.settings.no;

        return typeof value === 'number' ? (
            <span dir="ltr">{value}</span>
        ) : (
            String(value)
        );
    };

    const keys = Object.keys(report.rows[0] ?? {}).filter(
        (key) => !HIDDEN.has(key),
    );
    const columns: AttendanceColumn<Row>[] = keys.map((key) => {
        const numeric =
            typeof report.rows[0]?.[key] === 'number' &&
            key !== 'status' &&
            key !== 'sheet_status';
        const statusTone =
            key === 'band'
                ? (r: Row) =>
                      r.band === 'high' ? 4 : r.band === 'medium' ? 2 : 1
                : key === 'sheet_status'
                  ? (r: Row) =>
                        sheetStatusMeta(Number(r.sheet_status))?.tone ?? 0
                  : key === 'status' && report.type === 'student_detail'
                    ? (r: Row) => recordStatusMeta(Number(r.status)).tone
                    : undefined;

        return {
            id: key,
            header: rp.columns[key] ?? key,
            kind: statusTone
                ? 'status'
                : numeric
                  ? 'num'
                  : key === 'full_name' || key === 'teacher_name'
                    ? 'name'
                    : 'text',
            tone: statusTone,
            cell: (row: Row) => cell(key, row[key]),
        };
    });
    const viewer = useRowViewer(columns, (row) =>
        String(
            row.full_name ??
                row.teacher_name ??
                row.subject_name ??
                row.section_name ??
                row.date ??
                rp.types[report.type] ??
                '',
        ),
    );

    const editGroups = useMemo(
        (): PageRibbonGroup[] => [
            selectionGroup(
                'attendance-reports-selection',
                selection.targets,
                viewer.open,
                null,
                {
                    editTitle: rp.readOnly,
                    extra: [
                        {
                            id: 'attendance-reports-student',
                            label: rp.types.student_detail,
                            icon: History,
                            iconTone: 'education',
                            disabled:
                                single === null ||
                                typeof single.student_id !== 'number' ||
                                report.type === 'student_detail',
                            title: single === null ? c.needsOne : undefined,
                            onSelect: () => single && studentDetail(single),
                        },
                    ],
                },
            ),
            {
                id: 'attendance-reports-types',
                label: rp.type,
                commands: types.map((type) => ({
                    id: `attendance-reports-type-${type}`,
                    label: rp.types[type] ?? type,
                    icon: TYPE_ICONS[type]?.icon ?? Activity,
                    iconTone: TYPE_ICONS[type]?.tone ?? 'steel',
                    pressed: filters.type === type,
                    onSelect: () => visit({ type }),
                })),
            },
            {
                id: 'attendance-reports-export',
                label: rp.exportGroup,
                commands: [
                    {
                        id: 'attendance-reports-csv',
                        label: rp.export,
                        icon: Download,
                        iconTone: 'ok',
                        onSelect: () =>
                            window.location.assign(
                                `/attendance/reports/export?${exportQuery.toString()}`,
                            ),
                    },
                    {
                        id: 'attendance-reports-queue-csv',
                        label: rp.queueCsv,
                        icon: FileSpreadsheet,
                        iconTone: 'growth',
                        onSelect: () => queueExport(1),
                    },
                    {
                        id: 'attendance-reports-queue-pdf',
                        label: rp.queuePdf,
                        icon: FileText,
                        iconTone: 'rose',
                        onSelect: () => queueExport(2),
                    },
                    {
                        id: 'attendance-reports-exports',
                        label: rp.exportsTitle,
                        icon: FolderDown,
                        iconTone: 'sky',
                        count: exports.length,
                        onSelect: () => setPanel('exports'),
                    },
                    {
                        id: 'attendance-reports-print',
                        label: rp.print,
                        icon: Printer,
                        iconTone: 'steel',
                        onSelect: () => window.print(),
                    },
                    {
                        id: 'attendance-reports-info',
                        label: rp.info,
                        icon: Info,
                        iconTone: 'education',
                        onSelect: () => setPanel('info'),
                    },
                ],
            },
        ],
        // eslint-disable-next-line react-hooks/exhaustive-deps
        [selection.targets, filters, types, exports.length],
    );
    useAttendanceEditRibbon('reports', abilities, editGroups);

    const multi = (
        key: ListKey,
        label: string,
        allLabel: string,
        options: Array<{ value: string; label: string }>,
    ): FilterSpec => ({
        key,
        type: 'multi',
        label,
        allLabel,
        values: filters[key].map(String),
        options,
        onChange: (values: string[]) => visit({ [key]: values.map(Number) }),
    });
    const yearSpec = useYearFilterSpec(filters.academic_year_id, (year) =>
        visitWith('/attendance/reports', {
            type: filters.type,
            academic_year_id: year,
        }),
    );
    useAttendanceHomeFilters(
        [
            yearSpec,
            {
                key: 'date_from',
                date: true,
                label: a.review.dateFrom,
                value: filters.date_from,
                onChange: (date_from: string) => visit({ date_from }),
            },
            {
                key: 'date_to',
                date: true,
                label: a.review.dateTo,
                value: filters.date_to,
                onChange: (date_to: string) => visit({ date_to }),
            },
            multi(
                'class_id',
                c.class,
                c.allClasses,
                lookups.classes.map((cls) => ({
                    value: String(cls.id),
                    label: cls.name,
                })),
            ),
            multi(
                'section_id',
                c.section,
                c.allSections,
                lookups.classes.flatMap((cls) =>
                    cls.sections.map((s) => ({
                        value: String(s.id),
                        label: `${cls.name} / ${s.name}`,
                    })),
                ),
            ),
            multi(
                'subject_id',
                c.subject,
                c.allSubjects,
                lookups.subjects.map((s) => ({
                    value: String(s.id),
                    label: s.name,
                })),
            ),
            multi(
                'teacher_id',
                c.teacher,
                c.allTeachers,
                lookups.teachers.map((tch) => ({
                    value: String(tch.id),
                    label: tch.full_name,
                })),
            ),
            multi(
                'status',
                a.sheet.status,
                c.allStatuses,
                RECORD_STATUSES.map((s) => ({
                    value: String(s.value),
                    label: a.recordStatus[s.value],
                })),
            ),
            multi(
                'lesson_type',
                a.review.lessonType,
                c.allTypes,
                [1, 2].map((v) => ({
                    value: String(v),
                    label: a.lessonType[v],
                })),
            ),
            {
                key: 'approved_only',
                type: 'single',
                label: rp.approvedOnly,
                value: filters.approved_only ? '1' : '',
                options: [
                    { value: '', label: c.allSubmissions },
                    { value: '1', label: rp.approvedOnly },
                ],
                onChange: (value: string) =>
                    visit({ approved_only: value === '1' }),
            },
        ],
        () =>
            visitWith('/attendance/reports', {
                type: filters.type,
                academic_year_id: filters.academic_year_id,
            }),
        [filters, lookups, yearSpec.options.length],
    );

    useAttendanceMessages(
        report.truncated
            ? [{ key: 'truncated', tone: 'warning', text: rp.truncated }]
            : [],
        rp.title,
    );

    const exportColumns: AttendanceColumn<ExportRow>[] = [
        {
            id: 'type',
            header: rp.type,
            kind: 'name',
            cell: (e) => rp.types[e.report_type] ?? e.report_type,
        },
        {
            id: 'format',
            header: rp.format,
            kind: 'text',
            cell: (e) => rp.formats[e.format] ?? e.format,
        },
        {
            id: 'status',
            header: a.sheet.status,
            kind: 'status',
            tone: (e) => EXPORT_TONES[e.status] ?? 0,
            cell: (e) => rp.exportStatuses[e.status] ?? e.status,
        },
        {
            id: 'rows',
            header: rp.rowCount,
            kind: 'num',
            cell: (e) => e.row_count ?? '—',
        },
        {
            id: 'created',
            header: rp.requestedAt,
            kind: 'text',
            cell: (e) => dateTime(e.created_at),
        },
        {
            id: 'download',
            header: rp.download,
            kind: 'plain',
            cell: (e) =>
                e.status === 3 ? (
                    <a
                        className="underline"
                        href={`/attendance/reports/exports/${e.id}/download`}
                    >
                        {rp.download}
                        {e.truncated ? ` (${rp.truncatedShort})` : ''}
                    </a>
                ) : e.status === 4 ? (
                    (e.error ?? '—')
                ) : (
                    '—'
                ),
        },
    ];

    return (
        <AttendancePage
            title={`${rp.title} — ${rp.types[report.type] ?? report.type}`}
        >
            <AttendanceTable
                caption={rp.types[report.type] ?? report.type}
                storageKey={`reports.${report.type}`}
                rows={rows}
                rowKey={(row) => row.__i}
                columns={columns}
                emptyText={rp.empty}
                selection={selection}
                onOpen={(row) => viewer.open([row])}
                server={{
                    page: report.meta.page,
                    perPage: report.meta.per_page,
                    total: report.meta.total,
                    lastPage: report.meta.last_page,
                    onPage: (page, per_page) => visit({ page, per_page }),
                }}
            />

            {viewer.element}

            {panel === 'exports' ? (
                <AttendanceWindow
                    wide
                    title={rp.exportsTitle}
                    onClose={() => setPanel(null)}
                    sections={[
                        {
                            id: 'att-exports',
                            title: `${rp.exportsTitle} (${exports.length})`,
                            plain: true,
                            content: (
                                <div className="sis-branches-field--wide">
                                    <AttendanceTable
                                        caption={rp.exportsTitle}
                                        storageKey="reports.exports"
                                        rows={exports}
                                        rowKey={(e) => e.id}
                                        columns={exportColumns}
                                        emptyText={rp.noExports}
                                        fit={false}
                                        emptyAsDialog={false}
                                    />
                                </div>
                            ),
                        },
                    ]}
                />
            ) : null}

            {panel === 'info' ? (
                <AttendanceWindow
                    title={rp.info}
                    columns={2}
                    onClose={() => setPanel(null)}
                    sections={[
                        {
                            id: 'att-report-info',
                            title: rp.types[report.type] ?? report.type,
                            content: (
                                <>
                                    <WindowFact
                                        label={rp.generatedAt}
                                        value={dateTime(report.generated_at)}
                                    />
                                    <WindowFact
                                        label={rp.rowCount}
                                        value={
                                            <span dir="ltr">
                                                {report.meta.total}
                                            </span>
                                        }
                                    />
                                    <WindowFact
                                        label={a.review.dateFrom}
                                        value={
                                            <span dir="ltr">
                                                {filters.date_from}
                                            </span>
                                        }
                                    />
                                    <WindowFact
                                        label={a.review.dateTo}
                                        value={
                                            <span dir="ltr">
                                                {filters.date_to}
                                            </span>
                                        }
                                    />
                                </>
                            ),
                        },
                    ]}
                />
            ) : null}
        </AttendancePage>
    );
}
