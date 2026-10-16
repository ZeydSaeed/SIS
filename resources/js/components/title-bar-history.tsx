import { ChevronDown, Redo2, Undo2 } from 'lucide-react';
import { useEffect } from 'react';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { direction, locale, t } from '@/i18n';
import {
    EDIT_STEPS,
    redoEdits,
    startEditHistory,
    undoEdits,
    useEditHistory,
    type EditStep,
} from '@/lib/edit-history';

function shown(value: string | boolean): string {
    const h = t().window.history;
    if (typeof value === 'boolean') {
        return value ? h.checked : h.unchecked;
    }
    const text = value.trim();

    return text === ''
        ? h.blank
        : text.length > 24
          ? `${text.slice(0, 24)}…`
          : text;
}

/** «الاسم: أحمد ← محمد» (RTL) / «Name: Ahmed → Mohammed» (LTR): the field, and what the step changes it from and to. */
function stepLabel(step: EditStep): string {
    const field = step.label || t().window.history.field;
    if (step.previous) {
        return field;
    }

    const arrow = direction() === 'rtl' ? '←' : '→';

    return `${field}: ${shown(step.before)} ${arrow} ${shown(step.after)}`;
}

function when(at: number): string {
    return new Date(at).toLocaleTimeString(locale(), {
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
        hour12: false,
        numberingSystem: 'latn',
    });
}

/** One split button: the arrow takes one step, the chevron lists up to 30 steps (as in Office). */
function HistoryButton({
    direction: way,
    steps,
}: {
    direction: 'back' | 'forward';
    steps: EditStep[];
}) {
    const h = t().window.history;
    const Icon = way === 'back' ? Undo2 : Redo2;
    const label = way === 'back' ? h.back : h.forward;
    const list = way === 'back' ? h.backList : h.forwardList;
    const take = way === 'back' ? undoEdits : redoEdits;
    const first = steps[0];
    const firstLabel = first
        ? `${label}: ${stepLabel(first)}`
        : `${label} — ${h.empty}`;

    return (
        <div className="sis-titlebar__history" role="group" aria-label={label}>
            <button
                type="button"
                className="sis-window__control"
                disabled={first === undefined}
                aria-label={firstLabel}
                title={firstLabel}
                onClick={() => take(1)}
            >
                <Icon className="size-3.5" aria-hidden />
            </button>
            <DropdownMenu dir={direction()}>
                <DropdownMenuTrigger asChild disabled={first === undefined}>
                    <button
                        type="button"
                        className="sis-window__control sis-titlebar__history-more"
                        aria-label={list}
                        title={list}
                    >
                        <ChevronDown className="size-3" aria-hidden />
                    </button>
                </DropdownMenuTrigger>
                <DropdownMenuContent
                    align="end"
                    className="sis-titlebar__history-list"
                >
                    <DropdownMenuLabel>{`${list} (${steps.length})`}</DropdownMenuLabel>
                    {steps.map((step, n) => (
                        <DropdownMenuItem
                            key={`${n}-${step.at}`}
                            onSelect={() => take(n + 1)}
                        >
                            <span
                                className="sis-titlebar__history-step"
                                dir="ltr"
                            >
                                {n + 1}
                            </span>
                            <span className="sis-titlebar__history-title">
                                {stepLabel(step)}
                            </span>
                            <span
                                className="sis-titlebar__history-time"
                                dir="ltr"
                            >
                                {when(step.at)}
                            </span>
                        </DropdownMenuItem>
                    ))}
                </DropdownMenuContent>
            </DropdownMenu>
        </div>
    );
}

/**
 * «رجوع» / «تقدم»: the unsaved edits of the open page (typing, choosing, ticking, clearing a field). Never reverses a
 * saved operation; page-to-page movement is the arrows of TitleBarPageNav.
 */
export function TitleBarHistory() {
    const { done, undone } = useEditHistory();
    useEffect(() => startEditHistory(), []);

    return (
        <div className="sis-titlebar__history-group" dir="ltr">
            <HistoryButton
                direction="back"
                steps={done.slice(-EDIT_STEPS).reverse()}
            />
            <HistoryButton
                direction="forward"
                steps={undone.slice(-EDIT_STEPS).reverse()}
            />
        </div>
    );
}
