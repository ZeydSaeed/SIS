import { Lock } from 'lucide-react';
import type { ReactNode } from 'react';
import { displayRow, entityLabel, type DisplayCatalog, type DisplaySettings } from './display-settings';

/** The lesson facts a cell can show (ids resolved through the display catalogue; names as fallbacks). */
export type CellLesson = {
    subject_id: number;
    teacher_id: number;
    section_id: number;
    room_id: number | null;
    group_id?: number | null;
    co_teacher_id?: number | null;
    week_no?: number | null;
    locked?: boolean;
};

export type CellContext = {
    catalog: DisplayCatalog | null;
    subjectName: string;
    teacherName: string;
    sectionName: string;
    className: string;
    roomName: string | null;
    groupName: string | null;
    coTeacherName: string | null;
    students: number | null;
    branchName: string | null;
    departmentName: string | null;
    practical: boolean;
    /** «+n»: parallel lessons sharing the section cell. */
    shared: number;
    labels: { practical: string; week: string; students: string; locked: string };
    /** The secondary line when the view itself names the teacher (teacher view shows the section instead). */
    contextLine?: string | null;
};

/**
 * What a lesson cell says for the chosen layout and fields:
 * - corner   subject, then one secondary line: teacher · markers (today's card)
 * - columns  subject and teacher side by side, markers below
 * - subject  the subject only (+ chosen markers)
 * - full     teacher, subject, room (+ markers)
 */
export function cellLines(lesson: CellLesson, settings: DisplaySettings, ctx: CellContext): { subject: string; teacher: string | null; extra: string[]; title: string } {
    const f = settings.fields;
    const a = settings.abbreviate;
    const subject = entityLabel(ctx.catalog, 'subjects', lesson.subject_id, a.subject, ctx.subjectName);
    const teacherRow = displayRow(ctx.catalog, 'teachers', lesson.teacher_id);
    const titleText = settings.teacher_title_style === 'full' ? (teacherRow?.title ?? null) : (teacherRow?.title_abbreviation ?? null);
    const title = settings.teacher_title && titleText ? `${titleText} ` : '';
    const teacherText = ctx.contextLine ?? `${title}${entityLabel(ctx.catalog, 'teachers', lesson.teacher_id, a.teacher, ctx.teacherName)}`;
    const teacher = settings.layout === 'subject' || (!f.teacher && !ctx.contextLine) ? null : teacherText;
    const room = lesson.room_id === null ? null : entityLabel(ctx.catalog, 'rooms', lesson.room_id, a.room, ctx.roomName ?? '');

    const extra: string[] = [];
    const push = (on: boolean, value: string | null | undefined) => {
        if (on && value) {
            extra.push(value);
        }
    };
    push(settings.layout === 'full' || f.room, room);
    push(f.class, ctx.className);
    push(f.section, ctx.sectionName);
    push(f.students, ctx.students !== null && ctx.students > 0 ? `${ctx.labels.students} ${ctx.students}` : null);
    push(f.group, ctx.groupName);
    push(f.branch, ctx.branchName);
    push(f.department, ctx.departmentName);
    push(true, ctx.coTeacherName ? `+ ${ctx.coTeacherName}` : null);
    push(lesson.week_no !== null && lesson.week_no !== undefined, lesson.week_no ? `${ctx.labels.week} ${lesson.week_no}` : null);
    push(ctx.shared > 0, `+${ctx.shared}`);

    const tooltip = [ctx.subjectName, ctx.contextLine ?? ctx.teacherName, ...(room ? [room] : []), ...extra].filter(Boolean).join(' — ') + (lesson.locked ? ` — ${ctx.labels.locked}` : '');

    return { subject, teacher, extra, title: tooltip };
}

/** The inside of a `sis-timetable-card` (the existing classes); `trailing` = buttons of an interactive card. */
export function CellBody({ lesson, settings, ctx, trailing = null }: { lesson: CellLesson; settings: DisplaySettings; ctx: CellContext; trailing?: ReactNode }) {
    const { subject, teacher, extra } = cellLines(lesson, settings, ctx);
    const badge = ctx.practical && settings.fields.subject_type ? <span className="sis-timetable-card__badge">{ctx.labels.practical}</span> : null;
    const subjectLine = (
        <span className="sis-timetable-card__subject">
            {lesson.locked ? <Lock aria-label={ctx.labels.locked} width={11} height={11} /> : null}
            {subject}
            {badge}
        </span>
    );
    // «corner» keeps today's card: one secondary line «teacher · markers».
    if (settings.layout === 'corner') {
        const line = [teacher, ...extra].filter((x): x is string => x !== null && x !== '');

        return (
            <>
                {subjectLine}
                {line.length > 0 ? <span className="sis-timetable-card__line sis-timetable-card__line--teacher">{line.join(' · ')}</span> : null}
                {trailing}
            </>
        );
    }
    const teacherLine = teacher === null ? null : <span className="sis-timetable-card__line sis-timetable-card__line--teacher">{teacher}</span>;

    return (
        <>
            {settings.layout === 'full' ? teacherLine : null}
            {subjectLine}
            {settings.layout === 'columns' ? teacherLine : null}
            {extra.length > 0 ? <span className="sis-timetable-card__line">{extra.join(' · ')}</span> : null}
            {trailing}
        </>
    );
}
