import { router } from '@inertiajs/react';
import { useState } from 'react';
import { SheetSection } from '@/components/sis/admission-sheet';
import { Button } from '@/components/ui/button';
import { t } from '@/i18n';
import type { EngineContext } from './engine-context';
import type { MoveSuggestions, Substitutes } from './engine-types';
import { EngineField, EngineRow, useEngineRequest } from './engine-ui';

type Lesson = { id: number; locked?: boolean; joined_to?: number | null; day_of_week: number };

/**
 * The lesson sheet's engine tools: lock / unlock, «اقتراح أماكن» (best moves and swaps with their effect, from
 * the solver's cost model) and «بديل ليوم» (ranked substitutes for a date → the existing schedule exception).
 */
export function LessonEngineTools({
    ctx,
    lesson,
    suggestions,
    substitutes,
    periodLabel,
    canLock,
    canSubstitute,
    onMove,
    onSwap,
}: {
    ctx: EngineContext;
    lesson: Lesson;
    suggestions: MoveSuggestions | null;
    substitutes: Substitutes | null;
    periodLabel: (periodId: number) => string;
    canLock: boolean;
    canSubstitute: boolean;
    onMove: (day: number, periodId: number) => void;
    onSwap: (withScheduleId: number) => void;
}) {
    const i18n = t();
    const e = i18n.timetable.engine;
    const request = useEngineRequest();
    const [date, setDate] = useState(() => nextDateFor(lesson.day_of_week));
    const [saving, setSaving] = useState(false);

    const toggleLock = async () => {
        setSaving(true);
        await request('post', '/timetable/schedules/lock', { academic_year_id: ctx.yearId, schedule_ids: [lesson.id], lock: !lesson.locked });
        setSaving(false);
    };

    const assign = async (teacherId: number) => {
        setSaving(true);
        await request('post', `/timetable/schedules/${lesson.id}/substitute`, { exception_date: date, substitute_teacher_id: teacherId });
        setSaving(false);
    };

    const impact = (soft: number) => (soft < 0 ? e.impactBetter : soft === 0 ? e.impactSame : e.impactWorse);

    return (
        <>
            {canLock ? (
                <SheetSection id="timetable-lesson-lock" title={lesson.locked ? e.locked : e.lock}>
                    <EngineRow>
                        <p className="sis-timetable-sheet__hint sis-branches-field--wide">{e.lockHint}</p>
                        <Button type="button" size="sm" variant="outline" disabled={saving} onClick={() => void toggleLock()}>
                            {lesson.locked ? e.unlock : e.lock}
                        </Button>
                    </EngineRow>
                </SheetSection>
            ) : null}
            {canLock && !lesson.locked && (lesson.joined_to ?? null) === null ? (
                <SheetSection id="timetable-lesson-suggest" title={e.suggest}>
                    <div className="sis-timetable-audit__bar">
                        <Button type="button" size="sm" variant="outline" title={e.suggestHint} onClick={() => router.reload({ only: ['moveSuggestions'], data: { suggest: lesson.id } })}>
                            {e.suggest}
                        </Button>
                    </div>
                    {suggestions !== null ? (
                        suggestions.options.length === 0 ? (
                            <p className="sis-timetable-audit__clean">{suggestions.reason === 'block' ? e.suggestBlock : e.suggestNone}</p>
                        ) : (
                            <ul className="sis-timetable-audit__items sis-branches-field--wide">
                                {suggestions.options.map((o, i) => (
                                    <li key={i} className={`sis-timetable-audit__item${o.soft > 0 ? ' sis-timetable-audit__item--warning' : ''}`}>
                                        <span className="sis-timetable-audit__text">
                                            {(o.kind === 'move' ? e.moveTo : e.swapWith).replace('{day}', ctx.dayLabel(o.day)).replace('{period}', periodLabel(o.period_id))} — {impact(o.soft)}
                                        </span>
                                        <Button type="button" size="sm" onClick={() => (o.kind === 'move' ? onMove(o.day, o.period_id) : o.with_schedule_id !== null && onSwap(o.with_schedule_id))}>
                                            {e.apply}
                                        </Button>
                                    </li>
                                ))}
                            </ul>
                        )
                    ) : null}
                </SheetSection>
            ) : null}
            {canSubstitute ? (
                <SheetSection id="timetable-lesson-substitute" title={e.substitute}>
                    <EngineRow>
                        <EngineField label={e.substituteDate}>
                            <input className="sis-admission-sheet__control" type="date" dir="ltr" value={date} onChange={(ev) => setDate(ev.target.value)} />
                        </EngineField>
                        <Button type="button" size="sm" variant="outline" title={e.substituteHint} onClick={() => router.reload({ only: ['substitutes'], data: { substitute: lesson.id, date } })}>
                            {e.substituteFind}
                        </Button>
                    </EngineRow>
                    {substitutes !== null ? (
                        substitutes.candidates.length === 0 ? (
                            <p className="sis-timetable-audit__clean">{substitutes.reason === 'weekday_mismatch' ? e.substituteWeekday : e.substituteNone}</p>
                        ) : (
                            <ul className="sis-timetable-audit__items sis-branches-field--wide">
                                {substitutes.candidates.map((c) => (
                                    <li key={c.teacher_id} className={`sis-timetable-audit__item${c.over_limit ? ' sis-timetable-audit__item--warning' : ''}`}>
                                        <span className="sis-timetable-audit__text">
                                            {c.name} — {e.compatibility} <bdi dir="ltr">{c.compatibility}%</bdi>
                                            {c.qualified ? ` · ${e.qualified}` : ''}
                                            {c.knows_section ? ` · ${e.knowsSection}` : ''}
                                            {` · ${e.lessonsThatDay} ${c.lessons_that_day}`}
                                        </span>
                                        <Button type="button" size="sm" disabled={saving} onClick={() => void assign(c.teacher_id)}>
                                            {e.assign}
                                        </Button>
                                    </li>
                                ))}
                            </ul>
                        )
                    ) : null}
                </SheetSection>
            ) : null}
        </>
    );
}

/** The next date (today or later) falling on the lesson's weekday (day_of_week 1 = Sunday). */
function nextDateFor(dayOfWeek: number): string {
    const date = new Date();
    const delta = (dayOfWeek - 1 - date.getDay() + 7) % 7;
    date.setDate(date.getDate() + delta);

    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
}
