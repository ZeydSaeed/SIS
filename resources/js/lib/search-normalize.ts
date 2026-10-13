/**
 * Smart search normalisation (Arabic-aware): case-folded, diacritics / tatweel dropped, alef / ya / ta-marbuta
 * variants unified, Arabic-Indic digits read as Latin ones. `map[i]` is the index in the original text of
 * normalised character `i` (so a match can be highlighted in the text as written).
 */
const FOLD: Record<string, string> = {
    'أ': 'ا',
    'إ': 'ا',
    'آ': 'ا',
    'ٱ': 'ا',
    'ى': 'ي',
    'ئ': 'ي',
    'ؤ': 'و',
    'ة': 'ه',
};

export function normalizeForSearch(text: string): {
    norm: string;
    map: number[];
} {
    let norm = '';
    const map: number[] = [];
    for (let i = 0; i < text.length; i++) {
        const code = text.charCodeAt(i);
        // harakat, superscript alef, tatweel
        if (
            (code >= 0x064b && code <= 0x065f) ||
            code === 0x0670 ||
            code === 0x0640
        ) {
            continue;
        }
        let ch = text[i];
        if (code >= 0x0660 && code <= 0x0669) {
            ch = String(code - 0x0660);
        } else if (code >= 0x06f0 && code <= 0x06f9) {
            ch = String(code - 0x06f0);
        } else {
            ch = FOLD[ch] ?? ch.toLowerCase();
        }
        norm += ch;
        map.push(i);
    }

    return { norm, map };
}

export const searchNeedle = (query: string): string =>
    normalizeForSearch(query.trim()).norm;

export const searchIncludes = (haystack: string, needle: string): boolean =>
    needle === '' || normalizeForSearch(haystack).norm.includes(needle);
