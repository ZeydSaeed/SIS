import { router } from '@inertiajs/react';
import { Breadcrumbs } from '@/components/breadcrumbs';
import { TitleBarMenu } from '@/components/title-bar-menu';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import type { BreadcrumbItem as BreadcrumbItemType } from '@/types';

export function AppSidebarHeader({
    breadcrumbs = [],
}: {
    breadcrumbs?: BreadcrumbItemType[];
}) {
    return (
        <header className="sis-titlebar flex h-9 shrink-0 items-center gap-3 px-3 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-9 md:px-3">
            <div className="flex min-w-0 flex-1 items-center gap-2">
                <SidebarTrigger className="sis-titlebar__trigger size-7" />
                <TitleBarMenu
                    onHome={() => {
                        router.visit(dashboard());
                    }}
                />
                <Breadcrumbs breadcrumbs={breadcrumbs} />
            </div>
        </header>
    );
}
