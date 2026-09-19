import { usePage } from '@inertiajs/react';
import { useEffect } from 'react';
import { AppContent } from '@/components/app-content';
import { AppShell } from '@/components/app-shell';
import { AppSidebar } from '@/components/app-sidebar';
import { AppSidebarHeader } from '@/components/app-sidebar-header';
import { PageRibbonProvider } from '@/components/sis/page-ribbon-context';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { restorePageAlignments } from '@/hooks/use-page-alignment';
import { restorePageTextStyle } from '@/hooks/use-page-text-style';
import { cn } from '@/lib/utils';
import type { AppLayoutProps } from '@/types';

export default function AppSidebarLayout({
    breadcrumbs = [],
    children,
}: AppLayoutProps) {
    const { component, url } = usePage();
    const isDashboard = component === 'dashboard';

    useEffect(() => {
        restorePageAlignments();
        restorePageTextStyle();
    }, [component, url]);

    return (
        <AppShell variant="sidebar">
            <PageRibbonProvider>
            <AppSidebar />
            <AppContent
                variant="sidebar"
                className="flex min-h-0 min-w-0 flex-1 flex-col overflow-hidden"
            >
                {isDashboard ? (
                    <div className="sis-dashboard-toggle shrink-0">
                        <SidebarTrigger className="sis-titlebar__trigger size-7" />
                    </div>
                ) : (
                    <AppSidebarHeader breadcrumbs={breadcrumbs} />
                )}
                <div
                    className={cn(
                        'sis-page-surface min-h-0 flex-1 overscroll-x-none',
                        isDashboard
                            ? 'overflow-hidden overscroll-none'
                            : 'sis-scroll-hidden overflow-x-hidden overflow-y-auto',
                    )}
                >
                    {children}
                </div>
            </AppContent>
            </PageRibbonProvider>
        </AppShell>
    );
}
