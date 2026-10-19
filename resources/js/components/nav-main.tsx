import { Link } from '@inertiajs/react';
import {
    SidebarGroup,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { OVERVIEW_SUFFIX } from '@/components/sis/module-overview';
import { useCurrentUrl } from '@/hooks/use-current-url';
import type { NavItem } from '@/types';

export function NavMain({ items = [] }: { items: NavItem[] }) {
    const { isCurrentOrParentUrl } = useCurrentUrl();

    return (
        <SidebarGroup className="px-2 py-0">
            <SidebarMenu>
                {items.map((item) => (
                    <SidebarMenuItem key={item.href.toString()}>
                        <SidebarMenuButton
                            asChild
                            isActive={isCurrentOrParentUrl(
                                item.href.toString().replace(OVERVIEW_SUFFIX, ''),
                            )}
                            tooltip={{ children: item.title }}
                        >
                            <Link href={item.href} prefetch>
                                {item.icon ? <item.icon /> : null}
                                <span>{item.title}</span>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                ))}
            </SidebarMenu>
        </SidebarGroup>
    );
}
