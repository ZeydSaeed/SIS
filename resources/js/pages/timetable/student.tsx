import { Head } from '@inertiajs/react';
import { dayOfWeekLabel } from '@/components/sis/status-chip';
import { t } from '@/i18n';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

type Period = { id: number; period_number: number; start_time: string; end_time: string; period_type: number };
type Lesson = { day_of_week: number; period_id: number; subject_id: number; teacher_id: number; room_id: number | null; group_id: number | null; week_no: number | null };
type Props = {
    lessons: Lesson[];
    meta: { student_id: number; section_id: number; version_id: number | null; unassigned_divisions: number[] };
    periods: Period[];
    teachers: Array<{ id: number; short_name: string }>;
    days: number[];
    subjects?: Array<{ id: number; name: string }>;
};

const LESSON = 1;

/** «جدول الطالب»: the week derived from the student's section and groups (read-only, from the effective version). */
export default function StudentTimetable({ lessons, meta, periods, teachers, days, subjects = [] }: Props) {
    const i18n = t();
    const tt = i18n.timetable;
    const breadcrumbs: BreadcrumbItem[] = [{ title: tt.title, href: '/timetable' }];
    const lessonPeriods = [...periods].filter((p) => p.period_type === LESSON).sort((a, b) => a.period_number - b.period_number);
    const teacherName = new Map(teachers.map((x) => [x.id, x.short_name]));
    const subjectName = new Map(subjects.map((s) => [s.id, s.name]));
    const cells = new Map<string, Lesson[]>();
    for (const l of lessons) {
        const key = `${l.day_of_week}:${l.period_id}`;
        cells.set(key, [...(cells.get(key) ?? []), l]);
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={tt.title} />
            <div className="sis-ops-hub sis-admission-page sis-timetable-page flex h-full min-h-0 flex-col overflow-hidden pb-4" dir="rtl" lang="ar">
                {meta.unassigned_divisions.length > 0 ? <p className="sis-branches-page__notice">{tt.engine.noGroups}</p> : null}
                <div className="sis-admission-page-body sis-timetable-body">
                    <section className="sis-timetable-board">
                        <header className="sis-timetable-board__head">
                            <span className="sis-timetable-board__title">{tt.title}</span>
                            <span className="sis-timetable-board__meta">
                                <bdi dir="ltr">{lessons.length}</bdi> {tt.lessonsUnit}
                            </span>
                        </header>
                        <div className="sis-timetable-grid">
                            <table className="sis-timetable-table">
                                <thead>
                                    <tr>
                                        <th>{tt.day}</th>
                                        {lessonPeriods.map((p, i) => (
                                            <th key={p.id}>
                                                {tt.periodLabel} {i + 1}
                                            </th>
                                        ))}
                                    </tr>
                                </thead>
                                <tbody>
                                    {days.map((day) => (
                                        <tr key={day}>
                                            <th>{dayOfWeekLabel(day)}</th>
                                            {lessonPeriods.map((p) => (
                                                <td key={p.id} className="sis-timetable-cell">
                                                    {(cells.get(`${day}:${p.id}`) ?? []).map((l, i) => (
                                                        <div key={i} className="sis-timetable-card">
                                                            <span className="sis-timetable-card__subject">{subjectName.get(l.subject_id) ?? `#${l.subject_id}`}</span>
                                                            <span className="sis-timetable-card__line">
                                                                {teacherName.get(l.teacher_id) ?? ''}
                                                                {l.week_no ? ` · ${tt.engine.week} ${l.week_no}` : ''}
                                                            </span>
                                                        </div>
                                                    ))}
                                                </td>
                                            ))}
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </section>
                </div>
            </div>
        </AppLayout>
    );
}
