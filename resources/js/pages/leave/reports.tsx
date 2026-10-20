import {
    BarChart3,
    CalendarDays,
    Clock4,
    FileDown,
    Hourglass,
    Printer,
    Users,
} from 'lucide-react';
import { useMemo } from 'react';
import {
    AttendanceTable,
    useAttendanceHomeFilters,
    useYearFilterSpec,
    visitWith,
    type AttendanceColumn,
} from '@/components/attendance/attendance-chrome';
import { dateTime } from '@/components/attendance/attendance-ui';
import {
    periodText,
    quantityText,
    LeavePage,
    useLeaveEditRibbon,
    type LeaveAbilities,
    type LeaveType,
} from '@/components/leave/leave-chrome';
import type { PageRibbonGroup } from '@/components/sis/page-ribbon-context';
import type { FilterSpec } from '@/components/sis/ribbon-filter';
import { t } from '@/i18n';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

type Row = Record<string, string | number | boolean | null>;
type Props = {
    report: { kind: string; rows: Row[] };
    types: LeaveType[];
    filters: {
        academic_year_id: number;
        kind: number[];
        type: number[];
        from: string | null;
        to: string | null;
        report: string;
    };
    abilities: LeaveAbilities;
};

const PATH = '/leave/reports';
const KINDS = [
    { key: 'by_person', icon: Users },
    { key: 'by_type', icon: BarChart3 },
    { key: 'pending', icon: Hourglass },
    { key: 'approval_time', icon: Clock4 },
    { key: 'holidays', icon: CalendarDays },
] as const;

export default function LeaveReports(props: Props) {
    const l = t().leave;
    const breadcrumbs: BreadcrumbItem[] = [
        { title: l.title, href: '/leave' },
        { title: l.reports, href: PATH },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <ReportsPage {...props} />
        </AppLayout>
    );
}

