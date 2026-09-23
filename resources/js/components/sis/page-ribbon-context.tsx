import {
    createContext,
    useCallback,
    useContext,
    useId,
    useLayoutEffect,
    useMemo,
    useState,
    type ReactNode,
} from 'react';
import type { LucideIcon } from 'lucide-react';

export type PageRibbonTab =
    | 'file'
    | 'home'
    | 'edit'
    | 'add'
    | 'settings'
    | 'lists'
    | 'tools'
    | 'reports'
    | 'help';

export type PageRibbonCommand = {
    id: string;
    label: string;
    icon: LucideIcon;
    disabled?: boolean;
    pressed?: boolean;
    title?: string;
    /** Optional count shown above the icon (e.g. status tallies). */
    count?: number;
    tone?: 'edit' | 'save' | 'delete';
    onSelect: () => void;
};

export type PageRibbonGroup = {
    id: string;
    label: string;
    commands: PageRibbonCommand[];
    custom?: ReactNode;
};

export type PageRibbonRegistration = {
    tab: PageRibbonTab;
    groups: PageRibbonGroup[];
};

export type SetPageRibbonTabOptions = {
    /** Close even when the ribbon is pinned (also clears pin). */
    force?: boolean;
};

type PageRibbonOwners = Record<string, PageRibbonRegistration>;

type PageRibbonApi = {
    groupsByTab: Partial<Record<PageRibbonTab, PageRibbonGroup[]>>;
    activeTab: PageRibbonTab | null;
    pinned: boolean;
    setActiveTab: (tab: PageRibbonTab | null, options?: SetPageRibbonTabOptions) => void;
    setPinned: (pinned: boolean) => void;
    setOwner: (ownerId: string, next: PageRibbonRegistration | null) => void;
};

const COMMAND_PRIORITY = ['view', 'edit', 'save', 'cancel', 'delete'] as const;
const RIBBON_PIN_STORAGE_KEY = 'sis.ribbon.pinned';

const PageRibbonContext = createContext<PageRibbonApi | null>(null);

function readPinnedPreference(): boolean {
    try {
        return window.sessionStorage.getItem(RIBBON_PIN_STORAGE_KEY) === '1';
    } catch {
        return false;
    }
}

function writePinnedPreference(pinned: boolean): void {
    try {
        if (pinned) {
            window.sessionStorage.setItem(RIBBON_PIN_STORAGE_KEY, '1');
        } else {
            window.sessionStorage.removeItem(RIBBON_PIN_STORAGE_KEY);
        }
    } catch {
        // Ignore storage failures (private mode / quota).
    }
}

function commandPriority(id: string): number {
    const index = COMMAND_PRIORITY.findIndex((token) => id.includes(token));

    return index === -1 ? COMMAND_PRIORITY.length : index;
}

function mergeGroups(groups: PageRibbonGroup[]): PageRibbonGroup[] {
    const merged: PageRibbonGroup[] = [];
    const indexByLabel = new Map<string, number>();

    for (const group of groups) {
        const existingIndex = indexByLabel.get(group.label);

        if (existingIndex === undefined) {
            indexByLabel.set(group.label, merged.length);
            merged.push({
                ...group,
                commands: [...group.commands],
                custom: group.custom,
            });
            continue;
        }

        const existing = merged[existingIndex];
        const commands = [...existing.commands];

        for (const command of group.commands) {
            const commandIndex = commands.findIndex((item) => item.id === command.id);

            if (commandIndex >= 0) {
                commands[commandIndex] = command;
            } else {
                commands.push(command);
            }
        }

        commands.sort(
            (left, right) => commandPriority(left.id) - commandPriority(right.id),
        );
        merged[existingIndex] = {
            ...existing,
            commands,
            custom: group.custom ?? existing.custom,
        };
    }

    return merged;
}

function groupsByTabFromOwners(
    owners: PageRibbonOwners,
): Partial<Record<PageRibbonTab, PageRibbonGroup[]>> {
    const grouped: Partial<Record<PageRibbonTab, PageRibbonGroup[]>> = {};

    for (const registration of Object.values(owners)) {
        const current = grouped[registration.tab] ?? [];
        grouped[registration.tab] = current.concat(registration.groups);
    }

    const result: Partial<Record<PageRibbonTab, PageRibbonGroup[]>> = {};

    for (const [tab, groups] of Object.entries(grouped) as Array<
        [PageRibbonTab, PageRibbonGroup[]]
    >) {
        result[tab] = mergeGroups(groups);
    }

    return result;
}

