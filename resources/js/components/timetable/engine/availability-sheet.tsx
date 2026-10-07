import { useMemo, useState } from 'react';
import { RegistrySheetDialog } from '@/components/organization/registry-sheet';
import { SheetSection } from '@/components/sis/admission-sheet';
import { Button } from '@/components/ui/button';
import { t } from '@/i18n';
import type { EngineContext } from './engine-context';
import { EngineField, EngineRow, EngineSelect, useEngineRequest, engineSheetClass } from './engine-ui';

type TargetType = 'teacher' | 'room' | 'section' | 'workshop';
const KINDS = [0, 1, 2, 3];
const TONE: Record<number, string> = { 1: 'error', 2: 'warning', 3: 'info' };

/**
 * «الإتاحة»: one teacher / room / section / workshop, days × lessons; a click cycles a cell through
 * available → unavailable → avoid → preferred. Saving sends only the changed cells, grouped by kind.
 */
export function AvailabilitySheet({ ctx, onClose }: { ctx: EngineContext; onClose: () => void }) {
    const i18n = t();
    const e = i18n.timetable.engine;
    const request = useEngineRequest();
    const [type, setType] = useState<TargetType>('teacher');
    const options = useMemo(() => ({
        teacher: ctx.teachers.map((x) => ({ value: String(x.id), label: x.name })),
        room: ctx.engine.rooms.map((r) => ({ value: String(r.id), label: `${r.code} — ${r.name}` })),
        section: ctx.sections.map((s) => ({ value: String(s.id), label: s.label })),
        workshop: ctx.engine.workshops.map((w) => ({ value: String(w.id), label: `${w.code} — ${w.name}` })),
    }), [ctx]);
    const [targetId, setTargetId] = useState(options.teacher[0]?.value ?? '');
    const saved = useMemo(() => {
        const map = new Map<string, number>();
        for (const a of ctx.engine.availability) {
            if (a.week_no === null && String(a[`${type}_id` as const] ?? '') === targetId) {
                map.set(`${a.day}:${a.period_id}`, a.kind);
            }
        }

        return map;
    }, [ctx.engine.availability, type, targetId]);
    const [draft, setDraft] = useState<Map<string, number>>(new Map());
    const [saving, setSaving] = useState(false);
    const kindOf = (key: string) => draft.get(key) ?? saved.get(key) ?? 0;

    const cycle = (key: string) => {
        setDraft((current) => {
            const next = new Map(current);
            next.set(key, (kindOf(key) + 1) % 4);

            return next;
        });
    };

    const save = async () => {
        const byKind = new Map<number, Array<{ day: number; period_id: number }>>();
        for (const [key, kind] of draft) {
            if (kind === (saved.get(key) ?? 0)) {
                continue;
            }
            const [day, period] = key.split(':').map(Number);
            byKind.set(kind, [...(byKind.get(kind) ?? []), { day, period_id: period }]);
        }
        setSaving(true);
        for (const [kind, slots] of byKind) {
            const ok = await request('post', '/timetable/availability', { academic_year_id: ctx.yearId, target_type: type, target_id: Number(targetId), slots, kind: kind === 0 ? null : kind });
            if (!ok) {
                break;
            }
        }
        setSaving(false);
        setDraft(new Map());
    };

    const changeType = (next: string) => {
        setType(next as TargetType);
        setTargetId(options[next as TargetType][0]?.value ?? '');
        setDraft(new Map());
    };

    return (
        <RegistrySheetDialog title={e.availability} className={engineSheetClass('availability')} onClose={onClose}>
            <div className="sis-timetable-audit__list">
                <SheetSection id="timetable-availability-target" title={e.target}>
                    <EngineRow>
                        <EngineField label={e.target}>
                            <EngineSelect value={type} label={e.target} onChange={changeType} options={(['teacher', 'room', 'section', 'workshop'] as const).map((k) => ({ value: k, label: e.targetTypes[k] }))} />
                        </EngineField>
                        <EngineField label={e.targetTypes[type]}>
                            <EngineSelect value={targetId} label={e.targetTypes[type]} onChange={(v) => { setTargetId(v); setDraft(new Map()); }} options={options[type]} />
                        </EngineField>
                    </EngineRow>
                    <p className="sis-timetable-sheet__hint">{e.cellHint}</p>
                    <div className="sis-timetable-audit__bar">
                        {KINDS.map((k) => (
                            <span key={k} className={`sis-timetable-badge sis-timetable-badge--${TONE[k] ?? 'info'}`}>{e.kinds[k]}</span>
                        ))}
                    </div>
                </SheetSection>
                <SheetSection id="timetable-availability-grid" title={e.availability}>
                    {targetId === '' ? null : (
                        <div className="sis-admission-periods-table sis-admission-drafts-table sis-branches-field--wide sis-timetable-availability">
                            <div className="sis-admission-drafts-table__scroller" data-allow-x-scroll>
                                <table>
                                    <colgroup>
                                        <col className="sis-timetable-availability__day" />
                                        {ctx.lessonPeriods.map((p) => (
                                            <col key={p.id} />
                                        ))}
                                    </colgroup>
                                    <thead>
                                        <tr>
                                            <th>{i18n.timetable.day}</th>
                                            {ctx.lessonPeriods.map((p) => (
                                                <th key={p.id} className="sis-admission-drafts-table__num">{p.number}</th>
                                            ))}
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {ctx.days.map((day) => (
                                            <tr key={day}>
                                                <td>{ctx.dayLabel(day)}</td>
                                                {ctx.lessonPeriods.map((p) => {
                                                    const key = `${day}:${p.id}`;
                                                    const kind = kindOf(key);

                                                    return (
                                                        <td key={p.id} className="sis-admission-drafts-table__num">
                                                            <button type="button" className={`sis-timetable-badge sis-timetable-badge--${TONE[kind] ?? 'info'}`} title={e.kinds[kind]} onClick={() => cycle(key)} disabled={!ctx.can.manage}>
                                                                {e.kinds[kind]}
                                                            </button>
                                                        </td>
                                                    );
                                                })}
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    )}
                </SheetSection>
            </div>
            <div className="sis-admission-sheet__actions">
                {ctx.can.manage ? (
                    <Button type="button" disabled={saving || draft.size === 0} onClick={() => void save()}>
                        {saving ? i18n.common.saving : e.saveAvailability}
                    </Button>
                ) : null}
                <Button type="button" variant="outline" onClick={onClose}>
                    {i18n.timetable.close}
                </Button>
            </div>
        </RegistrySheetDialog>
    );
}
