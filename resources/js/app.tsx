import { createInertiaApp } from '@inertiajs/react';
import { Toaster } from '@/components/ui/sonner';
import { TooltipProvider } from '@/components/ui/tooltip';
import { initializeTheme } from '@/hooks/use-appearance';
import { initializePageAlignment } from '@/hooks/use-page-alignment';
import { initializePageTextStyle } from '@/hooks/use-page-text-style';
import { initializePageTypography } from '@/hooks/use-page-typography';
import AppLayout from '@/layouts/app-layout';
import AuthLayout from '@/layouts/auth-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { lockChromePan } from '@/lib/lock-chrome-pan';

void createInertiaApp({
    title: () => 'Student Information System',
    layout: (name) => {
        switch (true) {
            case name === 'welcome':
                return null;
            case name.startsWith('auth/'):
                return AuthLayout;
            case name.startsWith('settings/'):
                return [AppLayout, SettingsLayout];
            default:
                // Dashboard + ops pages wrap AppLayout in the page (sidebar only on dashboard).
                return null;
        }
    },
    strictMode: true,
    withApp(app) {
        return (
            <TooltipProvider delayDuration={0}>
                {app}
                <Toaster />
            </TooltipProvider>
        );
    },
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on load...
initializeTheme();
initializePageTypography();
initializePageAlignment();
initializePageTextStyle();
lockChromePan();
