import { TypeHourTotalsBar } from '@/components/curriculum/type-hour-totals';
import {
    statusLabel,
    subjectTypeLabel,
    subjectTypesWithHoursLabel,
    type CurriculumLineTeacher,
    type CurriculumRow,
    type LinkedSubjectRow,
} from '@/components/curriculum/curriculum-workspace';
import { t } from '@/i18n';
import { typeHourTotals } from '@/lib/subject-type-order';

/** The subjects table with inner borders on every cell (header and body), in the page's own palette. */
const GRID_CLASS = 'sis-curriculum-sheet-grid';
// The table's own theme sets separate, darker edges: these local rules win (scoped to this table only).
const GRID_STYLE = `.${GRID_CLASS} table{border-collapse:collapse!important}.${GRID_CLASS} table th,.${GRID_CLASS} table td{border:1px solid var(--sis-powder-blue)!important}.${GRID_CLASS}.${GRID_CLASS}.${GRID_CLASS}.${GRID_CLASS} table thead th{border-color:rgb(255 255 255 / 0.35)!important}`;

/** «نوع المادة مع الساعات» and the hour totals: the subject's own types and hours. */
export function lineHoursSource(row: LinkedSubjectRow) {
    return {
        subject_type: row.subject_type ?? row.subject_types?.[0] ?? 1,
        subject_types: row.subject_types,
        subject_type_hours: row.subject_type_hours,
        credit_hours: row.credit_hours ?? row.weekly_hours,
    };
}

/**
 * الفرع · الاختصاص · الصف · الشعبة — the curriculum's heading: plain text on one line (no box, background or frame),
 * «الفرع : …   الاختصاص : …   الصف : …   الشعبة : …», with a little space above and below.
 */
export function CurriculumPlaceHeading({
    curriculum,
    select,
}: {
    curriculum: CurriculumRow;
    /** «تحديد المنهج»: a box at the line's start. */
    select?: { checked: boolean; onChange: () => void; label: string };
}) {
    const c = t().curriculum;

    return (
        <dl className="my-1 flex flex-wrap items-center gap-x-8 gap-y-1 px-1 text-base" aria-label={curriculum.name}>
            {select ? (
                <input
                    type="checkbox"
                    className="size-5 cursor-pointer"
                    checked={select.checked}
                    onChange={select.onChange}
                    aria-label={`${select.label} ${curriculum.name}`}
                />
            ) : null}
            {[
                [c.branch, curriculum.branch_name ?? '—'],
                [c.department, curriculum.department_name ?? '—'],
                [c.gradeLevel, curriculum.grade_level_name ?? String(curriculum.grade_level_id)],
                [c.section, curriculum.section_name ?? '—'],
            ].map(([label, value]) => (
                <div key={label} className="flex items-baseline gap-1.5 whitespace-nowrap">
                    <dt className="font-semibold">{label} :</dt>
                    <dd className="font-bold">{value}</dd>
                </div>
            ))}
        </dl>
    );
}

/** اكتمال المنهج · المواد · الحالة — one per line. */
export function CurriculumPlaceStatus({ curriculum }: { curriculum: CurriculumRow }) {
    const i18n = t();
    const c = i18n.curriculum;

    return (
        <div className="flex flex-col gap-0.5 px-1 text-sm">
            <span>
                {c.planCompleteness}: {curriculum.is_complete ? c.planComplete : c.planIncomplete} (
                {c.planCompletenessDetail
                    .replace(':staffed', String(curriculum.staffed_count ?? 0))
                    .replace(':total', String(curriculum.subject_count ?? 0))}
                )
            </span>
            {curriculum.planned_subject_count ? (
                <span>
                    {c.subjectsProgress
                        .replace(':n', String(curriculum.subject_count ?? 0))
                        .replace(':planned', String(curriculum.planned_subject_count))
                        .replace(':percent', String(curriculum.subjects_percent ?? 0))}
                </span>
            ) : null}
            <span>
                {i18n.common.status}: {statusLabel(curriculum.status, i18n.status.active, i18n.status.inactive)}
            </span>
        </div>
    );
}

/**
 * One curriculum in one card: its place as the heading, the subjects table, اكتمال · المواد · الحالة under it, then
 * the hour totals. The curriculum page passes `selection` (its «تحرير» acts on the ticked rows); «كل المناهج» does not.
 */
