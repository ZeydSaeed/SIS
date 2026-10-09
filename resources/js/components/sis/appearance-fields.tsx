import { RotateCcw } from 'lucide-react';
import { useState, type CSSProperties } from 'react';
import { RegistrySheetDialog, RegistryTextField, useRegistryRequest } from '@/components/organization/registry-sheet';
import { SheetSection } from '@/components/sis/admission-sheet';
import { Button } from '@/components/ui/button';
import { t } from '@/i18n';

/**
 * «الاختصار واللون» — one editor for every entity the timetable shows (subject, teacher, branch, department, class,
 * section, room, room type, break). The colour is a hue on the approved card palette: the stylesheet keeps lightness
 * and saturation, so contrast stays governed; `null` = the default colour. The value is saved by the entity's owner
 * (each page passes its own endpoint) — never copied into the timetable.
 */
export const HUE_SWATCHES = [0, 18, 36, 54, 90, 130, 160, 185, 205, 225, 250, 275, 300, 330] as const;

export const ABBREVIATION_MAX = 20;

/** The card tint of a hue (`--subject-hue`, the same variable the timetable cards read). */
export function hueStyle(hue: number | null | undefined, light?: number): CSSProperties | undefined {
    if (hue === null || hue === undefined) {
        return undefined;
    }

    return { '--subject-hue': hue, ...(light === undefined ? {} : { '--subject-light': `${light}%` }) } as CSSProperties;
}

export type AppearanceValue = { abbreviation: string; color_hue: number | null };

export function AppearanceFields({
    value,
    onChange,
    editing,
    suggested,
    defaultHue = null,
    previewTitle,
}: {
    value: AppearanceValue;
    onChange: (next: AppearanceValue) => void;
    editing: boolean;
    /** The abbreviation shown while none is set (the server's suggestion). */
    suggested?: string | null;
    /** The colour the entity has when none is chosen (shown on «افتراضي»). */
    defaultHue?: number | null;
    previewTitle?: string;
}) {
    const a = t().appearance;
    const shownHue = value.color_hue ?? defaultHue;
    const shownAbbreviation = value.abbreviation.trim() !== '' ? value.abbreviation.trim() : (suggested ?? '');
    const tooLong = value.abbreviation.trim().length > ABBREVIATION_MAX;

    return (
        <>
            <RegistryTextField
                label={`${a.abbreviation}${suggested ? ` (${a.suggested}: ${suggested})` : ''}`}
                editing={editing}
                value={value.abbreviation}
                onChange={(abbreviation) => onChange({ ...value, abbreviation })}
                fieldClassName="sis-branches-field--wide"
            />
            {tooLong ? <p className="sis-timetable-sheet__hint sis-branches-field--wide">{a.abbreviationTooLong.replace('{n}', String(ABBREVIATION_MAX))}</p> : null}
            <div className="sis-admission-sheet__field sis-branches-field--wide">
                <span className="sis-admission-sheet__label">{a.color}</span>
                <div className="sis-appearance-swatches" role="radiogroup" aria-label={a.color}>
                    <button
                        type="button"
                        role="radio"
                        aria-checked={value.color_hue === null}
                        disabled={!editing}
                        className={`sis-appearance-swatch sis-appearance-swatch--default${value.color_hue === null ? ' is-selected' : ''}`}
                        style={hueStyle(defaultHue)}
                        title={a.defaultColor}
                        onClick={() => onChange({ ...value, color_hue: null })}
                    >
                        <RotateCcw aria-hidden />
                        <span className="sr-only">{a.defaultColor}</span>
                    </button>
                    {HUE_SWATCHES.map((hue) => (
                        <button
                            key={hue}
                            type="button"
                            role="radio"
                            aria-checked={value.color_hue === hue}
                            aria-label={`${a.color} ${hue}`}
                            disabled={!editing}
                            className={`sis-appearance-swatch${value.color_hue === hue ? ' is-selected' : ''}`}
                            style={hueStyle(hue)}
                            onClick={() => onChange({ ...value, color_hue: hue })}
                        />
                    ))}
                </div>
            </div>
            <div className="sis-admission-sheet__field sis-branches-field--wide">
                <span className="sis-admission-sheet__label">{a.preview}</span>
                <div className="sis-appearance-preview">
                    <div className="sis-timetable-card" style={hueStyle(shownHue ?? 205)}>
                        <span className="sis-timetable-card__subject">{shownAbbreviation !== '' ? shownAbbreviation : '—'}</span>
                        {previewTitle ? <span className="sis-timetable-card__line">{previewTitle}</span> : null}
                    </div>
                </div>
            </div>
        </>
    );
}

/**
 * The «الاختصار واللون» window: used from context menus and owner pages. `url` + `payload` belong to the owner
 * (e.g. PATCH /organization/appearance with { target, id }), so the timetable never writes another context's data.
 */
export function AppearanceDialog({
    title,
    entityName,
    initial,
    suggested,
    defaultHue,
    url,
    payload = {},
    method = 'patch',
    reloadProps,
    canEdit,
    onClose,
}: {
    title: string;
    entityName: string;
    initial: AppearanceValue;
    suggested?: string | null;
    defaultHue?: number | null;
    url: string;
    payload?: Record<string, string | number | boolean | null>;
    method?: 'patch' | 'post';
    reloadProps: string[];
    canEdit: boolean;
    onClose: () => void;
}) {
    const i18n = t();
    const a = i18n.appearance;
    const request = useRegistryRequest(reloadProps);
    const [value, setValue] = useState<AppearanceValue>(initial);
    const [saving, setSaving] = useState(false);
    const changed = value.abbreviation.trim() !== initial.abbreviation.trim() || value.color_hue !== initial.color_hue;
    const valid = value.abbreviation.trim().length <= ABBREVIATION_MAX;

    const save = async () => {
        setSaving(true);
        const ok = await request(method, url, { ...payload, abbreviation: value.abbreviation.trim() === '' ? null : value.abbreviation.trim(), color_hue: value.color_hue });
        setSaving(false);
        if (ok) {
            onClose();
        }
    };

    return (
        <RegistrySheetDialog title={title} className="sis-branches-sheet sis-appearance-sheet" onClose={onClose}>
            <SheetSection id="appearance" title={entityName}>
                <div className="sis-admission-sheet__row sis-admission-sheet__row--full sis-branches-sheet__row">
                    <AppearanceFields value={value} onChange={setValue} editing={canEdit} suggested={suggested} defaultHue={defaultHue} previewTitle={entityName} />
                    <p className="sis-timetable-sheet__hint sis-branches-field--wide">{a.ownerHint}</p>
                </div>
            </SheetSection>
            <div className="sis-admission-sheet__actions">
                <Button type="button" variant="outline" disabled={saving} onClick={onClose}>
                    {i18n.common.cancel}
                </Button>
                {canEdit ? (
                    <>
                        <Button type="button" variant="outline" disabled={saving || (value.abbreviation === '' && value.color_hue === null)} onClick={() => setValue({ abbreviation: '', color_hue: null })}>
                            <RotateCcw aria-hidden />
                            {a.reset}
                        </Button>
                        <Button type="button" disabled={saving || !changed || !valid} onClick={() => void save()}>
                            {saving ? i18n.common.saving : i18n.common.save}
                        </Button>
                    </>
                ) : null}
            </div>
        </RegistrySheetDialog>
    );
}
