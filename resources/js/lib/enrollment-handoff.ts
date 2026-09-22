export type EnrollmentHandoffStudent = {
    id: number;
    full_name: string;
};

export type EnrollmentHandoffPayload = {
    academic_year_id: number | null;
    students: EnrollmentHandoffStudent[];
};

const STORAGE_KEY = 'sis.enrollment.handoff';

export function storeEnrollmentHandoff(payload: EnrollmentHandoffPayload): void {
    if (typeof window === 'undefined') {
        return;
    }

    const students = payload.students
        .filter((student) => student.id > 0)
        .map((student) => ({
            id: student.id,
            full_name: student.full_name.trim(),
        }));

    if (students.length === 0) {
        clearEnrollmentHandoff();

        return;
    }

    window.sessionStorage.setItem(
        STORAGE_KEY,
        JSON.stringify({
            academic_year_id: payload.academic_year_id,
            students,
        } satisfies EnrollmentHandoffPayload),
    );
}

/** Merge newly queued students into any existing handoff for the same year. */
export function appendEnrollmentHandoff(payload: EnrollmentHandoffPayload): number {
    const incoming = payload.students.filter((student) => student.id > 0);
    if (incoming.length === 0) {
        return readEnrollmentHandoff()?.students.length ?? 0;
    }

    const existing = readEnrollmentHandoff();
    const byId = new Map<number, EnrollmentHandoffStudent>();

    if (
        existing !== null
        && existing.academic_year_id === payload.academic_year_id
    ) {
        for (const student of existing.students) {
            byId.set(student.id, student);
        }
    }

    for (const student of incoming) {
        byId.set(student.id, {
            id: student.id,
            full_name: student.full_name.trim(),
        });
    }

    const merged = Array.from(byId.values());
    storeEnrollmentHandoff({
        academic_year_id: payload.academic_year_id,
        students: merged,
    });

    return merged.length;
}

export function readEnrollmentHandoff(): EnrollmentHandoffPayload | null {
    if (typeof window === 'undefined') {
        return null;
    }

    const raw = window.sessionStorage.getItem(STORAGE_KEY);
    if (raw === null || raw === '') {
        return null;
    }

    try {
        const parsed = JSON.parse(raw) as EnrollmentHandoffPayload;
        if (!Array.isArray(parsed.students) || parsed.students.length === 0) {
            return null;
        }

        return {
            academic_year_id:
                typeof parsed.academic_year_id === 'number' ? parsed.academic_year_id : null,
            students: parsed.students
                .filter((student) => typeof student?.id === 'number' && student.id > 0)
                .map((student) => ({
                    id: student.id,
                    full_name:
                        typeof student.full_name === 'string' && student.full_name.trim() !== ''
                            ? student.full_name.trim()
                            : `طالب #${student.id}`,
                })),
        };
    } catch {
        return null;
    }
}

export function clearEnrollmentHandoff(): void {
    if (typeof window === 'undefined') {
        return;
    }

    window.sessionStorage.removeItem(STORAGE_KEY);
}

export function shiftEnrollmentHandoffStudent(
    studentId: number,
): EnrollmentHandoffPayload | null {
    const current = readEnrollmentHandoff();
    if (current === null) {
        return null;
    }

    const remaining = current.students.filter((student) => student.id !== studentId);
    if (remaining.length === 0) {
        clearEnrollmentHandoff();

        return null;
    }

    const next: EnrollmentHandoffPayload = {
        academic_year_id: current.academic_year_id,
        students: remaining,
    };
    storeEnrollmentHandoff(next);

    return next;
}
