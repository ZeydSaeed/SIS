import { useCallback, useEffect, useRef, type PointerEvent as ReactPointerEvent } from 'react';

type DragOffset = { x: number; y: number };

type Options = {
    /** When maximized, drag is disabled and transform resets. */
    disabled?: boolean;
};

/**
 * Smooth dialog drag via title/hero bar — lerped transform over centered DialogContent.
 */
export function useSmoothDialogDrag(open: boolean, options: Options = {}) {
    const { disabled = false } = options;
    const contentRef = useRef<HTMLDivElement | null>(null);
    const current = useRef<DragOffset>({ x: 0, y: 0 });
    const target = useRef<DragOffset>({ x: 0, y: 0 });
    const dragStart = useRef({ pointerX: 0, pointerY: 0, originX: 0, originY: 0 });
    const dragging = useRef(false);
    const rafId = useRef(0);

    const applyTransform = useCallback(() => {
        const node = contentRef.current;
        if (node === null || disabled) {
            return;
        }

        const { x, y } = current.current;
        node.style.transform = `translate(calc(-50% + ${x}px), calc(-50% + ${y}px))`;
    }, [disabled]);

    const stopLoop = useCallback(() => {
        if (rafId.current !== 0) {
            cancelAnimationFrame(rafId.current);
            rafId.current = 0;
        }
    }, []);

    const tick = useCallback(() => {
        const ease = dragging.current ? 0.42 : 0.2;
        const cur = current.current;
        const tgt = target.current;
        cur.x += (tgt.x - cur.x) * ease;
        cur.y += (tgt.y - cur.y) * ease;
        applyTransform();

        const dx = Math.abs(tgt.x - cur.x);
        const dy = Math.abs(tgt.y - cur.y);
        if (dragging.current || dx > 0.2 || dy > 0.2) {
            rafId.current = requestAnimationFrame(tick);
            return;
        }

        cur.x = tgt.x;
        cur.y = tgt.y;
        applyTransform();
        rafId.current = 0;
    }, [applyTransform]);

    const ensureLoop = useCallback(() => {
        if (rafId.current === 0) {
            rafId.current = requestAnimationFrame(tick);
        }
    }, [tick]);

    const resetPosition = useCallback(() => {
        current.current = { x: 0, y: 0 };
        target.current = { x: 0, y: 0 };
        dragging.current = false;
        stopLoop();
        const node = contentRef.current;
        if (node !== null) {
            node.dataset.dragging = 'false';
            if (!disabled) {
                node.style.transform = 'translate(-50%, -50%)';
            } else {
                node.style.transform = '';
            }
        }
    }, [disabled, stopLoop]);

    useEffect(() => {
        if (!open) {
            resetPosition();
            return;
        }

        resetPosition();
        return () => {
            stopLoop();
        };
    }, [open, resetPosition, stopLoop]);

    useEffect(() => {
        if (disabled) {
            dragging.current = false;
            stopLoop();
            const node = contentRef.current;
            if (node !== null) {
                node.style.transform = '';
            }
        } else if (open) {
            applyTransform();
        }
    }, [disabled, open, applyTransform, stopLoop]);

    const bringToFront = useCallback(() => {
        const node = contentRef.current;
        if (node === null) {
            return;
        }

        const base = 25000;
        const next = base + (Date.now() % 1000);
        node.style.zIndex = String(next);
    }, []);

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
            if (hit?.closest('button, a, input, select, textarea, label, .sis-window__controls')) {
                return;
            }

            dragging.current = true;
            dragStart.current = {
                pointerX: event.clientX,
                pointerY: event.clientY,
                originX: target.current.x,
                originY: target.current.y,
            };
            contentRef.current?.setAttribute('data-dragging', 'true');
            event.currentTarget.setPointerCapture(event.pointerId);
            event.preventDefault();
            ensureLoop();
        },
        [bringToFront, disabled, ensureLoop],
    );

    const onHeroPointerMove = useCallback(
        (event: ReactPointerEvent<HTMLElement>) => {
            if (!dragging.current || disabled) {
                return;
            }

            target.current = {
                x: dragStart.current.originX + (event.clientX - dragStart.current.pointerX),
                y: dragStart.current.originY + (event.clientY - dragStart.current.pointerY),
            };
            ensureLoop();
        },
        [disabled, ensureLoop],
    );

    const endDrag = useCallback(
        (event: ReactPointerEvent<HTMLElement>) => {
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
            ensureLoop();
        },
        [ensureLoop],
    );

    return {
        contentRef,
        bringToFront,
        heroDragProps: {
            onPointerDown: onHeroPointerDown,
            onPointerMove: onHeroPointerMove,
            onPointerUp: endDrag,
            onPointerCancel: endDrag,
        },
    };
}
