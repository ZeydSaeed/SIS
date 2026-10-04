import {
    createElement,
    useCallback,
    useEffect,
    useRef,
    type PointerEvent as ReactPointerEvent,
    type ReactNode,
    type RefObject,
} from 'react';

type Edge = 'n' | 's' | 'e' | 'w' | 'ne' | 'nw' | 'se' | 'sw';

type Box = { left: number; top: number; width: number; height: number };

const EDGES: Edge[] = ['n', 's', 'e', 'w', 'ne', 'nw', 'se', 'sw'];
const MIN_WIDTH = 280;
const MIN_HEIGHT = 160;

/** Header areas that move the window (dialog header, sheet / notice hero, or opt-in). */
const DRAG_HANDLE = '[data-slot="dialog-header"], .sis-admission-sheet__hero, [data-window-drag-handle]';
const INTERACTIVE = 'button, a, input, select, textarea, label, [role="button"], [contenteditable="true"], .sis-window__edge';

/**
 * Drag (by its header) + resize (invisible edge strips) for any dialog window.
 * Nothing changes until the user acts: the first drag/resize pins the window at
 * its current on-screen box with inline styles, so its look stays identical.
 */
export function useWindowMoveResize(
    nodeRef: RefObject<HTMLElement | null>,
    enabled: boolean,
): { onPointerDown: (event: ReactPointerEvent<HTMLElement>) => void; resizeHandles: ReactNode } {
    const cleanupRef = useRef<(() => void) | null>(null);

    useEffect(() => () => cleanupRef.current?.(), []);

    const apply = useCallback(
        (box: Box) => {
            const node = nodeRef.current;
            if (node === null) {
                return;
            }

            node.dataset.userPlaced = 'true';
            const set = (property: string, value: string) => node.style.setProperty(property, value, 'important');
            set('position', 'fixed');
            set('left', `${Math.round(box.left)}px`);
            set('top', `${Math.round(box.top)}px`);
            set('right', 'auto');
            set('bottom', 'auto');
            set('margin', '0');
            set('transform', 'none');
            set('translate', 'none');
            set('width', `${Math.round(box.width)}px`);
            set('height', `${Math.round(box.height)}px`);
            set('max-width', 'none');
            set('max-height', 'none');
        },
        [nodeRef],
    );

    const currentBox = useCallback((): Box | null => {
        const node = nodeRef.current;
        if (node === null) {
            return null;
        }
        const rect = node.getBoundingClientRect();

        return { left: rect.left, top: rect.top, width: rect.width, height: rect.height };
    }, [nodeRef]);

    const track = useCallback(
        (event: ReactPointerEvent<HTMLElement>, onMove: (dx: number, dy: number, start: Box) => Box) => {
            const start = currentBox();
            if (start === null) {
                return;
            }
            event.preventDefault();
            event.stopPropagation();
            apply(start);

            const originX = event.clientX;
            const originY = event.clientY;
            const move = (e: PointerEvent) => apply(onMove(e.clientX - originX, e.clientY - originY, start));
            const stop = () => {
                window.removeEventListener('pointermove', move);
                window.removeEventListener('pointerup', stop);
                window.removeEventListener('pointercancel', stop);
                cleanupRef.current = null;
            };

            cleanupRef.current?.();
            window.addEventListener('pointermove', move);
            window.addEventListener('pointerup', stop);
            window.addEventListener('pointercancel', stop);
            cleanupRef.current = stop;
        },
        [apply, currentBox],
    );

    const onPointerDown = useCallback(
        (event: ReactPointerEvent<HTMLElement>) => {
            if (!enabled || event.button !== 0) {
                return;
            }
            const target = event.target as HTMLElement | null;
            const handle = target?.closest(DRAG_HANDLE);
            if (!handle || !nodeRef.current?.contains(handle) || target?.closest(INTERACTIVE)) {
                return;
            }

            track(event, (dx, dy, start) => ({
                ...start,
                left: Math.min(Math.max(start.left + dx, 40 - start.width), window.innerWidth - 40),
                top: Math.min(Math.max(start.top + dy, 0), window.innerHeight - 32),
            }));
        },
        [enabled, nodeRef, track],
    );

    const resizeFrom = useCallback(
        (edge: Edge) => (event: ReactPointerEvent<HTMLElement>) => {
            if (event.button !== 0) {
                return;
            }

            track(event, (dx, dy, start) => {
                let { left, top, width, height } = start;
                if (edge.includes('e')) {
                    width = Math.max(MIN_WIDTH, start.width + dx);
                }
                if (edge.includes('w')) {
                    width = Math.max(MIN_WIDTH, start.width - dx);
                    left = start.left + (start.width - width);
                }
                if (edge.includes('s')) {
                    height = Math.max(MIN_HEIGHT, start.height + dy);
                }
                if (edge.includes('n')) {
                    height = Math.max(MIN_HEIGHT, start.height - dy);
                    top = start.top + (start.height - height);
                }

                return { left, top, width, height };
            });
        },
        [track],
    );

    const resizeHandles: ReactNode = enabled
        ? EDGES.map((edge) =>
              createElement('div', {
                  key: edge,
                  'aria-hidden': true,
                  className: `sis-window__edge sis-window__edge--${edge}`,
                  onPointerDown: resizeFrom(edge),
              }),
          )
        : null;

    return { onPointerDown, resizeHandles };
}
