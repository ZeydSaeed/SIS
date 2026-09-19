import { useSyncExternalStore } from 'react';

export const PAGE_TYPOGRAPHY_DEFAULT = 'default';

export const APPROVED_PAGE_FONTS = ['Segoe UI', 'Tahoma', 'Calibri', 'Aptos'] as const;
export const PAGE_FONT_SIZES = ['10', '11', '12', '14', '16', '18', '20', '24'] as const;

export type PageFontFamily =
    | typeof PAGE_TYPOGRAPHY_DEFAULT
    | (typeof APPROVED_PAGE_FONTS)[number];
export type PageFontSize = typeof PAGE_TYPOGRAPHY_DEFAULT | (typeof PAGE_FONT_SIZES)[number];

type PageTypographyState = {
    fontFamily: PageFontFamily;
    fontSize: PageFontSize;
};

export type UsePageTypographyReturn = PageTypographyState & {
    setFontFamily: (fontFamily: PageFontFamily) => void;
    setFontSize: (fontSize: PageFontSize) => void;
    resetTypography: () => void;
};

const FONT_STORAGE_KEY = 'sis-page-font';
const SIZE_STORAGE_KEY = 'sis-page-font-size';

const listeners = new Set<() => void>();

let currentTypography: PageTypographyState = {
    fontFamily: PAGE_TYPOGRAPHY_DEFAULT,
    fontSize: PAGE_TYPOGRAPHY_DEFAULT,
};

const isApprovedFont = (value: string): value is (typeof APPROVED_PAGE_FONTS)[number] =>
    (APPROVED_PAGE_FONTS as readonly string[]).includes(value);

const isApprovedSize = (value: string): value is (typeof PAGE_FONT_SIZES)[number] =>
    (PAGE_FONT_SIZES as readonly string[]).includes(value);

const readStoredFamily = (): PageFontFamily => {
    if (typeof window === 'undefined') {
        return PAGE_TYPOGRAPHY_DEFAULT;
    }

    const stored = localStorage.getItem(FONT_STORAGE_KEY);

    if (stored === PAGE_TYPOGRAPHY_DEFAULT || (stored !== null && isApprovedFont(stored))) {
        return stored;
    }

    return PAGE_TYPOGRAPHY_DEFAULT;
};

const readStoredSize = (): PageFontSize => {
    if (typeof window === 'undefined') {
        return PAGE_TYPOGRAPHY_DEFAULT;
    }

    const stored = localStorage.getItem(SIZE_STORAGE_KEY);

    if (stored === PAGE_TYPOGRAPHY_DEFAULT || (stored !== null && isApprovedSize(stored))) {
        return stored;
    }

    return PAGE_TYPOGRAPHY_DEFAULT;
};

const applyPageTypography = (next: PageTypographyState): void => {
    if (typeof document === 'undefined') {
        return;
    }

    const root = document.documentElement;

    if (next.fontFamily === PAGE_TYPOGRAPHY_DEFAULT) {
        root.removeAttribute('data-sis-font');
    } else {
        root.setAttribute('data-sis-font', next.fontFamily);
    }

    if (next.fontSize === PAGE_TYPOGRAPHY_DEFAULT) {
        root.removeAttribute('data-sis-font-size');
    } else {
        root.setAttribute('data-sis-font-size', next.fontSize);
    }
};

const persist = (next: PageTypographyState): void => {
    if (typeof window === 'undefined') {
        return;
    }

    localStorage.setItem(FONT_STORAGE_KEY, next.fontFamily);
    localStorage.setItem(SIZE_STORAGE_KEY, next.fontSize);
};

const notify = (): void => listeners.forEach((listener) => listener());

const commit = (next: PageTypographyState): void => {
    currentTypography = next;
    persist(next);
    applyPageTypography(next);
    notify();
};

const subscribe = (callback: () => void) => {
    listeners.add(callback);

    return () => listeners.delete(callback);
};

export function initializePageTypography(): void {
    if (typeof window === 'undefined') {
        return;
    }

    currentTypography = {
        fontFamily: readStoredFamily(),
        fontSize: readStoredSize(),
    };
    applyPageTypography(currentTypography);
}

const SERVER_TYPOGRAPHY: PageTypographyState = {
    fontFamily: PAGE_TYPOGRAPHY_DEFAULT,
    fontSize: PAGE_TYPOGRAPHY_DEFAULT,
};

export function usePageTypography(): UsePageTypographyReturn {
    const typography = useSyncExternalStore(
        subscribe,
        () => currentTypography,
        () => SERVER_TYPOGRAPHY,
    );

    return {
        fontFamily: typography.fontFamily,
        fontSize: typography.fontSize,
        setFontFamily: (fontFamily) => {
            commit({ ...currentTypography, fontFamily });
        },
        setFontSize: (fontSize) => {
            commit({ ...currentTypography, fontSize });
        },
        resetTypography: () => {
            commit({
                fontFamily: PAGE_TYPOGRAPHY_DEFAULT,
                fontSize: PAGE_TYPOGRAPHY_DEFAULT,
            });
        },
    };
}
