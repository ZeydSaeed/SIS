/**
 * Shared UI mutation helpers for Admission / Students / Enrollments.
 * Goal: tiny payloads, partial Inertia reloads, smooth preserveState updates.
 */

export type SisScalar = string | number | boolean | null | undefined;

export type SisSmoothVisitOptions = {
    preserveScroll: true;
    preserveState: true;
    async: true;
    only?: string[];
};

/** Baseline Inertia options for fast in-place mutations (no full remount). */
export function sisSmoothMutation(only?: string[]): SisSmoothVisitOptions {
    return only && only.length > 0
        ? { preserveScroll: true, preserveState: true, async: true, only }
        : { preserveScroll: true, preserveState: true, async: true };
}

/** Toggle a boolean query flag on the current path (Inertia-friendly). */
export function sisToggleQueryFlag(url: string, flag: string, enabled: boolean): string {
    const [path, query = ''] = url.split('?');
    const params = new URLSearchParams(query);
    if (enabled) {
        params.set(flag, '1');
    } else {
        params.delete(flag);
    }
    const next = params.toString();

    return next === '' ? path : `${path}?${next}`;
}

function normalizeScalar(value: SisScalar): string | number | boolean | null {
    if (value === undefined || value === '') {
        return null;
    }

    return value;
}

export function sisValuesEqual(left: SisScalar, right: SisScalar): boolean {
    return normalizeScalar(left) === normalizeScalar(right);
}

/**
 * Build a payload containing only changed fields.
 * - `always` keys are always included (required by FormRequest)
 * - `groups`: if any key in a group is dirty, include the whole group (atomic overlays)
 */
export function pickDirtyPayload(
    baseline: Record<string, SisScalar>,
    next: Record<string, SisScalar>,
    options?: {
        always?: string[];
        groups?: string[][];
    },
): Record<string, string | number | boolean | null> {
    const always = new Set(options?.always ?? []);
    const forced = new Set<string>();

    for (const group of options?.groups ?? []) {
        const dirty = group.some((key) => !sisValuesEqual(baseline[key], next[key]));
        if (dirty) {
            for (const key of group) {
                forced.add(key);
            }
        }
    }

    const payload: Record<string, string | number | boolean | null> = {};

    for (const [key, value] of Object.entries(next)) {
        if (always.has(key) || forced.has(key) || !sisValuesEqual(baseline[key], value)) {
            payload[key] = normalizeScalar(value);
        }
    }

    for (const key of always) {
        if (!(key in payload) && key in next) {
            payload[key] = normalizeScalar(next[key]);
        }
    }

    return payload;
}

/** True when payload has no meaningful changes beyond required anchors. */
export function isDirtyPayloadEmpty(
    payload: Record<string, string | number | boolean | null>,
    always: string[] = [],
): boolean {
    const alwaysSet = new Set(always);

    return Object.keys(payload).every((key) => alwaysSet.has(key));
}
