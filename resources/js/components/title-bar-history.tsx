import { ChevronDown, Redo2, Undo2 } from 'lucide-react';
import { useEffect } from 'react';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { t } from '@/i18n';
import {
    HISTORY_STEPS,
    startNavigationHistory,
    travelTo,
    useNavigationHistory,
    type HistoryEntry,
} from '@/lib/navigation-history';

const DETAIL_PARAMS = [
    'date',
    'date_from',
    'date_to',
    'type',
    'view',
    'page',
    'q',
    'search',
];

/** «لوحة الحضور — 2026-10-14»: the page, and what tells two steps of the same page apart. */
function stepLabel(entry: HistoryEntry): string {
    const title = entry.title || entry.url.split('?')[0];
    try {
        const params = new URL(entry.url, 'http://x').searchParams;
        const detail = DETAIL_PARAMS.map((key) => params.get(key))
            .filter((v): v is string => v !== null && v !== '' && v !== '1')
            .join(' · ');

        return detail === '' ? title : `${title} — ${detail}`;
    } catch {
        return title;
    }
}

function when(at: number): string {
    return new Date(at).toLocaleTimeString('ar', {
        hour: '2-digit',
        minute: '2-digit',
        hour12: false,
        numberingSystem: 'latn',
    });
}

/** One split button: the arrow takes one step, the chevron lists up to 30 steps to jump to (as in Office). */
function HistoryButton({
    direction,
    steps,
}: {
    direction: 'back' | 'forward';
    steps: Array<{ entry: HistoryEntry; index: number }>;
}) {
    const h = t().window.history;
    const Icon = direction === 'back' ? Undo2 : Redo2;
    const label = direction === 'back' ? h.back : h.forward;
    const first = steps[0];

    return (
        <div className="sis-titlebar__history" role="group" aria-label={label}>
            <button
                type="button"
                className="sis-window__control"
                disabled={first === undefined}
                aria-label={
                    first ? `${label}: ${stepLabel(first.entry)}` : label
                }
                title={first ? `${label}: ${stepLabel(first.entry)}` : label}
                onClick={() => first && travelTo(first.index)}
            >
                <Icon className="size-3.5" aria-hidden />
            </button>
            <DropdownMenu dir="rtl">
                <DropdownMenuTrigger asChild disabled={first === undefined}>
                    <button
                        type="button"
                        className="sis-window__control sis-titlebar__history-more"
                        aria-label={
                            direction === 'back' ? h.backList : h.forwardList
                        }
                        title={
                            direction === 'back' ? h.backList : h.forwardList
                        }
                    >
                        <ChevronDown className="size-3" aria-hidden />
                    </button>
                </DropdownMenuTrigger>
                <DropdownMenuContent
                    align="end"
                    className="sis-titlebar__history-list"
                >
                    <DropdownMenuLabel>{`${direction === 'back' ? h.backList : h.forwardList} (${steps.length})`}</DropdownMenuLabel>
                    {steps.map(({ entry, index }, n) => (
                        <DropdownMenuItem
                            key={`${index}-${entry.at}`}
                            onSelect={() => travelTo(index)}
                            title={entry.url}
                        >
                            <span
                                className="sis-titlebar__history-step"
                                dir="ltr"
                            >
                                {n + 1}
                            </span>
                            <span className="sis-titlebar__history-title">
                                {stepLabel(entry)}
                            </span>
                            <span
                                className="sis-titlebar__history-time"
                                dir="ltr"
                            >
                                {when(entry.at)}
                            </span>
                        </DropdownMenuItem>
                    ))}
                </DropdownMenuContent>
            </DropdownMenu>
        </div>
    );
}

/** «رجوع» / «تقدم» between the pages and views of this session (navigation only — never undoes a saved change). */
export function TitleBarHistory() {
    const { entries, index } = useNavigationHistory();
    useEffect(() => startNavigationHistory(), []);

    const back = entries
        .slice(Math.max(0, index - HISTORY_STEPS), Math.max(0, index))
        .map((entry, i) => ({
            entry,
            index: Math.max(0, index - HISTORY_STEPS) + i,
        }))
        .reverse();
    const forward = entries
        .slice(index + 1, index + 1 + HISTORY_STEPS)
        .map((entry, i) => ({ entry, index: index + 1 + i }));

    return (
        <div className="sis-titlebar__history-group" dir="ltr">
            <HistoryButton direction="back" steps={back} />
            <HistoryButton direction="forward" steps={forward} />
        </div>
    );
}
