import { useRef } from 'react';
import {
    AlertCircle,
    AlertTriangle,
    CheckCircle2,
    Info,
    type LucideIcon,
} from 'lucide-react';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { t } from '@/i18n';

export type MessageDialogTone = 'error' | 'warning' | 'success' | 'info';

type MessageDialogProps = {
    open: boolean;
    tone?: MessageDialogTone;
    title?: string;
    description: string;
    details?: string[];
    closeLabel?: string;
    onOpenChange: (open: boolean) => void;
};

const TONE_ICON: Record<MessageDialogTone, LucideIcon> = {
    error: AlertCircle,
    warning: AlertTriangle,
    success: CheckCircle2,
    info: Info,
};

/**
 * App-wide message dialog (SSOT) — classic WINDOW chrome:
 * Night titlebar / Pearl body / Oxford border / tone icon.
 * Use for error, warning, success, and info notices.
 */
export function MessageDialog({
    open,
    tone = 'error',
    title,
    description,
    details,
    closeLabel,
    onOpenChange,
}: MessageDialogProps) {
    const i18n = t();
    const close = closeLabel ?? i18n.dialog.close;
    const heading =
        title
        ?? (tone === 'error'
            ? i18n.dialog.errorTitle
            : tone === 'warning'
              ? i18n.dialog.warningTitle
              : tone === 'success'
                ? i18n.dialog.successTitle
                : i18n.dialog.infoTitle);
    const closeRef = useRef<HTMLButtonElement>(null);
    const Icon = TONE_ICON[tone];
    const uniqueDetails = details
        ? Array.from(new Set(details.map((item) => item.trim()).filter(Boolean)))
        : [];

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent
                className={`sis-message-dialog sis-message-dialog--${tone}`}
                overlayClassName="sis-message-dialog__overlay"
                dir="rtl"
                role={tone === 'error' || tone === 'warning' ? 'alertdialog' : 'dialog'}
                aria-describedby="sis-message-dialog-description"
                onOpenAutoFocus={(event) => {
                    event.preventDefault();
                    closeRef.current?.focus();
                }}
            >
                <DialogHeader className="sis-message-dialog__titlebar">
                    <DialogTitle className="sis-message-dialog__title">{heading}</DialogTitle>
                </DialogHeader>
                <div className="sis-message-dialog__body">
                    <span className="sis-message-dialog__icon-wrap" aria-hidden="true">
                        <Icon className="sis-message-dialog__icon" />
                    </span>
                    <div className="sis-message-dialog__copy-stack">
                        <DialogDescription
                            id="sis-message-dialog-description"
                            className="sis-message-dialog__copy"
                        >
                            {description}
                        </DialogDescription>
                        {uniqueDetails.length > 0 ? (
                            <ul className="sis-message-dialog__details">
                                {uniqueDetails.map((item) => (
                                    <li key={item}>{item}</li>
                                ))}
                            </ul>
                        ) : null}
                    </div>
                </div>
                <div className="sis-message-dialog__actions">
                    <button
                        ref={closeRef}
                        type="button"
                        className="sis-message-dialog__btn"
                        onClick={() => onOpenChange(false)}
                    >
                        {close}
                    </button>
                </div>
            </DialogContent>
        </Dialog>
    );
}
