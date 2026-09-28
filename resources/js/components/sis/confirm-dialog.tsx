import { useRef } from 'react';
import { NoticeSheetFrame } from '@/components/sis/notice-sheet-frame';
import {
    Dialog,
    DialogContent,
    DialogDescription,
} from '@/components/ui/dialog';
import { useCenterNoticeOverHost } from '@/hooks/use-center-notice-over-host';
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
 * Shared confirmation dialog — same admission-form chrome as MessageDialog (SSOT).
 * Centered over the open host sheet, else viewport center.
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
    const contentRef = useRef<HTMLDivElement>(null);
    useCenterNoticeOverHost(open, contentRef);
    const isDanger = tone === 'danger';
    const banner = isDanger ? i18n.dialog.warningTitle : i18n.dialog.infoTitle;

    return (
        <Dialog open={open} onOpenChange={onOpenChange} modal={false}>
            <DialogContent
                ref={contentRef}
                className={`sis-confirm-dialog sis-notice-sheet sis-confirm-dialog--${tone}`}
                showOverlay={false}
                dir="rtl"
                lang="ar"
                onOpenAutoFocus={(event) => {
                    if (!isDanger) {
                        return;
                    }

                    event.preventDefault();
                    cancelRef.current?.focus();
                }}
                onInteractOutside={(event) => {
                    event.preventDefault();
                }}
                onPointerDownOutside={(event) => {
                    event.preventDefault();
                }}
            >
                <NoticeSheetFrame
                    title={title}
                    banner={banner}
                    onClose={() => onOpenChange(false)}
                >
                    <DialogDescription className="sis-notice-sheet__copy">
                        {description}
                    </DialogDescription>
                    <div className="sis-admission-sheet__actions">
                        <button
                            ref={cancelRef}
                            type="button"
                            onClick={() => onOpenChange(false)}
                            disabled={confirmPending}
                        >
                            {cancel}
                        </button>
                        <button
                            type="button"
                            onClick={onConfirm}
                            disabled={confirmPending}
                        >
                            {confirmPending ? i18n.dialog.working : confirm}
                        </button>
                    </div>
                </NoticeSheetFrame>
            </DialogContent>
        </Dialog>
    );
}
