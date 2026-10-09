import { useEffect, type RefObject } from 'react';

const VIEWPORT_GUTTER = 24;

/**
 * Window width that follows its content: after every render / content change, the sheet widens (never past the
 * screen) until no field, table or button row inside it is clipped. Height already follows the content (CSS:
 * fit-content, inner scroll past the screen height). A size the user chose by dragging an edge is left alone.
 */
export function useFitSheetToContent(contentRef: RefObject<HTMLElement | null>, enabled: boolean): void {
    useEffect(() => {
        if (!enabled || typeof window === 'undefined') {
            return;
        }
        let frame = 0;
        let observed: HTMLElement | null = null;
        const resize = typeof ResizeObserver === 'undefined' ? null : new ResizeObserver(() => schedule());
        const mutation = typeof MutationObserver === 'undefined' ? null : new MutationObserver(() => schedule());

        const fit = () => {
            const dialog = contentRef.current;
            if (dialog === null) {
                frame = requestAnimationFrame(fit);

                return;
            }
            if (observed !== dialog) {
                observed = dialog;
                resize?.observe(dialog);
                mutation?.observe(dialog, { childList: true, subtree: true });
            }
            if (dialog.classList.contains('sis-admission-sheet-dialog--maximized')) {
                return;
            }
            // The user resized the window by hand (the drag hook wrote its own width): keep their size.
            const inline = dialog.style.getPropertyValue('--sis-sheet-w');
            if (inline !== '' && inline !== dialog.dataset.fitWidth) {
                return;
            }
            let overflow = 0;
            // Every element of the window: a clipped label, field, table or button row means the window is too narrow.
            dialog.querySelectorAll<HTMLElement>('.sis-admission-sheet *').forEach((element) => {
                if (element.clientWidth > 0 && element.closest('[data-allow-x-scroll]') === null) {
                    overflow = Math.max(overflow, element.scrollWidth - element.clientWidth);
                }
            });
            if (overflow <= 1) {
                return;
            }
            const current = dialog.getBoundingClientRect().width;
            const next = Math.min(window.innerWidth - VIEWPORT_GUTTER, Math.ceil(current + overflow + 2));
            if (next > current + 1) {
                const value = `${next}px`;
                dialog.dataset.fitWidth = value;
                dialog.style.setProperty('--sis-sheet-w', value);
            }
        };
        const schedule = () => {
            cancelAnimationFrame(frame);
            frame = requestAnimationFrame(fit);
        };

        schedule();
        window.addEventListener('resize', schedule);

        return () => {
            cancelAnimationFrame(frame);
            window.removeEventListener('resize', schedule);
            resize?.disconnect();
            mutation?.disconnect();
        };
    }, [contentRef, enabled]);
}
