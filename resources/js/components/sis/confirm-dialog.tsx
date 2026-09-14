import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

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
 */
export function ConfirmDialog({
    open,
    title,
    description,
    confirmLabel = 'Confirm',
    cancelLabel = 'Cancel',
    confirmPending = false,
    onConfirm,
    onOpenChange,
}: ConfirmDialogProps) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sis-ops-hub border-[color:var(--sis-powder-blue)] sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>{title}</DialogTitle>
                    <DialogDescription>{description}</DialogDescription>
                </DialogHeader>
                <DialogFooter className="gap-2 sm:gap-2">
                    <button
                        type="button"
                        className="sis-ops-hub__link min-h-11 px-4 py-2 text-sm"
                        onClick={() => onOpenChange(false)}
                        disabled={confirmPending}
                    >
                        {cancelLabel}
                    </button>
                    <button
                        type="button"
                        className="sis-ops-hub__link min-h-11 px-4 py-2 text-sm"
                        onClick={onConfirm}
                        disabled={confirmPending}
                    >
                        {confirmPending ? 'Working…' : confirmLabel}
                    </button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
