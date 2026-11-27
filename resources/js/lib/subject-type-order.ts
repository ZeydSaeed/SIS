/** How a subject's types are listed everywhere: أساسي، اختصاص، نظري، عملي (display only; the stored set is unchanged). */
export const SUBJECT_TYPE_ORDER = [1, 2, 4, 3] as const;

/** The types in display order, each once (unknown values last). */
export function orderSubjectTypes<T extends number | string>(types: readonly T[]): T[] {
    const rank = (type: T): number => {
        const index = (SUBJECT_TYPE_ORDER as readonly number[]).indexOf(Number(type));

        return index === -1 ? SUBJECT_TYPE_ORDER.length : index;
    };

    return [...new Set(types)].sort((a, b) => rank(a) - rank(b));
}

/** A subject as the hour totals read it: its types, each type's hours, and its own hours. */
export type SubjectHoursSource = {
    subject_type: number;
    subject_types?: number[];
    subject_type_hours?: Record<string, number | null>;
    credit_hours?: number | null;
};

/** Weekly hours by type over some subjects, and in all. */
export type TypeHourTotals = { core: number; specialization: number; theory: number; practical: number; total: number };

/**
 * «مجموع الساعات»: each subject counts the hours of each of its types (نظري 2 + عملي 4 …); a subject without hours
 * on its types counts its own hours for its primary type.
 */
export function typeHourTotals(subjects: readonly SubjectHoursSource[]): TypeHourTotals {
    const byType: Record<number, number> = { 1: 0, 2: 0, 3: 0, 4: 0 };
    for (const subject of subjects) {
        const types = subject.subject_types && subject.subject_types.length > 0 ? subject.subject_types : [subject.subject_type];
        const given = types.filter((type) => {
            const hours = subject.subject_type_hours?.[String(type)];

            return hours !== null && hours !== undefined;
        });
        if (given.length > 0) {
            for (const type of given) {
                byType[type] = (byType[type] ?? 0) + Number(subject.subject_type_hours?.[String(type)] ?? 0);
            }
        } else if (subject.credit_hours !== null && subject.credit_hours !== undefined) {
            byType[subject.subject_type] = (byType[subject.subject_type] ?? 0) + Number(subject.credit_hours);
        }
    }

    return {
        core: byType[1] ?? 0,
        specialization: byType[2] ?? 0,
        theory: byType[4] ?? 0,
        practical: byType[3] ?? 0,
        total: (byType[1] ?? 0) + (byType[2] ?? 0) + (byType[3] ?? 0) + (byType[4] ?? 0),
    };
}
