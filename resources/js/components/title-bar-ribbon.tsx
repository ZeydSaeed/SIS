import { Fragment, useId, type ReactNode } from 'react';
import {
    AlignCenter,
    AlignLeft,
    AlignRight,
    BarChart3,
    Bold,
    BookMarked,
    BookOpen,
    Briefcase,
    Building2,
    CalendarCheck,
    CalendarDays,
    CalendarRange,
    ClipboardList,
    ClipboardPaste,
    Copy,
    Database,
    FileBadge,
    FileBarChart,
    FileDown,
    FilePlus,
    FileText,
    FlaskConical,
    FolderOpen,
    GraduationCap,
    Hammer,
    Headphones,
    Info,
    Italic,
    Layers,
    LifeBuoy,
    ListChecks,
    ListTodo,
    MessagesSquare,
    NotebookPen,
    Package,
    Palette,
    PieChart,
    Printer,
    RefreshCw,
    Replace,
    Save,
    Scissors,
    Settings,
    Smartphone,
    StickyNote,
    Underline,
    UserPlus,
    UserRound,
    Users,
    Wand2,
    Wrench,
    X,
    type LucideIcon,
} from 'lucide-react';
import { usePageRibbonRegistration } from '@/components/sis/page-ribbon-context';
import { usePageAlignment, type PageAlignment } from '@/hooks/use-page-alignment';
import { preservePageClipboardSelection } from '@/hooks/use-page-clipboard';
import {
    usePageTextStyle,
    type PageTextStyleFlag,
} from '@/hooks/use-page-text-style';
import {
    APPROVED_PAGE_FONTS,
    PAGE_FONT_SIZES,
    PAGE_TYPOGRAPHY_DEFAULT,
    usePageTypography,
} from '@/hooks/use-page-typography';
import { t } from '@/i18n';

export type FileRibbonActionId =
    | 'new'
    | 'open'
    | 'save'
    | 'saveAs'
    | 'savePdf'
    | 'info'
    | 'print'
    | 'close'
    | 'account'
    | 'options';

export type HomeRibbonActionId =
    | 'copy'
    | 'cut'
    | 'paste'
    | 'alignStart'
    | 'alignCenter'
    | 'alignEnd'
    | 'bold'
    | 'italic'
    | 'underline'
    | 'findReplace';

export type AddRibbonActionId =
    | 'addSchool'
    | 'addStudent'
    | 'addTeacher'
    | 'addHoliday'
    | 'addCurriculum'
    | 'addGrades'
    | 'addDocument'
    | 'addAttendance'
    | 'addGuardian'
    | 'addExam'
    | 'addTimetable'
    | 'addSection'
    | 'addSubject';

export type SettingsRibbonActionId =
    | 'settingsAppearance'
    | 'settingsTimetable'
    | 'settingsAccount'
    | 'settingsNotes'
    | 'settingsDefinitions'
    | 'settingsPractical'
    | 'settingsLabs'
    | 'settingsReports'
    | 'settingsScheduling'
    | 'settingsDocuments'
    | 'settingsTasks'
    | 'settingsStats';

export type ListsRibbonActionId =
    | 'listStudents'
    | 'listCommunications'
    | 'listSchools'
    | 'listDepartments'
    | 'listSections'
    | 'listWorkshops'
    | 'listEmployees'
    | 'listTeachers'
    | 'listEquipment'
    | 'listGuardians';

export type ToolsRibbonActionId =
    | 'toolsCertificates'
    | 'toolsDbMaintenance'
    | 'toolsMobileSync'
    | 'toolsEvaluations'
    | 'toolsInstallWizard'
    | 'toolsStudentRequirements'
    | 'toolsTeacherRequirements';

export type ReportsRibbonActionId =
    | 'reportsStandard'
    | 'reportsCharts';

export type HelpRibbonActionId =
    | 'helpLiveSupport'
    | 'helpDocsSupport'
    | 'helpUpdate';

export type RibbonActionId =
    | FileRibbonActionId
    | HomeRibbonActionId
    | AddRibbonActionId
    | SettingsRibbonActionId
    | ListsRibbonActionId
    | ToolsRibbonActionId
    | ReportsRibbonActionId
    | HelpRibbonActionId;

