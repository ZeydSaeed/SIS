import {
    createContext,
    useCallback,
    useContext,
    useLayoutEffect,
    useMemo,
    useRef,
    useState,
    type ReactNode,
} from 'react';

/** A page's own show/hide switch, shown in the title bar next to the close (X) button (e.g. the section rail). */
export type PageTitlebarToggle = {
    /** True while the thing it controls is shown. */
    pressed: boolean;
    label: string;
    onToggle: () => void;
};

type PageTitlebarToggleApi = {
    toggle: PageTitlebarToggle | null;
    setToggle: (next: PageTitlebarToggle | null) => void;
};

const PageTitlebarToggleContext = createContext<PageTitlebarToggleApi | null>(
    null,
);

export function PageTitlebarToggleProvider({
    children,
}: {
    children: ReactNode;
}) {
    const [toggle, setToggle] = useState<PageTitlebarToggle | null>(null);
    const value = useMemo(() => ({ toggle, setToggle }), [toggle]);

    return (
        <PageTitlebarToggleContext.Provider value={value}>
            {children}
        </PageTitlebarToggleContext.Provider>
    );
}

export function usePageTitlebarToggle(): PageTitlebarToggle | null {
    return useContext(PageTitlebarToggleContext)?.toggle ?? null;
}

/** Registers the page's switch while the page is mounted (`null` = none). The latest `onToggle` is always the one called. */
export function useRegisterPageTitlebarToggle(
    toggle: PageTitlebarToggle | null,
): void {
    const setToggle = useContext(PageTitlebarToggleContext)?.setToggle;
    const onToggleRef = useRef(toggle?.onToggle);
    onToggleRef.current = toggle?.onToggle;
    const stable = useCallback(() => onToggleRef.current?.(), []);
    const present = toggle !== null;
    const pressed = toggle?.pressed ?? false;
    const label = toggle?.label ?? '';

    useLayoutEffect(() => {
        if (!setToggle) {
            return;
        }
        if (!present) {
            setToggle(null);

            return;
        }
        setToggle({ pressed, label, onToggle: stable });

        return () => setToggle(null);
    }, [label, present, pressed, setToggle, stable]);
}
