import AppSidebarLayout from '@/layouts/app/app-sidebar-layout';
import type { BreadcrumbItem } from '@/types';

/**
 * All authenticated ops pages (including dashboard) use the shared sidebar chrome
 * with the global title bar + ribbon.
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
