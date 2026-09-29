/** Shared class / section pick-lists for enrollment + academic→vocational transfer. */
export const SIS_CLASS_OPTIONS = [
    { value: '1', label: 'الأول' },
    { value: '2', label: 'الثاني' },
    { value: '3', label: 'الثالث' },
] as const;

export const SIS_SECTION_OPTIONS = [
    { value: 'A', label: 'A' },
    { value: 'B', label: 'B' },
    { value: 'C', label: 'C' },
] as const;

/** UI code → Arabic / localized aliases used in seeded section names & codes. */
const SECTION_ALIASES: Record<string, readonly string[]> = {
    A: ['A', 'أ', 'ا', 'شعبة أ', 'شعبة ا'],
    B: ['B', 'ب', 'شعبة ب'],
    C: ['C', 'ج', 'شعبة ج'],
};

export type SisClassOption = (typeof SIS_CLASS_OPTIONS)[number];
export type SisSectionOption = (typeof SIS_SECTION_OPTIONS)[number];

export type SisClassRef = {
    id: number;
    code: string;
    name: string;
    grade_level_id?: number;
};

export type SisSectionRef = {
    id: number;
    class_id: number;
    code: string;
    name: string;
};

export function sisClassLabel(value: string): string {
    return SIS_CLASS_OPTIONS.find((item) => item.value === value)?.label ?? '';
}

export function sisClassSelectOptions(): Array<{ value: string; label: string }> {
    return SIS_CLASS_OPTIONS.map((item) => ({ value: item.value, label: item.label }));
}

export function sisSectionSelectOptions(): Array<{ value: string; label: string }> {
    return SIS_SECTION_OPTIONS.map((item) => ({ value: item.value, label: item.label }));
}

export function resolveSisClassId(classKey: string, classes: SisClassRef[]): number | null {
    const label = sisClassLabel(classKey);
    if (label === '') {
        return null;
    }

    const byName = classes.find(
        (item) =>
            item.name.trim() === label
            || item.name.includes(label)
            || item.code.trim() === classKey
            || item.code.trim().toUpperCase() === `G${classKey}`
            || item.code.trim().toUpperCase() === `CLS-${classKey}`,
    );
    if (byName !== undefined) {
        return byName.id;
    }

    const sorted = [...classes].sort((left, right) => {
        const leftGrade = left.grade_level_id ?? left.id;
        const rightGrade = right.grade_level_id ?? right.id;
        if (leftGrade !== rightGrade) {
            return leftGrade - rightGrade;
        }

        return left.id - right.id;
    });
    const index = Number(classKey) - 1;
    if (!Number.isFinite(index) || index < 0 || index >= SIS_CLASS_OPTIONS.length) {
        return null;
    }

    return sorted[index]?.id ?? null;
}

function sectionAliasMatch(section: SisSectionRef, uiCode: string): boolean {
    const code = uiCode.trim().toUpperCase();
    const aliases = SECTION_ALIASES[code] ?? [code];
    const sectionCode = section.code.trim().toUpperCase();
    const sectionName = section.name.trim();

    // Prefer explicit Latin markers in section codes: A, SEC-A, 2A, SEC-2A.
    if (
        sectionCode === code
        || sectionCode.endsWith(`-${code}`)
        || /(?:^|[^A-Z0-9])/.test(sectionCode.slice(0, -1)) && sectionCode.endsWith(code)
    ) {
        const latinTail = sectionCode.match(/[A-Z]$/)?.[0];
        if (latinTail === code) {
            return true;
        }
    }

    return aliases.some((alias) => {
        const normalized = alias.trim();
        if (normalized === '') {
            return false;
        }

        // Exact name/code only for short Arabic letters to avoid false positives.
        if (normalized.length <= 1) {
            return sectionName === normalized || sectionName === `شعبة ${normalized}`;
        }

        return sectionName === normalized || sectionCode === normalized.toUpperCase();
    });
}

export function resolveSisSectionId(
    sectionCode: string,
    classId: number,
    sections: SisSectionRef[],
): number | null {
    const code = sectionCode.trim().toUpperCase();
    if (code === '') {
        return null;
    }

    const forClass = sections.filter((section) => section.class_id === classId);
    const match = forClass.find((section) => sectionAliasMatch(section, code));

    return match?.id ?? null;
}

/** Map a persisted section row back to the shared UI code (A / B / C). */
export function resolveSisSectionCode(
    sectionId: number | null | undefined,
    sections: SisSectionRef[],
): string {
    if (sectionId === null || sectionId === undefined || sectionId <= 0) {
        return '';
    }

    const section = sections.find((item) => item.id === sectionId);
    if (section === undefined) {
        return '';
    }

    for (const option of SIS_SECTION_OPTIONS) {
        if (sectionAliasMatch(section, option.value)) {
            return option.value;
        }
    }

    return '';
}
