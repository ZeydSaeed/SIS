import { router, usePage } from '@inertiajs/react';
import { Filter, FilterX } from 'lucide-react';
import { useMemo } from 'react';
import { catalogBranches, catalogDepartmentsForBranch } from '@/components/curriculum/curriculum-subject-catalog';
import { ALL_CURRICULA_PATH, type CurriculumRow } from '@/components/curriculum/curriculum-workspace';
import { formatAcademicYearOptionLabel, type YearOption } from '@/components/sis/ops-year-filter';
import { usePageError } from '@/components/sis/page-error-context';
import type { PageRibbonGroup } from '@/components/sis/page-ribbon-context';
import { RibbonFilterField } from '@/components/sis/ribbon-filter';
import { t } from '@/i18n';
import { sisClassLabel, sisClassSelectOptions } from '@/lib/sis-class-section-options';

/** Every curriculum's place (all years) — the filters pick the curriculum this page shows. */
export type CurriculumPlace = Pick<
    CurriculumRow,
    'id' | 'name' | 'academic_year_id' | 'branch_name' | 'department_name' | 'specialization_name' | 'grade_level_id' | 'grade_level_name' | 'class_id' | 'status'
>;

type Dimension = 'year' | 'branch' | 'specialization' | 'class';

const DIMENSIONS: Dimension[] = ['year', 'branch', 'specialization', 'class'];

/** A place's value on each filter: السنة · الفرع · الاختصاص · الصف (by the class name, the same in every year). */
function valueOf(place: CurriculumPlace, dimension: Dimension): string {
    switch (dimension) {
        case 'year':
            return String(place.academic_year_id);
        case 'branch':
            return place.branch_name ?? '';
        case 'specialization':
            return place.department_name ?? place.specialization_name ?? '';
        case 'class':
            return place.grade_level_name ?? '';
    }
}

/**
 * The curriculum the filters lead to: one whose changed filter matches, and that keeps as much of the shown curriculum's
 * place as it can (the shown curriculum itself first). Null when no curriculum has that filter.
 */
export function curriculumForFilter(
    places: CurriculumPlace[],
    shown: CurriculumPlace,
    dimension: Dimension,
    values: string[],
): CurriculumPlace | null {
    const candidates = places.filter((place) => values.length === 0 || values.includes(valueOf(place, dimension)));
    const score = (place: CurriculumPlace): number =>
        DIMENSIONS.filter((other) => other !== dimension && valueOf(place, other) === valueOf(shown, other)).length * 2 +
        (place.status === 1 ? 1 : 0);
    let best: CurriculumPlace | null = null;
    for (const place of candidates) {
        if (best === null || score(place) > score(best) || (score(place) === score(best) && place.id === shown.id)) {
            best = place;
        }
    }

    return best;
}

/**
 * «الصفحة الرئيسية» of the curriculum page: the curricula page's own «تصفية المناهج» (السنة · الصف · الفرع · الاختصاص,
 * «إلغاء الفلترة», «كل المناهج»), showing this curriculum's place. A filter works on this same page: it shows the
 * curriculum that has the chosen value. «كل المناهج» opens every curriculum's card.
 */
export function useCurriculumDetailHomeRibbon(curriculum: CurriculumRow, places: CurriculumPlace[]): PageRibbonGroup[] {
    const c = t().curriculum;
    const { academicYears } = usePage().props as { academicYears?: YearOption[] };
    const { showWarning } = usePageError();

    return useMemo((): PageRibbonGroup[] => {
        const shown: CurriculumPlace = places.find((place) => place.id === curriculum.id) ?? curriculum;
        const choose = (dimension: Dimension, values: string[]): void => {
            // The field shows this curriculum's value: a value ticked beside it is the one asked for.
            const added = values.filter((value) => value !== valueOf(shown, dimension));
            const next = curriculumForFilter(places, shown, dimension, added.length > 0 ? added : values);
            if (next === null) {
                showWarning(c.noCurriculumForFilter);

                return;
            }
            if (next.id !== shown.id) {
                router.get(`/curriculum/curricula/${next.id}`);
            }
        };
        const shownClassKey = sisClassSelectOptions().find((option) => option.label === shown.grade_level_name)?.value ?? '';
        const branch = valueOf(shown, 'branch');
        const specialization = valueOf(shown, 'specialization');

        return [
            {
                id: 'curriculum-filters',
                label: c.ribbonFilters,
                commands: [],
                custom: (
                    <div className="sis-ribbon__filters" aria-label={c.filtersTitle} dir="rtl">
                        <div className="sis-ribbon__filters-stack sis-ribbon__filters-stack--curriculum">
                            <RibbonFilterField
                                spec={{
                                    key: 'year',
                                    type: 'single',
                                    label: c.academicYear,
                                    value: String(shown.academic_year_id),
                                    options: (academicYears ?? []).map((item) => ({
                                        value: String(item.id),
                                        label: formatAcademicYearOptionLabel(item.name, item.code),
                                    })),
                                    onChange: (year) => choose('year', year === '' ? [] : [year]),
                                }}
                            />
                            <RibbonFilterField
                                spec={{
                                    key: 'class',
                                    type: 'multi',
                                    label: c.gradeLevel,
                                    allLabel: c.gradeLevel,
                                    values: shownClassKey === '' ? [] : [shownClassKey],
                                    options: sisClassSelectOptions(),
                                    onChange: (keys) => choose('class', keys.map((key) => sisClassLabel(key)).filter(Boolean)),
                                }}
                            />
                            <RibbonFilterField
                                spec={{
                                    key: 'branch',
                                    type: 'multi',
                                    label: c.branch,
                                    allLabel: c.branch,
                                    values: branch === '' ? [] : [branch],
                                    options: catalogBranches().map((name) => ({ value: name, label: name })),
                                    onChange: (values) => choose('branch', values),
                                }}
                            />
                            <RibbonFilterField
                                spec={{
                                    key: 'specialization',
                                    type: 'multi',
                                    label: c.specialization,
                                    allLabel: c.specialization,
                                    values: specialization === '' ? [] : [specialization],
                                    options: (branch === '' ? catalogBranches() : [branch])
                                        .flatMap((name) => catalogDepartmentsForBranch(name))
                                        .filter((name, index, all) => all.indexOf(name) === index)
                                        .map((name) => ({ value: name, label: name })),
                                    onChange: (values) => choose('specialization', values),
                                }}
                            />
                        </div>
                        <button
                            type="button"
                            className="sis-ribbon__item sis-ribbon__item--filter-clear"
                            aria-label={c.clearFiltersAria}
                            title={c.clearFiltersAria}
                            // The filters show this curriculum's own place: clearing keeps this page as it is.
                            onClick={() => router.reload()}
                        >
                            <FilterX className="sis-ribbon__icon" aria-hidden />
                            <span className="sis-ribbon__label">{c.clearFilters}</span>
                        </button>
                        <button
                            type="button"
                            className="sis-ribbon__item sis-ribbon__item--filter-clear"
                            aria-label={c.showAllCurriculaAria}
                            title={c.showAllCurriculaAria}
                            onClick={() => router.get(ALL_CURRICULA_PATH)}
                        >
                            <Filter className="sis-ribbon__icon" aria-hidden />
                            <span className="sis-ribbon__label">{c.showAllCurricula}</span>
                        </button>
                    </div>
                ),
            },
        ];
    }, [academicYears, c, curriculum, places, showWarning]);
}
