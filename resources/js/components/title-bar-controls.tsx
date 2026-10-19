import { router, usePage } from '@inertiajs/react';
import { useCallback } from 'react';
import { TitleBarHistory } from '@/components/title-bar-history';
import { TitleBarPageToggle } from '@/components/title-bar-page-toggle';
import { TitleBarPageNav } from '@/components/title-bar-page-nav';
import { WindowControls } from '@/components/window-controls';
import { t } from '@/i18n';
import { closingTarget, travelTo } from '@/lib/navigation-history';
import { dashboard } from '@/routes';

export function TitleBarControls() {
    const i18n = t();
    const { component } = usePage();
    const isDashboard = component === 'dashboard';
    const pageTitle = isDashboard
        ? i18n.modules.dashboard
        : i18n.window.controls;

    // X closes the page: back to the page it was opened from; with none (first page of the tab), the dashboard.
    const onClose = useCallback(() => {
        const target = closingTarget();
        if (target !== null) {
            travelTo(target);

            return;
        }
        if (!isDashboard) {
            router.visit(dashboard());
        }
    }, [isDashboard]);

    // Right of the close (X) button: «التالية» / «السابقة» page arrows, then «رجوع» / «تقدم» (unsaved edits).
    return (
        <>
            <TitleBarPageToggle />
            <WindowControls
                className="sis-titlebar__controls shrink-0"
                label={`${pageTitle} — ${i18n.window.controls}`}
                minimizeLabel=""
                maximizeLabel=""
                restoreLabel=""
                closeLabel={`${i18n.window.close}: ${pageTitle}`}
                minimizable={false}
                maximizable={false}
                closable
                onClose={onClose}
            />
            <TitleBarPageNav />
            <TitleBarHistory />
        </>
    );
}
