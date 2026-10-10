import { SisListSelect } from '@/components/sis/sis-list-select';
import { multiSelectSummary, SisMultiSelect } from '@/components/sis/sis-multi-select';

export type FilterOption = { value: string; label: string };

/**
 * One filter control of a page's tab-strip ribbon — the single source for every page (timetable, rooms, enrollments,
 * students, curriculum, admission): `single` = one choice (the academic year), `multi` = any number of choices
 * (several classes, sections, branches …). Same field, same sizing, same summary text everywhere.
 */
export type FilterSpec =
    | { key: string; type: 'single'; label: string; disabled?: boolean; value: string; options: FilterOption[]; onChange: (value: string) => void }
    | { key: string; type: 'multi'; label: string; disabled?: boolean; allLabel: string; values: string[]; options: FilterOption[]; onChange: (values: string[]) => void };

export const RIBBON_FILTER_TRIGGER = 'sis-ops-hub__link px-2 py-1 min-h-0 min-w-0 sis-admission-year-control';

export function filterSummary(spec: FilterSpec): string {
    return spec.type === 'single'
        ? (spec.options.find((o) => o.value === spec.value)?.label ?? spec.options[0]?.label ?? '')
        : multiSelectSummary(spec.values, spec.options, spec.allLabel, (n) => `${n} / ${spec.options.length}`);
}

export function FilterControl({ spec, className, triggerClassName }: { spec: FilterSpec; className?: string; triggerClassName?: string }) {
    return spec.type === 'single' ? (
        <SisListSelect value={spec.value} options={spec.options} onChange={spec.onChange} disabled={spec.disabled} className={className} triggerClassName={triggerClassName} dir="rtl" ariaLabel={spec.label} />
    ) : (
        <SisMultiSelect values={spec.values} options={spec.options} allLabel={spec.allLabel} onChange={spec.onChange} disabled={spec.disabled} className={className} triggerClassName={triggerClassName} dir="rtl" ariaLabel={spec.label} />
    );
}

/** The ribbon field: the control inside the width-fitting wrapper (its mirror text sizes the field to its content). */
export function RibbonFilterField({ spec }: { spec: FilterSpec }) {
    return (
        <div className="sis-ribbon__filter-field" dir="rtl">
            <span className="sis-admission-select-fit">
                <span className="sis-admission-select-fit__mirror" aria-hidden="true">
                    {filterSummary(spec)}
                </span>
                <FilterControl spec={spec} triggerClassName={RIBBON_FILTER_TRIGGER} />
            </span>
        </div>
    );
}
