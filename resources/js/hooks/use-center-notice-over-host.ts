import { useLayoutEffect, type RefObject } from 'react';

const HOST_SELECTORS = [
    '.sis-student-enrollment-sheet[data-slot="dialog-content"]',
    '.sis-admission-accepted-dialog[data-slot="dialog-content"]',
    '.sis-admission-draft-dialog[data-slot="dialog-content"]',
    '.sis-admission-sheet-dialog[data-slot="dialog-content"]',
] as const;

function resolveHostSheet(): HTMLElement | null {
    for (const selector of HOST_SELECTORS) {
        const nodes = document.querySelectorAll(selector);
        for (let i = nodes.length - 1; i >= 0; i -= 1) {
            const node = nodes[i];
            if (!(node instanceof HTMLElement)) {
                continue;
            }
            // Skip the notice itself if it also carries sheet classes.
            if (
                node.classList.contains('sis-notice-sheet')
                || node.classList.contains('sis-message-dialog')
                || node.classList.contains('sis-confirm-dialog')
            ) {
                continue;
            }
            const rect = node.getBoundingClientRect();
            if (rect.width > 0 && rect.height > 0) {
                return node;
            }
        }
    }

    return null;
}

/**
 * Center a notice dialog over the active sheet (enrollment / admission),
 * or over the viewport when no host sheet is open.
 */
export function useCenterNoticeOverHost(
    open: boolean,
    nodeRef: RefObject<HTMLElement | null>,
): void {
    useLayoutEffect(() => {
        if (!open) {
            return;
        }

        const place = () => {
            const node = nodeRef.current;
            // Moved / resized by the user (useWindowMoveResize) — keep their placement.
            if (node === null || node.dataset.userPlaced === 'true') {
                return;
            }

            const host = resolveHostSheet();
            if (host !== null) {
                const rect = host.getBoundingClientRect();
                const centerX = rect.left + rect.width / 2;
                const centerY = rect.top + rect.height / 2;
                node.style.setProperty('left', `${centerX}px`, 'important');
                node.style.setProperty('top', `${centerY}px`, 'important');
            } else {
                node.style.setProperty('left', '50%', 'important');
                node.style.setProperty('top', '50%', 'important');
            }

            node.style.setProperty('right', 'auto', 'important');
            node.style.setProperty('bottom', 'auto', 'important');
            node.style.setProperty('margin', '0', 'important');
            node.style.setProperty('margin-right', '0', 'important');
            node.style.setProperty('transform', 'translate(-50%, -50%)', 'important');
        };

        place();
        // Re-measure after paint (notice size / host drag).
        const raf = window.requestAnimationFrame(place);
        window.addEventListener('resize', place);
        window.addEventListener('scroll', place, true);

        const host = resolveHostSheet();
        const observer =
            typeof ResizeObserver !== 'undefined' ? new ResizeObserver(place) : null;
        if (host !== null && observer !== null) {
            observer.observe(host);
        }
        if (nodeRef.current !== null && observer !== null) {
            observer.observe(nodeRef.current);
        }

        const mo = new MutationObserver(place);
        mo.observe(document.body, {
            attributes: true,
            subtree: true,
            attributeFilter: ['style', 'class', 'data-placed', 'data-state'],
        });

        return () => {
            window.cancelAnimationFrame(raf);
            window.removeEventListener('resize', place);
            window.removeEventListener('scroll', place, true);
            observer?.disconnect();
            mo.disconnect();
            const node = nodeRef.current;
            if (node !== null) {
                node.style.removeProperty('left');
                node.style.removeProperty('top');
                node.style.removeProperty('right');
                node.style.removeProperty('bottom');
                node.style.removeProperty('margin');
                node.style.removeProperty('margin-right');
                node.style.removeProperty('transform');
            }
        };
    }, [open, nodeRef]);
}
