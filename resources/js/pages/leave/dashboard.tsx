import { Link } from '@inertiajs/react';
import {
    Ban,
    CalendarClock,
    CalendarX2,
    FilePen,
    Hourglass,
    MessageCircleQuestion,
    PlaneTakeoff,
    PlusCircle,
    ShieldCheck,
    Undo2,
    UserX,
    type LucideIcon,
} from 'lucide-react';
import { useMemo } from 'react';
import {
    AttendancePage,
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
    useLeaveEditRibbon,
    type LeaveAbilities,
    type LeaveRequestRow,
} from '@/components/leave/leave-chrome';
import type { PageRibbonGroup } from '@/components/sis/page-ribbon-context';
import { t } from '@/i18n';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

type Counts = {
    drafts: number;
    pending: number;
    info_required: number;
    approved: number;
    rejected: number;
    closed: number;
    new_requests: number;
    away_today: number;
    teachers_away_today: number;
    starting_soon: number;
};

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
        counts: Counts;
        away_today: LeaveRequestRow[];
        upcoming: LeaveRequestRow[];
        uncovered_today: UncoveredLesson[];
    };
    today: string;
    filters: { academic_year_id: number };
    abilities: LeaveAbilities;
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

function DashboardPage({ dashboard, today, filters, abilities }: Props) {
    const l = t().leave;
    const c = dashboard.counts;
    const list = (query: Record<string, string | number | boolean>) => {
        const params = new URLSearchParams({
            academic_year_id: String(filters.academic_year_id),
        });
        Object.entries(query).forEach(([k, v]) => params.append(k, String(v)));

        return `/leave/requests?${params.toString()}`;
    };

    // Every card opens the list it counts (same filters on the server). Tones reuse the follow-up cards' palette.
    const cards: Array<{
        key: string;
        label: string;
        count: number;
        icon: LucideIcon;
        tone: string;
        href: string;
    }> = [
        {
            key: 'pending',
            label: l.cards.pending,
            count: c.pending,
            icon: Hourglass,
            tone: 'under-review',
            href: list({ 'state[]': 2 }),
        },
        {
            key: 'new',
            label: l.cards.newRequests,
            count: c.new_requests,
            icon: PlaneTakeoff,
            tone: 'submitted',
            href: list({ 'state[]': 2, sort: 'submitted' }),
        },
        {
            key: 'info',
            label: l.cards.infoRequired,
            count: c.info_required,
            icon: MessageCircleQuestion,
            tone: 'waitlisted',
            href: list({ 'state[]': 3 }),
        },
        {
            key: 'approved',
            label: l.cards.approved,
            count: c.approved,
            icon: ShieldCheck,
            tone: 'vocational',
            href: list({ 'state[]': 4 }),
        },
        {
            key: 'away',
            label: l.cards.awayToday,
            count: c.away_today,
            icon: UserX,
            tone: 'interview',
            href: list({ 'state[]': 4, from: today, to: today }),
        },
        {
            key: 'teachers',
            label: l.cards.teachersAway,
            count: c.teachers_away_today,
            icon: CalendarX2,
            tone: 'transfer',
            href: list({ 'state[]': 4, from: today, to: today, kind: 1 }),
        },
        {
            key: 'soon',
            label: l.cards.startingSoon,
            count: c.starting_soon,
            icon: CalendarClock,
            tone: 'submitted',
            href: list({ from: today }),
        },
        {
            key: 'rejected',
            label: l.cards.rejected,
            count: c.rejected,
            icon: Ban,
            tone: 'rejected',
            href: list({ 'state[]': 5 }),
        },
        {
            key: 'closed',
            label: l.cards.closed,
            count: c.closed,
            icon: Undo2,
            tone: 'withdrawn',
            href: list({ 'state[]': 6 }),
        },
        {
            key: 'drafts',
            label: l.cards.drafts,
            count: c.drafts,
            icon: FilePen,
            tone: 'withdrawn',
            href: list({ 'state[]': 1 }),
        },
    ];

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
    useLeaveEditRibbon('dashboard', abilities, groups);
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
                <span dir="ltr">{periodText(r.date_from, r.date_to)}</span>
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
        <AttendancePage title={l.title}>
            <div
                className="sis-follow-up-cards"
                role="list"
                aria-label={l.dashboard}
            >
                {cards.map((card) => (
                    <Link
                        key={card.key}
                        href={card.href}
                        role="listitem"
                        className={`sis-follow-up-card sis-follow-up-tone--${card.tone}`}
                        aria-label={`${card.label}: ${card.count}`}
                    >
                        <span
                            className="sis-follow-up-card__avatar"
                            aria-hidden
                        >
                            <card.icon className="size-5" strokeWidth={2} />
                        </span>
                        <span className="sis-follow-up-card__label">
                            {card.label}
                        </span>
                        <span className="sis-follow-up-card__count" dir="ltr">
                            {card.count}
                        </span>
                    </Link>
                ))}
            </div>

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
        </AttendancePage>
    );
}