function ReportsPage({ report, types, filters, abilities }: Props) {
    const l = t().leave;
    const visit = (next: Partial<Props['filters']>) =>
        visitWith(PATH, { ...filters, ...next });
    const type = (r: Row) => (r.masked ? l.masked : String(r.type_name ?? '—'));
    const num = (v: unknown) =>
        typeof v === 'number'
            ? Number.isInteger(v)
                ? v
                : v.toFixed(2)
            : String(v ?? '—');

    const columns: Record<string, AttendanceColumn<Row>[]> = {
        by_person: [
            {
                id: 'person',
                header: l.person,
                kind: 'name',
                cell: (r) => String(r.person_name ?? '—'),
            },
            {
                id: 'kind',
                header: l.kind,
                kind: 'text',
                cell: (r) => l.kinds[Number(r.requester_kind)],
            },
            { id: 'type', header: l.type, kind: 'text', cell: type },
            {
                id: 'requests',
                header: l.reportRequests,
                kind: 'num',
                cell: (r) => num(r.requests),
            },
            {
                id: 'quantity',
                header: l.reportApprovedQty,
                kind: 'text',
                cell: (r) => quantityText(Number(r.quantity), Number(r.unit)),
            },
        ],
        by_type: [
            { id: 'type', header: l.type, kind: 'name', cell: type },
            {
                id: 'open',
                header: l.reportOpen,
                kind: 'num',
                cell: (r) => num(r.open),
            },
            {
                id: 'approved',
                header: l.statuses[4],
                kind: 'num',
                cell: (r) => num(r.approved),
            },
            {
                id: 'rejected',
                header: l.statuses[5],
                kind: 'num',
                cell: (r) => num(r.rejected),
            },
            {
                id: 'closed',
                header: l.cards.closed,
                kind: 'num',
                cell: (r) => num(r.closed),
            },
            {
                id: 'qty',
                header: l.reportApprovedQty,
                kind: 'text',
                cell: (r) =>
                    quantityText(Number(r.approved_quantity), Number(r.unit)),
            },
        ],
        pending: [
            {
                id: 'no',
                header: l.requestNo,
                kind: 'num',
                cell: (r) => `#${r.request_no}`,
            },
            {
                id: 'person',
                header: l.person,
                kind: 'name',
                cell: (r) => String(r.person_name ?? '—'),
            },
            { id: 'type', header: l.type, kind: 'text', cell: type },
            {
                id: 'period',
                header: l.period,
                kind: 'text',
                cell: (r) => (
                    <span dir="ltr">
                        {periodText(String(r.date_from), String(r.date_to))}
                    </span>
                ),
            },
            {
                id: 'qty',
                header: l.quantity,
                kind: 'text',
                cell: (r) => quantityText(Number(r.quantity), Number(r.unit)),
            },
            {
                id: 'status',
                header: l.status,
                kind: 'text',
                cell: (r) => l.statuses[Number(r.status)],
            },
            {
                id: 'stage',
                header: l.stage,
                kind: 'text',
                cell: (r) => `${r.approved_levels} / ${r.approval_levels}`,
            },
            {
                id: 'submitted',
                header: l.submittedAt,
                kind: 'text',
                cell: (r) => dateTime(r.submitted_at as string | null),
            },
            {
                id: 'age',
                header: l.reportAge,
                kind: 'num',
                cell: (r) => num(r.age_days),
            },
        ],
        approval_time: [
            { id: 'type', header: l.type, kind: 'name', cell: type },
            {
                id: 'decided',
                header: l.reportDecided,
                kind: 'num',
                cell: (r) => num(r.decided),
            },
            {
                id: 'avg',
                header: l.reportAverage,
                kind: 'num',
                cell: (r) => num(r.average_hours),
            },
            {
                id: 'max',
                header: l.reportLongest,
                kind: 'num',
                cell: (r) => num(r.longest_hours),
            },
        ],
        holidays: [
            {
                id: 'name',
                header: l.holiday,
                kind: 'name',
                cell: (r) => String(r.name),
            },
            {
                id: 'period',
                header: l.period,
                kind: 'text',
                cell: (r) => (
                    <span dir="ltr">
                        {periodText(String(r.start_date), String(r.end_date))}
                    </span>
                ),
            },
            {
                id: 'type',
                header: l.reportHolidayType,
                kind: 'text',
                cell: (r) => t().holidays.types[Number(r.holiday_type)] ?? '—',
            },
            {
                id: 'scope',
                header: l.reportScope,
                kind: 'text',
                cell: (r) => (r.global ? l.reportGlobal : l.reportSchool),
            },
        ],
    };

    const groups = useMemo(
        (): PageRibbonGroup[] => [
            {
                id: 'leave-report-kinds',
                label: l.reports,
                commands: KINDS.map((k) => ({
                    id: `leave-report-${k.key}`,
                    label: l.reportKinds[k.key],
                    icon: k.icon,
                    iconTone: 'steel' as const,
                    pressed: report.kind === k.key,
                    onSelect: () => visit({ report: k.key }),
                })),
            },
            {
                id: 'leave-report-print',
                label: l.actions,
                commands: [
                    {
                        id: 'leave-report-export-cmd',
                        label: l.exportCsv,
                        icon: FileDown,
                        iconTone: 'ok',
                        onSelect: () => {
                            // A plain download (a file, not an Inertia page): the same report and filters.
                            const params = new URLSearchParams({
                                report: report.kind,
                                academic_year_id: String(filters.academic_year_id),
                            });
                            if (filters.from) params.set('from', filters.from);
                            if (filters.to) params.set('to', filters.to);
                            if (filters.kind !== null) params.set('kind', String(filters.kind));
                            filters.type.forEach((id) => params.append('type[]', String(id)));
                            window.location.assign(`${PATH}/export?${params.toString()}`);
                        },
                    },
                    {
                        id: 'leave-report-print-cmd',
                        label: l.print,
                        icon: Printer,
                        iconTone: 'sky',
                        onSelect: () => window.print(),
                    },
                ],
            },
        ],
        // eslint-disable-next-line react-hooks/exhaustive-deps
        [report.kind, filters],
    );
    useLeaveEditRibbon(groups);
    const yearSpec = useYearFilterSpec(filters.academic_year_id, (year) =>
        visitWith(PATH, { academic_year_id: year, report: report.kind }),
    );
    useAttendanceHomeFilters(
        [
            yearSpec,
            {
                key: 'from',
                date: true,
                label: l.dateFrom,
                value: filters.from ?? '',
                onChange: (from: string) => visit({ from }),
            },
            {
                key: 'to',
                date: true,
                label: l.dateTo,
                value: filters.to ?? '',
                onChange: (to: string) => visit({ to }),
            },
            {
                key: 'kind',
                type: 'multi',
                label: l.kind,
                allLabel: l.allKinds,
                values: filters.kind.map(String),
                options: [
                    { value: '1', label: l.kindsPlural[1] },
                    { value: '2', label: l.kindsPlural[2] },
                ],
                onChange: (values: string[]) =>
                    visit({ kind: values.map(Number) }),
            } as FilterSpec,
            {
                key: 'type',
                type: 'multi',
                label: l.type,
                allLabel: l.allTypes,
                values: filters.type.map(String),
                options: types.map((x) => ({
                    value: String(x.id),
                    label: x.name_ar,
                })),
                onChange: (values: string[]) =>
                    visit({ type: values.map(Number) }),
            } as FilterSpec,
        ],
        () =>
            visitWith(PATH, {
                academic_year_id: filters.academic_year_id,
                report: report.kind,
            }),
        [filters, types, yearSpec.options.length],
    );

    return (
        <LeavePage title={l.reports} current={'reports'} abilities={abilities}>
            <AttendanceTable
                caption={l.reportKinds[report.kind] ?? l.reports}
                storageKey={`leave.report.${report.kind}`}
                rows={report.rows.map((r, i) => ({ ...r, __id: i + 1 }))}
                rowKey={(r) => Number(r.__id)}
                columns={columns[report.kind] ?? []}
                emptyText={l.noEvents}
            />
        </LeavePage>
    );
}
