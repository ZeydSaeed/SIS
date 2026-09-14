import { ar } from '@/i18n/ar';

/** Arabic-first translator for operational UI. */
export function t(): typeof ar {
    return ar;
}

export function locale(): 'ar' {
    return 'ar';
}

export function direction(): 'rtl' {
    return 'rtl';
}
