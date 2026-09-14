import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { t } from '@/i18n';

type ConfirmDialogProps = {
    open: boolean;
    title: string;
    description: string;
    confirmLabel?: string;
    cancelLabel?: string;
    confirmPending?: boolean;
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
    onConfirm,
    onOpenChange,
}: ConfirmDialogProps) {
    const i18n = t();
    const confirm = confirmLabel ?? i18n.dialog.confirm;
    const cancel = cancelLabel ?? i18n.dialog.cancel;

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sis-ops-hub border-[color:var(--sis-powder-blue)] sm:max-w-md" dir="rtl">
                <DialogHeader>
                    <DialogTitle>{title}</DialogTitle>
                    <DialogDescription>{description}</DialogDescription>
                </DialogHeader>
                <DialogFooter className="gap-2 sm:gap-2 sm:flex-row-reverse">
                    <button
                        type="button"
                        className="sis-ops-hub__link min-h-11 px-4 py-2 text-sm"
                        onClick={() => onOpenChange(false)}
                        disabled={confirmPending}
                    >
                        {cancel}
                    </button>
                    <button
                        type="button"
                        className="sis-ops-hub__link min-h-11 px-4 py-2 text-sm"
                        onClick={onConfirm}
                        disabled={confirmPending}
                    >
                        {confirmPending ? i18n.dialog.working : confirm}
                    </button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
