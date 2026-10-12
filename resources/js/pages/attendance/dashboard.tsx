import { router } from '@inertiajs/react';
import { Building2, School } from 'lucide-react';
import { useMemo } from 'react';
import {
    AttendancePage,
    AttendanceTable,
    PageNotice,
    useAttendanceEditRibbon,
    useAttendanceHomeFilters,
    useYearFilterSpec,
    visitWith,
    type AttendanceColumn,
} from '@/components/attendance/attendance-chrome';
import type { AttendanceAbilities } from '@/components/attendance/attendance-ui';
import type { PageRibbonGroup } from '@/components/sis/page-ribbon-context';
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

type Props = {
    dashboard: {
        today: string;
        period: { from: string; to: string };
        formula: string;
        today_totals: Totals;
        month_totals: Totals;
        top_absentees: Array<{
            student_id: number;
            full_name: string;
            student_code: string;
            absent: number;
            excused: number;
            decided: number;
        }>;
        top_risk: RiskRow[];
        risk_method: string;
        alerts_open: number;
        excuses_pending: number;
        central: CentralRow[];
    };
    scope: 'school' | 'central';
    can_central: boolean;
    filters: { academic_year_id: number };
    abilities: AttendanceAbilities;
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

/** One indicator tile — the hub tile look used on the SIS home pages. */
function Kpi({
    label,
    value,
    hint,
}: {
    label: string;
    value: string | number;
    hint?: string;
}) {
    return (
        <div className="sis-ops-hub__link" role="group" aria-label={label}>
            <span className="sis-ops-hub__link-title">{label}</span>
            <span className="text-2xl font-semibold" dir="ltr">
                {value}
            </span>
            {hint ? <span className="text-xs opacity-80">{hint}</span> : null}
        </div>
    );
}

/** «مرتفع ≥ 60 …» → explained reasons («نسبة الغياب 23%» …). */
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
    scope,
    can_central,
    filters,
    abilities,
}: Props) {
    const a = t().attendanceSystem;
    const d = a.dashboard;
    const today = dashboard.today_totals;
    const month = dashboard.month_totals;

    const editGroups = useMemo(
        (): PageRibbonGroup[] =>
            can_central
                ? [
                      {
                          id: 'attendance-dashboard-scope',
                          label: d.scope,
                          commands: [
                              {
                                  id: 'attendance-dashboard-school',
                                  label: d.school,
                                  icon: School,
                                  iconTone: 'education',
                                  pressed: scope === 'school',
                                  onSelect: () =>
                                      router.get('/attendance/dashboard'),
                              },
                              {
                                  id: 'attendance-dashboard-central',
                                  label: d.central,
                                  icon: Building2,
                                  iconTone: 'authority',
                                  pressed: scope === 'central',
                                  onSelect: () =>
                                      router.get('/attendance/dashboard', {
                                          scope: 'central',
                                      }),
                              },
                          ],
                      },
                  ]
                : [],
        // eslint-disable-next-line react-hooks/exhaustive-deps
        [scope, can_central],
    );
    useAttendanceEditRibbon('dashboard', abilities, editGroups);
    const yearSpec = useYearFilterSpec(filters.academic_year_id, (year) =>
        visitWith('/attendance/dashboard', {
            academic_year_id: year,
            scope: scope === 'central' ? 'central' : null,
        }),
    );
    useAttendanceHomeFilters([yearSpec], null, [
        filters.academic_year_id,
        scope,
        yearSpec.options.length,
    ]);

    const absenteeColumns: AttendanceColumn<
        Props['dashboard']['top_absentees'][number]
    >[] = [
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

    return (
        <AttendancePage
            title={d.title}
            scroll
            notices={
                <PageNotice>{`${d.formula}: ${dashboard.formula}`}</PageNotice>
            }
        >
            <p className="sis-branches-page__notice">
                {d.today} <span dir="ltr">{dashboard.today}</span>
            </p>
            <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
                <Kpi
                    label={d.attendanceRate}
                    value={pct(today.attendance_rate)}
                    hint={`${d.records}: ${today.decided}`}
                />
                <Kpi
                    label={a.sheet.absent}
                    value={today.absent + today.excused}
                />
                <Kpi label={a.sheet.late} value={today.late} />
                <Kpi label={d.pending} value={today.drafts + today.returned} />
                <Kpi
                    label={d.inReview}
                    value={today.submitted + today.under_review}
                />
                <Kpi label={d.approved} value={today.approved} />
            </div>
            <p className="sis-branches-page__notice mt-3">
                {d.last30} —{' '}
                <span dir="ltr">{`${dashboard.period.from} → ${dashboard.period.to}`}</span>
            </p>
            <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
                <Kpi
                    label={d.attendanceRate}
                    value={pct(month.attendance_rate)}
                />
                <Kpi label={d.absenceRate} value={pct(month.absence_rate)} />
                <Kpi label={d.lateRate} value={pct(month.late_rate)} />
                <Kpi label={d.returned} value={month.returned} />
                <Kpi label={d.lateSubmissions} value={month.late_submissions} />
                <Kpi
                    label={d.alertsOpen}
                    value={dashboard.alerts_open}
                    hint={`${d.excusesPending}: ${dashboard.excuses_pending}`}
                />
            </div>

            {scope === 'central' ? (
                <>
                    <p className="sis-branches-page__notice mt-3">
                        {d.central}
                    </p>
                    <AttendanceTable
                        caption={d.central}
                        rows={dashboard.central}
                        rowKey={(r) => r.school_id}
                        columns={centralColumns}
                        emptyText={d.noData}
                    />
                </>
            ) : null}

            <p className="sis-branches-page__notice mt-3">{d.topAbsentees}</p>
            <AttendanceTable
                caption={d.topAbsentees}
                rows={dashboard.top_absentees}
                rowKey={(s) => s.student_id}
                columns={absenteeColumns}
                emptyText={d.noData}
                onOpen={(s) =>
                    router.visit(
                        `/attendance/reports?type=student_detail&student_id=${s.student_id}`,
                    )
                }
            />
            <p className="sis-branches-page__notice mt-3">{`${d.topRisk} — ${dashboard.risk_method}`}</p>
            <AttendanceTable
                caption={d.topRisk}
                rows={dashboard.top_risk}
                rowKey={(s) => s.student_id}
                columns={riskColumns}
                emptyText={d.noData}
                onOpen={(s) =>
                    router.visit(
                        `/attendance/reports?type=student_detail&student_id=${s.student_id}`,
                    )
                }
            />
        </AttendancePage>
    );
}
