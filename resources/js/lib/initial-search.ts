/**
 * A page opened from another page can arrive with its own search («فتح الصفحة الأصلية» from the timetable passes the
 * name): the first non-empty value of the given query parameters, else ''.
 */
export function initialSearchParam(...names: string[]): string {
    if (typeof window === 'undefined') {
        return '';
    }
    const params = new URLSearchParams(window.location.search);
    for (const name of names) {
        const value = params.get(name)?.trim() ?? '';
        if (value !== '') {
            return value;
        }
    }

    return '';
}
