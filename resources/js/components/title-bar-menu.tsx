const TITLE_BAR_ITEMS = [
    'ملف',
    'الصفحة الرئيسية',
    'اضافة',
    'اعدادات',
    'قوائم',
    'ادوات',
    'تقارير',
    'مساعدة',
] as const;

type TitleBarMenuProps = {
    onHome?: () => void;
};

export function TitleBarMenu({ onHome }: TitleBarMenuProps) {
    return (
        <nav className="sis-titlebar__menu" aria-label="شريط القوائم" dir="rtl">
            {TITLE_BAR_ITEMS.map((label) => (
                <button
                    key={label}
                    type="button"
                    className="sis-titlebar__menu-item"
                    onClick={label === 'الصفحة الرئيسية' ? onHome : undefined}
                >
                    {label}
                </button>
            ))}
        </nav>
    );
}
