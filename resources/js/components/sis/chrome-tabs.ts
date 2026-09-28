/**
 * SSOT — titlebar chrome tabs (tab strip on every page).
 * Order here = visual RTL order in TitleBarMenu.
 * Labels: i18n `chrome.tabs` only — do not hardcode Arabic elsewhere.
 */
export const CHROME_TAB_IDS = [
    'file',
    'home',
    'edit',
    'add',
    'settings',
    'lists',
    'tools',
    'reports',
    'help',
] as const;

export type ChromeTabId = (typeof CHROME_TAB_IDS)[number];

/** Alias used by ribbon chrome / page registrations. */
export type RibbonTab = ChromeTabId;
export type PageRibbonTab = ChromeTabId;

export type ChromeTabLabels = Record<ChromeTabId, string>;

export type ChromeTabItem = {
    id: ChromeTabId;
    label: string;
};

/** Build ordered tab strip from i18n labels (SSOT for menu rendering). */
export function chromeTabItems(labels: ChromeTabLabels): ChromeTabItem[] {
    return CHROME_TAB_IDS.map((id) => ({
        id,
        label: labels[id],
    }));
}

export function isChromeTabId(value: string | null | undefined): value is ChromeTabId {
    return (
        typeof value === 'string'
        && (CHROME_TAB_IDS as readonly string[]).includes(value)
    );
}
