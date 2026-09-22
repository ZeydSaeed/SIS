import {
    createContext,
    useContext,
    useId,
    useLayoutEffect,
    useMemo,
    useState,
    type ReactNode,
} from 'react';

export type PageTitlebarSearchConfig = {
    committedQuery: string;
    label: string;
    placeholder: string;
    onCommit: (query: string) => void;
    onDraftChange?: (query: string) => void;
};

type PageTitlebarSearchApi = {
    search: PageTitlebarSearchConfig | null;
    setOwner: (ownerId: string, next: PageTitlebarSearchConfig | null) => void;
};

type Owners = Record<string, PageTitlebarSearchConfig>;

const PageTitlebarSearchContext = createContext<PageTitlebarSearchApi | null>(null);

export function PageTitlebarSearchProvider({ children }: { children: ReactNode }) {
    const [owners, setOwners] = useState<Owners>({});

    const setOwner = useMemo(
        () => (ownerId: string, next: PageTitlebarSearchConfig | null) => {
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
        },
        [],
    );

    const search = useMemo(() => {
        const values = Object.values(owners);

        return values.length > 0 ? values[values.length - 1] : null;
    }, [owners]);

    const value = useMemo(
        (): PageTitlebarSearchApi => ({ search, setOwner }),
        [search, setOwner],
    );

    return (
        <PageTitlebarSearchContext.Provider value={value}>
            {children}
        </PageTitlebarSearchContext.Provider>
    );
}

export function usePageTitlebarSearch(): PageTitlebarSearchConfig | null {
    return useContext(PageTitlebarSearchContext)?.search ?? null;
}

export function useRegisterPageTitlebarSearch(
    config: PageTitlebarSearchConfig | null,
): void {
    const ownerId = useId();
    const setOwner = useContext(PageTitlebarSearchContext)?.setOwner;

    useLayoutEffect(() => {
        if (!setOwner) {
            return;
        }

        if (config === null) {
            setOwner(ownerId, null);

            return () => setOwner(ownerId, null);
        }

        setOwner(ownerId, config);

        return () => setOwner(ownerId, null);
    }, [
        config,
        config?.committedQuery,
        config?.label,
        config?.onCommit,
        config?.onDraftChange,
        config?.placeholder,
        ownerId,
        setOwner,
    ]);
}
