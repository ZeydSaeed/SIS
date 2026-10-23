import { router } from '@inertiajs/react';
import { FormEvent, useState } from 'react';
import { t } from '@/i18n';

export type PickerStudent = {
    enrollment_id: number;
    student_name: string;
    student_code: string;
    placement: string;
};

export type ResultsPicker = {
    q: string;
    candidates: PickerStudent[];
    student: PickerStudent | null;
};

/**
 * «اختيار الطالب» — search the year's students by name or code (server-side, at most 20 matches) and open the
 * results page for the chosen one; replaces typing a raw enrollment id.
 */
export function ResultsStudentPicker({
    path,
    academicYearId,
    picker,
}: {
    path: string;
    academicYearId: number | null;
    picker: ResultsPicker;
}) {
    const r = t().results.picker;
    const [q, setQ] = useState(picker.q);

    const search = (event: FormEvent) => {
        event.preventDefault();
        router.get(
            path,
            {
                academic_year_id: academicYearId ?? undefined,
                enrollment_id: picker.student?.enrollment_id,
                q: q.trim() === '' ? undefined : q.trim(),
            },
            { preserveState: true, preserveScroll: true },
        );
    };

    const choose = (student: PickerStudent) => {
        router.get(path, {
            academic_year_id: academicYearId ?? undefined,
            enrollment_id: student.enrollment_id,
        });
    };

    return (
        <section className="flex flex-col gap-3" aria-label={r.title} data-results-picker>
            {picker.student ? (
                <p className="text-sm" role="status" data-picked-student>
                    {r.current}: <strong>{picker.student.student_name}</strong>{' '}
                    <span dir="ltr">({picker.student.student_code})</span>
                    {picker.student.placement !== '' ? ` — ${picker.student.placement}` : null}
                </p>
            ) : null}
            <form onSubmit={search} className="flex flex-col gap-3 sm:flex-row sm:items-end">
                <label className="flex min-w-0 flex-1 flex-col gap-1 text-sm">
                    <span>{r.label}</span>
                    <input
                        type="search"
                        name="q"
                        value={q}
                        onChange={(e) => setQ(e.target.value)}
                        placeholder={r.placeholder}
                        className="sis-ops-hub__link min-h-11 px-3 py-2"
                    />
                </label>
                <button type="submit" className="sis-ops-hub__link min-h-11 px-4 py-2 text-sm">
                    {r.search}
                </button>
            </form>
            {picker.q !== '' ? (
                picker.candidates.length === 0 ? (
                    <p className="text-sm" role="status">
                        {r.none}
                    </p>
                ) : (
                    <ul className="flex flex-col gap-1 text-sm" data-picker-candidates>
                        {picker.candidates.map((student) => (
                            <li key={student.enrollment_id}>
                                <button type="button" className="underline" onClick={() => choose(student)}>
                                    {student.student_name}
                                </button>{' '}
                                <span dir="ltr">({student.student_code})</span>
                                {student.placement !== '' ? ` — ${student.placement}` : null}
                            </li>
                        ))}
                    </ul>
                )
            ) : null}
        </section>
    );
}
