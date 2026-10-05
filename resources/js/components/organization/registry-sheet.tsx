import { router } from '@inertiajs/react';
import { useCallback, useEffect, useMemo, useRef, useState, type ReactNode, type RefObject } from 'react';
import { usePageError } from '@/components/sis/page-error-context';
import AppLogo from '@/components/app-logo';
import { SisListSelect } from '@/components/sis/sis-list-select';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogTitle } from '@/components/ui/dialog';
import { WindowControls } from '@/components/window-controls';
import { useSheetMaximize } from '@/hooks/use-sheet-maximize';
import { useSmoothDialogDrag } from '@/hooks/use-smooth-dialog-drag';
import { t } from '@/i18n';

/**
 * Organization registry sheets (schools, directorates) — SSOT for chrome, fields,
 * actions and list/form editing. Same classes as the curriculum subject sheet.
 */

export type RegistryMode = 'view' | 'edit' | 'create';

export function newIdempotencyKey(prefix: string): string {
    if (typeof crypto !== 'undefined' && 'randomUUID' in crypto) {
        return `${prefix}-${crypto.randomUUID()}`;
    }

    return `${prefix}-${Date.now()}-${Math.random().toString(16).slice(2)}`;
}

type RequestPayload = Parameters<typeof router.post>[1];

/**
 * Registry write → Promise<true> on success (errors shown), so multi-step saves
 * (status + fields) can run in order and stop at the first failure.
 */
export function useRegistryRequest(reloadProps: string[]) {
    const i18n = t();
    const { showInertiaErrors } = usePageError();

    return (method: 'post' | 'patch', url: string, data: RequestPayload = {}): Promise<boolean> =>
        new Promise((resolve) => {
            router[method](url, data, {
                preserveScroll: true,
                preserveState: true,
                only: reloadProps,
                headers: { 'X-Idempotency-Key': newIdempotencyKey('organization') },
                onError: (errors) => {
                    showInertiaErrors(errors, i18n.errors.saveFailed);
                    resolve(false);
                },
                onSuccess: () => resolve(true),
            });
        });
}

/** URL that moves a registry row to the chosen status ('1' active, '2' inactive). */
export function statusUrl(base: string, id: number, status: string): string {
    return `${base}/${id}/${status === '1' ? 'reactivate' : 'deactivate'}`;
}

export function blankToNull(value: string): string | null {
    const trimmed = value.trim();

    return trimmed === '' ? null : trimmed;
}

function isFilled(value: string): boolean {
    return value.trim() !== '' && value !== '—';
}

function filledControlClass(filled: boolean, editing: boolean): string {
    return `sis-admission-sheet__control${filled ? ' sis-admission-draft-field--filled' : ''}${
        editing ? '' : ' sis-admission-draft-readonly'
    }`;
}

function FieldLabel({ label, required }: { label: string; required: boolean }) {
    return (
        <span className="sis-admission-sheet__label">
            {label}
            {required ? (
                <span aria-hidden="true">
                    {' '}
                    *
                </span>
            ) : null}
        </span>
    );
}

export function RegistryTextField({
    label,
    editing,
    value,
    onChange,
    dir = 'rtl',
    required = false,
    type = 'text',
    fieldClassName = '',
    inputRef,
}: {
    label: string;
    editing: boolean;
    value: string;
    onChange: (value: string) => void;
    dir?: 'rtl' | 'ltr';
    required?: boolean;
    type?: 'text' | 'email' | 'tel';
    fieldClassName?: string;
    inputRef?: RefObject<HTMLInputElement | null>;
}) {
    const display = value.trim() === '' ? '—' : value;

    return (
        <label className={`sis-admission-sheet__field ${fieldClassName}`.trim()}>
            <FieldLabel label={label} required={required} />
            {editing ? (
                <input
                    ref={inputRef}
                    className={filledControlClass(isFilled(value), true)}
                    value={value}
                    dir={dir}
                    type={type}
                    required={required}
                    onChange={(event) => onChange(event.target.value)}
                />
            ) : (
                <div className={filledControlClass(isFilled(display), false)} dir={dir}>
                    {display}
                </div>
            )}
        </label>
    );
}

