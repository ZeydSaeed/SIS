import { ar, type ArCatalog } from '@/i18n/ar';
import { en } from '@/i18n/en';

export type Locale = 'ar' | 'en';

export const LOCALES: readonly Locale[] = ['ar', 'en'];

/** The language the server rendered the page in (<html lang>, from the `locale` cookie — see HandleLocale). */
export function locale(): Locale {
    return typeof document !== 'undefined' &&
        document.documentElement.lang.startsWith('en')
        ? 'en'
        : 'ar';
}

export function direction(): 'rtl' | 'ltr' {
    return locale() === 'ar' ? 'rtl' : 'ltr';
}

function merge(base: unknown, over: unknown): unknown {
    if (
        typeof base !== 'object' ||
        base === null ||
        Array.isArray(base) ||
        typeof over !== 'object' ||
        over === null
    ) {
        return over ?? base;
    }
    const out: Record<string, unknown> = { ...(base as object) };
    for (const [key, value] of Object.entries(over)) {
        out[key] = merge(out[key], value);
    }

    return out;
}

let english: ArCatalog | null = null;

/** UI strings of the current language; English falls back to Arabic key by key. */
export function t(): ArCatalog {
    if (locale() === 'ar') {
        return ar;
    }
    english ??= merge(ar, en) as ArCatalog;

    return english;
}

/** Switch the interface language: the cookie is read by the server, the page reloads in the new direction. */
export function setLocale(next: Locale): void {
    document.cookie = `locale=${next};path=/;max-age=${365 * 24 * 60 * 60};SameSite=Lax`;
    window.location.reload();
}
