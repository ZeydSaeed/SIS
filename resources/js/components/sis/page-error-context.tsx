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
import { router, usePage } from '@inertiajs/react';
import {
    MessageDialog,
    type MessageDialogAction,
    type MessageDialogTone,
} from '@/components/sis/message-dialog';
import { resolveUiMessage } from '@/lib/resolve-ui-message';
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
    action?: MessageDialogAction;
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
        /** Optional next step for flash.success: i18n label key + in-app href. */
        successAction?: { label: string; href: string } | null;
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
    const [action, setAction] = useState<MessageDialogAction | undefined>(undefined);
    const lastFlashRef = useRef<string | null>(null);

    const openMessage = useCallback(
        (input: string | ShowMessageInput, defaultTone: MessageDialogTone) => {
            if (typeof input === 'string') {
                setTone(defaultTone);
                setTitle(undefined);
                setDescription(resolveErrorMessage(input));
                setDetails([]);
                setAction(undefined);
                setOpen(true);

                return;
            }

            setTone(input.tone ?? defaultTone);
            setTitle(input.title);
            setDescription(resolveErrorMessage(input.description, i18n.errors.generic));
            setDetails(input.details ?? []);
            setAction(input.action);
            setOpen(true);
        },
        [i18n.errors.generic],
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

        if (typeof flashSuccess === 'string' && flashSuccess.trim() !== '') {
            const successAction = page.props.flash?.successAction;
            showSuccess({
                description: resolveErrorMessage(flashSuccess),
                action:
                    successAction && successAction.href.startsWith('/')
                        ? {
                              label: resolveUiMessage(successAction.label),
                              onSelect: () => router.visit(successAction.href),
                          }
                        : undefined,
            });
        }
    }, [
        page.props.flash?.error,
        page.props.flash?.success,
        page.props.flash?.successAction,
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
                action={action}
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
