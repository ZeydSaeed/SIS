import { Head, Link, usePage } from '@inertiajs/react';
import { ListChecks, type LucideIcon } from 'lucide-react';
import { useMemo } from 'react';
import { sisListPageClass } from '@/components/sis/page-theme';
import { useRegisterPageTitlebarHome } from '@/components/sis/page-titlebar-home-context';
import { useRegisterPageTitlebarSearch } from '@/components/sis/page-titlebar-search-context';
import { t } from '@/i18n';

/** One card of a module's overview: the page's own category (icon, name, count) and where pressing it goes. */
export type OverviewCard = {
    key: string;
    label: string;
    icon: LucideIcon;
    /** Absent for a section that has no count of its own. */
    count?: number | string;
    /** A `sis-follow-up-tone--*` name (the follow-up overview's colours). */
    tone: string;
    href: string;
};

/** Overview paths end with `/overview` (e.g. `/students/overview`); the list keeps the module's own path. */
export const OVERVIEW_SUFFIX = '/overview';

export function overviewPath(listPath: string): string {
    return `${listPath}${OVERVIEW_SUFFIX}`;
}

/** True when the visited page is a module's overview. */
export function useIsOverview(): boolean {
    const { url } = usePage();

    return (url.split('?')[0] ?? url).endsWith(OVERVIEW_SUFFIX);
}

/** On a module's list page: the title bar's home button goes back to the module's overview. */
export function useOverviewHome(listPath: string, enabled = true): void {
    const label = t().overview.back;

    useRegisterPageTitlebarHome(
        useMemo(
            () => (enabled ? { href: overviewPath(listPath), ariaLabel: label } : null),
            [enabled, label, listPath],
        ),
    );
}

const percent = new Intl.NumberFormat('ar', {
    style: 'percent',
    maximumFractionDigits: 0,
    numberingSystem: 'latn',
});

/**
 * The overview that opens every module — laid out exactly like «متابعة طلبات التقديم»: one segmented bar with each category's
 * share, its legend and the «all» link at the start, and a card per category that opens the module's page on it. The cards'
 * content comes from the module's own page (its status tabs or its side rail). Styling is the follow-up overview's, once.
 */
