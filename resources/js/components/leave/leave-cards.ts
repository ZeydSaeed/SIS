import {
    Ban,
    CalendarClock,
    CalendarX2,
    ClipboardCheck,
    FilePen,
    Hourglass,
    MessageCircleQuestion,
    PlaneTakeoff,
    ShieldCheck,
    Undo2,
    UserX,
} from 'lucide-react';
import type { LeaveAbilities, LeaveBadges } from '@/components/leave/leave-chrome';
import type { OverviewCard } from '@/components/sis/module-overview';
import { t } from '@/i18n';

export type LeaveCounts = {
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

/** The leave cards (every one opens the request list it counts, with the same filters on the server) — shown on the overview. */
export function leaveDashboardCards(
    c: LeaveCounts,
    abilities: LeaveAbilities,
    badges: LeaveBadges,
    today: string,
    yearId: number,
): OverviewCard[] {
    const l = t().leave;
    const list = (query: Record<string, string | number | boolean>) => {
        const params = new URLSearchParams({
            academic_year_id: String(yearId),
        });
        Object.entries(query).forEach(([k, v]) => params.append(k, String(v)));

        return `/leave/requests?${params.toString()}`;
    };

    // Tones reuse the follow-up cards' palette.
    return [
        {
            key: 'pending',
            label: l.cards.pending,
            count: c.pending,
            icon: Hourglass,
            tone: 'under-review',
            href: list({ 'state[]': 2 }),
        },
        ...(abilities.approve
            ? [
                  {
                      key: 'awaiting',
                      label: l.awaitingRail,
                      count: badges.awaiting,
                      icon: ClipboardCheck,
                      tone: 'under-review',
                      href: list({ awaiting: 1 }),
                  },
              ]
            : [
                  {
                      key: 'new',
                      label: l.cards.newRequests,
                      count: c.new_requests,
                      icon: PlaneTakeoff,
                      tone: 'submitted',
                      href: list({ 'state[]': 2, sort: 'submitted' }),
                  },
              ]),
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
}
