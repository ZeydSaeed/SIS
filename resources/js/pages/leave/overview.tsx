import { usePage } from '@inertiajs/react';
import {
    leaveRailItems,
    type LeaveBadges,
} from '@/components/leave/leave-chrome';
import {
    ModuleOverview,
    type OverviewCard,
} from '@/components/sis/module-overview';
import { t } from '@/i18n';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

type Props = {
    badges: LeaveBadges;
    abilities: Parameters<typeof leaveRailItems>[0];
};

const TONES = [
    'interview',
    'submitted',
    'under-review',
    'transfer',
    'waitlisted',
    'rejected',
    'withdrawn',
];

/** «الإجازات والغياب» overview: a card per section of the page's side rail; a count where the rail shows one. */
export default function LeaveOverview({ badges, abilities }: Props) {
    const l = t().leave;
    const totals = usePage<{
        leaveShell?: { totals: { staff: number; away: number } } | null;
    }>().props.leaveShell?.totals;
    // The bar and legend: today's staff on approved leave against those on duty (the side panel's leave-rate card).
    const segments =
        totals === undefined
            ? []
            : [
                  { key: 'away', label: t().shellPanels.onLeave, count: totals.away, tone: 'waitlisted' },
                  { key: 'on-duty', label: t().overview.onDuty, count: Math.max(0, totals.staff - totals.away), tone: 'transfer' },
              ];
    const breadcrumbs: BreadcrumbItem[] = [{ title: l.title, href: '/leave' }];
    const cards: OverviewCard[] = leaveRailItems(abilities, badges)
        .filter((item) => item.show)
        .map((item, index) => ({
            key: item.key,
            label: item.label,
            icon: item.icon,
            count: item.count,
            tone: TONES[index % TONES.length],
            href: item.href,
        }));

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <ModuleOverview
                title={l.title}
                listPath="/leave"
                cards={cards}
                allLabel={l.requests}
                segments={segments}
            />
        </AppLayout>
    );
}