export function ModuleOverview({
    title,
    listPath,
    cards,
    allLabel,
    withBar = true,
    segments,
}: {
    title: string;
    /** The module's list page: «all» and the search open it. */
    listPath: string;
    /** A card with key `all` is not drawn as a card: it feeds the «all» pill. */
    cards: OverviewCard[];
    /** When set, a pill opens the whole list (counted from the card with key `all`, when present). */
    allLabel?: string;
    /** The share bar and legend (categories that add up to one whole). Off for a module made of sections. */
    withBar?: boolean;
    /** The bar and legend's shares when they differ from the cards (a module made of sections, e.g. the day's attendance). */
    segments?: Array<{ key: string; label: string; count: number; tone: string }>;
}) {
    const text = t().overview;
    const all = cards.find((card) => card.key === 'all');
    const drawn = cards.filter((card) => card.key !== 'all');
    const parts = (segments ?? drawn.map((card) => ({ key: card.key, label: card.label, count: card.count, tone: card.tone }))).filter(
        (part): part is { key: string; label: string; count: number; tone: string } => typeof part.count === 'number',
    );
    // A module made of sections keeps its legend even when nothing was recorded; categories hide when empty.
    const shown = segments === undefined ? parts.filter((card) => card.count > 0) : parts;
    const total = parts.reduce((sum, card) => sum + card.count, 0);
    const showBar = withBar && (segments !== undefined || total > 0);
    const share = (count: number) => (total > 0 ? count / total : 0);

    useRegisterPageTitlebarSearch(
        useMemo(
            () => ({
                committedQuery: '',
                label: title,
                placeholder: t().chrome.search,
                onDraftChange: () => undefined,
                onCommit: (next: string) => {
                    window.location.assign(
                        `${listPath}?q=${encodeURIComponent(next.trim())}`,
                    );
                },
            }),
            [listPath, title],
        ),
    );

    return (
        <>
            <Head title={title} />
            <div
                className={sisListPageClass({ extra: 'sis-follow-up-page' })}
                dir="rtl"
                lang="ar"
            >
                <div className="sis-follow-up-overview">
                    <h1 className="sr-only">{title}</h1>
                    <section
                        aria-label={title}
                        className="sis-follow-up-overview__stage"
                    >
                        <div className="sis-follow-up-overview__inner">
                            {showBar ? (
                                <div
                                    className="sis-follow-up-bar"
                                    role="img"
                                    aria-label={title}
                                >
                                    {total === 0 ? (
                                        <span
                                            className="sis-follow-up-bar__segment sis-follow-up-tone--withdrawn"
                                            style={{
                                                flexGrow: 1,
                                                flexBasis: 0,
                                                opacity: 0.35,
                                            }}
                                        />
                                    ) : null}
                                    {shown
                                        .filter((card) => card.count > 0)
                                        .map((card) => (
                                        <span
                                            key={card.key}
                                            className={`sis-follow-up-bar__segment sis-follow-up-tone--${card.tone}`}
                                            style={{
                                                flexGrow: card.count,
                                                flexBasis: 0,
                                            }}
                                            title={`${card.label} · ${card.count} (${percent.format(share(card.count))})`}
                                        />
                                    ))}
                                </div>
                            ) : null}

                            <div className="sis-follow-up-overview__row">
                                <div className="sis-follow-up-overview__side">
                                    {showBar ? (
                                        <ul className="sis-follow-up-legend">
                                            {shown.map((card) => (
                                                <li
                                                    key={card.key}
                                                    className="sis-follow-up-legend__item"
                                                >
                                                    <span
                                                        className={`sis-follow-up-legend__dot sis-follow-up-tone--${card.tone}`}
                                                        aria-hidden
                                                    />
                                                    <span className="sis-follow-up-legend__label">
                                                        {card.label}
                                                    </span>
                                                    <span
                                                        className="sis-follow-up-legend__share"
                                                        dir="ltr"
                                                    >
                                                        {percent.format(
                                                            share(card.count),
                                                        )}
                                                    </span>
                                                </li>
                                            ))}
                                        </ul>
                                    ) : null}
                                    {allLabel !== undefined ? (
                                        <Link
                                            href={all?.href ?? listPath}
                                            className="sis-follow-up-all"
                                        >
                                            <ListChecks
                                                className="size-4"
                                                aria-hidden
                                            />
                                            <span>{allLabel}</span>
                                            {all !== undefined ? (
                                                <span
                                                    className="sis-follow-up-all__count"
                                                    dir="ltr"
                                                >
                                                    {all.count}
                                                </span>
                                            ) : null}
                                        </Link>
                                    ) : null}
                                </div>

                                <div className="sis-follow-up-cards">
                                    {drawn.map((card) => (
                                        <Link
                                            key={card.key}
                                            href={card.href}
                                            className={`sis-follow-up-card sis-follow-up-tone--${card.tone}`}
                                            aria-label={`${card.label}${card.count !== undefined ? `: ${card.count}` : ''} — ${text.open}`}
                                        >
                                            <span
                                                className="sis-follow-up-card__avatar"
                                                aria-hidden
                                            >
                                                <card.icon
                                                    className="size-5"
                                                    strokeWidth={2}
                                                />
                                            </span>
                                            <span className="sis-follow-up-card__label">
                                                {card.label}
                                            </span>
                                            {card.count !== undefined ? (
                                                <span
                                                    className="sis-follow-up-card__count"
                                                    dir="ltr"
                                                >
                                                    {card.count}
                                                </span>
                                            ) : null}
                                        </Link>
                                    ))}
                                </div>
                            </div>
                        </div>
                    </section>
                </div>
            </div>
        </>
    );
}
