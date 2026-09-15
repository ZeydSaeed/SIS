import AppSidebarLayout from '@/layouts/app/app-sidebar-layout';
import type { BreadcrumbItem } from '@/types';

/**
 * All operational pages keep the sidebar so the full module map stays reachable.
 * Toggle sidebar control remains dashboard-only (see AppSidebarHeader).
 */
export default function AppLayout({
    breadcrumbs = [],
    children,
}: {
    breadcrumbs?: BreadcrumbItem[];
    children: React.ReactNode;
}) {
    return (
        <AppSidebarLayout breadcrumbs={breadcrumbs}>{children}</AppSidebarLayout>
    );
}
