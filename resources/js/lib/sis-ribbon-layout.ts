import { useEffect, useRef } from 'react';

/** Keep in sync with `--sis-ribbon-motion-ms` in `resources/css/app.css`. */
export const SIS_RIBBON_MOTION_MS = 320;

/** Slightly after CSS motion so ResizeObserver has the final stage height. */
export const SIS_RIBBON_LAYOUT_SETTLE_MS = 360;

export const SIS_RIBBON_LAYOUT_EVENT = 'sis:ribbon-layout';

export type SisRibbonLayoutDetail = {
    phase: 'start' | 'settled';
    open: boolean;
};

export function sisRibbonMotionMs(): number {
    if (
        typeof window !== 'undefined' &&
        window.matchMedia('(prefers-reduced-motion: reduce)').matches
    ) {
        return 0;
    }

    return SIS_RIBBON_MOTION_MS;
}

export function sisRibbonLayoutSettleMs(): number {
    const motion = sisRibbonMotionMs();

    return motion === 0 ? 0 : SIS_RIBBON_LAYOUT_SETTLE_MS;
}

export function dispatchSisRibbonLayout(detail: SisRibbonLayoutDetail): void {
    if (typeof window === 'undefined') {
        return;
    }

    window.dispatchEvent(
        new CustomEvent<SisRibbonLayoutDetail>(SIS_RIBBON_LAYOUT_EVENT, { detail }),
    );
}

/** Instant client clip while quiet server `per_page` sync catches up. */
export function clipRowsToFitPageSize<T>(rows: T[], fitPageSize: number): T[] {
    if (fitPageSize < 1 || rows.length <= fitPageSize) {
        return rows;
    }

    return rows.slice(0, fitPageSize);
}

export function fitAwareLastPage(
    total: number,
    fitPageSize: number,
    serverLastPage: number,
): number {
    if (total <= 0 || fitPageSize < 1) {
        return Math.max(1, serverLastPage);
    }

    return Math.max(1, Math.ceil(total / fitPageSize));
}

/**
 * Coalesce quiet `per_page` visits until ribbon/layout motion settles —
 * avoids Inertia refetch feel while the table already clips client-side.
 */
export function useDebouncedFitPageSync(
    fitPageSize: number,
    currentPerPage: number,
    sync: (nextPerPage: number) => void,
    enabled = true,
): void {
    const syncRef = useRef(sync);
    syncRef.current = sync;

    useEffect(() => {
        if (!enabled || fitPageSize === currentPerPage) {
            return;
        }

        const delay = sisRibbonLayoutSettleMs();
        const timer = window.setTimeout(() => {
            syncRef.current(fitPageSize);
        }, delay);

        return () => {
            window.clearTimeout(timer);
        };
    }, [currentPerPage, enabled, fitPageSize]);
}

/** Viewport y of the title bar + ribbon bottom edge (Word-tab ops pages only). */
export const SIS_CHROME_BOTTOM_VAR = '--sis-chrome-bottom';

/** Current chrome bottom in px, or null when the page does not reserve it. */
export function readSisChromeBottom(): number | null {
    if (typeof document === 'undefined') {
        return null;
    }

    const raw = document.documentElement.style.getPropertyValue(SIS_CHROME_BOTTOM_VAR);
    const value = Number.parseFloat(raw);

    return Number.isFinite(value) ? value : null;
}
