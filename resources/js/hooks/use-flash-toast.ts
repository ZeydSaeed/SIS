import { router } from '@inertiajs/react';
import { useEffect, useRef } from 'react';
import { toast } from 'sonner';
import type { FlashToast } from '@/types/ui';

/**
 * Listens for Inertia flash toasts. Must NOT call usePage() — Toaster mounts
 * via withApp() as a sibling of the page tree and is outside Inertia context.
 */
export function useFlashToast(): void {
    const lastKeyRef = useRef<string | null>(null);

    useEffect(() => {
        return router.on('flash', (event) => {
            const flash = (event as CustomEvent).detail?.flash;
            const data = flash?.toast as FlashToast | undefined;

            if (!data || typeof data.message !== 'string' || data.message.trim() === '') {
                return;
            }

            const key = `${data.type}:${data.message}`;
            if (lastKeyRef.current === key) {
                return;
            }

            lastKeyRef.current = key;

            if (
                data.type === 'success'
                || data.type === 'info'
                || data.type === 'warning'
                || data.type === 'error'
            ) {
                toast[data.type](data.message);
            }
        });
    }, []);
}
