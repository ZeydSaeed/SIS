import {
    createContext,
    useContext,
    useLayoutEffect,
    useMemo,
    useState,
    type ReactNode,
} from 'react';

export type PageTitlebarHome = {
    href: string;
    ariaLabel: string;
};

type PageTitlebarHomeApi = {
    home: PageTitlebarHome | null;
    setHome: (next: PageTitlebarHome | null) => void;
};

const PageTitlebarHomeContext = createContext<PageTitlebarHomeApi | null>(null);

export function PageTitlebarHomeProvider({ children }: { children: ReactNode }) {
    const [home, setHome] = useState<PageTitlebarHome | null>(null);
    const value = useMemo(() => ({ home, setHome }), [home]);

    return (
        <PageTitlebarHomeContext.Provider value={value}>
            {children}
        </PageTitlebarHomeContext.Provider>
    );
}

export function usePageTitlebarHome(): PageTitlebarHome | null {
    return useContext(PageTitlebarHomeContext)?.home ?? null;
}

export function useRegisterPageTitlebarHome(home: PageTitlebarHome | null): void {
    const setHome = useContext(PageTitlebarHomeContext)?.setHome;
    const href = home?.href ?? '';
    const ariaLabel = home?.ariaLabel ?? '';

    useLayoutEffect(() => {
        if (!setHome) {
            return;
        }

        if (href === '') {
            setHome(null);

            return () => setHome(null);
        }

        setHome({ href, ariaLabel });

        return () => setHome(null);
    }, [ariaLabel, href, setHome]);
}
