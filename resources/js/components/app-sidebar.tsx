import { Link } from '@inertiajs/react';
import { LayoutGrid } from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { getSisModuleNavItems } from '@/nav/sis-module-nav';
import { t } from '@/i18n';
import { dashboard } from '@/routes';
import type { NavItem } from '@/types';

export function AppSidebar() {
    const i18n = t();
    const { isCurrentUrl } = useCurrentUrl();
    const mainNavItems: NavItem[] = getSisModuleNavItems()
        .filter((item) => !item.sidebarOnly)
        .map((item) => ({
            title: item.title,
            href: item.href,
            icon: item.icon,
        }));

    return (
        <Sidebar collapsible="icon" variant="inset" side="right">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton
                            size="lg"
                            asChild
                            className="hover:bg-transparent hover:text-sidebar-foreground active:bg-transparent data-[active=true]:bg-transparent data-[state=open]:hover:bg-transparent"
                        >
                            <Link href={dashboard()} prefetch className="pointer-events-auto">
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                    <SidebarMenuItem>
                        <SidebarMenuButton
                            asChild
                            isActive={isCurrentUrl(dashboard.url())}
                            tooltip={{ children: i18n.modules.dashboard }}
                        >
                            <Link href={dashboard()} prefetch>
                                <LayoutGrid />
                                <span>{i18n.modules.dashboard}</span>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent className="sis-scroll-hidden min-h-0 flex-1 overflow-x-hidden overflow-y-auto overscroll-x-none overscroll-contain group-data-[collapsible=icon]:overflow-x-hidden group-data-[collapsible=icon]:overflow-y-auto">
                <NavMain items={mainNavItems} />
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
