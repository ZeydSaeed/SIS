import { useCallback, useState, type RefObject } from 'react';

/**
 * Maximize / restore for admission-style sheet dialogs.
 * Clears absolute placement vars so the maximized CSS class can take over.
 */
export function useSheetMaximize(contentRef: RefObject<HTMLElement | null>) {
    const [maximized, setMaximized] = useState(false);

    const toggleMaximize = useCallback(() => {
        setMaximized((current) => {
            const next = !current;
            const node = contentRef.current;

            if (node !== null && next) {
                node.removeAttribute('data-placed');
                node.style.removeProperty('--sis-sheet-w');
                node.style.removeProperty('--sis-sheet-h');
                node.style.removeProperty('top');
                node.style.removeProperty('left');
                node.style.removeProperty('right');
                node.style.removeProperty('bottom');
                node.style.removeProperty('transform');
                node.style.removeProperty('translate');
            }

            return next;
        });
    }, [contentRef]);

    return {
        maximized,
        toggleMaximize,
        maximizeClassName: maximized ? ' sis-admission-sheet-dialog--maximized' : '',
    };
}
