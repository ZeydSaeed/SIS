import type { ReactNode } from 'react';
import AppLogo from '@/components/app-logo';
import { DialogTitle } from '@/components/ui/dialog';
import { WindowControls } from '@/components/window-controls';
import { t } from '@/i18n';

/**
 * Shared admission-form chrome for system notices (MessageDialog + ConfirmDialog).
 * One visual SSOT — only title / banner / body text differ per call.
 */
export function NoticeSheetFrame({
    title,
    banner,
    onClose,
    children,
}: {
    title: string;
    banner: string;
    onClose: () => void;
    children: ReactNode;
}) {
    const i18n = t();

    return (
        <div className="sis-admission-sheet sis-notice-sheet__sheet">
            <header className="sis-admission-sheet__hero">
                <WindowControls
                    className="sis-admission-sheet__window-controls"
                    label={i18n.window.controls}
                    minimizeLabel={i18n.window.minimize}
                    maximizeLabel={i18n.window.maximize}
                    restoreLabel={i18n.window.restore}
                    closeLabel={i18n.window.close}
                    minimizable={false}
                    maximizable={false}
                    onClose={onClose}
                />
                <div className="sis-admission-sheet__hero-copy">
                    <DialogTitle asChild>
                        <p className="sis-admission-sheet__hero-title">{title}</p>
                    </DialogTitle>
                </div>
                <div className="sis-admission-sheet__hero-logo">
                    <AppLogo tone="on-dark" className="sis-admission-sheet__logo" />
                </div>
            </header>

            <section className="sis-admission-sheet__section">
                <h3 className="sis-admission-sheet__banner sis-admission-sheet__banner--accent">
                    {banner}
                </h3>
                <div className="sis-admission-sheet__body sis-notice-sheet__body">{children}</div>
            </section>
        </div>
    );
}