export function CurriculumSheet({
    curriculum,
    lines,
    shownLines,
    selection,
    color,
    select,
}: {
    curriculum: CurriculumRow;
    /** Every line of the curriculum (the totals count the active ones). */
    lines: LinkedSubjectRow[];
    /** The rows drawn (the «حالة» filter); every line by default. */
    shownLines?: LinkedSubjectRow[];
    selection?: { selectedIds: number[]; onToggle: (id: number) => void; onToggleAll: () => void; allSelected: boolean };
    /** «كل المناهج»: the sheet in its curriculum's own colour (as its overview card) and light totals. */
    color?: string;
    /** «كل المناهج»: choosing this curriculum for «تحرير». */
    select?: { checked: boolean; onChange: () => void; label: string };
}) {
    const c = t().curriculum;
    const rows = shownLines ?? lines;
    const active = lines.filter((row) => row.status === 1);
    const teachersOf = (row: LinkedSubjectRow, role: 1 | 2): CurriculumLineTeacher[] =>
        (row.teachers ?? []).filter((teacher) => teacher.role === role);
    const teacherText = (teacher: CurriculumLineTeacher): string =>
        [teacher.name, teacher.subject_type ? subjectTypeLabel(teacher.subject_type, c) : null, teacher.section_name ?? null]
            .filter(Boolean)
            .join(' — ');
    const teacherCell = (teachers: CurriculumLineTeacher[], none: string) =>
        teachers.length === 0
            ? none
            : teachers.map((teacher) => (
                  <span key={teacher.assignment_id} className="block">
                      {teacherText(teacher)}
                  </span>
              ));

    return (
        <section
            className={`flex flex-col gap-2 rounded-md border p-2 ${color ? '' : 'border-[color:var(--sis-powder-blue)]'}`}
            // The same tint as its overview card: 8% of its colour, a 25% edge.
            style={
                color
                    ? {
                          background: `color-mix(in srgb, ${color} 8%, #ffffff)`,
                          borderColor: `color-mix(in srgb, ${color} 25%, transparent)`,
                          ...(select?.checked ? { outline: '3px solid var(--sis-oxford)', outlineOffset: '2px' } : {}),
                      }
                    : undefined
            }
            aria-label={curriculum.name}
        >
            <CurriculumPlaceHeading curriculum={curriculum} select={select} />
            {lines.length === 0 ? (
                <p className="px-1 py-2 text-sm">
                    {c.linkedEmptyTitle} — {c.linkedEmptyDesc}
                </p>
            ) : (
                <div className={`sis-admission-periods-table sis-admission-drafts-table ${GRID_CLASS}`}>
                    <style>{GRID_STYLE}</style>
                    <div className="sis-admission-drafts-table__scroller">
                        <table>
                            <caption className="sr-only">{c.linkedSubjectsCaption}</caption>
                            <thead>
                                <tr>
                                    {selection ? (
                                        <th className="sis-admission-drafts-table__select">
                                            <input
                                                type="checkbox"
                                                checked={selection.allSelected}
                                                onChange={selection.onToggleAll}
                                                aria-label={c.selectAllVisible}
                                            />
                                        </th>
                                    ) : null}
                                    <th className="sis-admission-drafts-table__name-head">{c.subjectName}</th>
                                    <th className="sis-admission-drafts-table__name-head">{c.subjectNameEn}</th>
                                    <th>{c.subjectTypeWithHours}</th>
                                    <th>{c.maxGrade}</th>
                                    <th>{c.passGrade}</th>
                                    <th>{c.mainTeacher}</th>
                                    <th>{c.assistantTeacherColumn}</th>
                                </tr>
                            </thead>
                            <tbody>
                                {rows.map((row) => {
                                    const selected = selection?.selectedIds.includes(row.id) ?? false;
                                    const name = row.subject_name ?? String(row.subject_id);

                                    return (
                                        <tr
                                            key={row.id}
                                            className={selected ? 'sis-admission-periods-table__row--selected' : undefined}
                                            onClick={selection ? () => selection.onToggle(row.id) : undefined}
                                        >
                                            {selection ? (
                                                <td className="sis-admission-drafts-table__select">
                                                    <input
                                                        type="checkbox"
                                                        checked={selected}
                                                        onChange={() => selection.onToggle(row.id)}
                                                        onClick={(event) => event.stopPropagation()}
                                                        aria-label={name}
                                                    />
                                                </td>
                                            ) : null}
                                            <td className="sis-admission-drafts-table__name">
                                                {name}
                                                {row.status !== 1 || (row.subject_status ?? 1) !== 1 ? ` ${c.inactiveSuffix}` : ''}
                                            </td>
                                            <td className="sis-admission-drafts-table__name" dir="ltr">
                                                {row.subject_name_en?.trim() ? row.subject_name_en : '—'}
                                            </td>
                                            <td className="sis-admission-drafts-table__text">
                                                {subjectTypesWithHoursLabel(lineHoursSource(row), c)}
                                            </td>
                                            <td className="sis-admission-drafts-table__text" dir="ltr">
                                                {row.max_grade ?? '—'}
                                            </td>
                                            <td className="sis-admission-drafts-table__text" dir="ltr">
                                                {row.pass_grade ?? '—'}
                                            </td>
                                            <td className="sis-admission-drafts-table__text">{teacherCell(teachersOf(row, 1), c.noTeacher)}</td>
                                            <td className="sis-admission-drafts-table__text">
                                                {teacherCell(teachersOf(row, 2), c.noAssistant)}
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>
                </div>
            )}
            <CurriculumPlaceStatus curriculum={curriculum} />
            {active.length > 0 ? (
                <TypeHourTotalsBar
                    totals={typeHourTotals(active.map(lineHoursSource))}
                    style={color ? { background: `color-mix(in srgb, ${color} 12%, #ffffff)` } : undefined}
                />
            ) : null}
        </section>
    );
}
