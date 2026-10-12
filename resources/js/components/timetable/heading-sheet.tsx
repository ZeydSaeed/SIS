import { Save } from 'lucide-react';
import { useState } from 'react';
import { RegistrySheetDialog } from '@/components/organization/registry-sheet';
import { SheetSection } from '@/components/sis/admission-sheet';
import { Button } from '@/components/ui/button';
import { t } from '@/i18n';
import type { DisplaySettings } from './display-settings';
import { useEngineRequest } from './engine/engine-ui';

/**
 * «ترويسة الجدول»: the line above every printed sheet — «اسم المدرسة · جدول الدروس الأسبوعي للسنة الدراسية … ·
 * المرحلة: … · يعمل بموجبه اعتباراً من …». The school comes from the account, the year from the academic year and
 * the stage from each sheet's class; only «يعمل بموجبه اعتباراً من» is set here (stored with «تنسيق الجدول»).
 */
export function HeadingSheet({
    settings,
    yearId,
    school,
    year,
    stages,
    canSave,
    onSaved,
    onClose,
}: {
    settings: DisplaySettings;
    yearId: number;
    school: string;
    year: string;
    stages: string[];
    canSave: boolean;
    onSaved: (next: DisplaySettings) => void;
    onClose: () => void;
}) {
    const i18n = t();
    const h = i18n.timetable.heading;
    const request = useEngineRequest();
    const [effective, setEffective] = useState(settings.heading.effective_from ?? '');
    const [saving, setSaving] = useState(false);

    const save = async () => {
        const next: DisplaySettings = { ...settings, heading: { effective_from: effective === '' ? null : effective } };
        setSaving(true);
        const ok = await request('post', '/timetable/display', { academic_year_id: yearId, display: next });
        setSaving(false);
        if (ok) {
            onSaved(next);
            onClose();
        }
    };

    const readOnly = (label: string, value: string) => (
        <div className="sis-admission-sheet__field">
            <span className="sis-admission-sheet__label">{label}</span>
            <div className="sis-admission-sheet__control sis-admission-draft-readonly">{value === '' ? '—' : value}</div>
        </div>
    );

    return (
        <RegistrySheetDialog title={h.title} className="sis-branches-sheet sis-timetable-sheet sis-timetable-subsheet sis-timetable-subsheet--form" onClose={onClose}>
            <SheetSection id="timetable-heading" title={h.title}>
                <div className="sis-admission-sheet__row sis-admission-sheet__row--full sis-branches-sheet__row">
                    {readOnly(h.school, school)}
                    {readOnly(h.year, year)}
                    {readOnly(h.stage, stages.length === 0 ? h.stageAuto : `${stages.join('، ')} — ${h.stageAuto}`)}
                    <label className="sis-admission-sheet__field">
                        <span className="sis-admission-sheet__label">{h.effectiveFrom}</span>
                        <input className="sis-admission-sheet__control" type="date" value={effective} disabled={!canSave} onChange={(e) => setEffective(e.target.value)} />
                    </label>
                </div>
                <p className="sis-timetable-sheet__hint">{h.hint}</p>
            </SheetSection>
            <div className="sis-admission-sheet__actions">
                <Button type="button" variant="outline" disabled={saving} onClick={onClose}>
                    {i18n.timetable.close}
                </Button>
                {canSave ? (
                    <Button type="button" disabled={saving} onClick={() => void save()}>
                        <Save aria-hidden />
                        {saving ? i18n.common.saving : h.save}
                    </Button>
                ) : null}
            </div>
        </RegistrySheetDialog>
    );
}
