import { useRef } from 'react';
import { NoticeSheetFrame } from '@/components/sis/notice-sheet-frame';
import {
    Dialog,
    DialogContent,
    DialogDescription,
} from '@/components/ui/dialog';
import { useCenterNoticeOverHost } from '@/hooks/use-center-notice-over-host';
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

function toneBannerLabel(
    tone: MessageDialogTone,
    labels: { error: string; warning: string; success: string; info: string },
): string {
    if (tone === 'error') {
        return labels.error;
    }
    if (tone === 'warning') {
        return labels.warning;
    }
    if (tone === 'success') {
        return labels.success;
    }

    return labels.info;
}

/**
 * App-wide message dialog (SSOT) — same chrome as admission student form.
 * Centered over the open host sheet (e.g. enrollment), else viewport center.
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
    const contentRef = useRef<HTMLDivElement>(null);
    useCenterNoticeOverHost(open, contentRef);
    const uniqueDetails = details
        ? Array.from(new Set(details.map((item) => item.trim()).filter(Boolean)))
        : [];
    const banner = toneBannerLabel(tone, {
        error: i18n.dialog.errorTitle,
        warning: i18n.dialog.warningTitle,
        success: i18n.dialog.successTitle,
        info: i18n.dialog.infoTitle,
    });

    return (
        <Dialog open={open} onOpenChange={onOpenChange} modal={false}>
            <DialogContent
                ref={contentRef}
                className={`sis-message-dialog sis-notice-sheet sis-message-dialog--${tone}`}
                showOverlay={false}
                dir="rtl"
                lang="ar"
                role={tone === 'error' || tone === 'warning' ? 'alertdialog' : 'dialog'}
                aria-describedby="sis-message-dialog-description"
                onOpenAutoFocus={(event) => {
                    event.preventDefault();
                    closeRef.current?.focus();
                }}
                onInteractOutside={(event) => {
                    event.preventDefault();
                }}
                onPointerDownOutside={(event) => {
                    event.preventDefault();
                }}
            >
                <NoticeSheetFrame
                    title={heading}
                    banner={banner}
                    onClose={() => onOpenChange(false)}
                >
                    <DialogDescription
                        id="sis-message-dialog-description"
                        className="sis-notice-sheet__copy"
                    >
                        {description}
                    </DialogDescription>
                    {uniqueDetails.length > 0 ? (
                        <ul className="sis-notice-sheet__details">
                            {uniqueDetails.map((item) => (
                                <li key={item}>{item}</li>
                            ))}
                        </ul>
                    ) : null}
                    <div className="sis-admission-sheet__actions">
                        <button
                            ref={closeRef}
                            type="button"
                            onClick={() => onOpenChange(false)}
                        >
                            {close}
                        </button>
                    </div>
                </NoticeSheetFrame>
            </DialogContent>
        </Dialog>
    );
}
