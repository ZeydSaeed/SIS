import { router, usePage } from '@inertiajs/react';
import { useCallback } from 'react';
import { WindowControls } from '@/components/window-controls';
import { t } from '@/i18n';
import { dashboard } from '@/routes';

export function TitleBarControls() {
    const i18n = t();
    const { component } = usePage();
    const isDashboard = component === 'dashboard';
    const pageTitle = isDashboard ? i18n.modules.dashboard : i18n.window.controls;

    const onClose = useCallback(() => {
        if (isDashboard) {
            return;
        }

        router.visit(dashboard());
    }, [isDashboard]);

    if (isDashboard) {
        return null;
    }

    return (
        <WindowControls
            className="sis-titlebar__controls ml-auto shrink-0"
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
    );
}