export type RibbonTab =
    | 'file'
    | 'home'
    | 'add'
    | 'settings'
    | 'lists'
    | 'tools'
    | 'reports'
    | 'help';

type RibbonItem = {
    id: string;
    label: string;
    icon: LucideIcon;
    disabled?: boolean;
    onSelect?: () => void;
};

type RibbonGroup = {
    id: string;
    label: string;
    items?: RibbonItem[];
    custom?: ReactNode;
};

const FILE_RIBBON_GROUPS: RibbonGroup[] = [
    {
        id: 'create',
        label: 'إنشاء',
        items: [
            { id: 'new', label: 'جديد', icon: FilePlus },
            { id: 'open', label: 'فتح', icon: FolderOpen },
        ],
    },
    {
        id: 'persist',
        label: 'حفظ',
        items: [
            { id: 'save', label: 'حفظ', icon: Save },
            { id: 'saveAs', label: 'حفظ باسم', icon: Save },
            { id: 'savePdf', label: 'حفظ كـ PDF', icon: FileDown },
        ],
    },
    {
        id: 'info',
        label: 'معلومات',
        items: [{ id: 'info', label: 'معلومات', icon: Info }],
    },
    {
        id: 'print',
        label: 'طباعة',
        items: [{ id: 'print', label: 'طباعة', icon: Printer }],
    },
    {
        id: 'account',
        label: 'الحساب',
        items: [
            { id: 'account', label: 'الحساب', icon: UserRound },
            { id: 'options', label: 'خيارات', icon: Settings },
        ],
    },
    {
        id: 'close',
        label: 'إغلاق',
        items: [{ id: 'close', label: 'اغلاق', icon: X }],
    },
];

const ADD_RIBBON_GROUPS: RibbonGroup[] = [
    {
        id: 'people',
        label: 'أشخاص',
        items: [
            { id: 'addStudent', label: 'طالب', icon: GraduationCap },
            { id: 'addTeacher', label: 'مدرس', icon: Users },
            { id: 'addGuardian', label: 'ولي امر طالب', icon: UserPlus },
        ],
    },
    {
        id: 'school',
        label: 'المدرسة',
        items: [
            { id: 'addSchool', label: 'مدرسة', icon: Building2 },
            { id: 'addSection', label: 'قسم', icon: Layers },
            { id: 'addSubject', label: 'مادة دراسية', icon: BookMarked },
        ],
    },
    {
        id: 'academic',
        label: 'أكاديمي',
        items: [
            { id: 'addCurriculum', label: 'منهج', icon: BookOpen },
            { id: 'addTimetable', label: 'جدول دراسي', icon: CalendarRange },
            { id: 'addExam', label: 'امتحان', icon: NotebookPen },
            { id: 'addGrades', label: 'درجات', icon: ListChecks },
        ],
    },
    {
        id: 'ops',
        label: 'تشغيل',
        items: [
            { id: 'addAttendance', label: 'حضور', icon: CalendarCheck },
            { id: 'addHoliday', label: 'اجازة', icon: CalendarDays },
            { id: 'addDocument', label: 'مستند', icon: FolderOpen },
        ],
    },
];

const SETTINGS_RIBBON_GROUPS: RibbonGroup[] = [
    {
        id: 'profile',
        label: 'الحساب',
        items: [
            { id: 'settingsAppearance', label: 'المظهر', icon: Palette },
            { id: 'settingsAccount', label: 'الحساب', icon: UserRound },
        ],
    },
    {
        id: 'schedule',
        label: 'الجدولة',
        items: [
            { id: 'settingsTimetable', label: 'الجدول الدراسي', icon: CalendarRange },
            { id: 'settingsScheduling', label: 'الجدولة', icon: ClipboardList },
        ],
    },
    {
        id: 'content',
        label: 'المحتوى',
        items: [
            { id: 'settingsNotes', label: 'الملاحظات', icon: StickyNote },
            { id: 'settingsDefinitions', label: 'التعريفات', icon: BookOpen },
            { id: 'settingsDocuments', label: 'المستندات', icon: FolderOpen },
        ],
    },
    {
        id: 'operations',
        label: 'العملي',
        items: [
            { id: 'settingsPractical', label: 'العملي', icon: Wrench },
            { id: 'settingsLabs', label: 'المختبرات', icon: FlaskConical },
            { id: 'settingsTasks', label: 'المهام', icon: ListTodo },
        ],
    },
    {
        id: 'analytics',
        label: 'التقارير',
        items: [
            { id: 'settingsReports', label: 'التقارير', icon: FileBarChart },
            { id: 'settingsStats', label: 'الاحصائيات', icon: BarChart3 },
        ],
    },
];

