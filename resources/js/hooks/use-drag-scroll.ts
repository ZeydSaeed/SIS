import { useEffect, useState } from 'react';

/** Elements a drag never starts on (fields, buttons, links…); the caller may add its own (e.g. a window's title bar). */
const DEFAULT_IGNORE = 'input, textarea, select, button, a, [role="combobox"], [data-drag-scroll-ignore]';

/**
 * Scroll an element by dragging its content (mouse or touch), not only by its scrollbar: the content follows the
 * pointer, keeps gliding a little after release and slows down smoothly. A press that barely moves is still a click;
 * after a real drag the click that follows is swallowed, so nothing is chosen by accident. Wheel and keyboard scrolling
 * are smooth (`scroll-behavior: smooth`).
 *
 * Returns a callback ref for the scrolling element (it may mount later, e.g. inside a dialog or a popup menu).
 */
export function useDragScroll<T extends HTMLElement>(
    enabled = true,
    ignore: string = DEFAULT_IGNORE,
): (node: T | null) => void {
    const [el, setEl] = useState<T | null>(null);

    useEffect(() => {
        if (!enabled || el === null) {
            return;
        }

        el.style.scrollBehavior = 'smooth';
        let pointerId: number | null = null;
        let startX = 0;
        let startY = 0;
        let startLeft = 0;
        let startTop = 0;
        let moved = false;
        let lastX = 0;
        let lastY = 0;
        let lastTime = 0;
        let velocityX = 0;
        let velocityY = 0;
        let glide = 0;
        /** Where a wheel scroll is gliding to (null = no wheel glide). */
        let wheelTarget: number | null = null;

        const stopGlide = (): void => {
            if (glide !== 0) {
                cancelAnimationFrame(glide);
                glide = 0;
            }
            wheelTarget = null;
        };

        // The wheel glides too: each notch moves the target, the list eases towards it and slows down.
        const onWheel = (event: WheelEvent): void => {
            if (event.defaultPrevented || event.ctrlKey || Math.abs(event.deltaY) < Math.abs(event.deltaX)) {
                return;
            }
            const max = el.scrollHeight - el.clientHeight;
            if (max <= 0) {
                return;
            }
            const delta = event.deltaMode === 1 ? event.deltaY * 16 : event.deltaMode === 2 ? event.deltaY * el.clientHeight : event.deltaY;
            const from = wheelTarget ?? el.scrollTop;
            const next = Math.max(0, Math.min(max, from + delta));
            if (next === from) {
                // At the top / bottom: the outer page scrolls instead.
                return;
            }
            event.preventDefault();
            if (wheelTarget === null) {
                stopGlide();
            }
            wheelTarget = next;
            el.style.scrollBehavior = 'auto';
            const step = (): void => {
                if (wheelTarget === null) {
                    glide = 0;

                    return;
                }
                const gap = wheelTarget - el.scrollTop;
                if (Math.abs(gap) < 0.5) {
                    el.scrollTop = wheelTarget;
                    wheelTarget = null;
                    glide = 0;
                    el.style.scrollBehavior = 'smooth';

                    return;
                }
                el.scrollTop += gap * 0.16;
                glide = requestAnimationFrame(step);
            };
            if (glide === 0) {
                glide = requestAnimationFrame(step);
            }
        };

        const onDown = (event: PointerEvent): void => {
            const marked = event as PointerEvent & { sisDragScrollHandled?: boolean };
            if (event.button !== 0 || marked.sisDragScrollHandled || (event.target instanceof Element && event.target.closest(ignore) !== null)) {
                return;
            }
            // Nested scroll areas (a list inside a scrolling form): the innermost one takes the drag.
            marked.sisDragScrollHandled = true;
            stopGlide();
            // No text selection while pressing on the content (a drag would otherwise select and auto-scroll).
            event.preventDefault();
            pointerId = event.pointerId;
            moved = false;
            startX = lastX = event.clientX;
            startY = lastY = event.clientY;
            startLeft = el.scrollLeft;
            startTop = el.scrollTop;
            lastTime = performance.now();
            velocityX = velocityY = 0;
        };

        const onMove = (event: PointerEvent): void => {
            if (pointerId !== event.pointerId) {
                return;
            }
            const dx = event.clientX - startX;
            const dy = event.clientY - startY;
            if (!moved) {
                if (Math.hypot(dx, dy) < 6) {
                    return;
                }
                moved = true;
                el.setPointerCapture(event.pointerId);
                el.style.scrollBehavior = 'auto';
                el.style.cursor = 'grabbing';
                el.style.userSelect = 'none';
            }
            event.preventDefault();
            el.scrollLeft = startLeft - dx;
            el.scrollTop = startTop - dy;
            const now = performance.now();
            const elapsed = Math.max(1, now - lastTime);
            velocityX = (event.clientX - lastX) / elapsed;
            velocityY = (event.clientY - lastY) / elapsed;
            lastX = event.clientX;
            lastY = event.clientY;
            lastTime = now;
        };

        const onUp = (event: PointerEvent): void => {
            if (pointerId !== event.pointerId) {
                return;
            }
            pointerId = null;
            if (!moved) {
                return;
            }
            el.style.cursor = '';
            el.style.userSelect = '';
            // The click that ends a drag chooses nothing.
            const swallow = (click: MouseEvent): void => {
                click.preventDefault();
                click.stopPropagation();
            };
            el.addEventListener('click', swallow, { capture: true, once: true });
            window.setTimeout(() => el.removeEventListener('click', swallow, { capture: true }), 0);
            // Keep gliding, slowing down smoothly.
            let vx = velocityX * 16;
            let vy = velocityY * 16;
            const step = (): void => {
                el.scrollLeft -= vx;
                el.scrollTop -= vy;
                vx *= 0.92;
                vy *= 0.92;
                if (Math.abs(vx) > 0.4 || Math.abs(vy) > 0.4) {
                    glide = requestAnimationFrame(step);
                } else {
                    glide = 0;
                    el.style.scrollBehavior = 'smooth';
                }
            };
            glide = requestAnimationFrame(step);
        };

        // While the button is held on the content, no text is selected (Chromium starts a selection even after
        // pointerdown.preventDefault()).
        const onSelectStart = (event: Event): void => {
            if (pointerId !== null) {
                event.preventDefault();
            }
        };

        el.addEventListener('selectstart', onSelectStart);
        el.addEventListener('pointerdown', onDown);
        el.addEventListener('pointermove', onMove);
        el.addEventListener('pointerup', onUp);
        el.addEventListener('pointercancel', onUp);
        el.addEventListener('wheel', onWheel, { passive: false });

        return () => {
            stopGlide();
            el.removeEventListener('selectstart', onSelectStart);
            el.removeEventListener('pointerdown', onDown);
            el.removeEventListener('pointermove', onMove);
            el.removeEventListener('pointerup', onUp);
            el.removeEventListener('pointercancel', onUp);
            el.removeEventListener('wheel', onWheel);
            el.style.scrollBehavior = '';
        };
    }, [el, enabled, ignore]);

    return setEl;
}
