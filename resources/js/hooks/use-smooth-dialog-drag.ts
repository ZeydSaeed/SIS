import {
    createElement,
    useCallback,
    useEffect,
    useLayoutEffect,
    useRef,
    type PointerEvent as ReactPointerEvent,
    type ReactNode,
} from 'react';

type ResizeEdge = 'n' | 's' | 'e' | 'w' | 'ne' | 'nw' | 'se' | 'sw';

type MinSize = { width: number; height: number };

type Box = { left: number; top: number; width: number; height: number };

type Options = {
    /** When true, drag/resize are disabled. */
    disabled?: boolean;
    /** Edge resize handles (left/right/top/bottom + corners). */
    resizable?: boolean;
    minSize?: MinSize;
};

const RESIZE_EDGES: ResizeEdge[] = ['n', 's', 'e', 'w', 'ne', 'nw', 'se', 'sw'];

const DEFAULT_MIN: MinSize = { width: 420, height: 280 };

/** Above chrome/titlebar, ribbons, taskbar, windows, and list-select (≤110000). */
const ADMISSION_SHEET_Z_FLOOR = 200000;
let admissionSheetZCursor = ADMISSION_SHEET_Z_FLOOR;

function nextAdmissionSheetZ(): number {
    admissionSheetZCursor += 1;

    return admissionSheetZCursor;
}

/**
 * Admission sheet chrome:
 * - On open: CSS-centered (50%/50% + translate) and raised above other windows.
 * - On first drag/resize: pin to absolute left/top from the visual rect (no jump),
 *   then mutate size/position from the dragged edge or title bar.
 */
