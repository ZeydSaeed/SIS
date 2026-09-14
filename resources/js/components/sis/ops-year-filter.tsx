import { router } from '@inertiajs/react';
import { useState } from 'react';
import { t } from '@/i18n';

type OpsYearFilterProps = {
    action: string;
    academicYearId: number | null;
    extraParams?: Record<string, string | number | undefined | null>;
    label?: string;
};

/** Shared academic-year filter — server pagination/filter (UI optimization Level 1). */
export function OpsYearFilter({
    action,
    academicYearId,
    extraParams = {},
    label,
}: OpsYearFilterProps) {
    const i18n = t();
    const [year, setYear] = useState(academicYearId?.toString() ?? '');

    const apply = () => {
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
                academic_year_id: year ? Number(year) : undefined,
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
                apply();
            }}
            aria-label={label ?? i18n.common.filterByYear}
        >
            <label className="flex min-w-[12rem] flex-col gap-1 text-sm">
                <span>{i18n.enrollments.academicYear}</span>
                <input
                    type="number"
                    min={1}
                    value={year}
                    onChange={(event) => setYear(event.target.value)}
                    className="sis-ops-hub__link min-h-11 px-3 py-2"
                    dir="ltr"
                    inputMode="numeric"
                />
            </label>
            <button type="submit" className="sis-ops-hub__link min-h-11 px-4 py-2 text-sm">
                {i18n.common.applyFilter}
            </button>
        </form>
    );
}