const LISTS_RIBBON_GROUPS: RibbonGroup[] = [
    {
        id: 'people',
        label: 'الأشخاص',
        items: [
            { id: 'listStudents', label: 'الطلاب', icon: GraduationCap },
            { id: 'listTeachers', label: 'المعلمون', icon: Users },
            { id: 'listEmployees', label: 'الموظفون', icon: Briefcase },
            { id: 'listGuardians', label: 'اولياء الامور', icon: UserRound },
        ],
    },
    {
        id: 'org',
        label: 'الهيكل',
        items: [
            { id: 'listSchools', label: 'المدارس', icon: Building2 },
            { id: 'listDepartments', label: 'الاقسام', icon: Layers },
            { id: 'listSections', label: 'الشعب', icon: ClipboardList },
        ],
    },
    {
        id: 'facilities',
        label: 'المرافق',
        items: [
            { id: 'listWorkshops', label: 'الورش', icon: Hammer },
            { id: 'listEquipment', label: 'التجهيزات', icon: Package },
            { id: 'listCommunications', label: 'الاتصالات', icon: MessagesSquare },
        ],
    },
];

const TOOLS_RIBBON_GROUPS: RibbonGroup[] = [
    {
        id: 'documents',
        label: 'الشهادات',
        items: [
            { id: 'toolsCertificates', label: 'الشهادات', icon: FileBadge },
        ],
    },
    {
        id: 'system',
        label: 'النظام',
        items: [
            { id: 'toolsDbMaintenance', label: 'صيانة قاعدة البيانات', icon: Database },
            { id: 'toolsMobileSync', label: 'تزامن الموبايل', icon: Smartphone },
            { id: 'toolsInstallWizard', label: 'معالج التنصيب', icon: Wand2 },
        ],
    },
    {
        id: 'requirements',
        label: 'المتطلبات',
        items: [
            { id: 'toolsEvaluations', label: 'التقييمات', icon: ListChecks },
            { id: 'toolsStudentRequirements', label: 'متطلبات الطلاب', icon: GraduationCap },
            { id: 'toolsTeacherRequirements', label: 'متطلبات المعلمون', icon: Users },
        ],
    },
];

const REPORTS_RIBBON_GROUPS: RibbonGroup[] = [
    {
        id: 'reports',
        label: 'التقارير',
        items: [
            { id: 'reportsStandard', label: 'القياسي', icon: FileText },
            { id: 'reportsCharts', label: 'الرسومات', icon: PieChart },
        ],
    },
];

const HELP_RIBBON_GROUPS: RibbonGroup[] = [
    {
        id: 'support',
        label: 'الدعم',
        items: [
            { id: 'helpLiveSupport', label: 'الدعم المباشر', icon: Headphones },
            { id: 'helpDocsSupport', label: 'دعم المستندات', icon: LifeBuoy },
            { id: 'helpUpdate', label: 'تحديث', icon: RefreshCw },
        ],
    },
];

