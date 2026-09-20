import { useRef } from 'react';
import { AlertTriangle } from 'lucide-react';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { t } from '@/i18n';

type ConfirmDialogTone = 'default' | 'danger';

type ConfirmDialogProps = {
    open: boolean;
    title: string;
    description: string;
    confirmLabel?: string;
    cancelLabel?: string;
    confirmPending?: boolean;
    tone?: ConfirmDialogTone;
    onConfirm: () => void;
    onOpenChange: (open: boolean) => void;
};

/**
 * Shared confirmation dialog — WINDOW-CONTRACT Dialog presentation.
 * Desktop: centered modal. Large workflows must remain full pages/windows.
 * Defaults Arabic (COLOR / UI / WINDOW contracts).
 */
export function ConfirmDialog({
    open,
    title,
    description,
    confirmLabel,
    cancelLabel,
    confirmPending = false,
    tone = 'default',
    onConfirm,
    onOpenChange,
}: ConfirmDialogProps) {
    const i18n = t();
    const confirm = confirmLabel ?? i18n.dialog.confirm;
    const cancel = cancelLabel ?? i18n.dialog.cancel;
    const cancelRef = useRef<HTMLButtonElement>(null);
    const isDanger = tone === 'danger';

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent
                className="sis-confirm-dialog"
                overlayClassName="sis-confirm-dialog__overlay"
                dir="rtl"
                onOpenAutoFocus={(event) => {
                    if (!isDanger) {
                        return;
                    }

                    event.preventDefault();
                    cancelRef.current?.focus();
                }}
            >
                <DialogHeader className="sis-confirm-dialog__titlebar">
                    <DialogTitle className="sis-confirm-dialog__title">{title}</DialogTitle>
                </DialogHeader>
                <div className="sis-confirm-dialog__body">
                    {isDanger ? (
                        <AlertTriangle className="sis-confirm-dialog__icon" aria-hidden="true" />
                    ) : null}
                    <DialogDescription className="sis-confirm-dialog__copy">
                        {description}
                    </DialogDescription>
                </div>
                <div className="sis-confirm-dialog__actions">
                    <button
                        ref={cancelRef}
                        type="button"
                        className="sis-confirm-dialog__btn sis-confirm-dialog__btn--cancel"
                        onClick={() => onOpenChange(false)}
                        disabled={confirmPending}
                    >
                        {cancel}
                    </button>
                    <button
                        type="button"
                        className={
                            isDanger
                                ? 'sis-confirm-dialog__btn sis-confirm-dialog__btn--danger'
                                : 'sis-confirm-dialog__btn sis-confirm-dialog__btn--confirm'
                        }
                        onClick={onConfirm}
                        disabled={confirmPending}
                    >
                        {confirmPending ? i18n.dialog.working : confirm}
                    </button>
                </div>
            </DialogContent>
        </Dialog>
    );
}
