import {
    createContext,
    useContext,
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

export type PageRibbonTone = 'edit' | 'save' | 'delete';

export type PageRibbonCommand = {
    id: string;
    label: string;
    icon: LucideIcon;
    disabled?: boolean;
    tone?: PageRibbonTone;
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

type PageRibbonApi = {
    registration: PageRibbonRegistration | null;
    setRegistration: (next: PageRibbonRegistration | null) => void;
};

const PageRibbonContext = createContext<PageRibbonApi | null>(null);

export function PageRibbonProvider({ children }: { children: ReactNode }) {
    const [registration, setRegistration] = useState<PageRibbonRegistration | null>(null);
    const value = useMemo(
        () => ({ registration, setRegistration }),
        [registration],
    );

    return <PageRibbonContext.Provider value={value}>{children}</PageRibbonContext.Provider>;
}

export function usePageRibbonRegistration(): PageRibbonRegistration | null {
    return useContext(PageRibbonContext)?.registration ?? null;
}

export function useRegisterPageRibbon(tab: PageRibbonTab, groups: PageRibbonGroup[]): void {
    const setRegistration = useContext(PageRibbonContext)?.setRegistration;

    useLayoutEffect(() => {
        if (!setRegistration) {
            return;
        }

        if (groups.length === 0) {
            setRegistration(null);

            return () => setRegistration(null);
        }

        setRegistration({ tab, groups });

        return () => setRegistration(null);
    }, [tab, groups, setRegistration]);
}
