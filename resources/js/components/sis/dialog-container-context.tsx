import { createContext, useContext } from 'react';

/**
 * Where windows render. Normally `null` (the document body); while a page shows a full-screen element (the
 * timetable preview) it provides that element, because the browser draws nothing outside a full-screen element.
 */
export const DialogContainerContext = createContext<HTMLElement | null>(null);

export function useDialogContainer(): HTMLElement | null {
    return useContext(DialogContainerContext);
}
