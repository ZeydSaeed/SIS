import { useLayoutEffect, useRef, type RefObject } from 'react';

export type ManySheetPageFitMode = 'hug' | 'fill';

type Options = {
    /** Dialog content root (receives --sis-sheet-h in hug mode). */
    contentRef: RefObject<HTMLElement | null>;
    /** Horizontal page scroller. */
    scrollerRef: RefObject<HTMLElement | null>;
    /** Number of pages; hook is idle when ≤ 1. */
    count: number;
    /** Skip fitting while maximized. */
    maximized?: boolean;
    /**
     * hug — dialog height follows the active page form (enrollment / curriculum).
     * fill — keep dialog size; each page fills scroller client box (student records).
     */
    mode?: ManySheetPageFitMode;
    /** Extra deps that rebuild page nodes (e.g. id key). */
    resetKey?: string | number;
};

/**
 * Multi-item sheet pages: lock each page to the scroller viewport width so the
 * first (and every) item snaps fully into the window — no peek of the next page.
 */
export function useManySheetPageFit({
    contentRef,
    scrollerRef,
    count,
    maximized = false,
    mode = 'hug',
    resetKey = '',
}: Options): void {
    const activePageIndexRef = useRef(0);

    useLayoutEffect(() => {
        if (count <= 1 || maximized) {
            contentRef.current?.style.removeProperty('--sis-sheet-h');
            return;
        }

        const dialog = contentRef.current;
        const scroller = scrollerRef.current;
        if (!dialog || !scroller) {
            return;
        }

        const pages = Array.from(
            scroller.querySelectorAll<HTMLElement>('.sis-student-sheet-dialog__many-page'),
        );
        if (pages.length === 0) {
            return;
        }

        let lastAppliedHeight = 0;
        let frame = 0;
        activePageIndexRef.current = 0;

        const pageWidthPx = (): number => Math.max(1, Math.round(scroller.clientWidth));
        const pageHeightPx = (): number => Math.max(1, Math.round(scroller.clientHeight));

        const syncPageBox = (): void => {
            const width = pageWidthPx();
            const height = mode === 'fill' ? pageHeightPx() : null;

            for (const page of pages) {
                page.style.flex = `0 0 ${width}px`;
                page.style.width = `${width}px`;
                page.style.minWidth = `${width}px`;
                page.style.maxWidth = `${width}px`;

                if (height !== null) {
                    page.style.height = `${height}px`;
                    page.style.minHeight = `${height}px`;
                    page.style.maxHeight = `${height}px`;
                }
            }
        };

        const snapToIndex = (index: number): void => {
            const page = pages[index];
            if (!page) {
                return;
            }

            page.scrollIntoView({
                behavior: 'instant',
                block: 'nearest',
                inline: 'start',
            });
        };

        const resolveIndexFromScroll = (): number => {
            const width = pageWidthPx();
            const raw = Math.abs(scroller.scrollLeft) / width;

            return Math.max(0, Math.min(pages.length - 1, Math.round(raw)));
        };

        const fitActivePage = (pageIndex?: number, snap = false): void => {
            if (
                dialog.dataset.placed === 'true' ||
                dialog.classList.contains('sis-admission-sheet-dialog--maximized')
            ) {
                return;
            }

            syncPageBox();

            const index =
                pageIndex !== undefined
                    ? Math.max(0, Math.min(pageIndex, pages.length - 1))
                    : activePageIndexRef.current;
            activePageIndexRef.current = index;

            if (snap) {
                snapToIndex(index);
            }

            if (mode === 'fill') {
                // Width/height box sync is enough; keep dialog CSS height.
                if (snap || index === 0) {
                    snapToIndex(index);
                }
                return;
            }

            const page = pages[index];
            const form = page?.querySelector<HTMLElement>('.sis-admission-draft-form');
            const hero = dialog.querySelector<HTMLElement>('.sis-student-sheet-dialog__shared-hero');
            const contentHeight = Math.ceil(
                form?.getBoundingClientRect().height ?? page?.getBoundingClientRect().height ?? 0,
            );
            const heroHeight = Math.ceil(hero?.getBoundingClientRect().height ?? 0);
            const total = heroHeight + contentHeight + 4;

            if (total > 0 && Math.abs(total - lastAppliedHeight) > 1) {
                lastAppliedHeight = total;
                dialog.style.setProperty('--sis-sheet-h', `${total}px`);
                syncPageBox();
                if (snap || index === 0) {
                    snapToIndex(index);
                }
            }
        };

        const scheduleFit = (pageIndex?: number, snap = false): void => {
            cancelAnimationFrame(frame);
            frame = requestAnimationFrame(() => {
                fitActivePage(pageIndex, snap);
            });
        };

        const onScroll = (): void => {
            scheduleFit(resolveIndexFromScroll(), false);
        };

        const resizeObserver = new ResizeObserver(() => {
            scheduleFit(activePageIndexRef.current, false);
        });

        resizeObserver.observe(scroller);
        resizeObserver.observe(dialog);
        for (const page of pages) {
            resizeObserver.observe(page);
            const form = page.querySelector('.sis-admission-draft-form');
            if (form) {
                resizeObserver.observe(form);
            }
        }

        scroller.addEventListener('scroll', onScroll, { passive: true });

        syncPageBox();
        snapToIndex(0);
        scheduleFit(0, true);
        const secondPass = window.setTimeout(() => scheduleFit(0, true), 50);
        const thirdPass = window.setTimeout(() => scheduleFit(0, true), 150);

        return () => {
            window.clearTimeout(secondPass);
            window.clearTimeout(thirdPass);
            cancelAnimationFrame(frame);
            scroller.removeEventListener('scroll', onScroll);
            resizeObserver.disconnect();
            for (const page of pages) {
                page.style.removeProperty('flex');
                page.style.removeProperty('width');
                page.style.removeProperty('min-width');
                page.style.removeProperty('max-width');
                page.style.removeProperty('height');
                page.style.removeProperty('min-height');
                page.style.removeProperty('max-height');
            }
            dialog.style.removeProperty('--sis-sheet-h');
        };
    }, [contentRef, scrollerRef, count, maximized, mode, resetKey]);
}
