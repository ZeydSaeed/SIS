import { router } from '@inertiajs/react';
import { useSyncExternalStore } from 'react';

export const PAGE_ALIGNMENTS = ['start', 'center', 'end'] as const;

export type PageAlignment = (typeof PAGE_ALIGNMENTS)[number];

type PageAlignmentSnapshot = {
    alignment: PageAlignment | null;
    hasTarget: boolean;
};

export type UsePageAlignmentReturn = PageAlignmentSnapshot & {
    applyAlignment: (alignment: PageAlignment) => void;
};

const STORAGE_KEY = 'sis-page-alignment';
const TARGET_ATTR = 'data-sis-align-target';
const ALIGN_ATTR = 'data-sis-align';
const CHROME_SELECTOR =
    '.sis-chrome, .sis-ribbon, .sis-titlebar, .sis-dashboard-toggle, [data-slot="sidebar"], [data-sidebar="sidebar"]';
const SURFACE_SELECTOR =
    '.sis-page-surface, [role="dialog"], [data-slot="dialog-content"], [data-slot="sheet-content"]';

const listeners = new Set<() => void>();

let selectedElement: HTMLElement | null = null;
let selectedPath: string | null = null;
let lastRouteKey = '';
let initialized = false;
let stopRouter: (() => void) | null = null;

const EMPTY_SNAPSHOT: PageAlignmentSnapshot = {
    alignment: null,
    hasTarget: false,
};

const isPageAlignment = (value: string): value is PageAlignment =>
    (PAGE_ALIGNMENTS as readonly string[]).includes(value);

const readAlignment = (element: HTMLElement | null): PageAlignment | null => {
    if (!element) {
        return null;
    }

    const value = element.getAttribute(ALIGN_ATTR);

    return value !== null && isPageAlignment(value) ? value : null;
};

const snapshot = (): PageAlignmentSnapshot => {
    if (selectedElement && !document.contains(selectedElement)) {
        selectedElement = null;
    }

    return {
        alignment: readAlignment(selectedElement),
        hasTarget: selectedElement !== null,
    };
};

let currentSnapshot: PageAlignmentSnapshot = EMPTY_SNAPSHOT;

const notify = (): void => {
    currentSnapshot = snapshot();
    listeners.forEach((listener) => listener());
};

const cssEscape = (value: string): string => {
    if (typeof CSS !== 'undefined' && typeof CSS.escape === 'function') {
        return CSS.escape(value);
    }

    return value.replace(/[^a-zA-Z0-9_-]/g, '\\$&');
};

const isChrome = (node: EventTarget | null): boolean =>
    node instanceof Element && Boolean(node.closest(CHROME_SELECTOR));

const closestSurface = (element: Element): HTMLElement | null => {
    const surface = element.closest(SURFACE_SELECTOR);

    return surface instanceof HTMLElement ? surface : null;
};

const isInlineOnly = (element: HTMLElement): boolean => {
    const display = window.getComputedStyle(element).display;

    return display === 'inline' || display === 'contents' || display === 'ruby';
};

const prefersControl = (element: HTMLElement, surface: HTMLElement): HTMLElement | null => {
    const control = element.closest(
        'input, textarea, select, button, a, [contenteditable="true"], [role="button"], [role="textbox"], [role="combobox"]',
    );

    if (control instanceof HTMLElement && surface.contains(control) && !isChrome(control)) {
        return control;
    }

    const cell = element.closest('th, td');

    if (cell instanceof HTMLElement && surface.contains(cell)) {
        return cell;
    }

    return null;
};

const nearestAlignBox = (element: HTMLElement, surface: HTMLElement): HTMLElement => {
    const preferred = prefersControl(element, surface);

    if (preferred) {
        return preferred;
    }

    let node: HTMLElement | null = element;

    while (node && node !== surface) {
        if (node.tagName.toLowerCase() === 'svg') {
            node = node.parentElement;
            continue;
        }

        if (!isInlineOnly(node) && node.tagName !== 'TR') {
            return node;
        }

        node = node.parentElement;
    }

    return element === surface ? surface : (element.parentElement ?? element);
};

const resolveAlignTarget = (target: EventTarget | null): HTMLElement | null => {
    if (isChrome(target)) {
        return null;
    }

    const start =
        target instanceof HTMLElement
            ? target
            : target instanceof Node
              ? target.parentElement
              : null;

    if (!start) {
        return null;
    }

    const surface = closestSurface(start);

    if (!surface) {
        return null;
    }

    const fromSvg = start.closest('svg');

    if (fromSvg instanceof SVGElement && fromSvg.parentElement instanceof HTMLElement) {
        return nearestAlignBox(fromSvg.parentElement, surface);
    }

    return nearestAlignBox(start, surface);
};

const elementPath = (element: HTMLElement, root: HTMLElement): string => {
    const parts: string[] = [];
    let node: HTMLElement | null = element;

    while (node && node !== root) {
        if (node.id) {
            parts.unshift(`#${cssEscape(node.id)}`);
            break;
        }

        const parent: HTMLElement | null = node.parentElement;

        if (!parent) {
            break;
        }

        const current: HTMLElement = node;
        const tag = current.tagName.toLowerCase();
        const sameTag = Array.from(parent.children).filter(
            (child: Element) => child.tagName === current.tagName,
        );
        const index = sameTag.indexOf(current) + 1;
        parts.unshift(`${tag}:nth-of-type(${index})`);
        node = parent;
    }

    return parts.join('>');
};

type AlignmentMap = Record<string, Record<string, PageAlignment>>;

