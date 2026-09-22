import { useRef } from 'react';
import { AlertCircle } from 'lucide-react';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { t } from '@/i18n';

type ErrorDialogProps = {
    open: boolean;
    title?: string;
    description: string;
    details?: string[];
    closeLabel?: string;
    onOpenChange: (open: boolean) => void;
};

/**
 * Shared error dialog — same WINDOW-CONTRACT chrome as ConfirmDialog
 * (Steel titlebar / Pearl body / Oxford border).
 */
export function ErrorDialog({
    open,
    title,
    description,
    details,
    closeLabel,
    onOpenChange,
}: ErrorDialogProps) {
    const i18n = t();
    const close = closeLabel ?? i18n.dialog.close;
    const heading = title ?? i18n.dialog.errorTitle;
    const closeRef = useRef<HTMLButtonElement>(null);
    const uniqueDetails = details
        ? Array.from(new Set(details.map((item) => item.trim()).filter(Boolean)))
        : [];

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent
                className="sis-confirm-dialog"
                overlayClassName="sis-confirm-dialog__overlay"
                dir="rtl"
                role="alertdialog"
                aria-describedby="sis-error-dialog-description"
                onOpenAutoFocus={(event) => {
                    event.preventDefault();
                    closeRef.current?.focus();
                }}
            >
                <DialogHeader className="sis-confirm-dialog__titlebar">
                    <DialogTitle className="sis-confirm-dialog__title">{heading}</DialogTitle>
                </DialogHeader>
                <div className="sis-confirm-dialog__body">
                    <AlertCircle className="sis-confirm-dialog__icon" aria-hidden="true" />
                    <div className="sis-confirm-dialog__copy-stack">
                        <DialogDescription
                            id="sis-error-dialog-description"
                            className="sis-confirm-dialog__copy"
                        >
                            {description}
                        </DialogDescription>
                        {uniqueDetails.length > 0 ? (
                            <ul className="sis-confirm-dialog__details">
                                {uniqueDetails.map((item) => (
                                    <li key={item}>{item}</li>
                                ))}
                            </ul>
                        ) : null}
                    </div>
                </div>
                <div className="sis-confirm-dialog__actions">
                    <button
                        ref={closeRef}
                        type="button"
                        className="sis-confirm-dialog__btn sis-confirm-dialog__btn--confirm"
                        onClick={() => onOpenChange(false)}
                    >
                        {close}
                    </button>
                </div>
            </DialogContent>
        </Dialog>
    );
}
