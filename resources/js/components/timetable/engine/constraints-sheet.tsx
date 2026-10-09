import { useMemo, useState } from 'react';
import { RegistrySheetDialog } from '@/components/organization/registry-sheet';
import { SheetSection } from '@/components/sis/admission-sheet';
import { Button } from '@/components/ui/button';
import { t } from '@/i18n';
import type { EngineContext } from './engine-context';
import type { EngineRule } from './engine-types';
import { EngineField, EngineNumber, EngineRow, EngineSelect, useEngineConfirm, useEngineRequest, engineSheetClass } from './engine-ui';

const PRIORITY_TONE: Record<number, string> = { 1: 'error', 2: 'error', 3: 'warning', 4: 'warning', 5: 'info', 6: 'info' };

/**
 * «القيود»: the school's constraint rules — type, priority (CRITICAL is hard; the generation mode decides
 * how far down rules are hard), where each comes from, and a form built from the rule catalogue.
 */
export function ConstraintsSheet({ ctx, onClose }: { ctx: EngineContext; onClose: () => void }) {
    const i18n = t();
    const e = i18n.timetable.engine;
    const request = useEngineRequest();
    const confirm = useEngineConfirm();
    const catalogue = ctx.engine.catalogue;
    const [type, setType] = useState(catalogue[0]?.type ?? '');
    const [priority, setPriority] = useState('3');
    const [scope, setScope] = useState<Record<string, string>>({});
    const [params, setParams] = useState<Record<string, string>>({});
    const [days, setDays] = useState<number[]>([]);
    const [lessons, setLessons] = useState<number[]>([]);
    const [reason, setReason] = useState('');
    const [saving, setSaving] = useState(false);
    const entry = catalogue.find((c) => c.type === type);

    const scopeOptions = useMemo((): Record<string, Array<{ value: string; label: string }>> => {
        const activityLabel = (id: number) => {
            const a = ctx.engine.activities.find((x) => x.id === id);

            return a === undefined ? `#${id}` : `${ctx.subjectName(a.subject_id)} — ${a.targets.map((tg) => ctx.sectionLabel(tg.section_id)).join('، ')}`;
        };

        return {
            branch_id: ctx.branches.map((b) => ({ value: String(b.id), label: b.name })),
            department_id: ctx.branches.flatMap((b) => b.departments.map((d) => ({ value: String(d.id), label: `${b.name} — ${d.name}` }))),
            class_id: [...new Map(ctx.sections.map((s) => [s.classId, s.label.split(' — ')[0] ?? ''])).entries()].map(([id, name]) => ({ value: String(id), label: name })),
            section_id: ctx.sections.map((s) => ({ value: String(s.id), label: s.label })),
            teacher_id: ctx.teachers.map((x) => ({ value: String(x.id), label: x.name })),
            subject_id: ctx.subjects.map((x) => ({ value: String(x.id), label: x.name })),
            room_id: ctx.engine.rooms.map((r) => ({ value: String(r.id), label: `${r.code} — ${r.name}` })),
            activity_id: ctx.engine.activities.map((a) => ({ value: String(a.id), label: activityLabel(a.id) })),
            other_activity_id: ctx.engine.activities.map((a) => ({ value: String(a.id), label: activityLabel(a.id) })),
        };
    }, [ctx]);

    const scopeText = (rule: EngineRule) => {
        const parts = Object.entries(rule.scope)
            .filter(([, id]) => id !== null)
            .map(([column, id]) => `${e.scopes[column] ?? column}: ${scopeOptions[column]?.find((o) => o.value === String(id))?.label ?? `#${id}`}`);

        return parts.length === 0 ? e.everyone : parts.join(' · ');
    };
    const paramsText = (rule: EngineRule) =>
        Object.entries(rule.params)
            .map(([name, value]) => {
                if (name === 'days' && Array.isArray(value)) {
                    return `${e.params.days}: ${value.map((d) => ctx.dayLabel(d)).join('، ')}`;
                }
                if (name === 'other_teacher_id' && typeof value === 'number') {
                    return `${e.params[name]}: ${ctx.teacherName(value)}`;
                }

                return `${e.params[name] ?? name}: ${Array.isArray(value) ? value.join('، ') : value}`;
            })
            .join(' · ');

    const changeType = (next: string) => {
        setType(next);
        setScope({});
        setParams({});
        setDays([]);
        setLessons([]);
    };

    const save = async () => {
        if (entry === undefined) {
            return;
        }
        const payloadParams: Record<string, number | number[]> = {};
        for (const [name, kind] of Object.entries(entry.params)) {
            if (kind.startsWith('days')) {
                payloadParams[name] = days;
            } else if (kind.startsWith('lessons')) {
                payloadParams[name] = lessons;
            } else if ((params[name] ?? '') !== '') {
                payloadParams[name] = Number(params[name]);
            }
        }
        const payloadScope: Record<string, number> = {};
        for (const [column, value] of Object.entries(scope)) {
            if (value !== '') {
                payloadScope[column] = Number(value);
            }
        }
        setSaving(true);
        const ok = await request('post', '/timetable/rules', { academic_year_id: ctx.yearId, rule_type: type, priority: Number(priority), scope: payloadScope, params: payloadParams, reason: reason.trim() === '' ? null : reason });
        setSaving(false);
        if (ok) {
            changeType(type);
            setReason('');
        }
    };

    return (
        <RegistrySheetDialog title={e.constraints} className={engineSheetClass('constraints')} onClose={onClose}>
            <div className="sis-timetable-audit__list">
                <SheetSection id="timetable-rules-list" title={`${e.constraints} (${ctx.engine.rules.length})`}>
                    {ctx.engine.rules.length === 0 ? (
                        <p className="sis-timetable-audit__clean">{e.noRules}</p>
                    ) : (
                        <ul className="sis-timetable-audit__items sis-branches-field--wide">
                            {ctx.engine.rules.map((rule) => (
                                <li key={rule.id} className={`sis-timetable-audit__item sis-timetable-audit__item--${rule.priority <= 2 ? 'error' : rule.priority <= 4 ? 'warning' : 'info'}`}>
                                    <span className="sis-timetable-audit__text">
                                        <span className={`sis-timetable-badge sis-timetable-badge--${PRIORITY_TONE[rule.priority]}`}>{e.priorities[rule.priority]}</span>{' '}
                                        {e.ruleTypes[rule.rule_type] ?? rule.rule_type} — {e.appliesTo}: {scopeText(rule)}
                                        {paramsText(rule) !== '' ? ` — ${paramsText(rule)}` : ''}
                                        {rule.reason ? ` — ${e.reasonLabel}: ${rule.reason}` : ''}
                                    </span>
                                    {ctx.can.manage ? (
                                        <Button type="button" size="sm" variant="outline" onClick={() => confirm.ask(e.confirmEndRule, () => void request('post', `/timetable/rules/${rule.id}/end`, { academic_year_id: ctx.yearId }))}>
                                            {e.end}
                                        </Button>
                                    ) : null}
                                </li>
                            ))}
                        </ul>
                    )}
                </SheetSection>
                {ctx.can.manage && entry !== undefined ? (
                    <SheetSection id="timetable-rules-new" title={e.newRule}>
                        <EngineRow>
                            <EngineField label={e.ruleType}>
                                <EngineSelect value={type} label={e.ruleType} onChange={changeType} options={catalogue.map((c) => ({ value: c.type, label: e.ruleTypes[c.type] ?? c.type }))} />
                            </EngineField>
                            <EngineField label={e.priority}>
                                <EngineSelect value={priority} label={e.priority} onChange={setPriority} options={[1, 2, 3, 4, 5, 6].map((p) => ({ value: String(p), label: e.priorities[p] }))} />
                            </EngineField>
                        </EngineRow>
                        <EngineRow>
                            {entry.scopes.filter((column) => column !== 'grade_level_id').map((column) => (
                                <EngineField key={column} label={e.scopes[column] ?? column}>
                                    <EngineSelect value={scope[column] ?? ''} label={e.scopes[column] ?? column} includeBlank onChange={(v) => setScope((s) => ({ ...s, [column]: v }))} options={scopeOptions[column] ?? []} />
                                </EngineField>
                            ))}
                        </EngineRow>
                        <EngineRow>
                            {Object.entries(entry.params).map(([name, kind]) => {
                                if (kind.startsWith('days')) {
                                    return (
                                        <EngineField key={name} label={e.params.days} wide>
                                            <span className="sis-timetable-audit__bar">
                                                {ctx.days.map((d) => (
                                                    <label key={d} className="sis-timetable-audit__filter">
                                                        <input type="checkbox" checked={days.includes(d)} onChange={(ev) => setDays((c) => (ev.target.checked ? [...c, d] : c.filter((x) => x !== d)))} />
                                                        {ctx.dayLabel(d)}
                                                    </label>
                                                ))}
                                            </span>
                                        </EngineField>
                                    );
                                }
                                if (kind.startsWith('lessons')) {
                                    return (
                                        <EngineField key={name} label={e.params.lessons} wide>
                                            <span className="sis-timetable-audit__bar">
                                                {ctx.lessonPeriods.map((p) => (
                                                    <label key={p.id} className="sis-timetable-audit__filter">
                                                        <input type="checkbox" checked={lessons.includes(p.number)} onChange={(ev) => setLessons((c) => (ev.target.checked ? [...c, p.number] : c.filter((x) => x !== p.number)))} />
                                                        {p.number}
                                                    </label>
                                                ))}
                                            </span>
                                        </EngineField>
                                    );
                                }
                                if (name === 'other_teacher_id') {
                                    return (
                                        <EngineField key={name} label={e.params[name]}>
                                            <EngineSelect value={params[name] ?? ''} label={e.params[name]} includeBlank onChange={(v) => setParams((p) => ({ ...p, [name]: v }))} options={scopeOptions.teacher_id} />
                                        </EngineField>
                                    );
                                }

                                return (
                                    <EngineField key={name} label={e.params[name] ?? name}>
                                        <EngineNumber value={params[name] ?? ''} onChange={(v) => setParams((p) => ({ ...p, [name]: v }))} min={kind === 'int0' ? 0 : 1} max={40} label={e.params[name] ?? name} />
                                    </EngineField>
                                );
                            })}
                            <EngineField label={e.reasonLabel} wide>
                                <input className="sis-admission-sheet__control" value={reason} maxLength={255} onChange={(ev) => setReason(ev.target.value)} />
                            </EngineField>
                        </EngineRow>
                    </SheetSection>
                ) : null}
            </div>
            <div className="sis-admission-sheet__actions">
                {ctx.can.manage ? (
                    <Button type="button" disabled={saving || entry === undefined} onClick={() => void save()}>
                        {saving ? i18n.common.saving : e.newRule}
                    </Button>
                ) : null}
                <Button type="button" variant="outline" onClick={onClose}>
                    {i18n.timetable.close}
                </Button>
            </div>
            {confirm.dialog}
        </RegistrySheetDialog>
    );
}
