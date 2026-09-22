import { Link } from '@inertiajs/react';
import {
    AlertCircle,
    CheckCircle2,
    Info,
    TriangleAlert,
} from 'lucide-react';
import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

export type WorkflowNoticeTone = 'info' | 'success' | 'warning' | 'error';

export type WorkflowNoticeData = {
    tone: WorkflowNoticeTone;
    title: string;
    message: string;
    action_href?: string | null;
    action_label?: string | null;
    step?: string | null;
};

const TONE_ICON: Record<WorkflowNoticeTone, typeof Info> = {
    info: Info,
    success: CheckCircle2,
    warning: TriangleAlert,
    error: AlertCircle,
};

type Props = {
    notice: WorkflowNoticeData;
    className?: string;
    onDismiss?: () => void;
    dismissLabel?: string;
    children?: ReactNode;
};

export function SisWorkflowNotice({
    notice,
    className,
    onDismiss,
    dismissLabel = 'إخفاء',
    children,
}: Props) {
    const Icon = TONE_ICON[notice.tone] ?? Info;
    const href = notice.action_href?.trim() || null;
    const actionLabel = notice.action_label?.trim() || null;

    return (
        <aside
            className={cn(
                'sis-workflow-notice',
                `sis-workflow-notice--${notice.tone}`,
                className,
            )}
            dir="rtl"
            lang="ar"
            role="status"
            aria-live="polite"
            data-workflow-step={notice.step ?? undefined}
        >
            <div className="sis-workflow-notice__icon" aria-hidden>
                <Icon className="size-5" />
            </div>
            <div className="sis-workflow-notice__body">
                <p className="sis-workflow-notice__title">{notice.title}</p>
                <p className="sis-workflow-notice__message">{notice.message}</p>
                {children}
                {(href && actionLabel) || onDismiss ? (
                    <div className="sis-workflow-notice__actions">
                        {href && actionLabel ? (
                            <Link
                                href={href}
                                className="sis-ops-hub__link sis-workflow-notice__action"
                                prefetch
                            >
                                {actionLabel}
                            </Link>
                        ) : null}
                        {onDismiss ? (
                            <button
                                type="button"
                                className="sis-workflow-notice__dismiss"
                                onClick={onDismiss}
                            >
                                {dismissLabel}
                            </button>
                        ) : null}
                    </div>
                ) : null}
            </div>
        </aside>
    );
}
