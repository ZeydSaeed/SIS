import { Link } from '@inertiajs/react';
import { ListChecks } from 'lucide-react';
import { useMemo } from 'react';
import {
    categoryToken,
    FOLLOW_UP_LIST_PATH,
    FOLLOW_UP_PATH,
    followUpCategories,
    followUpHref,
    useFollowUpQuery,
    useFollowUpRoster,
    type FollowUpCategory,
} from '@/components/admission/follow-up/follow-up-data';
import { useFollowUpFiltersRibbon } from '@/components/admission/follow-up/follow-up-filters-ribbon';
import type { AdmissionAcceptedStudent } from '@/components/admission/admission-workspace';
import { useRegisterPageTitlebarSearch } from '@/components/sis/page-titlebar-search-context';
import { t } from '@/i18n';

type Props = {
    students: AdmissionAcceptedStudent[];
    schools: Array<{ id: number; name: string }>;
    defaultSchoolId: number | null;
    defaultYearId: number | null;
};

type Segment = { category: FollowUpCategory; count: number };

const percent = new Intl.NumberFormat('ar', {
    style: 'percent',
    maximumFractionDigits: 0,
    numberingSystem: 'latn',
});

/**
 * «متابعة طلبات التقديم» — laid out like the EXAMIS staff dashboard: one segmented bar with the share of every
 * category, its legend at the start, and a card per category (icon, name, count) that opens the category's list.
 * A colour never carries meaning alone: every segment and card also has a name, and every card an icon.
 */
export function FollowUpOverview({
    students,
    schools,
    defaultSchoolId,
    defaultYearId,
}: Props) {
    const a = t().admission;
    const categories = useMemo(() => followUpCategories(), []);
    const { query, visit } = useFollowUpQuery(FOLLOW_UP_PATH);
    const { rows, schoolIds, periodIds, yearId, options } = useFollowUpRoster(
        students,
        query,
        schools,
        defaultSchoolId,
        defaultYearId,
    );

    useFollowUpFiltersRibbon({
        query,
        visit,
        options,
        schoolIds,
        periodIds,
        yearId,
        categories,
    });

    useRegisterPageTitlebarSearch(
        useMemo(
            () => ({
                committedQuery: query.q,
                label: a.acceptedStudentsDialogTitle,
                placeholder: t().chrome.search,
                onDraftChange: () => undefined,
                // A search lists what it finds.
                onCommit: (next: string) =>
                    visit({ q: next.trim() }, FOLLOW_UP_LIST_PATH),
            }),
            [a.acceptedStudentsDialogTitle, query.q, visit],
        ),
    );

    const segments: Segment[] = categories.map((category) => ({
        category,
        count: rows.filter(category.matches).length,
    }));
    const total = segments.reduce((sum, segment) => sum + segment.count, 0);
    const shown = segments.filter((segment) => segment.count > 0);
    const listHref = (category: FollowUpCategory['key'] | '') =>
        followUpHref(FOLLOW_UP_LIST_PATH, { ...query, category, page: 1 });

    return (
        <div className="sis-follow-up-overview">
            <h1 className="sr-only">{a.acceptedStudentsDialogTitle}</h1>
            <section
                aria-label={a.followUpOverview}
                className="sis-follow-up-overview__stage"
            >
                <div className="sis-follow-up-overview__inner">
                    {total > 0 ? (
                        <div
                            className="sis-follow-up-bar"
                            role="img"
                            aria-label={a.followUpOverview}
                        >
                            {shown.map(({ category, count }) => (
                                <span
                                    key={category.key}
                                    className={`sis-follow-up-bar__segment sis-follow-up-tone--${categoryToken(category.key)}`}
                                    style={{ flexGrow: count, flexBasis: 0 }}
                                    title={`${category.label} · ${count} (${percent.format(count / total)})`}
                                />
                            ))}
                        </div>
                    ) : null}

                    <div className="sis-follow-up-overview__row">
                        <div className="sis-follow-up-overview__side">
                            {total > 0 ? (
                                <ul className="sis-follow-up-legend">
                                    {shown.map(({ category, count }) => (
                                        <li
                                            key={category.key}
                                            className="sis-follow-up-legend__item"
                                        >
                                            <span
                                                className={`sis-follow-up-legend__dot sis-follow-up-tone--${categoryToken(category.key)}`}
                                                aria-hidden
                                            />
                                            <span className="sis-follow-up-legend__label">
                                                {category.label}
                                            </span>
                                            <span
                                                className="sis-follow-up-legend__share"
                                                dir="ltr"
                                            >
                                                {percent.format(count / total)}
                                            </span>
                                        </li>
                                    ))}
                                </ul>
                            ) : null}
                            <Link
                                href={listHref('')}
                                className="sis-follow-up-all"
                            >
                                <ListChecks className="size-4" aria-hidden />
                                <span>{a.followUpAll}</span>
                                <span
                                    className="sis-follow-up-all__count"
                                    dir="ltr"
                                >
                                    {total}
                                </span>
                            </Link>
                        </div>

                        {/* Four columns (two when narrow); an incomplete last row is centred. */}
                        <div className="sis-follow-up-cards">
                            {segments.map(({ category, count }) => {
                                const Icon = category.icon;

                                return (
                                    <Link
                                        key={category.key}
                                        href={listHref(category.key)}
                                        className={`sis-follow-up-card sis-follow-up-tone--${categoryToken(category.key)}`}
                                        aria-label={`${category.label}: ${count} — ${a.followUpOpenList}`}
                                    >
                                        <span
                                            className="sis-follow-up-card__avatar"
                                            aria-hidden
                                        >
                                            <Icon
                                                className="size-5"
                                                strokeWidth={2}
                                            />
                                        </span>
                                        <span className="sis-follow-up-card__label">
                                            {category.label}
                                        </span>
                                        <span
                                            className="sis-follow-up-card__count"
                                            dir="ltr"
                                        >
                                            {count}
                                        </span>
                                    </Link>
                                );
                            })}
                        </div>
                    </div>
                </div>
            </section>
        </div>
    );
}
