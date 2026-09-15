import {
    ArrowLeftRight,
    Award,
    BadgeCheck,
    Banknote,
    BookOpen,
    Brain,
    Briefcase,
    CalendarCheck,
    CalendarDays,
    CalendarRange,
    ClipboardList,
    FileBarChart,
    FileBadge,
    FolderOpen,
    GraduationCap,
    HeartPulse,
    LayoutGrid,
    ListChecks,
    MessagesSquare,
    NotebookPen,
    ScrollText,
    UserPlus,
    UserRound,
    Users,
    Workflow,
    type LucideIcon,
} from 'lucide-react';
import { t } from '@/i18n';
import { dashboard } from '@/routes';

export type SisModuleNavItem = {
    title: string;
    href: string;
    icon: LucideIcon;
    /** When true, shown in sidebar but not as a dashboard launcher tile. */
    sidebarOnly?: boolean;
};

/** Shared module map — sidebar + dashboard launcher (SSOT). */
export function getSisModuleNavItems(): SisModuleNavItem[] {
    const i18n = t();

    return [
        {
            title: i18n.modules.dashboard,
            href: dashboard.url(),
            icon: LayoutGrid,
            sidebarOnly: true,
        },
        {
            title: i18n.modules.students,
            href: '/students',
            icon: GraduationCap,
        },
        {
            title: i18n.modules.guardians,
            href: '/guardians',
            icon: UserRound,
        },
        {
            title: i18n.modules.admission,
            href: '/admission',
            icon: UserPlus,
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
            title: i18n.modules.holidays,
            href: '/holidays',
            icon: CalendarDays,
        },
        {
            title: i18n.modules.health,
            href: '/health',
            icon: HeartPulse,
        },
        {
            title: i18n.modules.teachers,
            href: '/teachers',
            icon: Users,
        },
        {
            title: i18n.modules.hr,
            href: '/hr',
            icon: Briefcase,
        },
        {
            title: i18n.modules.timetable,
            href: '/timetable',
            icon: CalendarRange,
        },
        {
            title: i18n.modules.curriculum,
            href: '/curriculum',
            icon: BookOpen,
        },
        {
            title: i18n.modules.exams,
            href: '/exams',
            icon: NotebookPen,
        },
        {
            title: i18n.modules.grades,
            href: '/grades',
            icon: ListChecks,
        },
        {
            title: i18n.modules.results,
            href: '/results',
            icon: ScrollText,
        },
        {
            title: i18n.modules.promotion,
            href: '/promotion',
            icon: Award,
        },
        {
            title: i18n.modules.transfers,
            href: '/transfers',
            icon: ArrowLeftRight,
        },
        {
            title: i18n.modules.graduation,
            href: '/graduation',
            icon: BadgeCheck,
        },
        {
            title: i18n.modules.certificates,
            href: '/certificates',
            icon: FileBadge,
        },
        {
            title: i18n.modules.finance,
            href: '/finance',
            icon: Banknote,
        },
        {
            title: i18n.modules.documents,
            href: '/documents',
            icon: FolderOpen,
        },
        {
            title: i18n.modules.communication,
            href: '/communication',
            icon: MessagesSquare,
        },
        {
            title: i18n.modules.workflow,
            href: '/workflow',
            icon: Workflow,
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
}
