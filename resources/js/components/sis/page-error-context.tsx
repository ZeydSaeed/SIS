import {
    createContext,
    useCallback,
    useContext,
    useEffect,
    useMemo,
    useRef,
    useState,
    type ReactNode,
} from 'react';
import { usePage } from '@inertiajs/react';
import { ErrorDialog } from '@/components/sis/error-dialog';
import {
    resolveErrorMessage,
    summarizeInertiaErrors,
    type InertiaErrorBag,
} from '@/lib/format-inertia-errors';
import { t } from '@/i18n';

type ShowErrorInput = {
    title?: string;
    description?: string;
    details?: string[];
};

type PageErrorContextValue = {
    showError: (input: string | ShowErrorInput) => void;
    showInertiaErrors: (errors: InertiaErrorBag | undefined | null, fallback?: string) => void;
};

const PageErrorContext = createContext<PageErrorContextValue | null>(null);

type FlashProps = {
    flash?: {
        error?: string | null;
        success?: string | null;
    };
};

export function PageErrorProvider({ children }: { children: ReactNode }) {
    const i18n = t();
    const page = usePage() as { props: FlashProps };
    const [open, setOpen] = useState(false);
    const [title, setTitle] = useState<string | undefined>(undefined);
    const [description, setDescription] = useState('');
    const [details, setDetails] = useState<string[]>([]);
    const lastFlashRef = useRef<string | null>(null);

    const showError = useCallback((input: string | ShowErrorInput) => {
        if (typeof input === 'string') {
            setTitle(undefined);
            setDescription(resolveErrorMessage(input));
            setDetails([]);
            setOpen(true);
            return;
        }

        setTitle(input.title);
        setDescription(resolveErrorMessage(input.description, i18n.errors.generic));
        setDetails(input.details ?? []);
        setOpen(true);
    }, [i18n.errors.generic]);

    const showInertiaErrors = useCallback(
        (errors: InertiaErrorBag | undefined | null, fallback?: string) => {
            const summary = summarizeInertiaErrors(errors, fallback);
            setTitle(undefined);
            setDescription(summary.description);
            setDetails(summary.details);
            setOpen(true);
        },
        [],
    );

    useEffect(() => {
        const flashError = page.props.flash?.error;
        if (typeof flashError !== 'string' || flashError.trim() === '') {
            lastFlashRef.current = null;
            return;
        }

        if (lastFlashRef.current === flashError) {
            return;
        }

        lastFlashRef.current = flashError;
        showError({
            description: resolveErrorMessage(flashError),
        });
    }, [page.props.flash?.error, showError]);

    const value = useMemo(
        () => ({
            showError,
            showInertiaErrors,
        }),
        [showError, showInertiaErrors],
    );

    return (
        <PageErrorContext.Provider value={value}>
            {children}
            <ErrorDialog
                open={open}
                title={title}
                description={description}
                details={details}
                onOpenChange={setOpen}
            />
        </PageErrorContext.Provider>
    );
}

export function usePageError(): PageErrorContextValue {
    const context = useContext(PageErrorContext);
    if (context === null) {
        throw new Error('usePageError must be used within PageErrorProvider');
    }

    return context;
}