function FontControls() {
    const fontId = useId();
    const sizeId = useId();
    const defaultLabel = t().common.fontDefault;
    const { fontFamily, fontSize, setFontFamily, setFontSize, resetTypography } =
        usePageTypography();

    return (
        <div className="sis-ribbon__font-controls">
            <label className="sis-ribbon__field" htmlFor={fontId}>
                <span className="sis-ribbon__field-label">نوع الخط</span>
                <select
                    id={fontId}
                    className="sis-ribbon__select"
                    value={fontFamily}
                    onChange={(event) => {
                        const value = event.target.value;
                        event.currentTarget.blur();

                        if (value === PAGE_TYPOGRAPHY_DEFAULT) {
                            resetTypography();
                            return;
                        }

                        setFontFamily(value as (typeof APPROVED_PAGE_FONTS)[number]);
                    }}
                >
                    {APPROVED_PAGE_FONTS.map((name) => (
                        <option key={name} value={name} style={{ fontFamily: name }}>
                            {name}
                        </option>
                    ))}
                    <option value={PAGE_TYPOGRAPHY_DEFAULT}>{defaultLabel}</option>
                </select>
            </label>
            <label className="sis-ribbon__field" htmlFor={sizeId}>
                <span className="sis-ribbon__field-label">حجم الخط</span>
                <select
                    id={sizeId}
                    className="sis-ribbon__select sis-ribbon__select--size"
                    value={fontSize}
                    onChange={(event) => {
                        const value = event.target.value;
                        event.currentTarget.blur();

                        if (value === PAGE_TYPOGRAPHY_DEFAULT) {
                            setFontSize(PAGE_TYPOGRAPHY_DEFAULT);
                            return;
                        }

                        setFontSize(value as (typeof PAGE_FONT_SIZES)[number]);
                    }}
                >
                    {PAGE_FONT_SIZES.map((value) => (
                        <option key={value} value={value}>
                            {value}
                        </option>
                    ))}
                    <option value={PAGE_TYPOGRAPHY_DEFAULT}>{defaultLabel}</option>
                </select>
            </label>
        </div>
    );
}

const ALIGN_ACTIONS: {
    id: PageAlignment;
    action: 'alignStart' | 'alignCenter' | 'alignEnd';
    label: string;
    icon: LucideIcon;
}[] = [
    { id: 'start', action: 'alignStart', label: 'بداية', icon: AlignRight },
    { id: 'center', action: 'alignCenter', label: 'توسيط', icon: AlignCenter },
    { id: 'end', action: 'alignEnd', label: 'نهاية', icon: AlignLeft },
];

function AlignControls() {
    const { alignment, applyAlignment } = usePageAlignment();

    return (
        <>
            {ALIGN_ACTIONS.map((item) => {
                const Icon = item.icon;
                const pressed = alignment === item.id;

                return (
                    <button
                        key={item.action}
                        type="button"
                        className="sis-ribbon__item"
                        aria-label={item.label}
                        aria-pressed={pressed}
                        onMouseDown={(event) => {
                            event.preventDefault();
                        }}
                        onClick={() => {
                            applyAlignment(item.id);
                        }}
                    >
                        <Icon className="sis-ribbon__icon" aria-hidden />
                        <span className="sis-ribbon__label">{item.label}</span>
                    </button>
                );
            })}
        </>
    );
}

const STYLE_ACTIONS: {
    id: PageTextStyleFlag;
    label: string;
    icon: LucideIcon;
}[] = [
    { id: 'bold', label: 'بولد', icon: Bold },
    { id: 'italic', label: 'ايتالك', icon: Italic },
    { id: 'underline', label: 'اندر لاين', icon: Underline },
];

function StyleControls() {
    const { bold, italic, underline, toggleStyle } = usePageTextStyle();
    const pressed: Record<PageTextStyleFlag, boolean> = { bold, italic, underline };

    return (
        <>
            {STYLE_ACTIONS.map((item) => {
                const Icon = item.icon;

                return (
                    <button
                        key={item.id}
                        type="button"
                        className="sis-ribbon__item"
                        aria-label={item.label}
                        aria-pressed={pressed[item.id]}
                        onMouseDown={(event) => {
                            event.preventDefault();
                        }}
                        onClick={() => {
                            toggleStyle(item.id);
                        }}
                    >
                        <Icon className="sis-ribbon__icon" aria-hidden />
                        <span className="sis-ribbon__label">{item.label}</span>
                    </button>
                );
            })}
        </>
    );
}

function buildHomeGroups(): RibbonGroup[] {
    return [
        {
            id: 'clipboard',
            label: 'الحافظة',
            items: [
                { id: 'paste', label: 'لصق', icon: ClipboardPaste },
                { id: 'cut', label: 'قص', icon: Scissors },
                { id: 'copy', label: 'نسخ', icon: Copy },
            ],
        },
        {
            id: 'font',
            label: 'الخط',
            custom: (
                <FontControls />
            ),
        },
        {
            id: 'align',
            label: 'المحاذاة',
            custom: <AlignControls />,
        },
        {
            id: 'style',
            label: 'النمط',
            custom: <StyleControls />,
        },
        {
            id: 'edit',
            label: 'تحرير',
            items: [{ id: 'findReplace', label: 'بحث واستبدال', icon: Replace }],
        },
    ];
}

