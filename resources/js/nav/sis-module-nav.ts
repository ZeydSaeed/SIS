import {
    ArrowLeftRight,
    Award,
    BadgeCheck,
    Banknote,
    BookOpen,
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
    /** When true, excluded from sidebar sections (dashboard tile only). */
    dashboardOnly?: boolean;
};

export type SisNavSection = {
    id: string;
    titleAr: string;
    titleEn: string;
    items: SisModuleNavItem[];
};

type ModuleKey =
    | 'students'
    | 'guardians'
    | 'admission'
    | 'enrollments'
    | 'attendance'
    | 'holidays'
    | 'health'
    | 'teachers'
    | 'hr'
    | 'timetable'
    | 'curriculum'
    | 'exams'
    | 'grades'
    | 'results'
    | 'promotion'
    | 'transfers'
    | 'graduation'
    | 'certificates'
    | 'finance'
    | 'documents'
    | 'communication'
    | 'workflow'
    | 'reports';

const MODULE_DEFS: Record<
    ModuleKey,
    { href: string; icon: LucideIcon; dashboardOnly?: boolean }
> = {
    students: { href: '/students', icon: GraduationCap },
    guardians: { href: '/guardians', icon: UserRound },
    admission: { href: '/admission', icon: UserPlus },
    enrollments: { href: '/enrollments', icon: ClipboardList },
    attendance: { href: '/attendance', icon: CalendarCheck },
    holidays: { href: '/holidays', icon: CalendarDays },
    health: { href: '/health', icon: HeartPulse },
    teachers: { href: '/teachers', icon: Users },
    hr: { href: '/hr', icon: Briefcase },
    timetable: { href: '/timetable', icon: CalendarRange },
    curriculum: { href: '/curriculum', icon: BookOpen },
    exams: { href: '/exams', icon: NotebookPen },
    grades: { href: '/grades', icon: ListChecks },
    results: { href: '/results', icon: ScrollText },
    promotion: { href: '/promotion', icon: Award },
    transfers: { href: '/transfers', icon: ArrowLeftRight },
    graduation: { href: '/graduation', icon: BadgeCheck },
    certificates: { href: '/certificates', icon: FileBadge },
    finance: { href: '/finance', icon: Banknote },
    documents: { href: '/documents', icon: FolderOpen },
    communication: { href: '/communication', icon: MessagesSquare },
    workflow: { href: '/workflow', icon: Workflow },
    reports: { href: '/reports', icon: FileBarChart },
};

function moduleItem(key: ModuleKey): SisModuleNavItem {
    const i18n = t();
    const def = MODULE_DEFS[key];

    return {
        title: i18n.modules[key],
        href: def.href,
        icon: def.icon,
        dashboardOnly: def.dashboardOnly,
    };
}

/** Sidebar sections — SSOT for grouped navigation. */
export function getSisSidebarSections(): SisNavSection[] {
    return [
        {
            id: 'student-management',
            titleAr: 'إدارة الطلاب',
            titleEn: 'Student Management',
            items: [
                moduleItem('admission'),
                moduleItem('enrollments'),
                moduleItem('students'),
                moduleItem('attendance'),
                moduleItem('holidays'),
            ],
        },
        {
            id: 'student-affairs',
            titleAr: 'شؤون الطلبة',
            titleEn: 'Student Affairs',
            items: [
                moduleItem('timetable'),
                moduleItem('curriculum'),
                moduleItem('guardians'),
                moduleItem('health'),
                moduleItem('transfers'),
                moduleItem('communication'),
            ],
        },
        {
            id: 'examinations',
            titleAr: 'الامتحانات والتقييم',
            titleEn: 'Examinations',
            items: [
                moduleItem('exams'),
                moduleItem('grades'),
                moduleItem('results'),
            ],
        },
        {
            id: 'staff-faculty',
            titleAr: 'الكادر التدريسي',
            titleEn: 'Staff & Faculty',
            items: [
                moduleItem('teachers'),
                moduleItem('hr'),
                moduleItem('promotion'),
                moduleItem('finance'),
            ],
        },
        {
            id: 'graduation',
            titleAr: 'التخرج والخريجون',
            titleEn: 'Graduation',
            items: [
                moduleItem('graduation'),
                moduleItem('certificates'),
            ],
        },
        {
            id: 'administration',
            titleAr: 'الشؤون الإدارية',
            titleEn: 'Administration',
            items: [
                moduleItem('documents'),
                moduleItem('reports'),
                moduleItem('workflow'),
            ],
        },
    ];
}

/** Flat module map — preserves sidebar section order (SSOT). */
export function getSisModuleNavItems(): SisModuleNavItem[] {
    const i18n = t();
    const sectionItems = getSisSidebarSections().flatMap(
        (section) => section.items,
    );

    return [
        {
            title: i18n.modules.dashboard,
            href: dashboard.url(),
            icon: LayoutGrid,
            sidebarOnly: true,
        },
        ...sectionItems,
    ];
}
