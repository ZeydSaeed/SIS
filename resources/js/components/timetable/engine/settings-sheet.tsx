import { useState } from 'react';
import { RegistrySheetDialog } from '@/components/organization/registry-sheet';
import { SheetSection } from '@/components/sis/admission-sheet';
import { Button } from '@/components/ui/button';
import { t } from '@/i18n';
import type { EngineContext } from './engine-context';
import { EngineField, EngineNumber, EngineRow, useEngineRequest, engineSheetClass } from './engine-ui';

const ALL_DAYS = [1, 2, 3, 4, 5, 6, 7];

/** «إعدادات الجدول»: working days, cycle, daily limits, double changeover (timetable.configs). */
export function SettingsSheet({ ctx, onClose }: { ctx: EngineContext; onClose: () => void }) {
    const i18n = t();
    const e = i18n.timetable.engine;
    const request = useEngineRequest();
    const s = ctx.engine.settings;
    const [days, setDays] = useState<number[]>(s.working_days);
    const [cycle, setCycle] = useState(String(s.cycle_weeks));
    const [maxTeacher, setMaxTeacher] = useState(String(s.max_teacher_per_day));
    const [maxSubject, setMaxSubject] = useState(String(s.max_subject_per_day));
    const [changeover, setChangeover] = useState(String(s.double_changeover_minutes));
    const [saving, setSaving] = useState(false);

    const save = async () => {
        setSaving(true);
        const ok = await request('post', '/timetable/settings', {
            academic_year_id: ctx.yearId,
            working_days: days,
            cycle_weeks: Number(cycle),
            max_teacher_per_day: Number(maxTeacher),
            max_subject_per_day: Number(maxSubject),
            double_changeover_minutes: Number(changeover),
        });
        setSaving(false);
        if (ok) {
            onClose();
        }
    };

    return (
        <RegistrySheetDialog title={e.settings} className={engineSheetClass('settings')} onClose={onClose}>
            <div className="sis-timetable-audit__list">
                <SheetSection id="timetable-settings-days" title={e.workingDays}>
                    <EngineRow>
                        <div className="sis-timetable-audit__bar sis-branches-field--wide">
                            {ALL_DAYS.map((day) => (
                                <label key={day} className="sis-timetable-audit__filter">
                                    <input
                                        type="checkbox"
                                        checked={days.includes(day)}
                                        onChange={(ev) => setDays((current) => (ev.target.checked ? [...current, day].sort((a, b) => a - b) : current.filter((d) => d !== day)))}
                                    />
                                    {ctx.dayLabel(day)}
                                </label>
                            ))}
                        </div>
                    </EngineRow>
                </SheetSection>
                <SheetSection id="timetable-settings-limits" title={e.settingsHint}>
                    <EngineRow two>
                        <EngineField label={e.cycleWeeks}>
                            <EngineNumber value={cycle} onChange={setCycle} min={1} max={4} label={e.cycleWeeks} />
                        </EngineField>
                        <EngineField label={e.maxTeacher}>
                            <EngineNumber value={maxTeacher} onChange={setMaxTeacher} min={1} max={20} label={e.maxTeacher} />
                        </EngineField>
                        <EngineField label={e.maxSubject}>
                            <EngineNumber value={maxSubject} onChange={setMaxSubject} min={1} max={20} label={e.maxSubject} />
                        </EngineField>
                        <EngineField label={e.changeover}>
                            <EngineNumber value={changeover} onChange={setChangeover} min={0} max={60} label={e.changeover} />
                        </EngineField>
                    </EngineRow>
                </SheetSection>
            </div>
            <div className="sis-admission-sheet__actions">
                <Button type="button" disabled={saving || days.length === 0} onClick={() => void save()}>
                    {saving ? i18n.common.saving : e.save}
                </Button>
                <Button type="button" variant="outline" onClick={onClose}>
                    {i18n.timetable.close}
                </Button>
            </div>
        </RegistrySheetDialog>
    );
}
