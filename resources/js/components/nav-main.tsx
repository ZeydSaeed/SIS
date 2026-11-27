import { Link, usePage } from '@inertiajs/react';
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
    const currentQuery = new URLSearchParams(usePage().url.split('?')[1] ?? '');

    /** An item whose link carries a query (e.g. «المناهج» = /curriculum?view=curricula) needs those params too. */
    const matchesQuery = (href: string): boolean => {
        const query = href.split('?')[1];
        if (query === undefined) {
            return true;
        }
        for (const [key, value] of new URLSearchParams(query)) {
            if (currentQuery.get(key) !== value) {
                return false;
            }
        }

        return true;
    };
    const pathOf = (href: string): string => (href.split('?')[0] ?? href).replace(OVERVIEW_SUFFIX, '');
    const isActive = (href: string): boolean => {
        if (!isCurrentOrParentUrl(pathOf(href)) || !matchesQuery(href)) {
            return false;
        }
        // A link yields to a sibling that matches the page more closely: a deeper path (/curriculum →
        // /curriculum/curricula) or, on the same path, one whose query matches too (/curriculum?view=…).
        const path = pathOf(href);

        return !items.some((other) => {
            const otherHref = other.href.toString();
            const otherPath = pathOf(otherHref);
            if (otherHref === href || !isCurrentOrParentUrl(otherPath) || !matchesQuery(otherHref)) {
                return false;
            }

            return otherPath.length > path.length || (otherPath === path && otherHref.includes('?') && !href.includes('?'));
        });
    };

    return (
        <SidebarGroup className="px-2 py-0">
            <SidebarMenu>
                {items.map((item) => (
                    <SidebarMenuItem key={item.href.toString()}>
                        <SidebarMenuButton
                            asChild
                            isActive={isActive(item.href.toString())}
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
