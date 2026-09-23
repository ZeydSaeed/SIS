import { usePage } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';
import {
    SisWorkflowNotice,
    type WorkflowNoticeData,
} from '@/components/sis/sis-workflow-notice';
import { t } from '@/i18n';
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

    return (
        typeof record.title === 'string'
        && typeof record.message === 'string'
        && (record.tone === 'info'
            || record.tone === 'success'
            || record.tone === 'warning'
            || record.tone === 'error')
    );
}

/**
 * Renders shared flash.workflow banners inside AppLayout (Inertia page tree).
 * Also surfaces flash.toast once per navigation for ops pages.
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
                if (type === 'success' || type === 'info' || type === 'warning' || type === 'error') {
                    toast[type](toastData.message);
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
        setNotice(workflow);
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
