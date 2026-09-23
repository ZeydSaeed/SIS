import {
    MessageDialog,
    type MessageDialogTone,
} from '@/components/sis/message-dialog';

type ErrorDialogProps = {
    open: boolean;
    tone?: MessageDialogTone;
    title?: string;
    description: string;
    details?: string[];
    closeLabel?: string;
    onOpenChange: (open: boolean) => void;
};

/**
 * Backward-compatible alias — app SSOT is MessageDialog.
 */
export function ErrorDialog({
    open,
    tone = 'error',
    title,
    description,
    details,
    closeLabel,
    onOpenChange,
}: ErrorDialogProps) {
    return (
        <MessageDialog
            open={open}
            tone={tone}
            title={title}
            description={description}
            details={details}
            closeLabel={closeLabel}
            onOpenChange={onOpenChange}
        />
    );
}
