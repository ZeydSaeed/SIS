import { RotateCcw, Save, Search } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { RegistrySheetDialog } from '@/components/organization/registry-sheet';
import { SheetSection } from '@/components/sis/admission-sheet';
import { Button } from '@/components/ui/button';
import { t } from '@/i18n';
import { DEFAULT_DISPLAY, formatClock, gridData, gridStyle, type AbbreviateField, type CellField, type DisplaySettings } from './display-settings';
import { EngineField, EngineRow, EngineSelect, useEngineRequest } from './engine/engine-ui';
import { CellBody, type CellContext, type CellLesson } from './lesson-card-content';

const FIELDS: CellField[] = ['subject', 'teacher', 'room', 'class', 'section', 'students', 'group', 'branch', 'department', 'subject_type'];
const ABBREVIATE: AbbreviateField[] = ['subject', 'teacher', 'room', 'section', 'class'];

/**
 * «عدسة المعاينة والتنسيق» (🔍): layout, visible fields, abbreviations, alignment, font, sizes, colours and the
 * period header — previewed live on real cells (the clicked cell when opened from one). «تطبيق» changes this
 * screen only; «حفظ للجميع» stores it for the school-year (planners).
 */
export function FormatSheet({
    settings,
    samples,
    sampleStyle,
    yearId,
    canSave,
    focusLabel,
    onApply,
    onClose,
}: {
    settings: DisplaySettings;
    /** Up to two cells to preview (the focused cell first). */
    samples: Array<{ lesson: CellLesson; ctx: CellContext }>;
    sampleStyle: (lesson: CellLesson) => React.CSSProperties | undefined;
    yearId: number | null;
    canSave: boolean;
    focusLabel: string | null;
    onApply: (next: DisplaySettings) => void;
    onClose: () => void;
}) {
    const i18n = t();
    const f = i18n.timetable.format;
    const request = useEngineRequest();
    const [draft, setDraft] = useState<DisplaySettings>(settings);
    const [saving, setSaving] = useState(false);
    // Every change shows on the timetable at once; closing without saving puts the saved look back.
    const initial = useRef(settings);
    const saved = useRef(false);
    useEffect(() => {
        onApply(draft);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [draft]);
    const close = () => {
        if (!saved.current) {
            onApply(initial.current);
        }
        onClose();
    };
    const set = <K extends keyof DisplaySettings>(key: K, value: DisplaySettings[K]) => setDraft((current) => ({ ...current, [key]: value }));
    const options = (map: Record<string, string>) => Object.entries(map).map(([value, label]) => ({ value, label }));

    const save = async () => {
        if (yearId === null) {
            return;
        }
        setSaving(true);
        const ok = await request('post', '/timetable/display', { academic_year_id: yearId, display: draft });
        setSaving(false);
        if (ok) {
            saved.current = true;
            onApply(draft);
            onClose();
        }
    };

    return (
        <RegistrySheetDialog title={f.title} className="sis-branches-sheet sis-timetable-sheet sis-timetable-engine-sheet sis-timetable-engine-sheet--settings sis-timetable-format-sheet" onClose={close}>
            <SheetSection id="timetable-format-preview" title={focusLabel === null ? f.preview : `${f.preview} — ${focusLabel}`}>
                <div className="sis-timetable-grid" {...gridData(draft)} style={gridStyle(draft)}>
                    <div className="sis-timetable-format-preview">
                        {samples.length === 0 ? (
                            <p className="sis-timetable-sheet__hint">{f.noSample}</p>
                        ) : (
                            samples.map((sample, index) => (
                                <div key={index} className="sis-timetable-card" style={sampleStyle(sample.lesson)}>
                                    <CellBody lesson={sample.lesson} settings={draft} ctx={sample.ctx} />
                                </div>
                            ))
                        )}
                    </div>
                    <p className="sis-timetable-sheet__hint">
                        {draft.period_header.show_number ? <span className="sis-timetable-grid__period">{f.headerSample}</span> : null}{' '}
                        {draft.period_header.show_time ? <span className="sis-timetable-grid__time">{formatClock('08:30', draft.period_header.clock, i18n.timetable.am, i18n.timetable.pm)} – {formatClock('09:15', draft.period_header.clock, i18n.timetable.am, i18n.timetable.pm)}</span> : null}
                    </p>
                </div>
            </SheetSection>
            <SheetSection id="timetable-format-layout" title={f.layoutTitle}>
                <EngineRow>
                    <EngineField label={f.layout}>
                        <EngineSelect value={draft.layout} label={f.layout} onChange={(v) => set('layout', v as DisplaySettings['layout'])} options={options(f.layouts)} />
                    </EngineField>
                    <EngineField label={f.colorBy}>
                        <EngineSelect value={draft.color_by} label={f.colorBy} onChange={(v) => set('color_by', v as DisplaySettings['color_by'])} options={options(f.colorByOptions)} />
                    </EngineField>
                </EngineRow>
                <EngineRow>
                    <EngineField label={f.fields} wide>
                        <span className="sis-timetable-audit__bar">
                            {FIELDS.map((field) => (
                                <label key={field} className="sis-timetable-audit__filter">
                                    <input type="checkbox" checked={draft.fields[field]} onChange={(e) => set('fields', { ...draft.fields, [field]: e.target.checked })} />
                                    {f.fieldNames[field]}
                                </label>
                            ))}
                        </span>
                    </EngineField>
                    <EngineField label={f.abbreviate} wide>
                        <span className="sis-timetable-audit__bar">
                            {ABBREVIATE.map((field) => (
                                <label key={field} className="sis-timetable-audit__filter">
                                    <input type="checkbox" checked={draft.abbreviate[field]} onChange={(e) => set('abbreviate', { ...draft.abbreviate, [field]: e.target.checked })} />
                                    {f.fieldNames[field]}
                                </label>
                            ))}
                            <label className="sis-timetable-audit__filter">
                                <input type="checkbox" checked={draft.teacher_title} onChange={(e) => set('teacher_title', e.target.checked)} />
                                {f.teacherTitle}
                            </label>
                        </span>
                    </EngineField>
                    <EngineField label={f.titleStyle}>
                        <EngineSelect
                            value={draft.teacher_title_style}
                            label={f.titleStyle}
                            onChange={(v) => set('teacher_title_style', v as DisplaySettings['teacher_title_style'])}
                            options={options(f.titleStyles)}
                        />
                    </EngineField>
                </EngineRow>
            </SheetSection>
            <SheetSection id="timetable-format-text" title={f.textTitle}>
                <EngineRow>
                    <EngineField label={f.alignV}>
                        <EngineSelect value={draft.align_v} label={f.alignV} onChange={(v) => set('align_v', v as DisplaySettings['align_v'])} options={options(f.alignVOptions)} />
                    </EngineField>
                    <EngineField label={f.alignH}>
                        <EngineSelect value={draft.align_h} label={f.alignH} onChange={(v) => set('align_h', v as DisplaySettings['align_h'])} options={options(f.alignHOptions)} />
                    </EngineField>
                    <EngineField label={f.font}>
                        <EngineSelect value={draft.font_family} label={f.font} onChange={(v) => set('font_family', v as DisplaySettings['font_family'])} options={options(f.fonts)} />
                    </EngineField>
                </EngineRow>
                <EngineRow>
                    <EngineField label={`${f.fontScale} (%)`}>
                        <input className="sis-admission-sheet__control" type="number" dir="ltr" min={70} max={150} step={5} value={draft.font_scale} onChange={(e) => set('font_scale', Math.max(70, Math.min(150, Number(e.target.value) || 100)))} />
                    </EngineField>
                    <EngineField label={f.weight}>
                        <EngineSelect value={String(draft.subject_weight)} label={f.weight} onChange={(v) => set('subject_weight', Number(v) as DisplaySettings['subject_weight'])} options={options(f.weights)} />
                    </EngineField>
                    <EngineField label={f.textColor}>
                        <EngineSelect value={draft.text_color} label={f.textColor} onChange={(v) => set('text_color', v as DisplaySettings['text_color'])} options={options(f.textColors)} />
                    </EngineField>
                </EngineRow>
                <EngineRow>
                    <EngineField label={f.style} wide>
                        <span className="sis-timetable-audit__bar">
                            <label className="sis-timetable-audit__filter">
                                <input type="checkbox" checked={draft.secondary_muted} onChange={(e) => set('secondary_muted', e.target.checked)} />
                                {f.secondaryMuted}
                            </label>
                            <label className="sis-timetable-audit__filter">
                                <input type="checkbox" checked={draft.italic} onChange={(e) => set('italic', e.target.checked)} />
                                {f.italic}
                            </label>
                        </span>
                    </EngineField>
                </EngineRow>
            </SheetSection>
            <SheetSection id="timetable-format-size" title={f.sizeTitle}>
                <EngineRow>
                    <EngineField label={f.cellHeight}>
                        <input className="sis-admission-sheet__control" type="number" dir="ltr" min={24} max={96} step={2} value={draft.cell_height} onChange={(e) => set('cell_height', Math.max(24, Math.min(96, Number(e.target.value) || 32)))} />
                    </EngineField>
                    <EngineField label={f.columnWidth}>
                        <input className="sis-admission-sheet__control" type="number" dir="ltr" min={0} max={200} step={5} value={draft.column_width} onChange={(e) => {
                            const value = Number(e.target.value) || 0;
                            set('column_width', value === 0 ? 0 : Math.max(40, Math.min(200, value)));
                        }} />
                    </EngineField>
                </EngineRow>
                <p className="sis-timetable-sheet__hint">{f.sizeHint}</p>
            </SheetSection>
            <SheetSection id="timetable-format-header" title={f.headerTitle}>
                <EngineRow>
                    <EngineField label={f.headerTitle} wide>
                        <span className="sis-timetable-audit__bar">
                            <label className="sis-timetable-audit__filter">
                                <input type="checkbox" checked={draft.period_header.show_number} onChange={(e) => set('period_header', { ...draft.period_header, show_number: e.target.checked })} />
                                {f.showNumber}
                            </label>
                            <label className="sis-timetable-audit__filter">
                                <input type="checkbox" checked={draft.period_header.number_bold} onChange={(e) => set('period_header', { ...draft.period_header, number_bold: e.target.checked })} />
                                {f.numberBold}
                            </label>
                            <label className="sis-timetable-audit__filter">
                                <input type="checkbox" checked={draft.period_header.show_time} onChange={(e) => set('period_header', { ...draft.period_header, show_time: e.target.checked })} />
                                {f.showTime}
                            </label>
                            <label className="sis-timetable-audit__filter">
                                <input type="checkbox" checked={draft.period_header.time_muted} onChange={(e) => set('period_header', { ...draft.period_header, time_muted: e.target.checked })} />
                                {f.timeMuted}
                            </label>
                        </span>
                    </EngineField>
                    <EngineField label={f.clock}>
                        <EngineSelect value={draft.period_header.clock} label={f.clock} onChange={(v) => set('period_header', { ...draft.period_header, clock: v as '12' | '24' })} options={options(f.clocks)} />
                    </EngineField>
                </EngineRow>
            </SheetSection>
            <div className="sis-admission-sheet__actions">
                <Button type="button" variant="outline" disabled={saving} onClick={close}>
                    {i18n.timetable.close}
                </Button>
                <Button type="button" variant="outline" disabled={saving} onClick={() => setDraft(DEFAULT_DISPLAY)}>
                    <RotateCcw aria-hidden />
                    {f.reset}
                </Button>
                <Button type="button" variant="outline" disabled={saving} onClick={() => onApply(draft)} title={f.applyHint}>
                    <Search aria-hidden />
                    {f.apply}
                </Button>
                {canSave ? (
                    <Button type="button" disabled={saving || yearId === null} onClick={() => void save()} title={f.saveHint}>
                        <Save aria-hidden />
                        {saving ? i18n.common.saving : f.save}
                    </Button>
                ) : null}
            </div>
        </RegistrySheetDialog>
    );
}
