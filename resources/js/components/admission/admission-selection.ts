import { useEffect, useId, useSyncExternalStore } from 'react';
import { clearPageAlignmentTarget } from '@/hooks/use-page-alignment';

const listeners = new Set<() => void>();
const clearers = new Map<string, () => void>();
const tableSelection = new Map<string, boolean>();

let tableSelected = false;

function notify(): void {
    listeners.forEach((listener) => listener());
}

function refreshTableSelected(): void {
    const next = [...tableSelection.values()].some(Boolean);

    if (next === tableSelected) {
        return;
    }

    tableSelected = next;
    notify();
}

export function clearAdmissionSelection(): void {
    clearPageAlignmentTarget();
    window.getSelection()?.removeAllRanges();

    const active = document.activeElement;

    if (
        active instanceof HTMLElement &&
        active.closest('.sis-admission-page') &&
        !active.closest('.sis-chrome, .sis-ribbon, .sis-titlebar')
    ) {
        active.blur();
    }

    clearers.forEach((clear) => clear());
}

export function useAdmissionSelection(): {
    hasTableSelection: boolean;
    clearSelection: () => void;
} {
    const hasTableSelection = useSyncExternalStore(
        (listener) => {
            listeners.add(listener);

            return () => listeners.delete(listener);
        },
        () => tableSelected,
        () => false,
    );

    return {
        hasTableSelection,
        clearSelection: clearAdmissionSelection,
    };
}

export function useAdmissionSelectionClearer(
    clear: () => void,
    hasSelection: boolean,
): void {
    const ownerId = useId();

    useEffect(() => {
        clearers.set(ownerId, clear);

        return () => {
            clearers.delete(ownerId);
        };
    }, [clear, ownerId]);

    useEffect(() => {
        tableSelection.set(ownerId, hasSelection);
        refreshTableSelected();

        return () => {
            tableSelection.delete(ownerId);
            refreshTableSelected();
        };
    }, [hasSelection, ownerId]);
}
