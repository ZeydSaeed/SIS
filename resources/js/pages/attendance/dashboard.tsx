import { Head, router } from '@inertiajs/react';
import {
    Activity,
    AlarmClock,
    BellRing,
    Building2,
    CheckCircle2,
    ClipboardCheck,
    FileCheck2,
    FilePen,
    Hourglass,
    LogOut,
    Percent,
    School,
    ShieldAlert,
    TrendingDown,
    Undo2,
    UserX,
    Users,
    XCircle,
    type LucideIcon,
} from 'lucide-react';
import { useMemo, useState, type ReactNode } from 'react';
import {
    AttendanceTable,
    AttendanceWindow,
    WindowFact,
    useAttendanceEditRibbon,
    useAttendanceHomeFilters,
    useAttendanceSearch,
    useYearFilterSpec,
    visitWith,
    type AttendanceColumn,
    type IconTone,
} from '@/components/attendance/attendance-chrome';
import { DayBoard, type Board } from '@/components/attendance/day-board';
import {
    SHEET_STATUSES,
    sheetLabel,
    type AttendanceAbilities,
} from '@/components/attendance/attendance-ui';
import type { PageRibbonGroup } from '@/components/sis/page-ribbon-context';
import { Button } from '@/components/ui/button';
import { t } from '@/i18n';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

type Totals = {
    present: number;
    absent: number;
    late: number;
    excused: number;
    left_early: number;
    decided: number;
    attendance_rate: number | null;
    absence_rate: number | null;
    late_rate: number | null;
    sheets: number;
    drafts: number;
    submitted: number;
    under_review: number;
    approved: number;
    returned: number;
    rejected: number;
    late_submissions: number;
};

type CentralRow = Totals & { school_id: number; school_name: string };
type RiskRow = {
    student_id: number;
    full_name: string;
    student_code: string;
    score: number;
    band: string;
    attendance_rate: number | null;
    reasons: string;
};
type Absentee = {
    student_id: number;
    full_name: string;
    student_code: string;
    absent: number;
    excused: number;
    decided: number;
};

type Props = {
    dashboard: {
        today: string;
        period: { from: string; to: string };
        formula: string;
        today_totals: Totals;
        /** The totals of the day the board shows (`day`), not only today. */
        day: string;
        day_totals: Totals;
        month_totals: Totals;
        top_absentees: Absentee[];
        top_risk: RiskRow[];
        risk_method: string;
        alerts_open: number;
        excuses_pending: number;
        central: CentralRow[];
    };
    board: Board;
    scope: 'school' | 'central';
    can_central: boolean;
    filters: { academic_year_id: number };
    abilities: AttendanceAbilities;
};

type Detail =
    | 'today'
    | 'month'
    | 'sheets'
    | 'absentees'
    | 'risk'
    | 'central'
    | 'alerts'
    | 'excuses';

/** A dashboard tile: one indicator; the ribbon shows the same tiles in the same rows. */
type Tile = {
    id: string;
    icon: LucideIcon;
    tone: IconTone;
    label: string;
    value: string | number;
    hint?: string;
    detail: Detail | null;
    href?: string;
};

const pct = (v: number | null) => (v === null ? '—' : `${v}%`);