export function RegistryListField({
    label,
    editing,
    value,
    display,
    options,
    onChange,
    required = false,
    fieldClassName = '',
}: {
    label: string;
    editing: boolean;
    value: string;
    display: string;
    options: Array<{ value: string; label: string }>;
    onChange: (value: string) => void;
    required?: boolean;
    fieldClassName?: string;
}) {
    const filled = isFilled(value) ? ' sis-admission-draft-field--filled' : '';

    return (
        <label className={`sis-admission-sheet__field ${fieldClassName}`.trim()}>
            <FieldLabel label={label} required={required} />
            {editing ? (
                <SisListSelect
                    value={value}
                    options={options}
                    onChange={onChange}
                    ariaLabel={label}
                    includeBlank={false}
                    className={`sis-admission-sheet-list-select${filled}`}
                    triggerClassName={`sis-admission-sheet__control sis-admission-draft-select${filled}`}
                    menuClassName="sis-admission-sheet-list-select__menu"
                />
            ) : (
                <div className={filledControlClass(isFilled(display), false)}>{display}</div>
            )}
        </label>
    );
}

export function RegistrySheetDialog({
    title,
    className,
    onClose,
    children,
}: {
    title: string;
    className: string;
    onClose: () => void;
    children: ReactNode;
}) {
    const i18n = t();
    const { contentRef, heroDragProps, bringToFront, resizeHandles } = useSmoothDialogDrag(true, {
        resizable: true,
        minSize: { width: 560, height: 420 },
    });
    const { maximized, toggleMaximize, maximizeClassName } = useSheetMaximize(contentRef);

    return (
        <Dialog
            open
            modal={false}
            onOpenChange={(open) => {
                if (!open) {
                    onClose();
                }
            }}
        >
            <DialogContent
                ref={contentRef}
                className={`sis-admission-draft-dialog sis-admission-sheet-dialog sis-enrollment-record-sheet sis-registry-sheet ${className}${maximizeClassName}`}
                overlayClassName="sis-admission-sheet-dialog__overlay"
                dir="rtl"
                lang="ar"
                onOpenAutoFocus={(event) => event.preventDefault()}
                onCloseAutoFocus={(event) => event.preventDefault()}
                onInteractOutside={(event) => event.preventDefault()}
                onPointerDownOutside={(event) => event.preventDefault()}
                onPointerDownCapture={bringToFront}
            >
                <DialogTitle className="sr-only">{title}</DialogTitle>
                {maximized ? null : resizeHandles}

                <article
                    className="sis-admission-draft-form sis-admission-sheet sis-student-record-form"
                    dir="rtl"
                    lang="ar"
                >
                    <header className="sis-admission-sheet__hero" {...(maximized ? {} : heroDragProps)}>
                        <WindowControls
                            className="sis-admission-sheet__window-controls"
                            label={i18n.window.controls}
                            minimizeLabel={i18n.window.minimize}
                            maximizeLabel={i18n.window.maximize}
                            restoreLabel={i18n.window.restore}
                            closeLabel={i18n.window.close}
                            minimizable={false}
                            maximizable
                            maximized={maximized}
                            onMaximize={toggleMaximize}
                            onClose={onClose}
                        />
                        <div className="sis-admission-sheet__hero-copy">
                            <p className="sis-admission-sheet__hero-title">{title}</p>
                        </div>
                        <div className="sis-admission-sheet__hero-logo">
                            <AppLogo tone="on-dark" className="sis-admission-sheet__logo" />
                        </div>
                    </header>
                    {children}
                </article>
            </DialogContent>
        </Dialog>
    );
}

export function RegistryActions({
    canManage,
    editing,
    saving,
    canSave,
    canEdit,
    loading,
    onCancel,
    onAdd,
    onEdit,
    onSave,
}: {
    canManage: boolean;
    editing: boolean;
    saving: boolean;
    canSave: boolean;
    canEdit: boolean;
    loading: boolean;
    onCancel: () => void;
    onAdd: () => void;
    onEdit: () => void;
    onSave: () => void;
}) {
    const i18n = t();

    return (
        <div className="sis-admission-sheet__actions">
            <Button type="button" variant="outline" onClick={onCancel} disabled={saving}>
                {i18n.dialog.cancel}
            </Button>
            {canManage ? (
                <>
                    <Button type="button" variant="outline" disabled={editing || loading} onClick={onAdd}>
                        {i18n.organizationRegistry.add}
                    </Button>
                    <Button type="button" variant="outline" disabled={editing || !canEdit} onClick={onEdit}>
                        {i18n.common.edit}
                    </Button>
                    <Button type="button" disabled={!canSave} onClick={onSave}>
                        {saving ? i18n.common.saving : i18n.common.save}
                    </Button>
                </>
            ) : null}
        </div>
    );
}

