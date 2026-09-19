import { router } from '@inertiajs/react';
import { useSyncExternalStore } from 'react';

export type PageTextStyleFlag = 'bold' | 'italic' | 'underline';

export type PageTextStyleState = {
    bold: boolean;
    italic: boolean;
    underline: boolean;
};

export type UsePageTextStyleReturn = PageTextStyleState & {
    toggleStyle: (flag: PageTextStyleFlag) => void;
};

const STORAGE_KEY = 'sis-page-text-style';
const BOLD_ATTR = 'data-sis-bold';
const ITALIC_ATTR = 'data-sis-italic';
const UNDERLINE_ATTR = 'data-sis-underline';

const DEFAULT_STYLE: PageTextStyleState = {
    bold: false,
    italic: false,
    underline: false,
};

const listeners = new Set<() => void>();

let currentStyle: PageTextStyleState = DEFAULT_STYLE;
let currentRoute = '/';
let initialized = false;
let stopRouter: (() => void) | null = null;

const routeKey = (): string => {
    if (typeof window === 'undefined') {
        return '/';
    }

    return window.location.pathname;
};

const isStyleState = (value: unknown): value is PageTextStyleState => {
    if (!value || typeof value !== 'object') {
        return false;
    }

    const record = value as Record<string, unknown>;

    return (
        typeof record.bold === 'boolean' &&
        typeof record.italic === 'boolean' &&
        typeof record.underline === 'boolean'
    );
};

const readStore = (): Record<string, PageTextStyleState> => {
    if (typeof window === 'undefined') {
        return {};
    }

    try {
        const raw = localStorage.getItem(STORAGE_KEY);

        if (!raw) {
            return {};
        }

        const parsed: unknown = JSON.parse(raw);

        if (!parsed || typeof parsed !== 'object') {
            return {};
        }

        const next: Record<string, PageTextStyleState> = {};

        for (const [path, style] of Object.entries(parsed as Record<string, unknown>)) {
            if (isStyleState(style)) {
                next[path] = style;
            }
        }

        return next;
    } catch {
        return {};
    }
};

const writeStore = (map: Record<string, PageTextStyleState>): void => {
    if (typeof window === 'undefined') {
        return;
    }

    localStorage.setItem(STORAGE_KEY, JSON.stringify(map));
};

const persist = (path: string, style: PageTextStyleState): void => {
    const map = readStore();

    if (!style.bold && !style.italic && !style.underline) {
        delete map[path];
    } else {
        map[path] = style;
    }

    writeStore(map);
};

const applyToSurface = (style: PageTextStyleState): void => {
    if (typeof document === 'undefined') {
        return;
    }

    const surface = document.querySelector('.sis-page-surface');

    if (!(surface instanceof HTMLElement)) {
        return;
    }

    surface.toggleAttribute(BOLD_ATTR, style.bold);
    surface.toggleAttribute(ITALIC_ATTR, style.italic);
    surface.toggleAttribute(UNDERLINE_ATTR, style.underline);
};

const notify = (): void => listeners.forEach((listener) => listener());

const readStyleForRoute = (path: string): PageTextStyleState => {
    const stored = readStore()[path];

    return stored ? { ...stored } : { ...DEFAULT_STYLE };
};

export function restorePageTextStyle(): void {
    currentRoute = routeKey();
    currentStyle = readStyleForRoute(currentRoute);
    applyToSurface(currentStyle);
    notify();
}

export function togglePageTextStyle(flag: PageTextStyleFlag): void {
    currentRoute = routeKey();
    currentStyle = {
        ...currentStyle,
        [flag]: !currentStyle[flag],
    };
    persist(currentRoute, currentStyle);
    applyToSurface(currentStyle);
    notify();
}

const subscribe = (callback: () => void) => {
    listeners.add(callback);

    return () => listeners.delete(callback);
};

export function initializePageTextStyle(): void {
    if (typeof window === 'undefined' || initialized) {
        return;
    }

    initialized = true;
    restorePageTextStyle();
    stopRouter = router.on('success', () => {
        requestAnimationFrame(() => {
            restorePageTextStyle();
        });
    });
}

export function teardownPageTextStyle(): void {
    if (!initialized) {
        return;
    }

    stopRouter?.();
    stopRouter = null;
    initialized = false;
}

export function usePageTextStyle(): UsePageTextStyleReturn {
    const style = useSyncExternalStore(
        subscribe,
        () => currentStyle,
        () => DEFAULT_STYLE,
    );

    return {
        bold: style.bold,
        italic: style.italic,
        underline: style.underline,
        toggleStyle: togglePageTextStyle,
    };
}
