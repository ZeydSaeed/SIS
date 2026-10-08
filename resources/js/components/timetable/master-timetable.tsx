import type { CSSProperties } from 'react';
import { t } from '@/i18n';

type Lesson = {
    id: number;
    section_id: number;
    day_of_week: number;
    period_id: number;
    subject_id: number;
    teacher_id: number;
    co_teacher_id?: number | null;
    group_id?: number | null;
    week_no?: number | null;
};

export type MasterColumn = { key: string; title: string; sections: Array<{ id: number; label: string }> };

/**
 * «الجدول الموحّد»: every department and section in one table — the school's printed layout:
 * days (rotated) and periods down the right; department → section (class + A / B / C) → «الدرس | اسم المدرس»
 * across. Consecutive periods of the same lesson (a practical double / triple) merge into one block.
 * Colours, lines and fonts are the timetable's own (subject tint, timetable line / head tokens).
 */
export function MasterTimetable({
    columns,
    days,
    dayLabel,
    periods,
    lessons,
    subjectName,
    teacherName,
    groupName,
    subjectStyle,
    heading,
}: {
    columns: MasterColumn[];
    days: number[];
    dayLabel: (day: number) => string;
    periods: Array<{ id: number; number: number }>;
    lessons: Lesson[];
    subjectName: (id: number) => string;
    teacherName: (id: number) => string;
    groupName: (id: number) => string;
    subjectStyle: (id: number) => CSSProperties | undefined;
    heading: { title: string; school?: string; year?: string; stage?: string; effectiveFrom?: string | null };
}) {
    const tt = t().timetable;
    const m = tt.master;

    // section → "day:period" → lessons of that cell (parallel groups share a cell).
    const cells = new Map<number, Map<string, Lesson[]>>();
    for (const lesson of lessons) {
        const bySlot = cells.get(lesson.section_id) ?? new Map<string, Lesson[]>();
        const key = `${lesson.day_of_week}:${lesson.period_id}`;
        bySlot.set(key, [...(bySlot.get(key) ?? []), lesson]);
        cells.set(lesson.section_id, bySlot);
    }
    const signature = (list: Lesson[]) =>
        list
            .map((l) => `${l.subject_id}:${l.teacher_id}:${l.co_teacher_id ?? 0}:${l.group_id ?? 0}:${l.week_no ?? 0}`)
            .sort()
            .join('|');

    /** For one section and day: per period index, its row span (0 = covered by the block above). */
    const spans = (sectionId: number, day: number) => {
        const sigs = periods.map((p) => signature(cells.get(sectionId)?.get(`${day}:${p.id}`) ?? []));
        const out = periods.map(() => 1);
        for (let i = 0; i < sigs.length; i++) {
            if (sigs[i] === '' || out[i] === 0) {
                continue;
            }
            let j = i + 1;
            while (j < sigs.length && sigs[j] === sigs[i]) {
                out[j] = 0;
                j++;
            }
            out[i] = j - i;
        }

        return out;
    };

    const subjectText = (list: Lesson[]) =>
        list
            .map((l) => `${subjectName(l.subject_id)}${l.group_id ? ` (${groupName(l.group_id)})` : ''}${l.week_no ? ` · ${tt.engine.week} ${l.week_no}` : ''}`)
            .join(' / ');
    const teacherText = (list: Lesson[]) =>
        list.map((l) => [l.teacher_id, l.co_teacher_id].filter((x): x is number => x !== null && x !== undefined).map(teacherName).join(' + ')).join(' / ');

    const sectionCount = columns.reduce((n, c) => n + c.sections.length, 0);
    /** The line closing a section's «الدرس | اسم المدرس» pair: heavier between sections, heaviest between specializations. */
    const edge = (column: MasterColumn, index: number) => (index === column.sections.length - 1 ? ' sis-timetable-master__edge--department' : ' sis-timetable-master__edge--section');

    return (
        <section className="sis-timetable-master" aria-label={heading.title}>
            <header className="sis-timetable-master__head">
                <span className="sis-timetable-master__school">{heading.school ?? ''}</span>
                <span className="sis-timetable-master__title">
                    {heading.title}
                    {heading.year ? ` ${m.forYear} ${heading.year}` : ''}
                </span>
                <span className="sis-timetable-master__stage">{heading.stage ? `${m.stage}: ${heading.stage}` : ''}</span>
                <span className="sis-timetable-master__effective">{heading.effectiveFrom ? m.effectiveFrom.replace('{date}', heading.effectiveFrom) : ''}</span>
            </header>
            <div className="sis-timetable-grid sis-timetable-master__grid">
                <table className="sis-timetable-table sis-timetable-master__table">
                    <colgroup>
                        <col className="sis-timetable-master__col-day" />
                        <col className="sis-timetable-master__col-period" />
                        {columns.flatMap((c) =>
                            c.sections.flatMap((s) => [<col key={`${c.key}:${s.id}:s`} className="sis-timetable-master__col-subject" />, <col key={`${c.key}:${s.id}:t`} className="sis-timetable-master__col-teacher" />]),
                        )}
                    </colgroup>
                    <thead>
                        <tr>
                            <th rowSpan={3} className="sis-timetable-master__corner">{m.days}</th>
                            <th rowSpan={3} className="sis-timetable-master__corner">{m.periods}</th>
                            {columns.map((c) => (
                                <th key={c.key} colSpan={c.sections.length * 2} className="sis-timetable-master__department sis-timetable-master__edge--department">
                                    {c.title}
                                </th>
                            ))}
                        </tr>
                        <tr>
                            {columns.flatMap((c) =>
                                c.sections.map((s, si) => (
                                    <th key={`${c.key}:${s.id}`} colSpan={2} className={`sis-timetable-master__section${edge(c, si)}`}>
                                        {s.label}
                                    </th>
                                )),
                            )}
                        </tr>
                        <tr>
                            {columns.flatMap((c) =>
                                c.sections.flatMap((s, si) => [
                                    <th key={`${c.key}:${s.id}:s`} className="sis-timetable-master__sub">{m.lesson}</th>,
                                    <th key={`${c.key}:${s.id}:t`} className={`sis-timetable-master__sub${edge(c, si)}`}>{m.teacher}</th>,
                                ]),
                            )}
                        </tr>
                    </thead>
                    <tbody>
                        {days.map((day) => {
                            const daySpans = new Map(columns.flatMap((c) => c.sections.map((s) => [s.id, spans(s.id, day)] as const)));

                            return periods.map((p, index) => (
                                <tr key={`${day}:${p.id}`} className={index === 0 ? 'sis-timetable-master__day-start' : undefined}>
                                    {index === 0 ? (
                                        <th rowSpan={periods.length} className="sis-timetable-master__day">
                                            <span>{dayLabel(day)}</span>
                                        </th>
                                    ) : null}
                                    <th className="sis-timetable-master__period">{p.number}</th>
                                    {columns.flatMap((c) =>
                                        c.sections.flatMap((s, si) => {
                                            const span = daySpans.get(s.id)?.[index] ?? 1;
                                            if (span === 0) {
                                                return [];
                                            }
                                            const list = cells.get(s.id)?.get(`${day}:${p.id}`) ?? [];
                                            if (list.length === 0) {
                                                return [<td key={`${c.key}:${s.id}:s`} className="sis-timetable-master__empty" />, <td key={`${c.key}:${s.id}:t`} className={`sis-timetable-master__empty${edge(c, si)}`} />];
                                            }
                                            const style = subjectStyle(list[0].subject_id);

                                            return [
                                                <td key={`${c.key}:${s.id}:s`} rowSpan={span} className="sis-timetable-master__lesson sis-timetable-master__lesson--subject" style={style}>
                                                    {subjectText(list)}
                                                </td>,
                                                <td key={`${c.key}:${s.id}:t`} rowSpan={span} className={`sis-timetable-master__lesson${edge(c, si)}`} style={style}>
                                                    {teacherText(list)}
                                                </td>,
                                            ];
                                        }),
                                    )}
                                </tr>
                            ));
                        })}
                        {sectionCount === 0 ? (
                            <tr>
                                <td colSpan={2} className="sis-timetable-master__empty">{m.noSections}</td>
                            </tr>
                        ) : null}
                    </tbody>
                </table>
            </div>
        </section>
    );
}
