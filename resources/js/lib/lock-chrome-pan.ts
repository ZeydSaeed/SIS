const ALLOW_X_SCROLL = '[data-allow-x-scroll], .sis-titlebar__menu, .sis-ribbon__body';

function isExplicitHorizontalScroller(target: EventTarget | null): boolean {
    return target instanceof Element && Boolean(target.closest(ALLOW_X_SCROLL));
}

function resetDocumentScrollX(): void {
    if (window.scrollX === 0 && document.documentElement.scrollLeft === 0) {
        return;
    }

    window.scrollTo(0, window.scrollY);
    document.documentElement.scrollLeft = 0;
    document.body.scrollLeft = 0;
}

function onWheel(event: WheelEvent): void {
    if (Math.abs(event.deltaX) <= Math.abs(event.deltaY)) {
        return;
    }

    if (isExplicitHorizontalScroller(event.target)) {
        return;
    }

    event.preventDefault();
}

function onScroll(): void {
    resetDocumentScrollX();
}

function onMouseDown(event: MouseEvent): void {
    if (event.button !== 1) {
        return;
    }

    if (event.target instanceof Element && event.target.closest('a[href]')) {
        return;
    }

    event.preventDefault();
}

/**
 * Keeps the app chrome (dashboard, pages, sidebar) from panning left/right
 * when the mouse is dragged, the wheel is tilted, or the document overscrolls.
 * Nested surfaces marked with data-allow-x-scroll keep their own x-scroll.
 */
export function lockChromePan(): () => void {
    if (typeof window === 'undefined') {
        return () => {};
    }

    window.addEventListener('wheel', onWheel, { passive: false });
    window.addEventListener('scroll', onScroll, { passive: true });
    document.addEventListener('mousedown', onMouseDown);
    resetDocumentScrollX();

    return () => {
        window.removeEventListener('wheel', onWheel);
        window.removeEventListener('scroll', onScroll);
        document.removeEventListener('mousedown', onMouseDown);
    };
}
