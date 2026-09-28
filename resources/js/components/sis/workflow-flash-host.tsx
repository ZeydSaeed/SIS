import { usePage } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';
import {
    SisWorkflowNotice,
    type WorkflowNoticeData,
} from '@/components/sis/sis-workflow-notice';
import { t } from '@/i18n';
import { resolveUiMessage, resolveWorkflowCopy } from '@/lib/resolve-ui-message';
import type { FlashToast } from '@/types/ui';

type FlashProps = {
    flash?: {
        workflow?: WorkflowNoticeData | null;
        toast?: FlashToast | null;
        success?: string | null;
        error?: string | null;
    };
};

function isWorkflowNotice(value: unknown): value is WorkflowNoticeData {
    if (value === null || typeof value !== 'object') {
        return false;
    }

    const record = value as Record<string, unknown>;
    const hasStep = typeof record.step === 'string' && record.step.trim() !== '';
    const hasCopy =
        typeof record.title === 'string'
        && typeof record.message === 'string'
        && record.title.trim() !== ''
        && record.message.trim() !== '';

    return (
        (hasStep || hasCopy)
        && (record.tone === 'info'
            || record.tone === 'success'
            || record.tone === 'warning'
            || record.tone === 'error')
    );
}

function resolveNotice(workflow: WorkflowNoticeData): WorkflowNoticeData {
    const fromStep = resolveWorkflowCopy(workflow.step);
    if (fromStep !== null) {
        return {
            ...workflow,
            title: fromStep.title,
            message: fromStep.message,
            action_label: workflow.action_href
                ? (fromStep.action_label ?? resolveUiMessage(workflow.action_label ?? '', fromStep.action_label))
                : workflow.action_label,
        };
    }

    return {
        ...workflow,
        title: resolveUiMessage(workflow.title, workflow.title),
        message: resolveUiMessage(workflow.message, workflow.message),
        action_label: workflow.action_label
            ? resolveUiMessage(workflow.action_label, workflow.action_label)
            : workflow.action_label,
    };
}

/**
 * Renders shared flash.workflow banners inside AppLayout (Inertia page tree).
 * Also surfaces flash.toast once per navigation for ops pages.
 * Copy is resolved from resources/js/i18n/ar.ts (Arabic SSOT).
 */
export function WorkflowFlashHost() {
    const i18n = t();
    const page = usePage() as { props: FlashProps; url: string };
    const [notice, setNotice] = useState<WorkflowNoticeData | null>(null);
    const lastWorkflowKeyRef = useRef<string | null>(null);
    const lastToastKeyRef = useRef<string | null>(null);

    useEffect(() => {
        const toastData = page.props.flash?.toast;
        if (toastData && typeof toastData.message === 'string' && toastData.message.trim() !== '') {
            const toastKey = `toast:${toastData.type}:${toastData.message}`;
            if (lastToastKeyRef.current !== toastKey) {
                lastToastKeyRef.current = toastKey;
                const type = toastData.type;
                const onAdmission = page.url.startsWith('/admission');
                // Admission: only error toasts.
                if (onAdmission && type !== 'error') {
                    /* skip */
                } else if (type === 'success' || type === 'info' || type === 'warning' || type === 'error') {
                    const fromStep = resolveWorkflowCopy(toastData.message);
                    const message = fromStep
                        ? `${fromStep.title} — ${fromStep.message}`
                        : resolveUiMessage(toastData.message);
                    toast[type](message);
                }
            }
        }

        const workflow = page.props.flash?.workflow;
        if (!isWorkflowNotice(workflow)) {
            return;
        }

        // Admission pages: no workflow banner notices.
        if (page.url.startsWith('/admission')) {
            return;
        }

        const key = `workflow:${workflow.step ?? ''}:${workflow.title}:${workflow.message}:${page.url}`;
        if (lastWorkflowKeyRef.current === key) {
            return;
        }

        lastWorkflowKeyRef.current = key;
        setNotice(resolveNotice(workflow));
    }, [page.props.flash?.workflow, page.props.flash?.toast, page.url]);

    if (notice === null) {
        return null;
    }

    return (
        <div className="sis-workflow-flash-host px-4 pt-3" dir="rtl" lang="ar">
            <SisWorkflowNotice
                notice={notice}
                dismissLabel={i18n.workflow.dismiss}
                onDismiss={() => setNotice(null)}
            />
        </div>
    );
}