export default function AttendanceDashboard(props: Props) {
    const a = t().attendanceSystem;
    const breadcrumbs: BreadcrumbItem[] = [
        { title: t().attendance.title, href: '/attendance' },
        { title: a.dashboard.title, href: '/attendance/dashboard' },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <DashboardPage {...props} />
        </AppLayout>
    );
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

function DashboardPage({
    dashboard,
    board,
    scope,
    can_central,
    filters,
    abilities,
}: Props) {
    const a = t().attendanceSystem;
    const d = a.dashboard;
    const today = dashboard.today_totals;
    const month = dashboard.month_totals;
    const [detail, setDetail] = useState<Detail | null>(null);
    const [classIds, setClassIds] = useState<number[]>([]);
    const [statusIds, setStatusIds] = useState<number[]>([]);
    const [query, setQuery] = useState('');
    useAttendanceSearch(query, d.board.search, setQuery, setQuery);
    const highRisk = dashboard.top_risk.filter((r) => r.band === 'high').length;

    /** The tiles, row by row (five per row, as on the SIS dashboard). */
    const rows = useMemo((): Array<{
        id: string;
        label: string;
        tiles: Tile[];
    }> => {
        const all: Array<{ id: string; label: string; tiles: Tile[] }> = [
            {
                id: 'today',
                label: `${d.today} — ${dashboard.today}`,
                tiles: [
                    {
                        id: 'today-rate',
                        icon: Percent,
                        tone: 'ok',
                        label: d.attendanceRate,
                        value: pct(today.attendance_rate),
                        hint: `${d.records}: ${today.decided}`,
                        detail: 'today',
                    },
                    {
                        id: 'today-absent',
                        icon: UserX,
                        tone: 'danger',
                        label: a.sheet.absent,
                        value: today.absent,
                        detail: 'today',
                    },
                    {
                        id: 'today-late',
                        icon: AlarmClock,
                        tone: 'amber',
                        label: a.sheet.late,
                        value: today.late,
                        detail: 'today',
                    },
                    {
                        id: 'today-excused',
                        icon: FileCheck2,
                        tone: 'sky',
                        label: a.sheet.excused,
                        value: today.excused,
                        detail: 'today',
                    },
                    {
                        id: 'today-left',
                        icon: LogOut,
                        tone: 'rose',
                        label: a.sheet.leftEarly,
                        value: today.left_early,
                        detail: 'today',
                    },
                ],
            },
            {
                id: 'sheets',
                label: d.sheets,
                tiles: [
                    {
                        id: 'sheets-pending',
                        icon: FilePen,
                        tone: 'steel',
                        label: d.pending,
                        value: today.drafts + today.returned,
                        detail: 'sheets',
                    },
                    {
                        id: 'sheets-review',
                        icon: Hourglass,
                        tone: 'amber',
                        label: d.inReview,
                        value: today.submitted + today.under_review,
                        detail: 'sheets',
                    },
                    {
                        id: 'sheets-approved',
                        icon: CheckCircle2,
                        tone: 'ok',
                        label: d.approved,
                        value: today.approved,
                        detail: 'sheets',
                    },
                    {
                        id: 'sheets-returned',
                        icon: Undo2,
                        tone: 'warning',
                        label: d.returned,
                        value: month.returned,
                        hint: d.last30,
                        detail: 'sheets',
                    },
                    {
                        id: 'sheets-late',
                        icon: ClipboardCheck,
                        tone: 'authority',
                        label: d.lateSubmissions,
                        value: month.late_submissions,
                        hint: d.last30,
                        detail: 'sheets',
                    },
                ],
            },
            {
                id: 'month',
                label: `${d.last30} — ${dashboard.period.from} → ${dashboard.period.to}`,
                tiles: [
                    {
                        id: 'month-rate',
                        icon: Activity,
                        tone: 'sky',
                        label: d.attendanceRate,
                        value: pct(month.attendance_rate),
                        hint: d.last30,
                        detail: 'month',
                    },
                    {
                        id: 'month-absence',
                        icon: TrendingDown,
                        tone: 'rose',
                        label: d.absenceRate,
                        value: pct(month.absence_rate),
                        hint: d.last30,
                        detail: 'month',
                    },
                    {
                        id: 'month-late',
                        icon: XCircle,
                        tone: 'warning',
                        label: d.lateRate,
                        value: pct(month.late_rate),
                        hint: d.last30,
                        detail: 'month',
                    },
                    {
                        id: 'month-absentees',
                        icon: Users,
                        tone: 'education',
                        label: d.topAbsentees,
                        value: dashboard.top_absentees.length,
                        detail: 'absentees',
                    },
                    {
                        id: 'month-risk',
                        icon: ShieldAlert,
                        tone: 'danger',
                        label: d.topRisk,
                        value: dashboard.top_risk.length,
                        hint: `${a.reports.bands.high}: ${highRisk}`,
                        detail: 'risk',
                    },
                ],
            },
            {
                id: 'follow',
                label: d.followUp,
                tiles: [
                    {
                        id: 'follow-alerts',
                        icon: BellRing,
                        tone: 'warning',
                        label: d.alertsOpen,
                        value: dashboard.alerts_open,
                        detail: 'alerts',
                    },
                    {
                        id: 'follow-excuses',
                        icon: FileCheck2,
                        tone: 'rose',
                        label: d.excusesPending,
                        value: dashboard.excuses_pending,
                        detail: 'excuses',
                    },
                    ...(abilities.review_sheet
                        ? [
                              {
                                  id: 'follow-review',
                                  icon: ClipboardCheck,
                                  tone: 'amber' as const,
                                  label: a.nav.review,
                                  value: today.submitted + today.under_review,
                                  detail: null,
                                  href: '/attendance/review',
                              },
                          ]
                        : []),
                    ...(scope === 'central'
                        ? [
                              {
                                  id: 'follow-central',
                                  icon: Building2,
                                  tone: 'authority' as const,
                                  label: d.central,
                                  value: dashboard.central.length,
                                  detail: 'central' as const,
                              },
                          ]
                        : []),
                ],
            },
        ];

        return all.filter((row) => row.tiles.length > 0);
    }, [
        dashboard,
        abilities.review_sheet,
        scope,
        today,
        month,
        highRisk,
        a,
        d,
    ]);

    const openTile = (tile: Tile) =>
        tile.detail !== null
            ? setDetail(tile.detail)
            : tile.href
              ? router.visit(tile.href)
              : undefined;

    // «تحرير»: the scope, then one group per tile row — same icons, same order as the page.
    const editGroups = useMemo(
        (): PageRibbonGroup[] => [
            ...(can_central
                ? [
                      {
                          id: 'attendance-dashboard-scope',
                          label: d.scope,
                          commands: [
                              {
                                  id: 'attendance-dashboard-school',
                                  label: d.school,
                                  icon: School,
                                  iconTone: 'education' as const,
                                  pressed: scope === 'school',
                                  onSelect: () =>
                                      router.get('/attendance/dashboard'),
                              },
                              {
                                  id: 'attendance-dashboard-central',
                                  label: d.central,
                                  icon: Building2,
                                  iconTone: 'authority' as const,
                                  pressed: scope === 'central',
                                  onSelect: () =>
                                      router.get('/attendance/dashboard', {
                                          scope: 'central',
                                      }),
                              },
                          ],
                      },
                  ]
                : []),
            ...rows.map((row) => ({
                id: `attendance-dashboard-${row.id}`,
                label:
                    row.id === 'today'
                        ? d.today
                        : row.id === 'month'
                          ? d.last30
                          : row.label,
                commands: row.tiles.map((tile) => ({
                    id: `attendance-dashboard-${tile.id}`,
                    label: tile.label,
                    icon: tile.icon,
                    iconTone: tile.tone,
                    count:
                        typeof tile.value === 'number' ? tile.value : undefined,
                    title: `${tile.label}: ${tile.value}`,
                    onSelect: () => openTile(tile),
                })),
            })),
        ],
        // eslint-disable-next-line react-hooks/exhaustive-deps
        [rows, scope, can_central],
    );
    useAttendanceEditRibbon('dashboard', abilities, editGroups, {
        zoom: false,
    });
    const yearSpec = useYearFilterSpec(filters.academic_year_id, (year) =>
        visitWith('/attendance/dashboard', {
            academic_year_id: year,
            scope: scope === 'central' ? 'central' : null,
        }),
    );
    const goDate = (date: string) =>
        visitWith('/attendance/dashboard', {
            date,
            academic_year_id: filters.academic_year_id,
            scope: scope === 'central' ? 'central' : null,
        });
    useAttendanceHomeFilters(
        [
            yearSpec,
            {
                key: 'date',
                date: true,
                label: d.date,
                value: board.date,
                onChange: goDate,
            },
            {
                key: 'class',
                type: 'multi',
                label: a.chrome.class,
                allLabel: a.chrome.allClasses,
                values: classIds.map(String),
                options: board.classes.map((cls) => ({
                    value: String(cls.id),
                    label: cls.name,
                })),
                onChange: (values: string[]) => setClassIds(values.map(Number)),
            },
            {
                key: 'status',
                type: 'multi',
                label: d.sheetState,
                allLabel: a.chrome.allStatuses,
                values: statusIds.map(String),
                options: [
                    { value: '0', label: d.board.notOpened },
                    ...SHEET_STATUSES.map((st) => ({
                        value: String(st.value),
                        label: sheetLabel(st.value),
                    })),
                ],
                onChange: (values: string[]) =>
                    setStatusIds(values.map(Number)),
            },
        ],
        classIds.length > 0 || statusIds.length > 0
            ? () => {
                  setClassIds([]);
                  setStatusIds([]);
              }
            : null,
        [
            filters.academic_year_id,
            scope,
            yearSpec.options.length,
            board.date,
            board.classes,
            classIds,
            statusIds,
        ],
    );

    const studentReport = (studentId: number) =>
        router.visit(
            `/attendance/reports?type=student_detail&student_id=${studentId}`,
        );
    const absenteeColumns: AttendanceColumn<Absentee>[] = [
        {
            id: 'code',
            header: a.sheet.code,
            kind: 'text',
            cell: (s) => <span dir="ltr">{s.student_code}</span>,
        },
        {
            id: 'name',
            header: a.sheet.student,
            kind: 'name',
            cell: (s) => s.full_name,
        },
        {
            id: 'absent',
            header: a.sheet.absent,
            kind: 'num',
            cell: (s) => s.absent,
        },
        {
            id: 'excused',
            header: a.sheet.excused,
            kind: 'num',
            cell: (s) => s.excused,
        },
        {
            id: 'decided',
            header: d.records,
            kind: 'num',
            cell: (s) => s.decided,
        },
    ];
    const riskColumns: AttendanceColumn<RiskRow>[] = [
        {
            id: 'code',
            header: a.sheet.code,
            kind: 'text',
            cell: (s) => <span dir="ltr">{s.student_code}</span>,
        },
        {
            id: 'name',
            header: a.sheet.student,
            kind: 'name',
            cell: (s) => s.full_name,
        },
        {
            id: 'band',
            header: a.reports.columns.band,
            kind: 'status',
            tone: (s) => (s.band === 'high' ? 4 : s.band === 'medium' ? 2 : 1),
            cell: (s) => `${a.reports.bands[s.band] ?? s.band} (${s.score})`,
        },
        {
            id: 'rate',
            header: d.attendanceRate,
            kind: 'num',
            cell: (s) => pct(s.attendance_rate),
        },
        {
            id: 'reasons',
            header: a.reports.columns.reasons,
            kind: 'text',
            cell: (s) => reasonText(s.reasons),
        },
    ];
    const centralColumns: AttendanceColumn<CentralRow>[] = [
        {
            id: 'school',
            header: d.schoolColumn,
            kind: 'name',
            cell: (r) => r.school_name,
        },
        {
            id: 'rate',
            header: d.attendanceRate,
            kind: 'num',
            cell: (r) => pct(r.attendance_rate),
        },
        {
            id: 'absence',
            header: d.absenceRate,
            kind: 'num',
            cell: (r) => pct(r.absence_rate),
        },
        {
            id: 'late',
            header: d.lateRate,
            kind: 'num',
            cell: (r) => pct(r.late_rate),
        },
        {
            id: 'review',
            header: d.inReview,
            kind: 'num',
            cell: (r) => r.submitted + r.under_review,
        },
        {
            id: 'pending',
            header: d.pending,
            kind: 'num',
            cell: (r) => r.drafts + r.returned,
        },
        {
            id: 'decided',
            header: d.records,
            kind: 'num',
            cell: (r) => r.decided,
        },
    ];

    const totalsFacts = (x: Totals) => (
        <>
            <WindowFact
                label={d.attendanceRate}
                value={<span dir="ltr">{pct(x.attendance_rate)}</span>}
            />
            <WindowFact
                label={d.absenceRate}
                value={<span dir="ltr">{pct(x.absence_rate)}</span>}
            />
            <WindowFact
                label={d.lateRate}
                value={<span dir="ltr">{pct(x.late_rate)}</span>}
            />
            <WindowFact
                label={d.records}
                value={<span dir="ltr">{x.decided}</span>}
            />
            <WindowFact
                label={a.sheet.present}
                value={<span dir="ltr">{x.present}</span>}
            />
            <WindowFact
                label={a.sheet.absent}
                value={<span dir="ltr">{x.absent}</span>}
            />
            <WindowFact
                label={a.sheet.late}
                value={<span dir="ltr">{x.late}</span>}
            />
            <WindowFact
                label={a.sheet.excused}
                value={<span dir="ltr">{x.excused}</span>}
            />
            <WindowFact
                label={a.sheet.leftEarly}
                value={<span dir="ltr">{x.left_early}</span>}
            />
        </>
    );
    const sheetFacts = (x: Totals) => (
        <>
            <WindowFact
                label={d.sheets}
                value={<span dir="ltr">{x.sheets}</span>}
            />
            <WindowFact
                label={a.sheetStatus[1]}
                value={<span dir="ltr">{x.drafts}</span>}
            />
            <WindowFact
                label={a.sheetStatus[2]}
                value={<span dir="ltr">{x.submitted}</span>}
            />
            <WindowFact
                label={a.sheetStatus[3]}
                value={<span dir="ltr">{x.under_review}</span>}
            />
            <WindowFact
                label={a.sheetStatus[4]}
                value={<span dir="ltr">{x.approved}</span>}
            />
            <WindowFact
                label={a.sheetStatus[5]}
                value={<span dir="ltr">{x.returned}</span>}
            />
            <WindowFact
                label={a.sheetStatus[6]}
                value={<span dir="ltr">{x.rejected}</span>}
            />
            <WindowFact
                label={d.lateSubmissions}
                value={<span dir="ltr">{x.late_submissions}</span>}
            />
        </>
    );
    function listSection<T>(
        id: string,
        title: string,
        list: T[],
        key: (row: T) => number,
        columns: AttendanceColumn<T>[],
        onOpen?: (row: T) => void,
    ) {
        return {
            id,
            title: `${title} (${list.length})`,
            plain: true,
            content: (
                <div className="sis-branches-field--wide">
                    <AttendanceTable
                        caption={title}
                        storageKey={`dashboard.${id}`}
                        rows={list}
                        rowKey={key}
                        columns={columns}
                        emptyText={d.noData}
                        fit={false}
                        emptyAsDialog={false}
                        onOpen={onOpen}
                    />
                </div>
            ),
        };
    }

    const windows: Record<
        Detail,
        {
            title: string;
            sections: Array<{ id: string; title: string; content: ReactNode }>;
            action?: { label: string; href: string };
        }
    > = {
        today: {
            title: `${d.today} — ${dashboard.today}`,
            sections: [
                { id: 'd-today', title: d.today, content: totalsFacts(today) },
            ],
            action: {
                label: a.nav.reports,
                href: `/attendance/reports?type=daily&date_from=${dashboard.today}&date_to=${dashboard.today}`,
            },
        },
        month: {
            title: `${d.last30} — ${dashboard.period.from} → ${dashboard.period.to}`,
            sections: [
                { id: 'd-month', title: d.last30, content: totalsFacts(month) },
            ],
            action: {
                label: a.nav.reports,
                href: `/attendance/reports?type=daily&date_from=${dashboard.period.from}&date_to=${dashboard.period.to}`,
            },
        },
        sheets: {
            title: d.sheets,
            sections: [
                {
                    id: 'd-sheets-today',
                    title: d.today,
                    content: sheetFacts(today),
                },
                {
                    id: 'd-sheets-month',
                    title: d.last30,
                    content: sheetFacts(month),
                },
            ],
            action: abilities.review_sheet
                ? { label: a.nav.review, href: '/attendance/review' }
                : undefined,
        },
        absentees: {
            title: d.topAbsentees,
            sections: [
                listSection(
                    'absentees',
                    d.topAbsentees,
                    dashboard.top_absentees,
                    (s) => s.student_id,
                    absenteeColumns,
                    (s) => studentReport(s.student_id),
                ),
            ],
        },
        risk: {
            title: d.topRisk,
            sections: [
                listSection(
                    'risk',
                    d.topRisk,
                    dashboard.top_risk,
                    (s) => s.student_id,
                    riskColumns,
                    (s) => studentReport(s.student_id),
                ),
            ],
            action: {
                label: a.nav.reports,
                href: '/attendance/reports?type=risk',
            },
        },
        central: {
            title: d.central,
            sections: [
                listSection(
                    'central',
                    d.central,
                    dashboard.central,
                    (r) => r.school_id,
                    centralColumns,
                ),
            ],
        },
        alerts: {
            title: d.alertsOpen,
            sections: [
                {
                    id: 'd-alerts',
                    title: d.alertsOpen,
                    content: (
                        <WindowFact
                            wide
                            label={d.alertsOpen}
                            value={
                                <span dir="ltr">{dashboard.alerts_open}</span>
                            }
                        />
                    ),
                },
            ],
            action: abilities.manage_alerts
                ? { label: a.nav.alerts, href: '/attendance/alerts' }
                : undefined,
        },
        excuses: {
            title: d.excusesPending,
            sections: [
                {
                    id: 'd-excuses',
                    title: d.excusesPending,
                    content: (
                        <WindowFact
                            wide
                            label={d.excusesPending}
                            value={
                                <span dir="ltr">
                                    {dashboard.excuses_pending}
                                </span>
                            }
                        />
                    ),
                },
            ],
            action: abilities.review_excuse
                ? { label: a.nav.excuses, href: '/attendance/excuses' }
                : undefined,
        },
    };
    const open = detail === null ? null : windows[detail];

    return (
        <>
            <Head title={d.title} />
            <div
                className="sis-ops-hub sis-admission-page sis-students-page sis-enrollments-page flex h-full min-h-0 flex-col overflow-hidden"
                dir="rtl"
                lang="ar"
            >
                <DayBoard
                    board={board}
                    abilities={abilities}
                    yearId={filters.academic_year_id}
                    scope={scope}
                    totals={dashboard.day_totals ?? today}
                    classIds={classIds}
                    statusIds={statusIds}
                    query={query}
                />
            </div>

            {open !== null ? (
                <AttendanceWindow
                    title={open.title}
                    columns={2}
                    wide={
                        detail === 'absentees' ||
                        detail === 'risk' ||
                        detail === 'central'
                    }
                    onClose={() => setDetail(null)}
                    footer={
                        open.action ? (
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() =>
                                    router.visit(
                                        open.action?.href ?? '/attendance',
                                    )
                                }
                            >
                                {open.action.label}
                            </Button>
                        ) : null
                    }
                    sections={open.sections}
                />
            ) : null}
        </>
    );
}
