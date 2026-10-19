import {
    CalendarDays,
    ChevronUp,
    Percent,
} from 'lucide-react';
import { useSyncExternalStore } from 'react';
import { useRegisterPageTitlebarToggle } from '@/components/sis/page-titlebar-toggle-context';
import { t } from '@/i18n';

/** The panels a page may hide so its table is seen whole: the quick-link rail, the side calendar and the day's totals. */
export type ShellPanel = 'rail' | 'calendar' | 'totals';

const STORAGE_KEY = 'sis.shell.hidden-panels';
const PANELS: readonly ShellPanel[] = ['rail', 'calendar', 'totals'];
const NONE: ShellPanel[] = [];

let hidden: ShellPanel[] | null = null;
const listeners = new Set<() => void>();

function readHidden(): ShellPanel[] {
    try {
        const parsed: unknown = JSON.parse(
            window.localStorage.getItem(STORAGE_KEY) ?? '[]',
        );

        return Array.isArray(parsed)
            ? PANELS.filter((panel) => parsed.includes(panel))
            : [];
    } catch {
        return [];
    }
}

function snapshot(): ShellPanel[] {
    hidden ??= readHidden();

    return hidden;
}

function setHidden(next: ShellPanel[]): void {
    hidden = next;
    try {
        window.localStorage.setItem(STORAGE_KEY, JSON.stringify(next));
    } catch {
        // Storage can be blocked: the choice then lasts until the page is reloaded.
    }
    listeners.forEach((listener) => listener());
}

function subscribe(listener: () => void): () => void {
    listeners.add(listener);

    return () => {
        listeners.delete(listener);
    };
}

/** One choice for every attendance and leave page (kept in this browser): which panels are hidden. */
export function useShellPanels() {
    const current = useSyncExternalStore(subscribe, snapshot, () => NONE);

    return {
        isHidden: (panel: ShellPanel) => current.includes(panel),
        toggle: (panel: ShellPanel) =>
            setHidden(
                current.includes(panel)
                    ? current.filter((item) => item !== panel)
                    : [...current, panel],
            ),
    };
}

/** Title bar of a collapsible card (the month calendar, the day's totals): the card folds to this bar and unfolds from it. */
export function PanelHead({
    panel,
    totalsTitle,
}: {
    panel: 'calendar' | 'totals';
    /** The totals card's title when it is not the attendance rate. */
    totalsTitle?: string;
}) {
    const text = t().shellPanels;
    const state = useShellPanels();
    const shown = !state.isHidden(panel);
    const title =
        panel === 'calendar' ? text.calendar : (totalsTitle ?? text.totals);

    return (
        <div className="sis-panel-head">
            <span>{title}</span>
            <button
                type="button"
                className="sis-panel-head__btn"
                aria-expanded={shown}
                title={`${shown ? text.hide : text.show}: ${title}`}
                onClick={() => state.toggle(panel)}
            >
                <ChevronUp
                    aria-hidden
                    className="sis-panel-head__chevron size-3.5"
                />
            </button>
        </div>
    );
}

/** The side column when both its cards are folded: a thin strip with one button per card to unfold it. */
export function SideStrip() {
    const text = t().shellPanels;
    const state = useShellPanels();

    return (
        <div className="sis-side-strip">
            <button
                type="button"
                className="sis-side-strip__btn"
                title={`${text.show}: ${text.calendar}`}
                aria-label={`${text.show}: ${text.calendar}`}
                onClick={() => state.toggle('calendar')}
            >
                <CalendarDays aria-hidden className="size-4" />
            </button>
            <button
                type="button"
                className="sis-side-strip__btn"
                title={`${text.show}: ${text.totals}`}
                aria-label={`${text.show}: ${text.totals}`}
                onClick={() => state.toggle('totals')}
            >
                <Percent aria-hidden className="size-4" />
            </button>
        </div>
    );
}

/** The rail's fold switch lives in the title bar (next to the close button): registers it while the rail is on the page. */
export function useRailTitlebarToggle(): void {
    const text = t().shellPanels;
    const state = useShellPanels();
    const shown = !state.isHidden('rail');

    useRegisterPageTitlebarToggle({
        pressed: shown,
        label: `${shown ? text.hide : text.show}: ${text.side}`,
        onToggle: () => state.toggle('rail'),
    });
}
