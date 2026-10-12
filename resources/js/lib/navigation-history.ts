import { router } from '@inertiajs/react';
import { useSyncExternalStore } from 'react';

/**
 * «رجوع» / «تقدم» of the title bar: the pages and views (URL with its filters, tabs, pages) the user went through in
 * this tab — up to 30 steps back and 30 forward, like Office's undo / redo lists. Navigation only: a step never
 * reverses a saved operation. Kept in sessionStorage (survives a reload, not shared between tabs).
 */
export type HistoryEntry = { url: string; title: string; at: number };

type State = { entries: HistoryEntry[]; index: number };

export const HISTORY_STEPS = 30;
const KEY = 'sis.navigation.history';
const APP_SUFFIX = / — Student Information System$/;

let state: State = { entries: [], index: -1 };
let pending: number | null = null;
let started = false;
const listeners = new Set<() => void>();

function load(): State {
    try {
        const raw = window.sessionStorage.getItem(KEY);
        const parsed = raw === null ? null : (JSON.parse(raw) as State);
        if (
            parsed &&
            Array.isArray(parsed.entries) &&
            Number.isInteger(parsed.index)
        ) {
            return parsed;
        }
    } catch {
        // private mode / corrupt — start empty
    }

    return { entries: [], index: -1 };
}

function save(next: State): void {
    state = next;
    try {
        window.sessionStorage.setItem(KEY, JSON.stringify(state));
    } catch {
        // quota — the history lasts for this page only
    }
    listeners.forEach((listener) => listener());
}

function pageTitle(): string {
    return (typeof document === 'undefined' ? '' : document.title)
        .replace(APP_SUFFIX, '')
        .trim();
}

/** The page title is set after the page renders: give the current step its title a moment later. */
function titleSoon(): void {
    window.setTimeout(() => {
        const current = state.entries[state.index];
        const title = pageTitle();
        if (current && title !== '' && current.title !== title) {
            const entries = state.entries.slice();
            entries[state.index] = { ...current, title };
            save({ entries, index: state.index });
        }
    }, 80);
}

function record(url: string): void {
    const { entries, index } = state;
    if (pending !== null) {
        save({ entries, index: pending });
        pending = null;
    } else if (entries[index]?.url === url) {
        // a reload / partial reload of the same view — not a step
    } else if (entries[index - 1]?.url === url) {
        save({ entries, index: index - 1 }); // the browser's own «back»
    } else if (entries[index + 1]?.url === url) {
        save({ entries, index: index + 1 }); // the browser's own «forward»
    } else {
        const kept = entries.slice(
            Math.max(0, index + 1 - HISTORY_STEPS),
            index + 1,
        );
        save({
            entries: [...kept, { url, title: '', at: Date.now() }],
            index: kept.length,
        });
    }
    titleSoon();
}

/** Start listening to Inertia visits (once, from the app shell). */
export function startNavigationHistory(): void {
    if (started || typeof window === 'undefined') {
        return;
    }
    started = true;
    state = load();
    record(window.location.pathname + window.location.search);
    router.on('navigate', (event) => record(event.detail.page.url));
}

/** Go to step `target` (an index of the list). */
export function travelTo(target: number): void {
    const entry = state.entries[target];
    if (entry === undefined || target === state.index) {
        return;
    }
    pending = target;
    router.visit(entry.url, {
        onCancel: () => (pending = null),
        onError: () => (pending = null),
    });
}

export function useNavigationHistory(): State {
    return useSyncExternalStore(
        (listener) => {
            listeners.add(listener);

            return () => listeners.delete(listener);
        },
        () => state,
        () => state,
    );
}
