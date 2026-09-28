import {
    chromeTabItems,
    type RibbonTab,
} from '@/components/sis/chrome-tabs';
import { t } from '@/i18n';

type TitleBarMenuProps = {
    activeRibbon?: RibbonTab | null;
    onRibbonChange?: (tab: RibbonTab | null) => void;
    ribbonPinned?: boolean;
};

export type { RibbonTab };

/**
 * Global titlebar tab strip — one SSOT (`chrome-tabs` + `i18n.chrome.tabs`) on every page.
 */
export function TitleBarMenu({
    activeRibbon = null,
    onRibbonChange,
    ribbonPinned = false,
}: TitleBarMenuProps) {
    const i18n = t();
    const tabs = chromeTabItems(i18n.chrome.tabs);

    return (
        <nav className="sis-titlebar__menu" aria-label={i18n.chrome.tabsAria} dir="rtl">
            {tabs.map(({ id, label }) => {
                const isActive = activeRibbon === id;

                return (
                    <button
                        key={id}
                        type="button"
                        className={
                            isActive
                                ? 'sis-titlebar__menu-item sis-titlebar__menu-item--active'
                                : 'sis-titlebar__menu-item'
                        }
                        aria-pressed={isActive}
                        onClick={() => {
                            if (activeRibbon === id) {
                                if (ribbonPinned) {
                                    return;
                                }
                                onRibbonChange?.(null);

                                return;
                            }
                            onRibbonChange?.(id);
                        }}
                    >
                        {label}
                    </button>
                );
            })}
        </nav>
    );
}
