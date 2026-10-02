const STORAGE_PREFIX = 'sis.listPage.v1.';

export type SisListPageScope =
    | 'students'
    | 'enrollments'
    | 'admission'
    | 'curriculum';

function storageKey(scope: SisListPageScope): string {
    return `${STORAGE_PREFIX}${scope}`;
}

export function readStoredListPage(scope: SisListPageScope, fallback = 1): number {
    if (typeof window === 'undefined') {
        return fallback;
    }

    try {
        const raw = window.sessionStorage.getItem(storageKey(scope));
        if (raw === null || raw === '') {
            return fallback;
        }

        const parsed = Number.parseInt(raw, 10);
        if (!Number.isFinite(parsed) || parsed < 1) {
            return fallback;
        }

        return parsed;
    } catch {
        return fallback;
    }
}

export function writeStoredListPage(scope: SisListPageScope, page: number): void {
    if (typeof window === 'undefined') {
        return;
    }

    const next = Number.isFinite(page) && page >= 1 ? Math.floor(page) : 1;

    try {
        window.sessionStorage.setItem(storageKey(scope), String(next));
    } catch {
        // Ignore quota / private-mode failures.
    }
}
