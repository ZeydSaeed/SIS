import { ChevronLeft, ChevronRight } from 'lucide-react';
import { useEffect } from 'react';
import { direction, t } from '@/i18n';
import {
    startNavigationHistory,
    travelTo,
    useNavigationHistory,
} from '@/lib/navigation-history';

/**
 * «الصفحة السابقة» / «الصفحة التالية»: one step through the pages of this tab. The arrows follow the reading
 * direction — in Arabic the right arrow goes back and the left arrow goes forward; in English the other way round.
 */
export function TitleBarPageNav() {
    const h = t().window.history;
    const { entries, index } = useNavigationHistory();
    useEffect(() => startNavigationHistory(), []);

    const previous = {
        entry: entries[index - 1],
        target: index - 1,
        label: h.previousPage,
    };
    const next = {
        entry: entries[index + 1],
        target: index + 1,
        label: h.nextPage,
    };
    const [left, right] =
        direction() === 'rtl' ? [next, previous] : [previous, next];

    return (
        <div
            className="sis-titlebar__page-nav"
            role="group"
            aria-label={h.pages}
            dir="ltr"
        >
            {[
                { step: left, Icon: ChevronLeft },
                { step: right, Icon: ChevronRight },
            ].map(({ step, Icon }) => {
                const label = step.entry?.title
                    ? `${step.label}: ${step.entry.title}`
                    : step.label;

                return (
                    <button
                        key={step.label}
                        type="button"
                        className="sis-window__control"
                        disabled={step.entry === undefined}
                        aria-label={label}
                        title={label}
                        onClick={() => travelTo(step.target)}
                    >
                        <Icon className="size-4" aria-hidden />
                    </button>
                );
            })}
        </div>
    );
}
