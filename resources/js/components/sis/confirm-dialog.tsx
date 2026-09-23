import { useRef } from 'react';
import { AlertTriangle, Info } from 'lucide-react';
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
 * Shared confirmation dialog — same classic chrome as MessageDialog (SSOT visual).
 * Use for yes/no decisions only; notices go through usePageError / MessageDialog.
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
    const Icon = isDanger ? AlertTriangle : Info;

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent
                className={`sis-confirm-dialog sis-confirm-dialog--${tone}`}
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
                    <span className="sis-confirm-dialog__icon-wrap" aria-hidden="true">
                        <Icon className="sis-confirm-dialog__icon" />
                    </span>
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
