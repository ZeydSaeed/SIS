import { useLayoutEffect, useState, type RefObject } from 'react';

type Options = {
    /** Used before the first successful measure. */
    fallbackRows?: number;
    minRows?: number;
    maxRows?: number;
    enabled?: boolean;
};

const DEFAULT_FALLBACK = 17;
const DEFAULT_MIN = 3;
const DEFAULT_MAX = 40;
const DEFAULT_ROW_PX = 36;

function resolveRowHeightPx(scroller: HTMLElement, table: HTMLTableElement | null): number {
    const card = scroller.closest('.sis-admission-drafts-table, .sis-curriculum-subjects-table');
    if (card instanceof HTMLElement) {
        const raw = getComputedStyle(card).getPropertyValue('--sis-admission-drafts-row-h').trim();
        if (raw !== '') {
            if (raw.endsWith('rem')) {
                const rem = Number.parseFloat(raw);
                const rootPx =
                    Number.parseFloat(getComputedStyle(document.documentElement).fontSize) || 16;
                if (Number.isFinite(rem) && rem > 0) {
                    return Math.max(20, Math.round(rem * rootPx));
                }
            }
            if (raw.endsWith('px')) {
                const px = Number.parseFloat(raw);
                if (Number.isFinite(px) && px > 0) {
                    return Math.max(20, Math.round(px));
                }
            }
        }
    }

    const sample = table?.tBodies[0]?.rows[0];
    if (sample) {
        const h = sample.getBoundingClientRect().height;
        if (h > 0) {
            return Math.max(20, Math.round(h));
        }
    }

    return DEFAULT_ROW_PX;
}

function resolveStage(scroller: HTMLElement): HTMLElement {
    const stage =
        scroller.closest('.sis-curriculum-table-stage') ??
        scroller.closest('section') ??
        scroller.closest('.sis-admission-page-body');

    return stage instanceof HTMLElement ? stage : scroller;
}

function outerBlockSize(el: HTMLElement): number {
    const rect = el.getBoundingClientRect();
    const style = getComputedStyle(el);

    return (
        rect.height +
        (Number.parseFloat(style.marginTop) || 0) +
        (Number.parseFloat(style.marginBottom) || 0)
    );
}

/**
 * Budget height for the table card from the stage (page body / section),
 * not from the card itself — so content-hug cards still adapt when the ribbon opens.
 */
function measurePageSize(scroller: HTMLElement): number | null {
    const card = scroller.closest('.sis-admission-drafts-table, .sis-curriculum-subjects-table');
    if (!(card instanceof HTMLElement)) {
        return null;
    }

    const stage = resolveStage(scroller);
    const stageRect = stage.getBoundingClientRect();
    const cardRect = card.getBoundingClientRect();
    if (stageRect.height < 40 || cardRect.width <= 0) {
        return null;
    }

    const stageStyle = getComputedStyle(stage);
    const gap = Number.parseFloat(stageStyle.rowGap || stageStyle.gap || '0') || 0;

    let reservedAfter = 0;
    let trailingCount = 0;
    let sibling: Element | null = card.nextElementSibling;
    while (sibling) {
        if (sibling instanceof HTMLElement) {
            const style = getComputedStyle(sibling);
            if (style.display !== 'none' && style.visibility !== 'hidden') {
                reservedAfter += outerBlockSize(sibling);
                trailingCount += 1;
            }
        }
        sibling = sibling.nextElementSibling;
    }

    const availableForCard =
        stageRect.bottom - cardRect.top - reservedAfter - gap * trailingCount - 2;
    if (availableForCard < 40) {
        return null;
    }

    const table = scroller.querySelector('table');
    const thead = table?.tHead;
    const headH = thead ? Math.ceil(thead.getBoundingClientRect().height) : 36;
    const cardStyle = getComputedStyle(card);
    const borderY =
        (Number.parseFloat(cardStyle.borderTopWidth) || 0) +
        (Number.parseFloat(cardStyle.borderBottomWidth) || 0);

    const availableForRows = availableForCard - headH - borderY - 2;
    if (availableForRows < 20) {
        return null;
    }

    const rowH = resolveRowHeightPx(scroller, table);

    return Math.floor(availableForRows / rowH);
}

/**
 * How many body rows fit in the remaining stage height (ribbon-aware).
 * Pair with content-hug table cards so the frame wraps rows 100%.
 */
export function useFitTablePageSize(
    scrollerRef: RefObject<HTMLElement | null>,
    options: Options = {},
): number {
    const fallback = options.fallbackRows ?? DEFAULT_FALLBACK;
    const minRows = options.minRows ?? DEFAULT_MIN;
    const maxRows = options.maxRows ?? DEFAULT_MAX;
    const enabled = options.enabled !== false;
    const [pageSize, setPageSize] = useState(fallback);

    useLayoutEffect(() => {
        if (!enabled) {
            return;
        }

        const scroller = scrollerRef.current;
        if (!scroller) {
            return;
        }

        const stage = resolveStage(scroller);
        const surface =
            scroller.closest('.sis-page-surface') ??
            scroller.closest('.sis-ops-hub') ??
            document.documentElement;

        let frame = 0;

        const apply = (): void => {
            const measured = measurePageSize(scroller);
            if (measured === null) {
                return;
            }

            const next = Math.min(maxRows, Math.max(minRows, measured));
            setPageSize((current) => (current === next ? current : next));
        };

        const schedule = (): void => {
            cancelAnimationFrame(frame);
            frame = requestAnimationFrame(apply);
        };

        schedule();

        const observer = new ResizeObserver(schedule);
        observer.observe(stage);
        if (surface instanceof Element && surface !== stage) {
            observer.observe(surface);
        }

        window.addEventListener('resize', schedule);

        return () => {
            cancelAnimationFrame(frame);
            observer.disconnect();
            window.removeEventListener('resize', schedule);
        };
    }, [enabled, maxRows, minRows, scrollerRef]);

    return pageSize;
}
