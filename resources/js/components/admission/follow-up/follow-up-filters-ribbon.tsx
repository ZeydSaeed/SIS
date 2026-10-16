import { FilterX } from 'lucide-react';
import { useMemo } from 'react';
import {
    filterValues,
    FOLLOW_UP_LIST_PATH,
    type FollowUpCategory,
    type FollowUpFilterOptions,
    type FollowUpQuery,
} from '@/components/admission/follow-up/follow-up-data';
import {
    useRegisterPageRibbon,
    type PageRibbonGroup,
} from '@/components/sis/page-ribbon-context';
import { RibbonFilterField } from '@/components/sis/ribbon-filter';
import { t } from '@/i18n';

type Props = {
    query: FollowUpQuery;
    visit: (patch: Partial<FollowUpQuery>, toPath?: string) => void;
    options: FollowUpFilterOptions;
    schoolIds: string[];
    periodIds: string[];
    yearId: string;
    categories: FollowUpCategory[];
    disabled?: boolean;
};

/**
 * «الصفحة الرئيسية» ribbon of both follow-up pages — the filters the EXAMIS list pages keep in their header bar:
 * schools, year, periods and categories (any number of each; the year is one). A category always opens the list page.
 */
export function useFollowUpFiltersRibbon({
    query,
    visit,
    options,
    schoolIds,
    periodIds,
    yearId,
    categories,
    disabled = false,
}: Props): void {
    const i18n = t();
    const a = i18n.admission;

    const groups = useMemo(
        (): PageRibbonGroup[] => [
            {
                id: 'follow-up-filters',
                label: a.sheetAcceptedFilters,
                commands: [],
                custom: (
                    <div
                        className="sis-ribbon__filters"
                        aria-label={a.sheetAcceptedFilters}
                    >
                        <div className="sis-ribbon__filters-stack sis-ribbon__filters-stack--curriculum">
                            <RibbonFilterField
                                spec={{
                                    key: 'school',
                                    type: 'multi',
                                    label: a.filterBySchool,
                                    allLabel: a.allSchools,
                                    disabled,
                                    values: schoolIds,
                                    options: options.schools,
                                    // None chosen = every school (not the default school).
                                    onChange: (next) =>
                                        visit({
                                            school:
                                                next.length === 0
                                                    ? 'all'
                                                    : next.join(','),
                                            period: '',
                                        }),
                                }}
                            />
                            <RibbonFilterField
                                spec={{
                                    key: 'academic-year',
                                    type: 'single',
                                    label: a.academicYear,
                                    disabled,
                                    value: yearId,
                                    options: options.years,
                                    onChange: (next) =>
                                        visit({ year: next, period: '' }),
                                }}
                            />
                            <RibbonFilterField
                                spec={{
                                    key: 'period',
                                    type: 'multi',
                                    label: a.filterByPeriod,
                                    allLabel: a.allPeriods,
                                    disabled,
                                    values: periodIds,
                                    options: options.periods,
                                    onChange: (next) =>
                                        visit({ period: next.join(',') }),
                                }}
                            />
                            <RibbonFilterField
                                spec={{
                                    key: 'category',
                                    type: 'multi',
                                    label: a.followUpCategory,
                                    allLabel: a.followUpAll,
                                    disabled,
                                    values: filterValues(query.category),
                                    options: categories.map((category) => ({
                                        value: category.key,
                                        label: category.label,
                                    })),
                                    onChange: (next) =>
                                        visit(
                                            { category: next.join(',') },
                                            FOLLOW_UP_LIST_PATH,
                                        ),
                                }}
                            />
                        </div>
                        <button
                            type="button"
                            className="sis-ribbon__item sis-ribbon__item--filter-clear"
                            disabled={disabled}
                            aria-label={i18n.students.clearFiltersAria}
                            title={i18n.students.clearFiltersAria}
                            onClick={() =>
                                visit({
                                    school: '',
                                    period: '',
                                    category: '',
                                    q: '',
                                })
                            }
                        >
                            <FilterX className="sis-ribbon__icon" aria-hidden />
                            <span className="sis-ribbon__label">
                                {i18n.students.clearFilters}
                            </span>
                        </button>
                    </div>
                ),
            },
        ],
        [
            a,
            categories,
            disabled,
            i18n.students,
            options,
            periodIds,
            query.category,
            schoolIds,
            visit,
            yearId,
        ],
    );

    useRegisterPageRibbon('home', groups);
}
