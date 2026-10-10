/** Multi-value ribbon filters travel as one CSV query value: `class_id=1,2,3` (read by `App\Http\Support\QueryIdList`). */
export function encodeIds(values: ReadonlyArray<string | number>): string | undefined {
    const clean = values.map(String).filter((value) => value !== '');

    return clean.length === 0 ? undefined : clean.join(',');
}

export function parseIds(value: string | null | undefined): string[] {
    return (value ?? '')
        .split(',')
        .map((part) => part.trim())
        .filter((part) => part !== '');
}

/** Same set of ids (order does not matter). */
export function sameIds(a: ReadonlyArray<number> | null | undefined, b: ReadonlyArray<number> | null | undefined): boolean {
    const left = [...(a ?? [])].sort((x, y) => x - y);
    const right = [...(b ?? [])].sort((x, y) => x - y);

    return left.length === right.length && left.every((value, index) => value === right[index]);
}

/** A multi-choice selection kept as one string (names / keys joined by `|`; '' = nothing chosen = «الكل»). */
export function splitSelection(value: string): string[] {
    return value === '' ? [] : value.split('|');
}

export function joinSelection(values: ReadonlyArray<string>): string {
    return values.join('|');
}
