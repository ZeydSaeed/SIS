import { router } from '@inertiajs/react';
import { usePage } from '@inertiajs/react';
import { useState } from 'react';
import { t } from '@/i18n';

type YearOption = { id: number; name: string; code: string; is_current: boolean };

type OpsYearFilterProps = {
    action: string;
    academicYearId: number | null;
    extraParams?: Record<string, string | number | undefined | null>;
    label?: string;
    /** When false, hides the field label above the select. */
    showLabel?: boolean;
    /** When false, omits the “active/current” suffix on year options. */
    showCurrentBadge?: boolean;
    /** Extra class for the select/input control. */
    controlClassName?: string;
    /** Place label beside the control (RTL: to the right). */
    inlineLabel?: boolean;
    /** Shrink the control to the selected text instead of a fixed min-width. */
    compact?: boolean;
};

function yearOptionLabel(name: string, code: string): string {
    const stripWords = (value: string): string =>
        value
            .replaceAll(/السنة الدراسية/gi, '')
            .replaceAll(/السنه الدراسية/gi, '')
            .replaceAll(/academic\s*year/gi, '')
            .replaceAll(/^[-–—:\s]+|[-–—:\s]+$/g, '')
            .trim();

    const pickYearToken = (value: string): string | null => {
        const range = value.match(/\d{4}\s*[-–/]\s*\d{2,4}/);
        if (range) {
            return range[0].replace(/\s+/g, '');
        }

        const single = value.match(/\d{4}/);

        return single ? single[0] : null;
    };

    const fromName = pickYearToken(stripWords(name));
    if (fromName) {
        return fromName;
    }

    const fromCode = pickYearToken(code);
    if (fromCode) {
        return fromCode;
    }

    const cleanedName = stripWords(name);
    if (cleanedName !== '') {
        return cleanedName;
    }

    return stripWords(code) || code;
}

/** Shared academic-year filter — prefers shared catalog select when available. */
export function OpsYearFilter({
    action,
    academicYearId,
    extraParams = {},
    label,
    showLabel = true,
    showCurrentBadge = true,
    controlClassName,
    inlineLabel = false,
    compact = false,
}: OpsYearFilterProps) {
    const i18n = t();
    const { academicYears } = usePage().props as { academicYears?: YearOption[] };
    const years = academicYears ?? [];
    const initial =
        academicYearId?.toString() ??
        years.find((y) => y.is_current)?.id.toString() ??
        years[0]?.id.toString() ??
        '';
    const [year, setYear] = useState(initial);
    const selectedYear = years.find((item) => item.id.toString() === year);
    const selectedYearLabel = selectedYear
        ? `${yearOptionLabel(selectedYear.name, selectedYear.code)}${
              showCurrentBadge && selectedYear.is_current ? ` · ${i18n.status.active}` : ''
          }`
        : year;

    const apply = (nextYear: string) => {
        const cleaned: Record<string, string | number> = {};
        for (const [key, value] of Object.entries(extraParams)) {
            if (value !== undefined && value !== null && value !== '') {
                cleaned[key] = value;
            }
        }
        router.get(
            action,
            {
                ...cleaned,
                academic_year_id: nextYear ? Number(nextYear) : undefined,
                page: 1,
            },
            { preserveState: true, preserveScroll: true },
        );
    };

    return (
        <form
            className="flex flex-wrap items-end gap-3"
            onSubmit={(event) => {
                event.preventDefault();
                apply(year);
            }}
            aria-label={label ?? i18n.common.filterByYear}
        >
            <label
                className={
                    inlineLabel
                        ? `flex flex-row items-center gap-2 text-sm ${compact ? 'min-w-0' : 'min-w-[12rem]'}`
                        : `flex flex-col gap-1 text-sm ${compact ? 'min-w-0' : 'min-w-[12rem]'}`
                }
                dir="rtl"
            >
                {showLabel ? (
                    <span className="shrink-0 font-medium">
                        {label ?? i18n.enrollments.academicYear}
                    </span>
                ) : null}
                {years.length > 0 ? (
                    compact ? (
                        <span className="sis-admission-select-fit">
                            <span className="sis-admission-select-fit__mirror" aria-hidden="true">
                                {selectedYearLabel}
                            </span>
                            <select
                                value={year}
                                onChange={(event) => {
                                    setYear(event.target.value);
                                    apply(event.target.value);
                                }}
                                className={`sis-ops-hub__link px-3 py-2 min-h-0 min-w-0 ${controlClassName ?? ''}`}
                                dir="rtl"
                                aria-label={label ?? i18n.enrollments.academicYear}
                            >
                                {years.map((item) => (
                                    <option key={item.id} value={item.id}>
                                        {yearOptionLabel(item.name, item.code)}
                                        {showCurrentBadge && item.is_current
                                            ? ` · ${i18n.status.active}`
                                            : ''}
                                    </option>
                                ))}
                            </select>
                        </span>
                    ) : (
                        <select
                            value={year}
                            onChange={(event) => {
                                setYear(event.target.value);
                                apply(event.target.value);
                            }}
                            className={`sis-ops-hub__link min-h-11 min-w-[10rem] px-3 py-2 ${controlClassName ?? ''}`}
                            dir="rtl"
                            aria-label={label ?? i18n.enrollments.academicYear}
                        >
                            {years.map((item) => (
                                <option key={item.id} value={item.id}>
                                    {yearOptionLabel(item.name, item.code)}
                                    {showCurrentBadge && item.is_current
                                        ? ` · ${i18n.status.active}`
                                        : ''}
                                </option>
                            ))}
                        </select>
                    )
                ) : (
                    <input
                        type="number"
                        min={1}
                        value={year}
                        onChange={(event) => setYear(event.target.value)}
                        className={`sis-ops-hub__link px-3 py-2 ${compact ? 'min-h-0 min-w-0' : 'min-h-11 min-w-[10rem]'} ${controlClassName ?? ''}`}
                        dir="ltr"
                        inputMode="numeric"
                    />
                )}
            </label>
            {years.length === 0 ? (
                <button type="submit" className="sis-ops-hub__link min-h-11 px-4 py-2 text-sm">
                    {i18n.common.applyFilter}
                </button>
            ) : null}
        </form>
    );
}
