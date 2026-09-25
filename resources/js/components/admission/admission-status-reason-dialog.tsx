import { useEffect, useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogTitle,
} from '@/components/ui/dialog';
import { t } from '@/i18n';

type Props = {
    open: boolean;
    kind: 'reject' | 'withdraw';
    busy?: boolean;
    onOpenChange: (open: boolean) => void;
    onConfirm: (reason: string) => void;
};

/** Collect required reject / withdraw reason before status transition. */
export function AdmissionStatusReasonDialog({
    open,
    kind,
    busy = false,
    onOpenChange,
    onConfirm,
}: Props) {
    const i18n = t();
    const admission = i18n.admission;
    const [reason, setReason] = useState('');

    useEffect(() => {
        if (open) {
            setReason('');
        }
    }, [open, kind]);

    const title =
        kind === 'reject' ? admission.rejectReasonDialogTitle : admission.withdrawReasonDialogTitle;
    const confirmLabel =
        kind === 'reject' ? admission.confirmRejectWithReason : admission.confirmWithdrawWithReason;
    const trimmed = reason.trim();
    const canSubmit = trimmed !== '' && !busy;

    return (
        <Dialog open={open} onOpenChange={onOpenChange} modal>
            <DialogContent
                className="sis-admission-status-reason-dialog sm:max-w-md"
                overlayClassName="sis-admission-status-reason-dialog__overlay"
                dir="rtl"
                lang="ar"
                onOpenAutoFocus={(event) => event.preventDefault()}
                onInteractOutside={(event) => {
                    if (busy) {
                        event.preventDefault();
                    }
                }}
            >
                <DialogTitle>{title}</DialogTitle>
                <label className="sis-admission-status-reason-dialog__field">
                    <span className="sr-only">{title}</span>
                    <textarea
                        className="sis-admission-status-reason-dialog__input"
                        rows={4}
                        value={reason}
                        maxLength={2000}
                        disabled={busy}
                        autoFocus
                        onChange={(event) => setReason(event.target.value)}
                        placeholder={admission.reasonRequiredHint}
                    />
                </label>
                <div className="sis-admission-status-reason-dialog__actions">
                    <Button
                        type="button"
                        variant="outline"
                        disabled={busy}
                        onClick={() => onOpenChange(false)}
                    >
                        {i18n.dialog.cancel}
                    </Button>
                    <Button
                        type="button"
                        disabled={!canSubmit}
                        onClick={() => {
                            if (canSubmit) {
                                onConfirm(trimmed);
                            }
                        }}
                    >
                        {confirmLabel}
                    </Button>
                </div>
            </DialogContent>
        </Dialog>
    );
}
