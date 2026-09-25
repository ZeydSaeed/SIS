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
import {
    MessageDialog,
    type MessageDialogTone,
} from '@/components/sis/message-dialog';
import {
    resolveErrorMessage,
    summarizeInertiaErrors,
    type InertiaErrorBag,
} from '@/lib/format-inertia-errors';
import { t } from '@/i18n';

type ShowMessageInput = {
    title?: string;
    description?: string;
    details?: string[];
    tone?: MessageDialogTone;
};

type PageErrorContextValue = {
    /** App-wide message dialog (error / warning / success / info). */
    showMessage: (input: string | ShowMessageInput) => void;
    showError: (input: string | ShowMessageInput) => void;
    showWarning: (input: string | ShowMessageInput) => void;
    showSuccess: (input: string | ShowMessageInput) => void;
    showInfo: (input: string | ShowMessageInput) => void;
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
    const page = usePage() as { props: FlashProps; url?: string; component?: string };
    const [open, setOpen] = useState(false);
    const [tone, setTone] = useState<MessageDialogTone>('error');
    const [title, setTitle] = useState<string | undefined>(undefined);
    const [description, setDescription] = useState('');
    const [details, setDetails] = useState<string[]>([]);
    const lastFlashRef = useRef<string | null>(null);

    const isAdmissionPage =
        (typeof page.url === 'string' && page.url.startsWith('/admission'))
        || (typeof page.component === 'string' && page.component.startsWith('admission/'));

    const openMessage = useCallback(
        (input: string | ShowMessageInput, defaultTone: MessageDialogTone) => {
            // Admission: only error dialogs — suppress success / info / warning chrome.
            if (isAdmissionPage && defaultTone !== 'error') {
                return;
            }

            if (typeof input === 'string') {
                setTone(defaultTone);
                setTitle(undefined);
                setDescription(resolveErrorMessage(input));
                setDetails([]);
                setOpen(true);

                return;
            }

            const nextTone = input.tone ?? defaultTone;
            if (isAdmissionPage && nextTone !== 'error') {
                return;
            }

            setTone(nextTone);
            setTitle(input.title);
            setDescription(resolveErrorMessage(input.description, i18n.errors.generic));
            setDetails(input.details ?? []);
            setOpen(true);
        },
        [i18n.errors.generic, isAdmissionPage],
    );

    const showMessage = useCallback(
        (input: string | ShowMessageInput) => {
            openMessage(input, typeof input === 'string' ? 'info' : (input.tone ?? 'info'));
        },
        [openMessage],
    );

    const showError = useCallback(
        (input: string | ShowMessageInput) => openMessage(input, 'error'),
        [openMessage],
    );

    const showWarning = useCallback(
        (input: string | ShowMessageInput) => openMessage(input, 'warning'),
        [openMessage],
    );

    const showSuccess = useCallback(
        (input: string | ShowMessageInput) => openMessage(input, 'success'),
        [openMessage],
    );

    const showInfo = useCallback(
        (input: string | ShowMessageInput) => openMessage(input, 'info'),
        [openMessage],
    );

    const showInertiaErrors = useCallback(
        (errors: InertiaErrorBag | undefined | null, fallback?: string) => {
            const summary = summarizeInertiaErrors(errors, fallback);
            setTone('error');
            setTitle(undefined);
            setDescription(summary.description);
            setDetails(summary.details);
            setOpen(true);
        },
        [],
    );

    useEffect(() => {
        const flashError = page.props.flash?.error;
        const flashSuccess = page.props.flash?.success;
        const flashKey =
            typeof flashError === 'string' && flashError.trim() !== ''
                ? `error:${flashError}`
                : typeof flashSuccess === 'string' && flashSuccess.trim() !== ''
                  ? `success:${flashSuccess}`
                  : null;

        if (flashKey === null) {
            lastFlashRef.current = null;

            return;
        }

        if (lastFlashRef.current === flashKey) {
            return;
        }

        lastFlashRef.current = flashKey;

        if (typeof flashError === 'string' && flashError.trim() !== '') {
            showError({
                description: resolveErrorMessage(flashError),
            });

            return;
        }

        // Admission: never surface success flash dialogs.
        if (isAdmissionPage) {
            return;
        }

        if (typeof flashSuccess === 'string' && flashSuccess.trim() !== '') {
            showSuccess({
                description: resolveErrorMessage(flashSuccess),
            });
        }
    }, [
        isAdmissionPage,
        page.props.flash?.error,
        page.props.flash?.success,
        showError,
        showSuccess,
    ]);

    const value = useMemo(
        () => ({
            showMessage,
            showError,
            showWarning,
            showSuccess,
            showInfo,
            showInertiaErrors,
        }),
        [showError, showInfo, showInertiaErrors, showMessage, showSuccess, showWarning],
    );

    return (
        <PageErrorContext.Provider value={value}>
            {children}
            <MessageDialog
                open={open}
                tone={tone}
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

/** Alias — preferred name for app-wide notices. */
export const usePageMessage = usePageError;
export const PageMessageProvider = PageErrorProvider;
