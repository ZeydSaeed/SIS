import { PlusCircle } from 'lucide-react';
import { useMemo } from 'react';
import {
    AttendanceTable,
    useAttendanceHomeFilters,
    useYearFilterSpec,
    visitWith,
    type AttendanceColumn,
} from '@/components/attendance/attendance-chrome';
import { StatusLabel } from '@/components/attendance/attendance-ui';
import {
    LeaveSectionTitle,
    leaveStatusMeta,
    periodText,
    quantityText,
    typeText,
    LeavePage,
    useLeaveEditRibbon,
    type LeaveAbilities,
    type LeaveBadges,
    type LeaveRequestRow,
} from '@/components/leave/leave-chrome';
import { type LeaveCounts } from '@/components/leave/leave-cards';
import type { PageRibbonGroup } from '@/components/sis/page-ribbon-context';
import { t } from '@/i18n';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

type UncoveredLesson = {
    schedule_id: number;
    teacher_name: string;
    period_number: number;
    start_time: string;
    end_time: string;
    class_name: string;
    section_name: string;
    subject_name: string;
};

type Props = {
    dashboard: {
        counts: LeaveCounts;
        away_today: LeaveRequestRow[];
        upcoming: LeaveRequestRow[];
        uncovered_today: UncoveredLesson[];
    };
    today: string;
    filters: { academic_year_id: number };
    abilities: LeaveAbilities;
    badges: LeaveBadges;
};

export default function LeaveDashboard(props: Props) {
    const l = t().leave;
    const breadcrumbs: BreadcrumbItem[] = [{ title: l.title, href: '/leave' }];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <DashboardPage {...props} />
        </AppLayout>
    );
}

function DashboardPage({
    dashboard,
    filters,
    abilities,
}: Props) {
    const l = t().leave;
    const groups = useMemo(
        (): PageRibbonGroup[] =>
            abilities.request || abilities.manage
                ? [
                      {
                          id: 'leave-dashboard-actions',
                          label: l.actions,
                          commands: [
                              {
                                  id: 'leave-dashboard-add',
                                  label: l.add,
                                  icon: PlusCircle,
                                  iconTone: 'ok',
                                  onSelect: () =>
                                      visitWith('/leave/requests', {
                                          academic_year_id:
                                              filters.academic_year_id,
                                      }),
                              },
                          ],
                      },
                  ]
                : [],
        // eslint-disable-next-line react-hooks/exhaustive-deps
        [abilities, filters],
    );
    useLeaveEditRibbon(groups);
    const yearSpec = useYearFilterSpec(filters.academic_year_id, (year) =>
        visitWith('/leave', { academic_year_id: year }),
    );
    useAttendanceHomeFilters([yearSpec], null, [
        filters,
        yearSpec.options.length,
    ]);

    const leaveColumns: AttendanceColumn<LeaveRequestRow>[] = [
        {
            id: 'person',
            header: l.person,
            kind: 'name',
            cell: (r) => r.person_name ?? '—',
        },
        {
            id: 'kind',
            header: l.kind,
            kind: 'text',
            cell: (r) => l.kinds[r.requester_kind],
        },
        { id: 'type', header: l.type, kind: 'text', cell: (r) => typeText(r) },
        {
            id: 'period',
            header: l.period,
            kind: 'text',
            cell: (r) => (
                <span className="sis-leave-period" dir="ltr">
                    {periodText(r.date_from, r.date_to)}
                </span>
            ),
        },
        {
            id: 'quantity',
            header: l.quantity,
            kind: 'text',
            cell: (r) => quantityText(r.quantity, r.unit),
        },
        {
            id: 'status',
            header: l.status,
            kind: 'status',
            tone: (r) => leaveStatusMeta(r.status)?.tone ?? 0,
            cell: (r) => (
                <StatusLabel
                    meta={leaveStatusMeta(r.status)}
                    label={l.statuses[r.status]}
                />
            ),
            text: (r) => l.statuses[r.status],
        },
    ];

    return (
        <LeavePage title={l.title} current={'dashboard'} abilities={abilities}>
            {abilities.view ? (
                <>
                    <LeaveSectionTitle
                        title={l.uncoveredToday}
                        count={dashboard.uncovered_today.length}
                    />
                    <AttendanceTable
                        caption={l.uncoveredToday}
                        storageKey="leave.dashboard.uncovered"
                        rows={dashboard.uncovered_today}
                        rowKey={(x) => x.schedule_id}
                        fit={false}
                        emptyText={l.nothing}
                        columns={[
                            {
                                id: 'teacher',
                                header: l.person,
                                kind: 'name',
                                cell: (x) => x.teacher_name,
                            },
                            {
                                id: 'period',
                                header: l.coveragePeriod,
                                kind: 'text',
                                cell: (x) => (
                                    <span dir="ltr">{`${x.period_number} · ${x.start_time}–${x.end_time}`}</span>
                                ),
                            },
                            {
                                id: 'section',
                                header: l.coverageSection,
                                kind: 'text',
                                cell: (x) =>
                                    `${x.class_name} / ${x.section_name}`,
                            },
                            {
                                id: 'subject',
                                header: l.coverageSubject,
                                kind: 'text',
                                cell: (x) => x.subject_name,
                            },
                        ]}
                    />
                </>
            ) : null}
            <LeaveSectionTitle
                title={l.awayToday}
                count={dashboard.away_today.length}
            />
            <AttendanceTable
                caption={l.awayToday}
                storageKey="leave.dashboard.away"
                rows={dashboard.away_today}
                rowKey={(r) => r.id}
                columns={leaveColumns}
                fit={false}
                emptyText={l.nothing}
            />
            <LeaveSectionTitle
                title={l.upcoming}
                count={dashboard.upcoming.length}
            />
            <AttendanceTable
                caption={l.upcoming}
                storageKey="leave.dashboard.upcoming"
                rows={dashboard.upcoming}
                rowKey={(r) => r.id}
                columns={leaveColumns}
                fit={false}
                emptyText={l.nothing}
            />
        </LeavePage>
    );
}