/**
 * List + form editing state: selected row, view/edit/create mode, draft.
 * After a create + reload, the row that was not in the list before is selected.
 */
export function useRegistryEditor<T extends { id: number }, D>({
    rows,
    loading,
    canManage,
    toDraft,
    blankDraft,
    initialId = null,
    initialMode = 'view',
}: {
    rows: T[];
    loading: boolean;
    canManage: boolean;
    toDraft: (row: T) => D;
    blankDraft: () => D;
    initialId?: number | null;
    initialMode?: 'view' | 'edit';
}) {
    const [selectedId, setSelectedId] = useState<number | null>(null);
    const [mode, setMode] = useState<RegistryMode>('view');
    const [draft, setDraft] = useState<D>(blankDraft);
    const [saving, setSaving] = useState(false);
    const nameInputRef = useRef<HTMLInputElement | null>(null);
    const knownIdsRef = useRef<Set<number> | null>(null);
    const initializedRef = useRef(false);
    const toDraftRef = useRef(toDraft);
    const blankDraftRef = useRef(blankDraft);
    toDraftRef.current = toDraft;
    blankDraftRef.current = blankDraft;

    const selected = useMemo(() => rows.find((row) => row.id === selectedId) ?? null, [rows, selectedId]);
    const editing = mode !== 'view' && canManage;

    const focusName = useCallback(() => {
        requestAnimationFrame(() => {
            nameInputRef.current?.focus();
            nameInputRef.current?.select();
        });
    }, []);

    // First load: requested row (optionally straight into edit), else the first row; empty → create.
    useEffect(() => {
        if (loading || initializedRef.current) {
            return;
        }
        initializedRef.current = true;
        const target = rows.find((row) => row.id === initialId) ?? rows[0] ?? null;
        if (target !== null) {
            setSelectedId(target.id);
            setDraft(toDraftRef.current(target));
            if (initialMode === 'edit' && canManage && target.id === initialId) {
                setMode('edit');
                focusName();
            }
        } else if (canManage) {
            setDraft(blankDraftRef.current());
            setMode('create');
            focusName();
        }
    }, [canManage, focusName, initialId, initialMode, loading, rows]);

    // After create + reload: select the new row.
    useEffect(() => {
        const known = knownIdsRef.current;
        if (known === null || mode !== 'view') {
            return;
        }
        const created = rows.find((row) => !known.has(row.id));
        if (created) {
            knownIdsRef.current = null;
            setSelectedId(created.id);
            setDraft(toDraftRef.current(created));
        }
    }, [mode, rows]);

    // Keep the readonly form in sync with the refreshed list.
    useEffect(() => {
        if (mode === 'view' && selected !== null) {
            setDraft(toDraftRef.current(selected));
        }
    }, [mode, selected]);

    const select = (row: T): void => {
        if (editing) {
            return;
        }
        setSelectedId(row.id);
        setDraft(toDraft(row));
    };

    const startCreate = (): void => {
        setDraft(blankDraft());
        setMode('create');
        focusName();
    };

    const startEdit = (): void => {
        if (selected === null) {
            return;
        }
        setDraft(toDraft(selected));
        setMode('edit');
        focusName();
    };

    /** Returns true when the sheet should close (nothing being edited). */
    const cancel = (): boolean => {
        if (!editing) {
            return true;
        }
        setMode('view');
        setDraft(selected !== null ? toDraft(selected) : blankDraft());

        return false;
    };

    const markSaved = (): void => {
        if (mode === 'create') {
            knownIdsRef.current = new Set(rows.map((row) => row.id));
        }
        setMode('view');
    };

    const setField = <K extends keyof D>(key: K, value: D[K]): void => {
        setDraft((current) => ({ ...current, [key]: value }));
    };

    return {
        selected,
        selectedId,
        mode,
        editing,
        isCreate: mode === 'create',
        draft,
        setField,
        saving,
        setSaving,
        nameInputRef,
        select,
        startCreate,
        startEdit,
        cancel,
        markSaved,
    };
}