type TitleBarRibbonProps = {
    tab: RibbonTab;
    onAction?: (id: RibbonActionId) => void;
    onCollapse?: () => void;
};

export function TitleBarRibbon({
    tab,
    onAction,
    onCollapse,
}: TitleBarRibbonProps) {
    const pageRibbon = usePageRibbonRegistration();
    const staticGroups =
        tab === 'file'
            ? FILE_RIBBON_GROUPS
            : tab === 'add'
              ? ADD_RIBBON_GROUPS
              : tab === 'settings'
                ? SETTINGS_RIBBON_GROUPS
                : tab === 'lists'
                  ? LISTS_RIBBON_GROUPS
                  : tab === 'tools'
                    ? TOOLS_RIBBON_GROUPS
                    : tab === 'reports'
                      ? REPORTS_RIBBON_GROUPS
                      : tab === 'help'
                        ? HELP_RIBBON_GROUPS
                        : buildHomeGroups();
    const pageGroups = pageRibbon?.tab === tab ? pageRibbon.groups : [];
    const groups: RibbonGroup[] = [
        ...pageGroups.map(
            (group): RibbonGroup => ({
                id: group.id,
                label: group.label,
                items: group.commands.map((command) => ({
                    id: command.id,
                    label: command.label,
                    icon: command.icon,
                    disabled: command.disabled,
                    onSelect: command.onSelect,
                })),
            }),
        ),
        ...staticGroups,
    ];

    const ariaLabel =
        tab === 'file'
            ? 'شريط ملف'
            : tab === 'add'
              ? 'شريط اضافة'
              : tab === 'settings'
                ? 'شريط اعدادات'
                : tab === 'lists'
                  ? 'شريط قوائم'
                  : tab === 'tools'
                    ? 'شريط ادوات'
                    : tab === 'reports'
                      ? 'شريط تقارير'
                      : tab === 'help'
                        ? 'شريط مساعدة'
                        : 'شريط الصفحة الرئيسية';

    return (
        <div className="sis-ribbon" role="region" aria-label={ariaLabel} dir="rtl">
            <div className="sis-ribbon__body">
                {groups.map((group, index) => (
                    <Fragment key={group.id}>
                        {index > 0 ? (
                            <div className="sis-ribbon__separator" aria-hidden />
                        ) : null}
                        <div className="sis-ribbon__group">
                            <div className="sis-ribbon__items">
                                {group.custom
                                    ? group.custom
                                    : group.items?.map((item) => {
                                          const Icon = item.icon;
                                          const isClipboard =
                                              item.id === 'copy' ||
                                              item.id === 'cut' ||
                                              item.id === 'paste';

                                          return (
                                              <button
                                                  key={item.id}
                                                  type="button"
                                                  className="sis-ribbon__item"
                                                  disabled={item.disabled}
                                                  aria-label={item.label}
                                                  onMouseDown={(event) => {
                                                      if (isClipboard) {
                                                          event.preventDefault();
                                                          preservePageClipboardSelection();
                                                      }
                                                  }}
                                                  onClick={() => {
                                                      if (item.onSelect) {
                                                          item.onSelect();

                                                          return;
                                                      }

                                                      onAction?.(item.id as RibbonActionId);
                                                  }}
                                              >
                                                  <Icon
                                                      className="sis-ribbon__icon"
                                                      aria-hidden
                                                  />
                                                  <span className="sis-ribbon__label">
                                                      {item.label}
                                                  </span>
                                              </button>
                                          );
                                      })}
                            </div>
                            <span className="sis-ribbon__group-label">{group.label}</span>
                        </div>
                    </Fragment>
                ))}
            </div>
            <button
                type="button"
                className="sis-ribbon__collapse"
                aria-label="طي الشريط"
                onClick={onCollapse}
            >
                <span aria-hidden>⌃</span>
            </button>
        </div>
    );
}
