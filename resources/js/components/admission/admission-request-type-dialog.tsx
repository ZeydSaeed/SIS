import AppLogo from '@/components/app-logo';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogTitle,
} from '@/components/ui/dialog';
import { WindowControls } from '@/components/window-controls';
import { useSmoothDialogDrag } from '@/hooks/use-smooth-dialog-drag';
import { t } from '@/i18n';

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    onSelectVocational: () => void;
    onSelectAcademicTransfer: () => void;
};

/** Choice sheet before opening admission draft — same visual language as draft form. */
export function AdmissionRequestTypeDialog({
    open,
    onOpenChange,
    onSelectVocational,
    onSelectAcademicTransfer,
}: Props) {
    const i18n = t();
    const { contentRef, heroDragProps, bringToFront } = useSmoothDialogDrag(open);

    return (
        <Dialog open={open} onOpenChange={onOpenChange} modal={false}>
            <DialogContent
                ref={contentRef}
                className="sis-admission-draft-dialog sis-admission-sheet-dialog sis-admission-request-type-dialog sm:max-w-lg"
                overlayClassName="sis-admission-sheet-dialog__overlay"
                dir="rtl"
                lang="ar"
                onOpenAutoFocus={(event) => event.preventDefault()}
                onCloseAutoFocus={(event) => event.preventDefault()}
                onInteractOutside={(event) => event.preventDefault()}
                onPointerDownOutside={(event) => event.preventDefault()}
                onPointerDownCapture={bringToFront}
            >
                <DialogTitle className="sr-only">{i18n.admission.draftDialogTitle}</DialogTitle>

                <div className="sis-admission-sheet sis-admission-request-type">
                    <header className="sis-admission-sheet__hero" {...heroDragProps}>
                        <WindowControls
                            className="sis-admission-sheet__window-controls"
                            label={i18n.window.controls}
                            minimizeLabel={i18n.window.minimize}
                            maximizeLabel={i18n.window.maximize}
                            restoreLabel={i18n.window.restore}
                            closeLabel={i18n.window.close}
                            minimizable={false}
                            maximizable={false}
                            onClose={() => onOpenChange(false)}
                        />
                        <div className="sis-admission-sheet__hero-copy">
                            <p className="sis-admission-sheet__hero-title">
                                {i18n.admission.draftDialogTitle}
                            </p>
                        </div>
                        <div className="sis-admission-sheet__hero-logo">
                            <AppLogo tone="on-dark" className="sis-admission-sheet__logo" />
                        </div>
                    </header>

                    <section className="sis-admission-sheet__section">
                        <h3 className="sis-admission-sheet__banner sis-admission-sheet__banner--accent">
                            {i18n.admission.requestTypeTitle}
                        </h3>
                        <div className="sis-admission-sheet__body sis-admission-request-type__body">
                            <button
                                type="button"
                                className="sis-admission-request-type__option"
                                onClick={onSelectVocational}
                            >
                                {i18n.admission.requestTypeVocational}
                            </button>
                            <button
                                type="button"
                                className="sis-admission-request-type__option"
                                onClick={onSelectAcademicTransfer}
                            >
                                {i18n.admission.requestTypeAcademicTransfer}
                            </button>
                        </div>
                    </section>

                    <div className="sis-admission-sheet__actions">
                        <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
                            {i18n.dialog.cancel}
                        </Button>
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    );
}