const readStore = (): AlignmentMap => {
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

        const next: AlignmentMap = {};

        for (const [path, entries] of Object.entries(parsed as Record<string, unknown>)) {
            if (!entries || typeof entries !== 'object') {
                continue;
            }

            const routeMap: Record<string, PageAlignment> = {};

            for (const [selector, alignment] of Object.entries(
                entries as Record<string, unknown>,
            )) {
                if (typeof alignment === 'string' && isPageAlignment(alignment)) {
                    routeMap[selector] = alignment;
                }
            }

            next[path] = routeMap;
        }

        return next;
    } catch {
        return {};
    }
};

const writeStore = (map: AlignmentMap): void => {
    if (typeof window === 'undefined') {
        return;
    }

    localStorage.setItem(STORAGE_KEY, JSON.stringify(map));
};

const routeKey = (): string => {
    if (typeof window === 'undefined') {
        return '/';
    }

    return window.location.pathname;
};

const persistAlignment = (element: HTMLElement, alignment: PageAlignment): void => {
    const surface = element.closest('.sis-page-surface');

    if (!(surface instanceof HTMLElement)) {
        return;
    }

    const path = elementPath(element, surface);

    if (!path) {
        return;
    }

    const map = readStore();
    const key = routeKey();
    map[key] = { ...(map[key] ?? {}), [path]: alignment };
    writeStore(map);
};

const markTarget = (element: HTMLElement | null): void => {
    document.querySelectorAll(`[${TARGET_ATTR}]`).forEach((node) => {
        node.removeAttribute(TARGET_ATTR);
    });

    selectedElement = element;

    if (element && document.contains(element)) {
        element.setAttribute(TARGET_ATTR, '');
        const surface = closestSurface(element);
        selectedPath = surface ? elementPath(element, surface) : null;
    } else {
        selectedElement = null;
        selectedPath = null;
    }

    notify();
};

const applyToElement = (element: HTMLElement, alignment: PageAlignment): void => {
    element.setAttribute(ALIGN_ATTR, alignment);
    persistAlignment(element, alignment);
};

export function restorePageAlignments(): void {
    if (typeof document === 'undefined') {
        return;
    }

    const key = routeKey();

    if (lastRouteKey !== '' && lastRouteKey !== key) {
        selectedElement = null;
        selectedPath = null;
        document.querySelectorAll(`[${TARGET_ATTR}]`).forEach((node) => {
            node.removeAttribute(TARGET_ATTR);
        });
    }

    lastRouteKey = key;

    const map = readStore()[key] ?? {};
    const pageSurface = document.querySelector('.sis-page-surface');

    if (pageSurface instanceof HTMLElement) {
        Object.entries(map).forEach(([selector, alignment]) => {
            try {
                const match = pageSurface.querySelector(selector);

                if (match instanceof HTMLElement) {
                    match.setAttribute(ALIGN_ATTR, alignment);
                }
            } catch {
                // Selector may be stale after a page structure change.
            }
        });
    }

    const surfaces = document.querySelectorAll(SURFACE_SELECTOR);

    if (selectedPath) {
        for (const surface of surfaces) {
            if (!(surface instanceof HTMLElement)) {
                continue;
            }

            try {
                const match = surface.querySelector(selectedPath);

                if (match instanceof HTMLElement) {
                    markTarget(match);
                    return;
                }
            } catch {
                // Selector may be stale after a page structure change.
            }
        }
    }

    if (selectedElement && document.contains(selectedElement)) {
        selectedElement.setAttribute(TARGET_ATTR, '');
        notify();
        return;
    }

    if (selectedElement !== null || selectedPath !== null) {
        markTarget(null);
    }
}

export function applyPageAlignment(alignment: PageAlignment): void {
    if (typeof document === 'undefined') {
        return;
    }

    if (selectedElement && !document.contains(selectedElement)) {
        selectedElement = null;
    }

    let target = selectedElement;

    if (!target) {
        target = resolveAlignTarget(document.activeElement);
    }

    if (!target) {
        const selection = window.getSelection();
        const anchor = selection?.anchorNode ?? null;
        target = resolveAlignTarget(anchor);
    }

    if (!target) {
        return;
    }

    applyToElement(target, alignment);
    markTarget(target);
}

const selectFromEvent = (event: Event): void => {
    if (isChrome(event.target)) {
        return;
    }

    const target = resolveAlignTarget(event.target);

    if (!target) {
        return;
    }

    markTarget(target);
};

const subscribe = (callback: () => void) => {
    listeners.add(callback);

    return () => listeners.delete(callback);
};

export function initializePageAlignment(): void {
    if (typeof window === 'undefined' || initialized) {
        return;
    }

    initialized = true;

    document.addEventListener('pointerdown', selectFromEvent, true);
    document.addEventListener('focusin', selectFromEvent);
    stopRouter = router.on('success', () => {
        requestAnimationFrame(() => {
            restorePageAlignments();
        });
    });

    restorePageAlignments();
}

export function teardownPageAlignment(): void {
    if (!initialized) {
        return;
    }

    document.removeEventListener('pointerdown', selectFromEvent, true);
    document.removeEventListener('focusin', selectFromEvent);
    stopRouter?.();
    stopRouter = null;
    initialized = false;
}

export function usePageAlignment(): UsePageAlignmentReturn {
    const state = useSyncExternalStore(subscribe, () => currentSnapshot, () => EMPTY_SNAPSHOT);

    return {
        alignment: state.alignment,
        hasTarget: state.hasTarget,
        applyAlignment: applyPageAlignment,
    };
}
