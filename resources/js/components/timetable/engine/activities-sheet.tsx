import { useMemo, useState } from 'react';
import { RegistrySheetDialog } from '@/components/organization/registry-sheet';
import { SheetSection } from '@/components/sis/admission-sheet';
import { Button } from '@/components/ui/button';
import { t } from '@/i18n';
import type { EngineContext } from './engine-context';
import type { EngineActivity } from './engine-types';
import { EngineField, EngineNumber, EngineRow, EngineSelect, toInt, useEngineRequest, engineSheetClass } from './engine-ui';

type Form = {
    subject: string;
    type: string;
    weekly: string;
    block: string;
    distribution: string;
    place: string; // '' | 'room:ID' | 'type:N' | 'workshop:ID'
    week: string;
    note: string;
    lead: string;
    co: string;
    coSessions: string;
    targets: Array<{ section: string; group: string }>;
};

const placeOf = (a: Pick<EngineActivity, 'room_id' | 'room_type' | 'workshop_id'>) =>
    a.workshop_id !== null ? `workshop:${a.workshop_id}` : a.room_id !== null ? `room:${a.room_id}` : a.room_type !== null ? `type:${a.room_type}` : '';

/**
 * «الأنشطة والمجموعات» for one section: its activities (load, block, distribution, room / workshop, week),
 * the requirement lessons not yet configured, a form for a new (joined / divided / co-taught) activity, and
 * the section's divisions into groups.
 */
