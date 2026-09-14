import { Link } from '@inertiajs/react';
import {
    Brain,
    CalendarCheck,
    CalendarRange,
    ClipboardList,
    FileBarChart,
    GraduationCap,
    LayoutGrid,
    ScrollText,
    Users,
} from 'lucide-react';
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
import { t } from '@/i18n';
import { dashboard } from '@/routes';
import type { NavItem } from '@/types';

export function AppSidebar() {
    const i18n = t();

    const mainNavItems: NavItem[] = [
        {
            title: i18n.modules.dashboard,
            href: dashboard(),
            icon: LayoutGrid,
        },
        {
            title: i18n.modules.students,
            href: '/students',
            icon: GraduationCap,
        },
        {
            title: i18n.modules.enrollments,
            href: '/enrollments',
            icon: ClipboardList,
        },
        {
            title: i18n.modules.attendance,
            href: '/attendance',
            icon: CalendarCheck,
        },
        {
            title: i18n.modules.teachers,
            href: '/teachers',
            icon: Users,
        },
        {
            title: i18n.modules.timetable,
            href: '/timetable',
            icon: CalendarRange,
        },
        {
            title: i18n.modules.results,
            href: '/results',
            icon: ScrollText,
        },
        {
            title: i18n.modules.exams,
            href: '/exams',
            icon: GraduationCap,
        },
        {
            title: i18n.modules.grades,
            href: '/grades',
            icon: ClipboardList,
        },
        {
            title: i18n.modules.reports,
            href: '/reports',
            icon: FileBarChart,
        },
        {
            title: i18n.modules.intelligence,
            href: '/intelligence/recommendations',
            icon: Brain,
        },
    ];

    return (
        <Sidebar collapsible="icon" variant="inset" side="right">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={mainNavItems} />
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
