import { router } from '@inertiajs/react';
import { useEffect, type ReactNode, type RefObject } from 'react';
import { usePageError } from '@/components/sis/page-error-context';
import AppLogo from '@/components/app-logo';
import { SisListSelect } from '@/components/sis/sis-list-select';
import { Dialog, DialogContent, DialogTitle } from '@/components/ui/dialog';
import { WindowControls } from '@/components/window-controls';
import { useSheetMaximize } from '@/hooks/use-sheet-maximize';
import { useSmoothDialogDrag } from '@/hooks/use-smooth-dialog-drag';
import { useFitSheetToContent } from '@/hooks/use-fit-sheet-to-content';
import { t } from '@/i18n';

/**
 * Organization sheets («المديريات والمدارس», «الفروع والاختصاصات») — SSOT for
 * window chrome, fields and requests. Same classes as the curriculum subject sheet.
 */

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
    type?: 'text' | 'email' | 'tel' | 'date' | 'number';
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
    // Timetable windows («الجدول المدرسي»): the width follows the fields, tables and buttons — nothing cut.
    // «تحرير الخلية» keeps one fixed size (its tabs never resize it).
    useFitSheetToContent(contentRef, className.includes('sis-timetable-sheet') && !className.includes('sis-timetable-lesson-sheet'));

    // The portal mounts the content after the first commit — raise the new window once it exists,
    // so a sheet opened from another sheet appears above it.
    useEffect(() => {
        let frame = 0;
        let attempts = 0;
        const raise = () => {
            bringToFront();
            attempts += 1;
            if (contentRef.current === null && attempts < 10) {
                frame = requestAnimationFrame(raise);
            }
        };
        frame = requestAnimationFrame(raise);

        return () => cancelAnimationFrame(frame);
    }, [bringToFront, contentRef]);

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
