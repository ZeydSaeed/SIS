import type { CSSProperties } from 'react';
import { t } from '@/i18n';
import { formatClock } from './display-settings';

type Lesson = {
    id: number;
    section_id: number;
    day_of_week: number;
    period_id: number;
    subject_id: number;
    teacher_id: number;
    room_id?: number | null;
    co_teacher_id?: number | null;
    group_id?: number | null;
    week_no?: number | null;
};

/** A cell of the table: section × day × period (a merged block counts by its first period). */
export type MasterCell = { sectionId: number; day: number; periodId: number };

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
    showTeacher = true,
    periodHeader = null,
    roomLabel,
    showRoom = false,
    showSection = false,
    lessonStyle,
    selected = null,
    onSelect,
    onOpen,
}: {
    columns: MasterColumn[];
    days: number[];
    dayLabel: (day: number) => string;
    periods: Array<{ id: number; number: number; start?: string; end?: string }>;
    lessons: Lesson[];
    subjectName: (id: number) => string;
    teacherName: (id: number) => string;
    groupName: (id: number) => string;
    subjectStyle: (id: number) => CSSProperties | undefined;
    heading: { title: string; school?: string; year?: string; stage?: string; effectiveFrom?: string | null };
    /** «الدرس | اسم المدرس» (default) or «الدرس» only. */
    showTeacher?: boolean;
    /** «تنسيق الجدول» › رأس الحصة: the number and the time shown in the period column. */
    periodHeader?: { show_number: boolean; show_time: boolean; clock: '12' | '24'; am: string; pm: string } | null;
    /** «الغرفة» in the lesson cell («تنسيق الجدول» › الحقول المعروضة). */
    roomLabel?: (roomId: number | null) => string | null;
    showRoom?: boolean;
    /** «الشعبة» in the lesson cell (the column already names it; this repeats it for the printed cell). */
    showSection?: boolean;
    /** The cell colour per the chosen «لوّن حسب» (default: the subject's colour). */
    lessonStyle?: (lesson: Lesson) => CSSProperties | undefined;
    /** The chosen cell (one click) and the editor of a cell (double click). */
    selected?: MasterCell | null;
    onSelect?: (cell: MasterCell) => void;
    onOpen?: (cell: MasterCell) => void;
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

    const subjectText = (list: Lesson[], sectionLabel: string | null = null) =>
        list
            .map((l) => {
                const room = showRoom && roomLabel !== undefined ? roomLabel(l.room_id ?? null) : null;

                return `${subjectName(l.subject_id)}${l.group_id ? ` (${groupName(l.group_id)})` : ''}${l.week_no ? ` · ${tt.engine.week} ${l.week_no}` : ''}${room ? ` · ${room}` : ''}${sectionLabel ? ` · ${sectionLabel}` : ''}`;
            })
            .join(' / ');
    const teacherText = (list: Lesson[]) =>
        list.map((l) => [l.teacher_id, l.co_teacher_id].filter((x): x is number => x !== null && x !== undefined).map(teacherName).join(' + ')).join(' / ');

    const sectionCount = columns.reduce((n, c) => n + c.sections.length, 0);
    const handlers = (sectionId: number, day: number, periodId: number) => ({
        onClick: onSelect === undefined ? undefined : () => onSelect({ sectionId, day, periodId }),
        onDoubleClick: onOpen === undefined ? undefined : () => onOpen({ sectionId, day, periodId }),
    });
    const chosen = (sectionId: number, day: number, periodId: number) =>
        selected !== null && selected.sectionId === sectionId && selected.day === day && selected.periodId === periodId ? ' sis-timetable-master__selected' : '';
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
                            c.sections.flatMap((s) => [
                                <col key={`${c.key}:${s.id}:s`} className="sis-timetable-master__col-subject" />,
                                ...(showTeacher ? [<col key={`${c.key}:${s.id}:t`} className="sis-timetable-master__col-teacher" />] : []),
                            ]),
                        )}
                    </colgroup>
                    <thead>
                        <tr>
                            <th rowSpan={showTeacher ? 3 : 2} className="sis-timetable-master__corner">{m.days}</th>
                            <th rowSpan={showTeacher ? 3 : 2} className="sis-timetable-master__corner sis-timetable-master__corner--vertical">
                                <span>{m.periods}</span>
                            </th>
                            {columns.map((c) => (
                                <th key={c.key} colSpan={c.sections.length * (showTeacher ? 2 : 1)} className="sis-timetable-master__department sis-timetable-master__edge--department">
                                    {c.title}
                                </th>
                            ))}
                        </tr>
                        <tr>
                            {columns.flatMap((c) =>
                                c.sections.map((s, si) => (
                                    <th key={`${c.key}:${s.id}`} colSpan={showTeacher ? 2 : 1} className={`sis-timetable-master__section${edge(c, si)}`}>
                                        {s.label}
                                    </th>
                                )),
                            )}
                        </tr>
                        {showTeacher ? (
                        <tr>
                            {columns.flatMap((c) =>
                                c.sections.flatMap((s, si) => [
                                    <th key={`${c.key}:${s.id}:s`} className="sis-timetable-master__sub">{m.lesson}</th>,
                                    <th key={`${c.key}:${s.id}:t`} className={`sis-timetable-master__sub${edge(c, si)}`}>{m.teacher}</th>,
                                ]),
                            )}
                        </tr>
                        ) : null}
                    </thead>
                    <tbody>
                        {days.map((day, dayIndex) => {
                            const daySpans = new Map(columns.flatMap((c) => c.sections.map((s) => [s.id, spans(s.id, day)] as const)));
                            // Between two days: a very thin empty row with a heavy line above and below (the school's sheet).
                            const gap =
                                dayIndex === 0
                                    ? []
                                    : [
                                          <tr key={`gap:${day}`} className="sis-timetable-master__day-gap" aria-hidden="true">
                                              <td colSpan={2 + sectionCount * (showTeacher ? 2 : 1)} />
                                          </tr>,
                                      ];

                            return [...gap, ...periods.map((p, index) => (
                                <tr key={`${day}:${p.id}`} className={index === 0 ? 'sis-timetable-master__day-start' : undefined}>
                                    {index === 0 ? (
                                        <th rowSpan={periods.length} className="sis-timetable-master__day">
                                            <span>{dayLabel(day)}</span>
                                        </th>
                                    ) : null}
                                    <th className="sis-timetable-master__period">
                                        {periodHeader === null || periodHeader.show_number ? <span className="sis-timetable-master__period-number">{p.number}</span> : null}
                                        {periodHeader !== null && periodHeader.show_time && p.start !== undefined && p.end !== undefined ? (
                                            <span className="sis-timetable-master__period-time">
                                                <bdi dir="ltr">{formatClock(p.start, periodHeader.clock, periodHeader.am, periodHeader.pm)}</bdi>
                                                <bdi dir="ltr">{formatClock(p.end, periodHeader.clock, periodHeader.am, periodHeader.pm)}</bdi>
                                            </span>
                                        ) : null}
                                    </th>
                                    {columns.flatMap((c) =>
                                        c.sections.flatMap((s, si) => {
                                            const span = daySpans.get(s.id)?.[index] ?? 1;
                                            if (span === 0) {
                                                return [];
                                            }
                                            const list = cells.get(s.id)?.get(`${day}:${p.id}`) ?? [];
                                            if (list.length === 0) {
                                                const empty = handlers(s.id, day, p.id);

                                                return showTeacher
                                                    ? [
                                                          <td key={`${c.key}:${s.id}:s`} className={`sis-timetable-master__empty${chosen(s.id, day, p.id)}`} {...empty} />,
                                                          <td key={`${c.key}:${s.id}:t`} className={`sis-timetable-master__empty${edge(c, si)}`} {...empty} />,
                                                      ]
                                                    : [<td key={`${c.key}:${s.id}:s`} className={`sis-timetable-master__empty${edge(c, si)}${chosen(s.id, day, p.id)}`} {...empty} />];
                                            }
                                            const style = lessonStyle?.(list[0]) ?? subjectStyle(list[0].subject_id);
                                            const on = handlers(s.id, day, p.id);
                                            const mark = chosen(s.id, day, p.id);

                                            if (!showTeacher) {
                                                return [
                                                    <td key={`${c.key}:${s.id}:s`} rowSpan={span} className={`sis-timetable-master__lesson sis-timetable-master__lesson--subject${edge(c, si)}${mark}`} style={style} {...on}>
                                                        {subjectText(list, showSection ? s.label : null)}
                                                    </td>,
                                                ];
                                            }

                                            return [
                                                <td key={`${c.key}:${s.id}:s`} rowSpan={span} className={`sis-timetable-master__lesson sis-timetable-master__lesson--subject${mark}`} style={style} {...on}>
                                                    {subjectText(list, showSection ? s.label : null)}
                                                </td>,
                                                <td key={`${c.key}:${s.id}:t`} rowSpan={span} className={`sis-timetable-master__lesson${edge(c, si)}`} style={style} {...on}>
                                                    {teacherText(list)}
                                                </td>,
                                            ];
                                        }),
                                    )}
                                </tr>
                            ))];
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
