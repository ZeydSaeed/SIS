import { Head, Link } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { getSisModuleNavItems } from '@/nav/sis-module-nav';

/** One icon fill color per dashboard row (5 columns). */
const ROW_ICON_COLORS = [
    '#809BCE',
    '#95B8D1',
    '#B8E0D2',
    '#D6EADF',
    '#EAC4D5',
] as const;

const GRID_COLUMNS = 5;

/**
 * Card background rotates from icon colors:
 * last row icon color → first row card, first → second, …
 */
function cardBackgroundForRow(row: number): string {
    const n = ROW_ICON_COLORS.length;
    return ROW_ICON_COLORS[(row - 1 + n) % n];
}

export default function Dashboard() {
    const tiles = getSisModuleNavItems().filter((item) => !item.sidebarOnly);

    return (
        <AppLayout breadcrumbs={[]}>
            <Head title="Student Information System" />
            <div className="sis-dashboard sis-dashboard-launch" lang="ar" dir="rtl">
                <ul className="sis-dashboard-launch__grid" aria-label="وحدات النظام">
                    {tiles.map((item, index) => {
                        const Icon = item.icon;
                        const row = Math.floor(index / GRID_COLUMNS);
                        const iconFill =
                            ROW_ICON_COLORS[row % ROW_ICON_COLORS.length];
                        const cardFill = cardBackgroundForRow(row);

                        return (
                            <li key={item.href}>
                                <Link
                                    href={item.href}
                                    prefetch
                                    className="sis-dashboard-launch__tile"
                                    style={{ backgroundColor: cardFill }}
                                >
                                    <span
                                        className="sis-dashboard-launch__icon"
                                        style={{ backgroundColor: iconFill }}
                                        aria-hidden
                                    >
                                        <Icon className="size-7" strokeWidth={1.75} />
                                    </span>
                                    <span className="sis-dashboard-launch__label">
                                        {item.title}
                                    </span>
                                </Link>
                            </li>
                        );
                    })}
                </ul>
            </div>
        </AppLayout>
    );
}
