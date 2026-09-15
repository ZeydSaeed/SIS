import type { RibbonTab } from '@/components/title-bar-ribbon';

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
    activeRibbon?: RibbonTab | null;
    onRibbonChange?: (tab: RibbonTab | null) => void;
};

export type { RibbonTab };

export function TitleBarMenu({
    activeRibbon = null,
    onRibbonChange,
}: TitleBarMenuProps) {
    return (
        <nav className="sis-titlebar__menu" aria-label="شريط القوائم" dir="rtl">
            {TITLE_BAR_ITEMS.map((label) => {
                const tab: RibbonTab | null =
                    label === 'ملف'
                        ? 'file'
                        : label === 'الصفحة الرئيسية'
                          ? 'home'
                          : label === 'اضافة'
                            ? 'add'
                            : null;
                const isActive = tab !== null && activeRibbon === tab;

                return (
                    <button
                        key={label}
                        type="button"
                        className={
                            isActive
                                ? 'sis-titlebar__menu-item sis-titlebar__menu-item--active'
                                : 'sis-titlebar__menu-item'
                        }
                        aria-pressed={tab ? isActive : undefined}
                        onClick={() => {
                            if (!tab) {
                                return;
                            }
                            onRibbonChange?.(activeRibbon === tab ? null : tab);
                        }}
                    >
                        {label}
                    </button>
                );
            })}
        </nav>
    );
}
