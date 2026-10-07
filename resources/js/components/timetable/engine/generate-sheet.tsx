import { router } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { RegistrySheetDialog } from '@/components/organization/registry-sheet';
import { SheetSection } from '@/components/sis/admission-sheet';
import { Button } from '@/components/ui/button';
import { t } from '@/i18n';
import type { EngineContext } from './engine-context';
import { RUN_ACTIVE, RUN_SUCCEEDED, type RunDetail } from './engine-types';
import { EngineField, EngineNumber, EngineRow, EngineSelect, useEngineRequest, engineSheetClass } from './engine-ui';

type Scenario = { type: 'teacher_absent' | 'workshop_closed' | 'room_closed'; id: string; days: number[] };

/**
 * «توليد الجدول»: scope (school / shown sections / one section / one teacher), mode, time budget, objectives,
 * what-if scenario, a preview of the load; then the runs — progress while one works (the sheet polls only
 * then), and the review of a finished one (unplaced blocks with reasons and single-blocker hints, relaxed
 * rules, difference from the current grid) before it is applied.
 */
export function GenerateSheet({
    ctx,
    visibleSectionIds,
    focusSectionId,
    focusTeacherId,
    preview,
    runDetail,
    onClose,
}: {
    ctx: EngineContext;
    visibleSectionIds: number[];
    focusSectionId: number | null;
    focusTeacherId: number | null;
    preview: { sections: number; teachers: number; lessons: number; locked: number };
    runDetail: RunDetail | null;
    onClose: () => void;
}) {
    const i18n = t();
    const e = i18n.timetable.engine;
    const request = useEngineRequest();
    const [scope, setScope] = useState('school');
    const [mode, setMode] = useState('2');
    const [budget, setBudget] = useState('20');
    const [objectives, setObjectives] = useState<string[]>(['minimize_teacher_gaps']);
    const [scenarios, setScenarios] = useState<Scenario[]>([]);
    const [saving, setSaving] = useState(false);
    const [reviewId, setReviewId] = useState<number | null>(runDetail?.id ?? null);
    const runs = ctx.engine.runs;
    const active = runs.find((r) => RUN_ACTIVE.includes(r.status));

    // Live progress: refresh the engine props every 2 s while a run is queued / running.
    useEffect(() => {
        if (active === undefined) {
            return;
        }
        const timer = window.setInterval(() => router.reload({ only: ['engine', 'flash'] }), 2000);

        return () => window.clearInterval(timer);
    }, [active?.id, active?.status]);

    const review = (id: number) => {
        setReviewId(id);
        router.reload({ only: ['runDetail'], data: { run: id } });
    };

    const start = async () => {
        const scopePayload = scope === 'filtered' ? { section_ids: visibleSectionIds }
            : scope === 'section' && focusSectionId !== null ? { section_ids: [focusSectionId] }
                : scope === 'teacher' && focusTeacherId !== null ? { teacher_ids: [focusTeacherId] } : {};
        setSaving(true);
        await request('post', '/timetable/runs', {
            academic_year_id: ctx.yearId, mode: Number(mode), scope: scopePayload, time_budget: Number(budget), objectives,
            what_if: scenarios.filter((s) => s.id !== '' && s.days.length > 0).map((s) => ({ type: s.type, id: Number(s.id), days: s.days })),
        });
        setSaving(false);
    };

    const runAction = async (id: number, action: 'apply' | 'discard' | 'cancel') => {
        setSaving(true);
        const ok = await request('post', `/timetable/runs/${id}/${action}`, {});
        setSaving(false);
        if (ok && reviewId === id) {
            review(id);
        }
    };

    const saveVersion = async (runId: number) => {
        setSaving(true);
        await request('post', '/timetable/versions', { academic_year_id: ctx.yearId, name: `${e.generate} #${runId}`, generation_run_id: runId });
        setSaving(false);
    };

    const reasonText = (reason: string) => {
        const [kind, id, extra] = reason.split(':');
        if (kind === 'rule') {
            return e.ruleReason.replace('{rule}', e.ruleTypes[id] ?? id);
        }
        const base = e.reasons[kind] ?? kind;
        if (kind.startsWith('teacher_') && id) {
            return `${base} (${ctx.teacherName(Number(id))})`;
        }
        if (kind.startsWith('section_') && id) {
            return `${base} (${ctx.sectionLabel(Number(id))})`;
        }

        return extra ? `${base} (${extra})` : base;
    };

    const detail = runDetail !== null && runDetail.id === reviewId ? runDetail : null;
    const scenarioOptions = (type: Scenario['type']) => (type === 'teacher_absent'
        ? ctx.teachers.map((x) => ({ value: String(x.id), label: x.name }))
        : type === 'workshop_closed' ? ctx.engine.workshops.map((w) => ({ value: String(w.id), label: w.name })) : ctx.engine.rooms.map((r) => ({ value: String(r.id), label: r.name })));

    return (
        <RegistrySheetDialog title={e.generate} className={engineSheetClass('generate')} onClose={onClose}>
            <div className="sis-timetable-audit__list">
                {ctx.can.generate ? (
                    <SheetSection id="timetable-generate-form" title={e.generate}>
                        <EngineRow>
                            <EngineField label={e.scope}>
                                <EngineSelect value={scope} label={e.scope} onChange={setScope} options={[
                                    { value: 'school', label: e.scopeSchool },
                                    { value: 'filtered', label: `${e.scopeFiltered} (${visibleSectionIds.length})` },
                                    ...(focusSectionId !== null ? [{ value: 'section', label: `${e.scopeSection}: ${ctx.sectionLabel(focusSectionId)}` }] : []),
                                    ...(focusTeacherId !== null ? [{ value: 'teacher', label: `${e.scopeTeacher}: ${ctx.teacherName(focusTeacherId)}` }] : []),
                                ]} />
                            </EngineField>
                            <EngineField label={e.mode}>
                                <EngineSelect value={mode} label={e.mode} onChange={setMode} options={Object.entries(e.modes).map(([v, label]) => ({ value: v, label }))} />
                            </EngineField>
                            <EngineField label={e.timeBudget}>
                                <EngineNumber value={budget} onChange={setBudget} min={2} max={120} label={e.timeBudget} />
                            </EngineField>
                        </EngineRow>
                        <EngineRow>
                            <EngineField label={e.objectives} wide>
                                <span className="sis-timetable-audit__bar">
                                    {Object.entries(e.objectiveLabels).map(([code, label]) => (
                                        <label key={code} className="sis-timetable-audit__filter">
                                            <input type="checkbox" checked={objectives.includes(code)} onChange={(ev) => setObjectives((c) => (ev.target.checked ? [...c, code] : c.filter((x) => x !== code)))} />
                                            {label}
                                        </label>
                                    ))}
                                </span>
                            </EngineField>
                        </EngineRow>
                        {scenarios.map((s, index) => (
                            <EngineRow key={index}>
                                <EngineField label={e.whatIf}>
                                    <EngineSelect value={s.type} label={e.whatIf} onChange={(v) => setScenarios((c) => c.map((x, i) => (i === index ? { ...x, type: v as Scenario['type'], id: '' } : x)))}
                                        options={[{ value: 'teacher_absent', label: e.whatIfTeacher }, { value: 'workshop_closed', label: e.whatIfWorkshop }, { value: 'room_closed', label: e.whatIfRoom }]} />
                                </EngineField>
                                <EngineField label={e.target}>
                                    <EngineSelect value={s.id} label={e.target} includeBlank onChange={(v) => setScenarios((c) => c.map((x, i) => (i === index ? { ...x, id: v } : x)))} options={scenarioOptions(s.type)} />
                                </EngineField>
                                <EngineField label={e.whatIfDays} wide>
                                    <span className="sis-timetable-audit__bar">
                                        {ctx.days.map((d) => (
                                            <label key={d} className="sis-timetable-audit__filter">
                                                <input type="checkbox" checked={s.days.includes(d)} onChange={(ev) => setScenarios((c) => c.map((x, i) => (i === index ? { ...x, days: ev.target.checked ? [...x.days, d] : x.days.filter((y) => y !== d) } : x)))} />
                                                {ctx.dayLabel(d)}
                                            </label>
                                        ))}
                                    </span>
                                </EngineField>
                            </EngineRow>
                        ))}
                        <div className="sis-timetable-audit__bar">
                            <Button type="button" size="sm" variant="outline" onClick={() => setScenarios((c) => [...c, { type: 'teacher_absent', id: '', days: [] }])}>
                                {e.whatIf} — {e.addScenario}
                            </Button>
                            <span className="sis-timetable-badge sis-timetable-badge--info" title={e.preview}>
                                {e.previewLine
                                    .replace('{sections}', String(preview.sections))
                                    .replace('{teachers}', String(preview.teachers))
                                    .replace('{activities}', String(ctx.engine.activities.length))
                                    .replace('{lessons}', String(preview.lessons))
                                    .replace('{rules}', String(ctx.engine.rules.length))
                                    .replace('{locked}', String(preview.locked))}
                            </span>
                        </div>
                        <div className="sis-admission-sheet__actions">
                            <Button type="button" disabled={saving || active !== undefined} onClick={() => void start()} title={e.generateHint}>
                                {e.start}
                            </Button>
                        </div>
                    </SheetSection>
                ) : null}

                <SheetSection id="timetable-generate-runs" title={e.runs}>
                    <ul className="sis-timetable-audit__items sis-branches-field--wide">
                        {runs.map((run) => (
                            <li key={run.id} className={`sis-timetable-audit__item${run.status === 4 ? ' sis-timetable-audit__item--error' : ''}`}>
                                <span className="sis-timetable-audit__text">
                                    #{run.id} · {e.modes[run.mode]?.split(' — ')[0]} · {e.runStatus[run.status]}
                                    {run.is_what_if ? ` · ${e.whatIfRun}` : ''}
                                    {RUN_ACTIVE.includes(run.status) && run.progress ? ` · ${e.progress.replace('{placed}', String(run.progress.placed)).replace('{total}', String(run.progress.total))}` : ''}
                                    {run.placed !== null ? ` · ${e.placed} ${run.placed} · ${e.unplaced} ${run.unplaced} · ${e.hardViolations} ${run.hard_violations}` : ''}
                                    {run.quality?.overall != null ? ` · ${e.quality} ${run.quality.overall}%` : ''}
                                    {run.error ? ` · ${run.error}` : ''}
                                </span>
                                <span>
                                    {RUN_ACTIVE.includes(run.status) && ctx.can.generate ? (
                                        <Button type="button" size="sm" variant="outline" disabled={saving} onClick={() => void runAction(run.id, 'cancel')}>{e.cancel}</Button>
                                    ) : null}
                                    {run.status === RUN_SUCCEEDED || run.status === 5 ? (
                                        <Button type="button" size="sm" variant="outline" onClick={() => review(run.id)}>{e.review}</Button>
                                    ) : null}
                                </span>
                            </li>
                        ))}
                    </ul>
                </SheetSection>

                {detail !== null && detail.result !== null ? (
                    <SheetSection id="timetable-generate-review" title={`${e.review} #${detail.id}`}>
                        <div className="sis-timetable-audit__bar">
                            <span className={`sis-timetable-badge sis-timetable-badge--${detail.unplaced === 0 && detail.hard_violations === 0 ? 'info' : 'error'}`}>
                                {e.placed} {detail.placed} · {e.unplaced} {detail.unplaced}
                            </span>
                            <span className="sis-timetable-badge sis-timetable-badge--info">{e.quality}: <bdi dir="ltr">{detail.quality?.overall ?? '—'}%</bdi></span>
                            <span className="sis-timetable-badge sis-timetable-badge--info">{e.elapsed}: <bdi dir="ltr">{(detail.result.stats.elapsed_ms / 1000).toFixed(1)}s</bdi></span>
                        </div>
                        {detail.result.diff !== null ? (
                            <p className="sis-timetable-sheet__hint">
                                {e.diffTitle}: {Object.entries(detail.result.diff.counts).reduce<string>((text, [k, v]) => text.replace(`{${k}}`, String(v)), e.diffLine)}
                            </p>
                        ) : null}
                        {detail.result.unplaced.length > 0 ? (
                            <ul className="sis-timetable-audit__items sis-branches-field--wide">
                                {detail.result.unplaced.map((u) => (
                                    <li key={u.card_id} className="sis-timetable-audit__item sis-timetable-audit__item--error">
                                        <span className="sis-timetable-audit__text">
                                            {ctx.subjectName(u.subject_id)} · {ctx.teacherName(u.teacher_id)} · {u.section_ids.map((s) => ctx.sectionLabel(s)).join(' + ')}
                                            {' — '}
                                            {Object.entries(u.reasons).slice(0, 3).map(([r, n]) => `${reasonText(r)} ×${n}`).join('، ')}
                                            {u.suggestions.length > 0 ? ` — ${e.suggestionsTitle}: ${u.suggestions.slice(0, 2).map((s) => `${reasonText(s.reason)}${s.day ? ` (${ctx.dayLabel(s.day)} · ${s.lesson})` : ''}`).join('، ')}` : ''}
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        ) : null}
                        {detail.result.violations.length > 0 ? (
                            <ul className="sis-timetable-audit__items sis-branches-field--wide">
                                {detail.result.violations.slice(0, 12).map((v, i) => (
                                    <li key={i} className={`sis-timetable-audit__item sis-timetable-audit__item--${v.hard ? 'error' : 'warning'}`}>
                                        <span className="sis-timetable-audit__text">
                                            {e.ruleTypes[v.rule_type] ?? v.rule_type} ×{v.count} · {e.priorities[v.priority]} · {e.sources[v.source] ?? v.source}
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        ) : null}
                        {ctx.can.generate ? (
                            <div className="sis-admission-sheet__actions">
                                {detail.status === RUN_SUCCEEDED && !detail.is_what_if ? (
                                    <Button type="button" disabled={saving} onClick={() => void runAction(detail.id, 'apply')}>{e.applyRun}</Button>
                                ) : null}
                                {detail.status === 6 && ctx.can.publish ? (
                                    <Button type="button" variant="outline" disabled={saving} onClick={() => void saveVersion(detail.id)}>{e.saveAsVersion}</Button>
                                ) : null}
                                {detail.status === RUN_SUCCEEDED || detail.status === 5 ? (
                                    <Button type="button" variant="outline" disabled={saving} onClick={() => void runAction(detail.id, 'discard')}>{e.discard}</Button>
                                ) : null}
                            </div>
                        ) : null}
                    </SheetSection>
                ) : null}
            </div>
            <div className="sis-admission-sheet__actions">
                <Button type="button" variant="outline" onClick={onClose}>
                    {i18n.timetable.close}
                </Button>
            </div>
        </RegistrySheetDialog>
    );
}
