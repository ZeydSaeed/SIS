import { Head, Link } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import AppLayout from '@/layouts/app-layout';
import { useRegisterPageTitlebarSearch } from '@/components/sis/page-titlebar-search-context';
import { getSisModuleNavItems, type SisModuleNavItem } from '@/nav/sis-module-nav';
import { t } from '@/i18n';

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

function normalizeSearch(value: string): string {
    return value.trim().toLocaleLowerCase('ar');
}

function tileMatchesQuery(item: SisModuleNavItem, query: string): boolean {
    if (query === '') {
        return true;
    }

    const needle = normalizeSearch(query);

    return (
        normalizeSearch(item.title).includes(needle) ||
        normalizeSearch(item.href).includes(needle)
    );
}

/** Must render inside AppLayout so titlebar search context is an ancestor. */
function DashboardWorkspace() {
    const i18n = t();
    const [searchQuery, setSearchQuery] = useState('');
    const allTiles = useMemo(
        () => getSisModuleNavItems().filter((item) => !item.sidebarOnly),
        [],
    );

    const titlebarSearch = useMemo(
        () => ({
            committedQuery: searchQuery,
            label: i18n.dashboard.searchAria,
            placeholder: i18n.dashboard.search,
            onDraftChange: (query: string) => {
                setSearchQuery(query);
            },
            onCommit: (query: string) => {
                setSearchQuery(query);
            },
        }),
        [i18n.dashboard.search, i18n.dashboard.searchAria, searchQuery],
    );

    useRegisterPageTitlebarSearch(titlebarSearch);

    const tiles = useMemo(
        () => allTiles.filter((item) => tileMatchesQuery(item, searchQuery)),
        [allTiles, searchQuery],
    );

    return (
        <div className="sis-dashboard sis-dashboard-launch" lang="ar" dir="rtl">
            {tiles.length === 0 ? (
                <p className="sis-dashboard-launch__empty" role="status">
                    {i18n.dashboard.emptySearch}
                </p>
            ) : (
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
            )}
        </div>
    );
}

export default function Dashboard() {
    return (
        <AppLayout breadcrumbs={[]}>
            <Head title="Student Information System" />
            <DashboardWorkspace />
        </AppLayout>
    );
}
