import { useCallback, useMemo, useState, useSyncExternalStore } from 'react';
import type { DragEvent, KeyboardEvent } from 'react';

/**
 * The order the user gave the sidebar module icons by dragging one onto another: it moves
 * to that place and the items in between shift by one.
 * A per-browser display preference: kept in localStorage as a list of hrefs.
 */
export type NavOrderScope = 'sidebar';

const STORAGE_PREFIX = 'sis.nav-order.';
const DRAG_TYPE = 'application/x-sis-nav-item';
const EMPTY: readonly string[] = [];

const cache = new Map<NavOrderScope, readonly string[]>();
const listeners = new Set<() => void>();

function read(scope: NavOrderScope): readonly string[] {
    const cached = cache.get(scope);
    if (cached !== undefined) {
        return cached;
    }
    let value: readonly string[] = EMPTY;
    try {
        const parsed: unknown = JSON.parse(window.localStorage.getItem(STORAGE_PREFIX + scope) ?? '[]');
        if (Array.isArray(parsed)) {
            value = parsed.filter((entry): entry is string => typeof entry === 'string');
        }
    } catch {
        // Unreadable storage: keep the default order.
    }
    cache.set(scope, value);

    return value;
}

function write(scope: NavOrderScope, next: readonly string[]): void {
    cache.set(scope, next);
    try {
        window.localStorage.setItem(STORAGE_PREFIX + scope, JSON.stringify(next));
    } catch {
        // Storage can be blocked: the order then lasts until the page is reloaded.
    }
    listeners.forEach((listener) => listener());
}

function subscribe(listener: () => void): () => void {
    listeners.add(listener);

    return () => {
        listeners.delete(listener);
    };
}

/** Saved hrefs first (those that still exist), then any item not yet in the saved list, in default order. */
function applyOrder<T>(items: readonly T[], keyOf: (item: T) => string, saved: readonly string[]): T[] {
    const byKey = new Map(items.map((item) => [keyOf(item), item]));
    const ordered: T[] = [];
    for (const key of saved) {
        const item = byKey.get(key);
        if (item !== undefined) {
            ordered.push(item);
            byKey.delete(key);
        }
    }

    return [...ordered, ...byKey.values()];
}

/**
 * Orders `items` by the saved preference and returns drag-and-drop / keyboard props:
 * dropping item A on item B moves A to B's place (the rest shift); Alt + arrow keys move an item one step.
 */
export function useNavOrder<T>(scope: NavOrderScope, items: readonly T[], keyOf: (item: T) => string) {
    const saved = useSyncExternalStore(
        subscribe,
        () => read(scope),
        () => EMPTY,
    );
    const ordered = useMemo(() => applyOrder(items, keyOf, saved), [items, keyOf, saved]);
    const [dragging, setDragging] = useState<string | null>(null);
    const [over, setOver] = useState<string | null>(null);

    const indexOf = useCallback((key: string) => ordered.findIndex((item) => keyOf(item) === key), [ordered, keyOf]);

    /** Takes `source` out and puts it where `target` is; everything between shifts one place. */
    const move = useCallback(
        (source: string, target: string) => {
            const from = indexOf(source);
            const to = indexOf(target);
            if (from < 0 || to < 0 || from === to) {
                return;
            }
            const keys = ordered.map(keyOf);
            keys.splice(from, 1);
            keys.splice(to, 0, source);
            write(scope, keys);
        },
        [ordered, keyOf, indexOf, scope],
    );

    /** Where the dragged item will land relative to the hovered one: above it when moving up, below when moving down. */
    const dropSide = (key: string): 'before' | 'after' | undefined => {
        if (over !== key || dragging === null || dragging === key) {
            return undefined;
        }

        return indexOf(dragging) > indexOf(key) ? 'before' : 'after';
    };

    const itemProps = useCallback(
        (key: string, neighbours?: { previous?: string; next?: string }) => ({
            draggable: true,
            'data-nav-dragging': dragging === key ? 'true' : undefined,
            'data-nav-drop-target': dropSide(key),
            onDragStart: (event: DragEvent) => {
                event.dataTransfer.effectAllowed = 'move';
                event.dataTransfer.setData(DRAG_TYPE, key);
                setDragging(key);
            },
            onDragEnd: () => {
                setDragging(null);
                setOver(null);
            },
            onDragOver: (event: DragEvent) => {
                if (dragging === null) {
                    return;
                }
                event.preventDefault();
                event.dataTransfer.dropEffect = 'move';
                if (over !== key) {
                    setOver(key);
                }
            },
            onDragLeave: (event: DragEvent) => {
                if (!event.currentTarget.contains(event.relatedTarget as Node | null)) {
                    setOver((current) => (current === key ? null : current));
                }
            },
            onDrop: (event: DragEvent) => {
                event.preventDefault();
                const source = event.dataTransfer.getData(DRAG_TYPE) || dragging;
                if (source) {
                    move(source, key);
                }
                setDragging(null);
                setOver(null);
            },
            onKeyDown: (event: KeyboardEvent) => {
                if (!event.altKey || neighbours === undefined) {
                    return;
                }
                const rtl = (event.currentTarget as HTMLElement).closest('[dir]')?.getAttribute('dir') !== 'ltr';
                const toPrevious =
                    event.key === 'ArrowUp' || event.key === (rtl ? 'ArrowRight' : 'ArrowLeft');
                const toNext = event.key === 'ArrowDown' || event.key === (rtl ? 'ArrowLeft' : 'ArrowRight');
                const target = toPrevious ? neighbours.previous : toNext ? neighbours.next : undefined;
                if (target !== undefined) {
                    event.preventDefault();
                    move(key, target);
                }
            },
        }),
        // eslint-disable-next-line react-hooks/exhaustive-deps -- dropSide reads only dragging/over/indexOf
        [dragging, over, move, indexOf],
    );

    return { ordered, itemProps };
}
