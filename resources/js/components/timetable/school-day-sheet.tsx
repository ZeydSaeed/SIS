import { ArrowDownToLine, Coffee, MoveVertical, Pencil, Plus, Timer, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { RegistrySheetDialog, useRegistryRequest } from '@/components/organization/registry-sheet';
import { AppearanceFields } from '@/components/sis/appearance-fields';
import { SheetSection } from '@/components/sis/admission-sheet';
import { ConfirmDialog } from '@/components/sis/confirm-dialog';
import { Button } from '@/components/ui/button';
import { t } from '@/i18n';
import { EngineField, EngineRow, EngineSelect } from './engine/engine-ui';

export type DayPeriod = {
    id: number;
    period_number: number;
    start_time: string;
    end_time: string;
    period_type: number;
    name?: string | null;
    abbreviation?: string | null;
    color_hue?: number | null;
    show_in?: number;
    print_in?: number;
};

const LESSON = 1;
/** Where a period shows / prints (bitmask, server PeriodPresentation). */
export const TARGETS = [
    ['general', 1],
    ['teachers', 2],
    ['sections', 4],
    ['students', 8],
    ['rooms', 16],
] as const;
const EVERYWHERE = 31;
const RELOAD = ['periods', 'issues', 'advice', 'workload', 'quality', 'flash', 'display'];

const minutesOf = (time: string) => {
    const [h, m] = time.split(':').map(Number);

    return (h ?? 0) * 60 + (m ?? 0);
};
const length = (p: DayPeriod) => minutesOf(p.end_time) - minutesOf(p.start_time);

type Editor =
    | { kind: 'edit'; period: DayPeriod; start: string; end: string; cascade: boolean; name: string; appearance: { abbreviation: string; color_hue: number | null }; show: number; print: number }
    | { kind: 'insert'; after: number | null; minutes: string; name: string; appearance: { abbreviation: string; color_hue: number | null }; show: number; print: number }
    | { kind: 'move'; period: DayPeriod; after: string };

/**
 * «اليوم الدراسي والاستراحات»: the periods of the day with automatic re-timing («تزحيف»). Insert a break after any
 * period (or before the first), several and consecutive breaks, move / resize / remove a break, re-time one period
 * (with or without moving the following ones), name it («استراحة الطلاب بعد الحصة الرابعة»), colour it and choose
 * where it shows and prints. «النمط الثابت» gives every lesson the same length; breaks keep theirs.
 */
export function SchoolDaySheet({ periods, lessonNumber, clock, onClose }: { periods: DayPeriod[]; lessonNumber: Map<number, number>; clock: (time: string) => string; onClose: () => void }) {
    const i18n = t();
    const tt = i18n.timetable;
    const d = tt.schoolDay;
    const request = useRegistryRequest(RELOAD);
    const [editor, setEditor] = useState<Editor | null>(null);
    const [saving, setSaving] = useState(false);
    const [confirmRemove, setConfirmRemove] = useState<DayPeriod | null>(null);
    const firstLesson = periods.find((p) => p.period_type === LESSON);
    const [dayStart, setDayStart] = useState(periods[0]?.start_time ?? '08:00');
    const [lessonMinutes, setLessonMinutes] = useState(String(firstLesson === undefined ? 45 : length(firstLesson)));

    const reshape = async (body: Record<string, string | number | boolean | null>) => {
        setSaving(true);
        const ok = await request('post', '/timetable/periods/reshape', body);
        setSaving(false);
        if (ok) {
            setEditor(null);
        }

        return ok;
    };
    const presentation = (name: string, appearance: { abbreviation: string; color_hue: number | null }, show: number, print: number) => ({
        name: name.trim() === '' ? null : name.trim(),
        abbreviation: appearance.abbreviation.trim() === '' ? null : appearance.abbreviation.trim(),
        color_hue: appearance.color_hue,
        show_in: show,
        print_in: print,
    });
    const label = (p: DayPeriod) =>
        p.period_type === LESSON ? `${tt.periodLabel} ${lessonNumber.get(p.id) ?? ''}` : `${p.name ?? tt.breakLabel} · ${length(p)} ${tt.minutesShort}`;
    const targetsText = (mask: number | undefined) =>
        (mask ?? EVERYWHERE) === EVERYWHERE ? d.everywhere : TARGETS.filter(([, bit]) => ((mask ?? EVERYWHERE) & bit) === bit).map(([key]) => d.targets[key]).join('، ') || d.nowhere;
    const afterOptions = [{ value: '', label: d.beforeFirst }, ...periods.map((p) => ({ value: String(p.id), label: `${d.after} ${label(p)}` }))];

    const targetBoxes = (value: number, onChange: (next: number) => void) => (
        <span className="sis-timetable-audit__bar">
            {TARGETS.map(([key, bit]) => (
                <label key={key} className="sis-timetable-audit__filter">
                    <input type="checkbox" checked={(value & bit) === bit} onChange={(e) => onChange(e.target.checked ? value | bit : value & ~bit)} />
                    {d.targets[key]}
                </label>
            ))}
        </span>
    );

    return (
        <RegistrySheetDialog title={d.title} className="sis-branches-sheet sis-timetable-sheet sis-timetable-periods-sheet" onClose={onClose}>
            <SheetSection id="timetable-day-pattern" title={d.fixedTitle}>
                <EngineRow>
                    <EngineField label={tt.dayStart}>
                        <input className="sis-admission-sheet__control" type="time" dir="ltr" value={dayStart} onChange={(e) => setDayStart(e.target.value)} />
                    </EngineField>
                    <EngineField label={tt.lessonMinutes}>
                        <input className="sis-admission-sheet__control" type="number" min={5} max={180} dir="ltr" value={lessonMinutes} onChange={(e) => setLessonMinutes(e.target.value)} />
                    </EngineField>
                </EngineRow>
                <div className="sis-admission-sheet__actions">
                    <Button type="button" disabled={saving || dayStart === '' || lessonMinutes === ''} title={d.fixedHint} onClick={() => void reshape({ operation: 'fixed_pattern', start_time: dayStart.slice(0, 5), minutes: Number(lessonMinutes) })}>
                        <Timer aria-hidden />
                        {d.applyFixed}
                    </Button>
                </div>
                <p className="sis-timetable-sheet__hint">{d.fixedHint}</p>
            </SheetSection>

            <SheetSection id="timetable-day-periods" title={d.periodsTitle}>
                <div className="sis-timetable-periods sis-branches-field--wide">
                    <table>
                        <thead>
                            <tr>
                                <th>{tt.periodOrder}</th>
                                <th>{d.what}</th>
                                <th>{tt.startTime}</th>
                                <th>{tt.endTime}</th>
                                <th>{d.minutes}</th>
                                <th>{d.showIn}</th>
                                <th aria-hidden="true" />
                            </tr>
                        </thead>
                        <tbody>
                            {periods.map((p) => (
                                <tr key={p.id} className={p.period_type === LESSON ? '' : 'sis-timetable-periods__break'} onDoubleClick={() => setEditor({ kind: 'edit', period: p, start: p.start_time.slice(0, 5), end: p.end_time.slice(0, 5), cascade: true, name: p.name ?? '', appearance: { abbreviation: p.abbreviation ?? '', color_hue: p.color_hue ?? null }, show: p.show_in ?? EVERYWHERE, print: p.print_in ?? EVERYWHERE })}>
                                    <td dir="ltr">{p.period_number}</td>
                                    <td>{label(p)}</td>
                                    <td>{clock(p.start_time)}</td>
                                    <td>{clock(p.end_time)}</td>
                                    <td dir="ltr">{length(p)}</td>
                                    <td>{targetsText(p.show_in)}</td>
                                    <td className="sis-timetable-periods__actions">
                                        <Button type="button" size="sm" variant="outline" title={d.edit} disabled={saving} onClick={() => setEditor({ kind: 'edit', period: p, start: p.start_time.slice(0, 5), end: p.end_time.slice(0, 5), cascade: true, name: p.name ?? '', appearance: { abbreviation: p.abbreviation ?? '', color_hue: p.color_hue ?? null }, show: p.show_in ?? EVERYWHERE, print: p.print_in ?? EVERYWHERE })}>
                                            <Pencil aria-hidden />
                                        </Button>
                                        <Button type="button" size="sm" variant="outline" title={d.insertAfter} disabled={saving} onClick={() => setEditor({ kind: 'insert', after: p.id, minutes: '15', name: '', appearance: { abbreviation: '', color_hue: null }, show: EVERYWHERE, print: EVERYWHERE })}>
                                            <Coffee aria-hidden />
                                        </Button>
                                        {p.period_type !== LESSON ? (
                                            <>
                                                <Button type="button" size="sm" variant="outline" title={d.move} disabled={saving} onClick={() => setEditor({ kind: 'move', period: p, after: '' })}>
                                                    <MoveVertical aria-hidden />
                                                </Button>
                                                <Button type="button" size="sm" variant="outline" title={d.remove} disabled={saving} onClick={() => setConfirmRemove(p)}>
                                                    <Trash2 aria-hidden />
                                                </Button>
                                            </>
                                        ) : null}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
                <div className="sis-admission-sheet__actions">
                    <Button type="button" variant="outline" disabled={saving} onClick={() => setEditor({ kind: 'insert', after: null, minutes: '10', name: '', appearance: { abbreviation: '', color_hue: null }, show: EVERYWHERE, print: EVERYWHERE })}>
                        <ArrowDownToLine aria-hidden />
                        {d.insertFirst}
                    </Button>
                    <Button type="button" disabled={saving || periods.length === 0} onClick={() => setEditor({ kind: 'insert', after: periods[periods.length - 1]?.id ?? null, minutes: '15', name: '', appearance: { abbreviation: '', color_hue: null }, show: EVERYWHERE, print: EVERYWHERE })}>
                        <Plus aria-hidden />
                        {d.addBreak}
                    </Button>
                </div>
            </SheetSection>

            {editor?.kind === 'edit' ? (
                <SheetSection id="timetable-day-edit" title={`${d.edit} — ${label(editor.period)}`}>
                    <EngineRow>
                        <EngineField label={tt.startTime}>
                            <input className="sis-admission-sheet__control" type="time" dir="ltr" value={editor.start} onChange={(e) => setEditor({ ...editor, start: e.target.value })} />
                        </EngineField>
                        <EngineField label={tt.endTime}>
                            <input className="sis-admission-sheet__control" type="time" dir="ltr" value={editor.end} onChange={(e) => setEditor({ ...editor, end: e.target.value })} />
                        </EngineField>
                        <EngineField label={d.cascade}>
                            <label className="sis-timetable-audit__filter">
                                <input type="checkbox" checked={editor.cascade} onChange={(e) => setEditor({ ...editor, cascade: e.target.checked })} />
                                {d.cascadeHint}
                            </label>
                        </EngineField>
                    </EngineRow>
                    <EngineRow>
                        <EngineField label={d.name} wide>
                            <input className="sis-admission-sheet__control" value={editor.name} maxLength={60} placeholder={d.namePlaceholder} onChange={(e) => setEditor({ ...editor, name: e.target.value })} />
                        </EngineField>
                    </EngineRow>
                    <div className="sis-admission-sheet__row sis-admission-sheet__row--full sis-branches-sheet__row">
                        <AppearanceFields value={editor.appearance} onChange={(appearance) => setEditor({ ...editor, appearance })} editing previewTitle={label(editor.period)} />
                    </div>
                    <EngineRow>
                        <EngineField label={d.showIn} wide>
                            {targetBoxes(editor.show, (show) => setEditor({ ...editor, show }))}
                        </EngineField>
                        <EngineField label={d.printIn} wide>
                            {targetBoxes(editor.print, (print) => setEditor({ ...editor, print }))}
                        </EngineField>
                    </EngineRow>
                    <div className="sis-admission-sheet__actions">
                        <Button type="button" variant="outline" disabled={saving} onClick={() => setEditor(null)}>
                            {tt.cancelEdit}
                        </Button>
                        <Button
                            type="button"
                            disabled={saving || editor.start === '' || editor.end === ''}
                            onClick={() =>
                                void reshape({
                                    operation: 'retime',
                                    period_id: editor.period.id,
                                    start_time: editor.start.slice(0, 5),
                                    end_time: editor.end.slice(0, 5),
                                    cascade: editor.cascade,
                                    ...presentation(editor.name, editor.appearance, editor.show, editor.print),
                                })
                            }
                        >
                            {saving ? i18n.common.saving : tt.savePeriod}
                        </Button>
                    </div>
                </SheetSection>
            ) : null}

            {editor?.kind === 'insert' ? (
                <SheetSection id="timetable-day-insert" title={d.newBreak}>
                    <EngineRow>
                        <EngineField label={d.position}>
                            <EngineSelect value={editor.after === null ? '' : String(editor.after)} label={d.position} onChange={(v) => setEditor({ ...editor, after: v === '' ? null : Number(v) })} options={afterOptions} />
                        </EngineField>
                        <EngineField label={d.minutes}>
                            <input className="sis-admission-sheet__control" type="number" min={1} max={180} dir="ltr" value={editor.minutes} onChange={(e) => setEditor({ ...editor, minutes: e.target.value })} />
                        </EngineField>
                    </EngineRow>
                    <EngineRow>
                        <EngineField label={d.name} wide>
                            <input className="sis-admission-sheet__control" value={editor.name} maxLength={60} placeholder={d.namePlaceholder} onChange={(e) => setEditor({ ...editor, name: e.target.value })} />
                        </EngineField>
                    </EngineRow>
                    <div className="sis-admission-sheet__row sis-admission-sheet__row--full sis-branches-sheet__row">
                        <AppearanceFields value={editor.appearance} onChange={(appearance) => setEditor({ ...editor, appearance })} editing previewTitle={editor.name || tt.breakLabel} />
                    </div>
                    <EngineRow>
                        <EngineField label={d.showIn} wide>
                            {targetBoxes(editor.show, (show) => setEditor({ ...editor, show }))}
                        </EngineField>
                        <EngineField label={d.printIn} wide>
                            {targetBoxes(editor.print, (print) => setEditor({ ...editor, print }))}
                        </EngineField>
                    </EngineRow>
                    <p className="sis-timetable-sheet__hint">{d.insertHint}</p>
                    <div className="sis-admission-sheet__actions">
                        <Button type="button" variant="outline" disabled={saving} onClick={() => setEditor(null)}>
                            {tt.cancelEdit}
                        </Button>
                        <Button
                            type="button"
                            disabled={saving || editor.minutes === ''}
                            onClick={() => void reshape({ operation: 'insert_break', after_period_id: editor.after, minutes: Number(editor.minutes), ...presentation(editor.name, editor.appearance, editor.show, editor.print) })}
                        >
                            {saving ? i18n.common.saving : d.addBreak}
                        </Button>
                    </div>
                </SheetSection>
            ) : null}

            {editor?.kind === 'move' ? (
                <SheetSection id="timetable-day-move" title={`${d.move} — ${label(editor.period)}`}>
                    <EngineRow>
                        <EngineField label={d.position}>
                            <EngineSelect value={editor.after} label={d.position} onChange={(v) => setEditor({ ...editor, after: v })} options={afterOptions.filter((o) => o.value !== String(editor.period.id))} />
                        </EngineField>
                        <EngineField label={d.minutes}>
                            <span className="sis-timetable-audit__bar">
                                {[5, 10, 15, 20, 30].map((m) => (
                                    <Button key={m} type="button" size="sm" variant={length(editor.period) === m ? 'default' : 'outline'} disabled={saving} onClick={() => void reshape({ operation: 'resize', period_id: editor.period.id, minutes: m })}>
                                        {m}
                                    </Button>
                                ))}
                            </span>
                        </EngineField>
                    </EngineRow>
                    <p className="sis-timetable-sheet__hint">{d.moveHint}</p>
                    <div className="sis-admission-sheet__actions">
                        <Button type="button" variant="outline" disabled={saving} onClick={() => setEditor(null)}>
                            {tt.cancelEdit}
                        </Button>
                        <Button type="button" disabled={saving} onClick={() => void reshape({ operation: 'move_break', period_id: editor.period.id, after_period_id: editor.after === '' ? null : Number(editor.after) })}>
                            {d.move}
                        </Button>
                    </div>
                </SheetSection>
            ) : null}

            <div className="sis-admission-sheet__actions">
                <Button type="button" variant="outline" onClick={onClose}>
                    {tt.close}
                </Button>
            </div>
            <ConfirmDialog
                open={confirmRemove !== null}
                title={d.remove}
                description={d.removeConfirm}
                confirmLabel={d.remove}
                tone="danger"
                onConfirm={() => {
                    if (confirmRemove !== null) {
                        void reshape({ operation: 'remove_break', period_id: confirmRemove.id });
                    }
                    setConfirmRemove(null);
                }}
                onOpenChange={(open) => {
                    if (!open) {
                        setConfirmRemove(null);
                    }
                }}
            />
        </RegistrySheetDialog>
    );
}
