import { leaveDashboardCards, type LeaveCounts } from '@/components/leave/leave-cards';
import { type LeaveBadges, type LeaveAbilities } from '@/components/leave/leave-chrome';
import { ModuleOverview } from '@/components/sis/module-overview';
import { t } from '@/i18n';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

type Props = {
    badges: LeaveBadges;
    dashboard: { counts: LeaveCounts };
    today: string;
    filters: { academic_year_id: number };
    abilities: LeaveAbilities;
};

/** The categories of the bar and legend: the request states the cards count, as shares of one whole. */
const STATE_KEYS = ['pending', 'info', 'approved', 'rejected', 'closed', 'drafts'];

/** «الإجازات والعطل» overview, the first page of the module: the request cards (every one opens the list it counts). */
export default function LeaveOverview({ badges, dashboard, today, filters, abilities }: Props) {
    const l = t().leave;
    const breadcrumbs: BreadcrumbItem[] = [{ title: l.title, href: '/leave' }];
    const cards = leaveDashboardCards(dashboard.counts, abilities, badges, today, filters.academic_year_id);
    const segments = cards
        .filter((card) => STATE_KEYS.includes(card.key) && typeof card.count === 'number')
        .map((card) => ({ key: card.key, label: card.label, count: card.count as number, tone: card.tone }));

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <ModuleOverview
                title={l.title}
                listPath="/leave/requests"
                cards={cards}
                allLabel={l.requests}
                segments={segments}
            />
        </AppLayout>
    );
}
