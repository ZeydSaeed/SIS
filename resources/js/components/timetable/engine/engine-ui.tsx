import { useState, type ReactNode } from 'react';
import { useRegistryRequest } from '@/components/organization/registry-sheet';
import { ConfirmDialog } from '@/components/sis/confirm-dialog';
import { SisListSelect } from '@/components/sis/sis-list-select';
import { t } from '@/i18n';

/** Props the engine writes refresh (the grid follows when a run is applied or a version restored). */
export const ENGINE_RELOAD = ['engine', 'schedules', 'issues', 'advice', 'workload', 'quality', 'flash', 'errors'];

/** Engine writes: idempotency key + partial reload + shared error display (same channel as the builder). */
export function useEngineRequest() {
    return useRegistryRequest(ENGINE_RELOAD);
}

/** A labelled field with the sheet's existing classes; `span` = how many columns it takes in a sheet's own grid. */
export function EngineField({ label, children, wide = false, span }: { label: string; children: ReactNode; wide?: boolean; span?: 2 | 3 }) {
    return (
        <label className={`sis-admission-sheet__field${wide ? ' sis-branches-field--wide' : ''}${span ? ` sis-timetable-engine-field--span${span}` : ''}`}>
            <span className="sis-admission-sheet__label">{label}</span>
            {children}
        </label>
    );
}

/** A select with the sheet's existing list-select classes. */
export function EngineSelect({
    value,
    options,
    onChange,
    label,
    includeBlank = false,
}: {
    value: string;
    options: Array<{ value: string; label: string }>;
    onChange: (value: string) => void;
    label: string;
    includeBlank?: boolean;
}) {
    return (
        <SisListSelect
            value={value}
            options={options}
            onChange={onChange}
            ariaLabel={label}
            includeBlank={includeBlank}
            className="sis-admission-sheet-list-select"
            triggerClassName="sis-admission-sheet__control sis-admission-draft-select"
            menuClassName="sis-admission-sheet-list-select__menu"
        />
    );
}

export function EngineNumber({ value, onChange, min, max, label }: { value: string; onChange: (value: string) => void; min: number; max: number; label: string }) {
    return <input className="sis-admission-sheet__control" type="number" dir="ltr" min={min} max={max} value={value} aria-label={label} onChange={(e) => onChange(e.target.value)} />;
}

/** A row of the sheet holding fields side by side (wide fields take the whole line). */
export function EngineRow({ children, two = false }: { children: ReactNode; two?: boolean }) {
    return <div className={`sis-admission-sheet__row${two ? ' sis-admission-sheet__row--2' : ''} sis-branches-sheet__row`}>{children}</div>;
}

/** Window classes of an engine sheet: shared timetable look + its own size. */
export const engineSheetClass = (kind: 'generate' | 'activities' | 'constraints' | 'availability' | 'versions' | 'settings' | 'readiness' | 'places') =>
    `sis-branches-sheet sis-timetable-sheet sis-timetable-audit-sheet sis-timetable-engine-sheet sis-timetable-engine-sheet--${kind}`;

export const toInt = (value: string): number | null => (value.trim() === '' ? null : Number(value));

/**
 * «تأكيد قبل التنفيذ» for the engine's consequential actions (ending an activity or a rule, taking a place out of
 * service, publishing / archiving a version): `ask(message, run)` opens the shared confirm dialog; `run` fires only
 * on confirm. Render `dialog` once in the sheet.
 */
export function useEngineConfirm() {
    const e = t().timetable.engine;
    const [pending, setPending] = useState<{ message: string; label?: string; run: () => void } | null>(null);
    const dialog = (
        <ConfirmDialog
            open={pending !== null}
            title={e.confirmTitle}
            description={pending?.message ?? ''}
            confirmLabel={pending?.label}
            tone="danger"
            onConfirm={() => {
                pending?.run();
                setPending(null);
            }}
            onOpenChange={(open) => {
                if (!open) {
                    setPending(null);
                }
            }}
        />
    );

    return { ask: (message: string, run: () => void, label?: string) => setPending({ message, run, label }), dialog };
}
