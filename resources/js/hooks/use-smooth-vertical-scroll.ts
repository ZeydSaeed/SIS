import { useEffect, type RefObject } from 'react';

/** Wheel travel multiplier — higher = much faster table browsing. */
const SPEED = 3.1;
/** Catch-up rate — high so the view keeps up with fast wheel bursts. */
const LERP = 0.45;
const STOP_EPS = 0.6;
/** Pixels per wheel “line” (deltaMode === 1). */
const LINE_PX = 44;

/**
 * Fast vertical wheel browsing for table scrollers (students / enrollments).
 * Amplifies wheel deltas and lerps scrollTop so Windows notch jumps still
 * feel continuous without feeling sluggish.
 * Skips when another handler already called preventDefault (e.g. cell horizontal scroll)
 * or when the user prefers reduced motion.
 */
export function useSmoothVerticalScroll(
    scrollerRef: RefObject<HTMLElement | null>,
    enabled = true,
): void {
    useEffect(() => {
        const el = scrollerRef.current;

        if (!el || !enabled) {
            return;
        }

        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            return;
        }

        let target = el.scrollTop;
        let current = el.scrollTop;
        let rafId = 0;
        let running = false;

        const stop = () => {
            running = false;

            if (rafId !== 0) {
                cancelAnimationFrame(rafId);
                rafId = 0;
            }
        };

        const tick = () => {
            const max = Math.max(0, el.scrollHeight - el.clientHeight);
            target = Math.max(0, Math.min(max, target));
            current += (target - current) * LERP;

            if (Math.abs(target - current) < STOP_EPS) {
                current = target;
                el.scrollTop = current;
                stop();

                return;
            }

            el.scrollTop = current;
            rafId = requestAnimationFrame(tick);
        };

        const start = () => {
            if (running) {
                return;
            }

            running = true;
            current = el.scrollTop;
            rafId = requestAnimationFrame(tick);
        };

        const onWheel = (event: WheelEvent) => {
            if (event.defaultPrevented || event.ctrlKey) {
                return;
            }

            // Leave horizontal / tilt-wheel pans alone.
            if (Math.abs(event.deltaY) <= Math.abs(event.deltaX)) {
                return;
            }

            const max = el.scrollHeight - el.clientHeight;

            if (max <= 0) {
                return;
            }

            let delta = event.deltaY;

            if (event.deltaMode === 1) {
                delta *= LINE_PX;
            } else if (event.deltaMode === 2) {
                delta *= el.clientHeight * 0.95;
            }

            delta *= SPEED;

            const next = Math.max(0, Math.min(max, target + delta));

            // Already at edge in this direction — allow native overscroll handling to stop.
            if (
                next === target
                && ((delta < 0 && el.scrollTop <= 0) || (delta > 0 && el.scrollTop >= max - 0.5))
            ) {
                return;
            }

            event.preventDefault();
            target = next;
            start();
        };

        const onScroll = () => {
            if (!running) {
                target = el.scrollTop;
                current = el.scrollTop;
            }
        };

        el.addEventListener('wheel', onWheel, { passive: false });
        el.addEventListener('scroll', onScroll, { passive: true });

        return () => {
            el.removeEventListener('wheel', onWheel);
            el.removeEventListener('scroll', onScroll);
            stop();
        };
    }, [enabled, scrollerRef]);
}