export function useSmoothDialogDrag(open: boolean, options: Options = {}) {
    const { disabled = false, resizable = false, minSize = DEFAULT_MIN } = options;
    const contentRef = useRef<HTMLDivElement | null>(null);
    const boxRef = useRef<Box | null>(null);
    const dragStart = useRef({ pointerX: 0, pointerY: 0, left: 0, top: 0 });
    const dragging = useRef(false);
    const resizeRef = useRef<{
        edge: ResizeEdge;
        pointerX: number;
        pointerY: number;
        left: number;
        top: number;
        width: number;
        height: number;
    } | null>(null);

    const bringToFront = useCallback(() => {
        const node = contentRef.current;
        if (node === null) {
            return;
        }

        node.style.setProperty('z-index', String(nextAdmissionSheetZ()), 'important');
    }, []);

    const applyBox = useCallback((box: Box) => {
        const node = contentRef.current;
        if (node === null) {
            return;
        }

        boxRef.current = box;
        node.setAttribute('data-placed', 'true');
        node.style.setProperty('top', `${Math.round(box.top)}px`, 'important');
        node.style.setProperty('left', `${Math.round(box.left)}px`, 'important');
        node.style.setProperty('right', 'auto', 'important');
        node.style.setProperty('bottom', 'auto', 'important');
        node.style.setProperty('transform', 'none', 'important');
        node.style.setProperty('translate', 'none', 'important');
        node.style.setProperty('--sis-sheet-w', `${Math.round(box.width)}px`);
        node.style.setProperty('--sis-sheet-h', `${Math.round(box.height)}px`);
    }, []);

    const clearPlacement = useCallback(() => {
        boxRef.current = null;
        dragging.current = false;
        resizeRef.current = null;
        const node = contentRef.current;
        if (node === null) {
            return;
        }

        node.dataset.dragging = 'false';
        node.removeAttribute('data-placed');
        node.style.removeProperty('--sis-sheet-w');
        node.style.removeProperty('--sis-sheet-h');
        node.style.removeProperty('top');
        node.style.removeProperty('left');
        node.style.removeProperty('right');
        node.style.removeProperty('bottom');
        node.style.removeProperty('transform');
        node.style.removeProperty('translate');
        node.style.removeProperty('z-index');
    }, []);

    /** Capture current on-screen rect into absolute left/top (keeps visual position). */
    const pinFromVisualRect = useCallback((): Box | null => {
        const node = contentRef.current;
        if (node === null) {
            return null;
        }

        if (boxRef.current !== null) {
            return boxRef.current;
        }

        const rect = node.getBoundingClientRect();
        const box: Box = {
            left: rect.left,
            top: rect.top,
            width: Math.max(minSize.width, rect.width),
            height: Math.max(minSize.height, rect.height),
        };
        applyBox(box);

        return box;
    }, [applyBox, minSize.height, minSize.width]);

    useLayoutEffect(() => {
        if (!open) {
            clearPlacement();
            return;
        }

        // Stay CSS-centered on open — do not convert to absolute until user drags/resizes.
        clearPlacement();
        bringToFront();
    }, [open, clearPlacement, bringToFront]);

    useEffect(() => {
        if (open) {
            bringToFront();
        }
    }, [open, bringToFront]);

    const onHeroPointerDown = useCallback(
        (event: ReactPointerEvent<HTMLElement>) => {
            bringToFront();
            if (disabled || event.button !== 0) {
                return;
            }

            const hit = event.target as HTMLElement | null;
            if (hit?.closest('button, a, input, select, textarea, label, .sis-window__controls, .sis-window__edge')) {
                return;
            }

            const box = pinFromVisualRect();
            if (box === null) {
                return;
            }

            dragging.current = true;
            dragStart.current = {
                pointerX: event.clientX,
                pointerY: event.clientY,
                left: box.left,
                top: box.top,
            };
            contentRef.current?.setAttribute('data-dragging', 'true');
            event.currentTarget.setPointerCapture(event.pointerId);
            event.preventDefault();
        },
        [bringToFront, disabled, pinFromVisualRect],
    );

    const onHeroPointerMove = useCallback(
        (event: ReactPointerEvent<HTMLElement>) => {
            if (!dragging.current || disabled) {
                return;
            }

            const box = boxRef.current;
            if (box === null) {
                return;
            }

            applyBox({
                ...box,
                left: dragStart.current.left + (event.clientX - dragStart.current.pointerX),
                top: dragStart.current.top + (event.clientY - dragStart.current.pointerY),
            });
        },
        [applyBox, disabled],
    );

    const endDrag = useCallback((event: ReactPointerEvent<HTMLElement>) => {
        if (!dragging.current) {
            return;
        }

        dragging.current = false;
        contentRef.current?.setAttribute('data-dragging', 'false');
        try {
            event.currentTarget.releasePointerCapture(event.pointerId);
        } catch {
            /* already released */
        }
    }, []);

    const onResizePointerDown = useCallback(
        (edge: ResizeEdge) => (event: ReactPointerEvent<HTMLDivElement>) => {
            if (!resizable || disabled || event.button !== 0) {
                return;
            }

            event.stopPropagation();
            event.preventDefault();
            bringToFront();

            const box = pinFromVisualRect();
            if (box === null) {
                return;
            }

            resizeRef.current = {
                edge,
                pointerX: event.clientX,
                pointerY: event.clientY,
                left: box.left,
                top: box.top,
                width: box.width,
                height: box.height,
            };
            event.currentTarget.setPointerCapture(event.pointerId);
        },
        [bringToFront, disabled, pinFromVisualRect, resizable],
    );

    const onResizePointerMove = useCallback(
        (event: ReactPointerEvent<HTMLDivElement>) => {
            const state = resizeRef.current;
            if (state === null || !resizable || disabled) {
                return;
            }

            const dx = event.clientX - state.pointerX;
            const dy = event.clientY - state.pointerY;
            let left = state.left;
            let top = state.top;
            let width = state.width;
            let height = state.height;

            if (state.edge.includes('e')) {
                width = Math.max(minSize.width, state.width + dx);
            }
            if (state.edge.includes('w')) {
                width = Math.max(minSize.width, state.width - dx);
                left = state.left + (state.width - width);
            }
            if (state.edge.includes('s')) {
                height = Math.max(minSize.height, state.height + dy);
            }
            if (state.edge.includes('n')) {
                height = Math.max(minSize.height, state.height - dy);
                top = state.top + (state.height - height);
            }

            applyBox({ left, top, width, height });
        },
        [applyBox, disabled, minSize.height, minSize.width, resizable],
    );

    const onResizePointerUp = useCallback((event: ReactPointerEvent<HTMLDivElement>) => {
        if (resizeRef.current === null) {
            return;
        }

        resizeRef.current = null;
        try {
            event.currentTarget.releasePointerCapture(event.pointerId);
        } catch {
            /* already released */
        }
    }, []);

    const resizeHandles: ReactNode =
        resizable && !disabled
            ? RESIZE_EDGES.map((edge) =>
                  createElement('div', {
                      key: edge,
                      className: `sis-window__edge sis-window__edge--${edge} sis-admission-sheet-dialog__edge`,
                      onPointerDown: onResizePointerDown(edge),
                      onPointerMove: onResizePointerMove,
                      onPointerUp: onResizePointerUp,
                      onPointerCancel: onResizePointerUp,
                  }),
              )
            : null;

    return {
        contentRef,
        bringToFront,
        resizeHandles,
        heroDragProps: {
            onPointerDown: onHeroPointerDown,
            onPointerMove: onHeroPointerMove,
            onPointerUp: endDrag,
            onPointerCancel: endDrag,
        },
    };
}