export function ActivitiesSheet({ ctx, sectionId, onClose }: { ctx: EngineContext; sectionId: number | null; onClose: () => void }) {
    const i18n = t();
    const e = i18n.timetable.engine;
    const request = useEngineRequest();
    const [saving, setSaving] = useState(false);
    const [editing, setEditing] = useState<number | 'new' | null>(null);
    const blank = (): Form => ({ subject: '', type: '1', weekly: '2', block: '1', distribution: '', place: '', week: '0', note: '', lead: '', co: '', coSessions: '', targets: [{ section: String(sectionId ?? ''), group: '' }] });
    const [form, setForm] = useState<Form>(blank);
    const [split, setSplit] = useState({ name: '', count: '2', capacity: '' });

    const activities = ctx.engine.activities.filter((a) => sectionId === null || a.targets.some((tg) => tg.section_id === sectionId));
    const groups = ctx.engine.groups.filter((g) => g.section_id === sectionId);
    const divisions = [...new Map(groups.map((g) => [g.division_id, g.division_name])).entries()];
    const groupName = (id: number | null) => ctx.engine.groups.find((g) => g.id === id)?.name ?? '';
    const roomTypes = [...new Set(ctx.engine.rooms.map((r) => r.room_type).filter((x): x is number => x !== null))];
    const placeOptions = useMemo(() => [
        { value: '', label: e.roomNone },
        ...ctx.engine.workshops.map((w) => ({ value: `workshop:${w.id}`, label: `${e.workshop}: ${w.name} (${w.safety_capacity})` })),
        ...roomTypes.map((n) => ({ value: `type:${n}`, label: `${e.roomType}: ${e.roomTypeNames[n] ?? n}` })),
        ...ctx.engine.rooms.map((r) => ({ value: `room:${r.id}`, label: `${e.room}: ${r.code} — ${r.name}${r.capacity ? ` (${r.capacity})` : ''}` })),
    ], [ctx, e, roomTypes]);
    const weekOptions = [{ value: '0', label: e.everyWeek }, ...Array.from({ length: Math.max(0, ctx.engine.settings.cycle_weeks > 1 ? ctx.engine.settings.cycle_weeks : 0) }, (_, i) => ({ value: String(i + 1), label: e.weekN.replace('{n}', String(i + 1)) }))];
    const set = (key: keyof Form) => (value: string) => setForm((f) => ({ ...f, [key]: value }));

    const edit = (a: EngineActivity) => {
        setEditing(a.id);
        setForm({ ...blank(), subject: String(a.subject_id), type: String(a.activity_type), weekly: String(a.weekly), block: String(a.block), distribution: a.distribution_text ?? '', place: placeOf(a), week: String(a.week_pattern), note: a.note ?? '' });
    };

    const placeFields = () => {
        const [kind, id] = form.place.split(':');

        return { room_id: kind === 'room' ? Number(id) : null, room_type: kind === 'type' ? Number(id) : null, workshop_id: kind === 'workshop' ? Number(id) : null };
    };

    const save = async () => {
        const common = {
            activity_type: Number(form.type), weekly_count: Number(form.weekly), block_length: Number(form.block),
            distribution: form.distribution.trim() === '' ? null : form.distribution.trim(), week_pattern: Number(form.week),
            note: form.note.trim() === '' ? null : form.note.trim(), ...placeFields(),
        };
        setSaving(true);
        const ok = editing === 'new'
            ? await request('post', '/timetable/activities', {
                ...common,
                academic_year_id: ctx.yearId,
                subject_id: Number(form.subject),
                targets: form.targets.filter((tg) => tg.section !== '').map((tg) => ({ section_id: Number(tg.section), group_id: toInt(tg.group) })),
                teachers: [
                    { teacher_id: Number(form.lead), role: 1, sessions: null },
                    ...(form.co === '' ? [] : [{ teacher_id: Number(form.co), role: 2, sessions: toInt(form.coSessions) }]),
                ],
            })
            : await request('patch', `/timetable/activities/${editing}`, common);
        setSaving(false);
        if (ok) {
            setEditing(null);
        }
    };

    const doSplit = async () => {
        if (sectionId === null) {
            return;
        }
        setSaving(true);
        const ok = await request('post', '/timetable/divisions', { academic_year_id: ctx.yearId, section_id: sectionId, name: split.name, group_count: toInt(split.count), capacity: toInt(split.capacity) });
        setSaving(false);
        if (ok) {
            setSplit({ name: '', count: '2', capacity: '' });
        }
    };

    const describe = (a: EngineActivity) => {
        const lead = a.teachers.find((x) => x.role === 1);
        const co = a.teachers.find((x) => x.role !== 1);
        const targets = a.targets.map((tg) => `${ctx.sectionLabel(tg.section_id)}${tg.group_id !== null ? ` / ${groupName(tg.group_id)}` : ''}`).join(' + ');
        const place = placeOptions.find((o) => o.value === placeOf(a))?.label;

        return [
            `${ctx.subjectName(a.subject_id)} (${e.activityTypes[a.activity_type]})`,
            `${a.weekly}×${a.distribution_text ? ` [${a.distribution_text}]` : a.block > 1 ? ` [${e.block} ${a.block}]` : ''}`,
            ctx.teacherName(lead?.teacher_id ?? null) + (co ? ` + ${ctx.teacherName(co.teacher_id)}${co.sessions ? ` (${co.sessions})` : ''}` : ''),
            targets,
            a.week_pattern > 0 ? e.weekN.replace('{n}', String(a.week_pattern)) : '',
            place && placeOf(a) !== '' ? place : '',
        ].filter((x) => x !== '').join(' · ');
    };

    const editor = (
        <SheetSection id="timetable-activity-form" title={editing === 'new' ? e.newActivity : e.edit}>
            <EngineRow>
                {editing === 'new' ? (
                    <EngineField label={e.subject} span={2}>
                        <EngineSelect value={form.subject} label={e.subject} includeBlank onChange={set('subject')} options={ctx.subjects.map((s) => ({ value: String(s.id), label: s.name }))} />
                    </EngineField>
                ) : null}
                <EngineField label={e.activityType}>
                    <EngineSelect value={form.type} label={e.activityType} onChange={set('type')} options={Object.entries(e.activityTypes).map(([v, label]) => ({ value: v, label }))} />
                </EngineField>
                <EngineField label={e.weekly}>
                    <EngineNumber value={form.weekly} onChange={set('weekly')} min={1} max={40} label={e.weekly} />
                </EngineField>
                <EngineField label={e.block}>
                    <EngineNumber value={form.block} onChange={set('block')} min={1} max={6} label={e.block} />
                </EngineField>
                <EngineField label={e.distribution}>
                    <input className="sis-admission-sheet__control" dir="ltr" value={form.distribution} placeholder="2+2+1" onChange={(ev) => set('distribution')(ev.target.value)} />
                </EngineField>
            </EngineRow>
            <EngineRow>
                <EngineField label={e.roomSource} span={2}>
                    <EngineSelect value={form.place} label={e.roomSource} onChange={set('place')} options={placeOptions} />
                </EngineField>
                <EngineField label={e.weekPattern}>
                    <EngineSelect value={form.week} label={e.weekPattern} onChange={set('week')} options={weekOptions} />
                </EngineField>
                <EngineField label={e.note} span={3}>
                    <input className="sis-admission-sheet__control" value={form.note} maxLength={255} onChange={(ev) => set('note')(ev.target.value)} />
                </EngineField>
            </EngineRow>
            {editing === 'new' ? (
                <>
                    <EngineRow>
                        <EngineField label={e.leadTeacher} span={2}>
                            <EngineSelect value={form.lead} label={e.leadTeacher} includeBlank onChange={set('lead')} options={ctx.teachers.map((x) => ({ value: String(x.id), label: x.name }))} />
                        </EngineField>
                        <EngineField label={e.coTeacher} span={2}>
                            <EngineSelect value={form.co} label={e.coTeacher} includeBlank onChange={set('co')} options={ctx.teachers.filter((x) => String(x.id) !== form.lead).map((x) => ({ value: String(x.id), label: x.name }))} />
                        </EngineField>
                        {form.co !== '' ? (
                            <EngineField label={e.sessions}>
                                <EngineNumber value={form.coSessions} onChange={set('coSessions')} min={1} max={40} label={e.sessions} />
                            </EngineField>
                        ) : null}
                    </EngineRow>
                    {form.targets.map((tg, index) => (
                        <EngineRow key={index}>
                            <EngineField label={e.targets} span={2}>
                                <EngineSelect value={tg.section} label={e.targets} includeBlank onChange={(v) => setForm((f) => ({ ...f, targets: f.targets.map((x, i) => (i === index ? { section: v, group: '' } : x)) }))} options={ctx.sections.map((s) => ({ value: String(s.id), label: s.label }))} />
                            </EngineField>
                            <EngineField label={e.group} span={2}>
                                <EngineSelect value={tg.group} label={e.group} onChange={(v) => setForm((f) => ({ ...f, targets: f.targets.map((x, i) => (i === index ? { ...x, group: v } : x)) }))}
                                    options={[{ value: '', label: e.wholeSection }, ...ctx.engine.groups.filter((g) => String(g.section_id) === tg.section).map((g) => ({ value: String(g.id), label: `${g.division_name}: ${g.name}` }))]} />
                            </EngineField>
                        </EngineRow>
                    ))}
                    <Button type="button" size="sm" variant="outline" onClick={() => setForm((f) => ({ ...f, targets: [...f.targets, { section: '', group: '' }] }))}>
                        {e.addTarget}
                    </Button>
                </>
            ) : null}
            <div className="sis-admission-sheet__actions">
                <Button type="button" disabled={saving || (editing === 'new' && (form.subject === '' || form.lead === ''))} onClick={() => void save()}>
                    {saving ? i18n.common.saving : e.save}
                </Button>
                <Button type="button" variant="outline" onClick={() => setEditing(null)}>
                    {i18n.timetable.cancelEdit}
                </Button>
            </div>
        </SheetSection>
    );

    return (
        <RegistrySheetDialog title={`${e.activities}${sectionId !== null ? ` — ${ctx.sectionLabel(sectionId)}` : ''}`} className={engineSheetClass('activities')} onClose={onClose}>
            <div className="sis-timetable-audit__list">
                <SheetSection id="timetable-activities" title={`${e.activities} (${activities.length})`}>
                    {ctx.can.manage ? (
                        <div className="sis-timetable-audit__bar">
                            <Button type="button" size="sm" disabled={saving} title={e.fromCurriculumHint}
                                onClick={() => void request('post', '/timetable/activities/sync', { academic_year_id: ctx.yearId, section_ids: sectionId === null ? null : [sectionId] })}>
                                {e.fromCurriculum}
                            </Button>
                            <Button type="button" size="sm" variant="outline" onClick={() => { setForm(blank()); setEditing('new'); }}>
                                {e.newActivity}
                            </Button>
                        </div>
                    ) : null}
                    <ul className="sis-timetable-audit__items sis-branches-field--wide">
                        {activities.map((a) => (
                            <li key={a.id} className="sis-timetable-audit__item">
                                <span className="sis-timetable-audit__text">{describe(a)}</span>
                                {ctx.can.manage ? (
                                    <span>
                                        <Button type="button" size="sm" variant="outline" onClick={() => edit(a)}>{e.edit}</Button>{' '}
                                        <Button type="button" size="sm" variant="outline" onClick={() => void request('post', `/timetable/activities/${a.id}/end`, { academic_year_id: ctx.yearId })}>{e.end}</Button>
                                    </span>
                                ) : null}
                            </li>
                        ))}
                    </ul>
                </SheetSection>
                {editing !== null && ctx.can.manage ? editor : null}
                <SheetSection id="timetable-groups" title={e.groups}>
                    {sectionId === null ? (
                        <p className="sis-timetable-audit__clean">{e.pickSection}</p>
                    ) : (
                        <>
                            {divisions.length === 0 ? <p className="sis-timetable-audit__clean">{e.noGroups}</p> : null}
                            <ul className="sis-timetable-audit__items sis-branches-field--wide">
                                {divisions.map(([divisionId, name]) => (
                                    <li key={divisionId} className="sis-timetable-audit__item">
                                        <span className="sis-timetable-audit__text">
                                            {name}: {groups.filter((g) => g.division_id === divisionId).map((g) => `${g.name} (${g.members || g.student_count || 0} ${e.members})`).join(' · ')}
                                        </span>
                                        {ctx.can.manage ? (
                                            <Button type="button" size="sm" variant="outline" onClick={() => void request('post', `/timetable/divisions/${divisionId}/end`, { academic_year_id: ctx.yearId })}>{e.end}</Button>
                                        ) : null}
                                    </li>
                                ))}
                            </ul>
                            {ctx.can.manage ? (
                                <EngineRow>
                                    <EngineField label={e.splitName} span={2}>
                                        <input className="sis-admission-sheet__control" value={split.name} maxLength={100} onChange={(ev) => setSplit((s) => ({ ...s, name: ev.target.value }))} />
                                    </EngineField>
                                    <EngineField label={e.groupCount}>
                                        <EngineNumber value={split.count} onChange={(v) => setSplit((s) => ({ ...s, count: v, capacity: '' }))} min={2} max={10} label={e.groupCount} />
                                    </EngineField>
                                    <EngineField label={e.capacity}>
                                        <EngineNumber value={split.capacity} onChange={(v) => setSplit((s) => ({ ...s, capacity: v, count: '' }))} min={1} max={500} label={e.capacity} />
                                    </EngineField>
                                    <Button type="button" disabled={saving || split.name.trim() === '' || (split.count === '' && split.capacity === '')} onClick={() => void doSplit()}>
                                        {e.split}
                                    </Button>
                                </EngineRow>
                            ) : null}
                        </>
                    )}
                </SheetSection>
            </div>
            <div className="sis-admission-sheet__actions">
                <Button type="button" variant="outline" onClick={onClose}>
                    {i18n.timetable.close}
                </Button>
            </div>
        </RegistrySheetDialog>
    );
}
