import { Fragment, useId, useState, type ReactNode } from 'react';
import {
    AlignCenter,
    AlignLeft,
    AlignRight,
    Bold,
    BookMarked,
    BookOpen,
    Building2,
    CalendarCheck,
    CalendarDays,
    CalendarRange,
    ClipboardPaste,
    Copy,
    FileDown,
    FilePlus,
    FolderOpen,
    GraduationCap,
    Info,
    Italic,
    Layers,
    ListChecks,
    NotebookPen,
    Printer,
    Replace,
    Save,
    Scissors,
    Settings,
    Underline,
    UserPlus,
    UserRound,
    Users,
    X,
    type LucideIcon,
} from 'lucide-react';

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

export type RibbonActionId =
    | FileRibbonActionId
    | HomeRibbonActionId
    | AddRibbonActionId;

export type RibbonTab = 'file' | 'home' | 'add';

type RibbonItem = {
    id: RibbonActionId;
    label: string;
    icon: LucideIcon;
};

type RibbonGroup = {
    id: string;
    label: string;
    items?: RibbonItem[];
    custom?: ReactNode;
};

const APPROVED_FONTS = ['Segoe UI', 'Tahoma', 'Calibri', 'Aptos'] as const;
const FONT_SIZES = ['10', '11', '12', '14', '16', '18', '20', '24'] as const;

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

function FontControls({
    onFontFamily,
    onFontSize,
}: {
    onFontFamily: (value: string) => void;
    onFontSize: (value: string) => void;
}) {
    const fontId = useId();
    const sizeId = useId();
    const [font, setFont] = useState<string>(APPROVED_FONTS[0]);
    const [size, setSize] = useState<string>('14');

    return (
        <div className="sis-ribbon__font-controls">
            <label className="sis-ribbon__field" htmlFor={fontId}>
                <span className="sis-ribbon__field-label">نوع الخط</span>
                <select
                    id={fontId}
                    className="sis-ribbon__select"
                    value={font}
                    onChange={(event) => {
                        const value = event.target.value;
                        setFont(value);
                        onFontFamily(value);
                    }}
                >
                    {APPROVED_FONTS.map((name) => (
                        <option key={name} value={name} style={{ fontFamily: name }}>
                            {name}
                        </option>
                    ))}
                </select>
            </label>
            <label className="sis-ribbon__field" htmlFor={sizeId}>
                <span className="sis-ribbon__field-label">حجم الخط</span>
                <select
                    id={sizeId}
                    className="sis-ribbon__select sis-ribbon__select--size"
                    value={size}
                    onChange={(event) => {
                        const value = event.target.value;
                        setSize(value);
                        onFontSize(value);
                    }}
                >
                    {FONT_SIZES.map((value) => (
                        <option key={value} value={value}>
                            {value}
                        </option>
                    ))}
                </select>
            </label>
        </div>
    );
}

function buildHomeGroups(
    onFontFamily: (value: string) => void,
    onFontSize: (value: string) => void,
): RibbonGroup[] {
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
                <FontControls onFontFamily={onFontFamily} onFontSize={onFontSize} />
            ),
        },
        {
            id: 'align',
            label: 'المحاذاة',
            items: [
                { id: 'alignStart', label: 'بداية', icon: AlignRight },
                { id: 'alignCenter', label: 'توسيط', icon: AlignCenter },
                { id: 'alignEnd', label: 'نهاية', icon: AlignLeft },
            ],
        },
        {
            id: 'style',
            label: 'النمط',
            items: [
                { id: 'bold', label: 'بولد', icon: Bold },
                { id: 'italic', label: 'ايتالك', icon: Italic },
                { id: 'underline', label: 'اندر لاين', icon: Underline },
            ],
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
    onFontFamily?: (value: string) => void;
    onFontSize?: (value: string) => void;
};

export function TitleBarRibbon({
    tab,
    onAction,
    onCollapse,
    onFontFamily,
    onFontSize,
}: TitleBarRibbonProps) {
    const groups =
        tab === 'file'
            ? FILE_RIBBON_GROUPS
            : tab === 'add'
              ? ADD_RIBBON_GROUPS
              : buildHomeGroups(
                    onFontFamily ?? (() => undefined),
                    onFontSize ?? (() => undefined),
                );

    const ariaLabel =
        tab === 'file'
            ? 'شريط ملف'
            : tab === 'add'
              ? 'شريط اضافة'
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

                                          return (
                                              <button
                                                  key={item.id}
                                                  type="button"
                                                  className="sis-ribbon__item"
                                                  onClick={() => onAction?.(item.id)}
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
