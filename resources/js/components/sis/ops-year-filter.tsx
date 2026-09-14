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
};

/** Shared academic-year filter — prefers shared catalog select when available. */
export function OpsYearFilter({
    action,
    academicYearId,
    extraParams = {},
    label,
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
            <label className="flex min-w-[12rem] flex-col gap-1 text-sm">
                <span>{i18n.enrollments.academicYear}</span>
                {years.length > 0 ? (
                    <select
                        value={year}
                        onChange={(event) => {
                            setYear(event.target.value);
                            apply(event.target.value);
                        }}
                        className="sis-ops-hub__link min-h-11 px-3 py-2"
                        dir="rtl"
                    >
                        {years.map((item) => (
                            <option key={item.id} value={item.id}>
                                {item.name}
                                {item.is_current ? ` · ${i18n.status.active}` : ''}
                            </option>
                        ))}
                    </select>
                ) : (
                    <input
                        type="number"
                        min={1}
                        value={year}
                        onChange={(event) => setYear(event.target.value)}
                        className="sis-ops-hub__link min-h-11 px-3 py-2"
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