export function PageRibbonProvider({ children }: { children: ReactNode }) {
    const [owners, setOwners] = useState<PageRibbonOwners>({});
    const [activeTab, setActiveTabState] = useState<PageRibbonTab | null>(null);
    const [pinned, setPinnedState] = useState<boolean>(() =>
        typeof window === 'undefined' ? false : readPinnedPreference(),
    );
    const groupsByTab = useMemo(() => groupsByTabFromOwners(owners), [owners]);
    const setOwner = useCallback((ownerId: string, next: PageRibbonRegistration | null) => {
        setOwners((current) => {
            if (next === null) {
                if (!(ownerId in current)) {
                    return current;
                }

                const { [ownerId]: _removed, ...rest } = current;

                return rest;
            }

            return { ...current, [ownerId]: next };
        });
    }, []);
    const setPinned = useCallback((next: boolean) => {
        setPinnedState(next);
        writePinnedPreference(next);
    }, []);
    const setActiveTab = useCallback(
        (tab: PageRibbonTab | null, options?: SetPageRibbonTabOptions) => {
            if (tab === null) {
                if (pinned && !options?.force) {
                    return;
                }

                if (pinned) {
                    setPinned(false);
                }
            }

            setActiveTabState(tab);
        },
        [pinned, setPinned],
    );
    const value = useMemo(
        (): PageRibbonApi => ({
            groupsByTab,
            activeTab,
            pinned,
            setActiveTab,
            setPinned,
            setOwner,
        }),
        [groupsByTab, activeTab, pinned, setActiveTab, setPinned, setOwner],
    );

    return <PageRibbonContext.Provider value={value}>{children}</PageRibbonContext.Provider>;
}

/** Active title-bar ribbon tab (null when ribbon collapsed). */
export function useActivePageRibbonTab(): PageRibbonTab | null {
    return useContext(PageRibbonContext)?.activeTab ?? null;
}

export function usePageRibbonPinned(): boolean {
    return useContext(PageRibbonContext)?.pinned ?? false;
}

export function useSetPageRibbonPinned(): (pinned: boolean) => void {
    const setPinned = useContext(PageRibbonContext)?.setPinned;

    return useCallback(
        (next: boolean) => {
            setPinned?.(next);
        },
        [setPinned],
    );
}

export function useSetActivePageRibbonTab(): (
    tab: PageRibbonTab | null,
    options?: SetPageRibbonTabOptions,
) => void {
    const setActiveTab = useContext(PageRibbonContext)?.setActiveTab;

    return useCallback(
        (tab: PageRibbonTab | null, options?: SetPageRibbonTabOptions) => {
            setActiveTab?.(tab, options);
        },
        [setActiveTab],
    );
}

export function usePageRibbonGroups(tab: PageRibbonTab): PageRibbonGroup[] {
    return useContext(PageRibbonContext)?.groupsByTab[tab] ?? [];
}

export function usePageRibbonRegistration(): PageRibbonRegistration | null {
    const groupsByTab = useContext(PageRibbonContext)?.groupsByTab;

    if (!groupsByTab) {
        return null;
    }

    const home = groupsByTab.home;

    if (home && home.length > 0) {
        return { tab: 'home', groups: home };
    }

    for (const [tab, groups] of Object.entries(groupsByTab) as Array<
        [PageRibbonTab, PageRibbonGroup[]]
    >) {
        if (groups.length > 0) {
            return { tab, groups };
        }
    }

    return null;
}

export function useRegisterPageRibbon(tab: PageRibbonTab, groups: PageRibbonGroup[]): void {
    const ownerId = useId();
    const setOwner = useContext(PageRibbonContext)?.setOwner;

    useLayoutEffect(() => {
        if (!setOwner) {
            return;
        }

        if (groups.length === 0) {
            setOwner(ownerId, null);

            return () => setOwner(ownerId, null);
        }

        setOwner(ownerId, { tab, groups });

        return () => setOwner(ownerId, null);
    }, [tab, groups, ownerId, setOwner]);
}
