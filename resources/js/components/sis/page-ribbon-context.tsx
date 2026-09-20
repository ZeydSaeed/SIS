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
    onSelect: () => void;
};

export type PageRibbonGroup = {
    id: string;
    label: string;
    commands: PageRibbonCommand[];
};

export type PageRibbonRegistration = {
    tab: PageRibbonTab;
    groups: PageRibbonGroup[];
};

type PageRibbonOwners = Record<string, PageRibbonRegistration>;

type PageRibbonApi = {
    groupsByTab: Partial<Record<PageRibbonTab, PageRibbonGroup[]>>;
    setOwner: (ownerId: string, next: PageRibbonRegistration | null) => void;
};

const COMMAND_PRIORITY = ['view', 'edit', 'save', 'cancel', 'delete'] as const;

const PageRibbonContext = createContext<PageRibbonApi | null>(null);

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
        merged[existingIndex] = { ...existing, commands };
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
    const value = useMemo(
        (): PageRibbonApi => ({ groupsByTab, setOwner }),
        [groupsByTab, setOwner],
    );

    return <PageRibbonContext.Provider value={value}>{children}</PageRibbonContext.Provider>;
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
