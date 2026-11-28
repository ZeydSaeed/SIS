import type { CSSProperties } from 'react';
import { t } from '@/i18n';
import type { TypeHourTotals } from '@/lib/subject-type-order';

/** «مجموع الساعات» by type — أساسي، اختصاص، نظري، عملي — and in all (same frame as the plan totals on a curriculum). */
export function TypeHourTotalsBar({
    totals,
    className = '',
    style,
}: {
    totals: TypeHourTotals;
    className?: string;
    /** E.g. a light background in the curriculum's colour («كل المناهج»). */
    style?: CSSProperties;
}) {
    const c = t().curriculum;
    const items: Array<[string, number]> = [
        [c.hoursTotalCore, totals.core],
        [c.hoursTotalSpecialization, totals.specialization],
        [c.hoursTotalTheory, totals.theory],
        [c.hoursTotalPractical, totals.practical],
        [c.hoursTotalAll, totals.total],
    ];

    return (
        <dl
            className={`grid grid-cols-5 gap-2 rounded-md border border-[color:var(--sis-powder-blue)] p-2 text-center ${className}`.trim()}
            aria-label={c.hoursTotalAll}
            style={style}
        >
            {items.map(([label, value]) => (
                <div key={label}>
                    <dt className="text-muted-foreground text-xs">{label}</dt>
                    <dd className="text-base font-semibold" dir="ltr">
                        {value}
                    </dd>
                </div>
            ))}
        </dl>
    );
}
