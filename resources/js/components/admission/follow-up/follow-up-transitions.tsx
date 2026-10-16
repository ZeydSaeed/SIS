import { router } from '@inertiajs/react';
import {
    CheckCircle2,
    Hourglass,
    LogOut,
    MessagesSquare,
    SearchCheck,
    XOctagon,
    type LucideIcon,
} from 'lucide-react';
import { useCallback, useState, type ReactNode } from 'react';
import { AdmissionStatusReasonDialog } from '@/components/admission/admission-status-reason-dialog';
import {
    ADMISSION_STATUS_ACCEPTED,
    ADMISSION_STATUS_INTERVIEW,
    ADMISSION_STATUS_REJECTED,
    ADMISSION_STATUS_UNDER_REVIEW,
    ADMISSION_STATUS_WAITLISTED,
    ADMISSION_STATUS_WITHDRAWN,
} from '@/components/admission/admission-workspace';
import { ConfirmDialog } from '@/components/sis/confirm-dialog';
import { usePageError } from '@/components/sis/page-error-context';
import { t } from '@/i18n';

/** Status label of an application (admission.applications.status). */
export function applicationStatusLabel(status: number): string {
    const a = t().admission;
    const labels: Record<number, string> = {
        1: a.statusDraft,
        2: a.statusSubmitted,
        3: a.statusUnderReview,
        4: a.statusInterview,
        5: a.statusWaitlisted,
        6: a.statusAccepted,
        7: a.statusRejected,
        8: a.statusWithdrawn,
        9: a.statusConverted,
    };

    return labels[status] ?? String(status);
}

/** The manual targets offered by the follow-up pages; the server decides which are allowed for each status. */
export const FOLLOW_UP_TRANSITIONS: Array<{
    status: number;
    icon: LucideIcon;
    tone?: 'save' | 'delete';
}> = [
    { status: ADMISSION_STATUS_UNDER_REVIEW, icon: SearchCheck },
    { status: ADMISSION_STATUS_INTERVIEW, icon: MessagesSquare },
    { status: ADMISSION_STATUS_WAITLISTED, icon: Hourglass },
    { status: ADMISSION_STATUS_ACCEPTED, icon: CheckCircle2, tone: 'save' },
    { status: ADMISSION_STATUS_REJECTED, icon: XOctagon, tone: 'delete' },
    { status: ADMISSION_STATUS_WITHDRAWN, icon: LogOut, tone: 'delete' },
];

type Pending = { ids: number[]; schoolId: number; toStatus: number };

/**
 * Moves applications of one school to a status through the existing bulk transition (the same command as the
 * admission page: accepting also converts to a student). Rejecting / withdrawing asks for the reason first,
 * every other move asks for confirmation.
 */
export function useFollowUpTransitions(onDone: () => void): {
    request: (ids: number[], schoolId: number, toStatus: number) => void;
    busy: boolean;
    dialogs: ReactNode;
} {
    const i18n = t();
    const a = i18n.admission;
    const { showInertiaErrors } = usePageError();
    const [pending, setPending] = useState<Pending | null>(null);
    const [busy, setBusy] = useState(false);

    const send = useCallback(
        (move: Pending, notes: string | null) => {
            setBusy(true);
            router.post(
                '/admission/applications/bulk-transition',
                { application_ids: move.ids, to_status: move.toStatus, notes },
                {
                    headers: {
                        'X-School-Id': String(move.schoolId),
                        'X-Idempotency-Key': `follow-up-${move.toStatus}-${move.ids.join('-')}-${Date.now()}`,
                    },
                    preserveScroll: true,
                    preserveState: true,
                    onSuccess: () => {
                        setPending(null);
                        onDone();
                    },
                    onError: (errors) =>
                        showInertiaErrors(errors, i18n.errors.transitionFailed),
                    onFinish: () => setBusy(false),
                },
            );
        },
        [i18n.errors.transitionFailed, onDone, showInertiaErrors],
    );

    const request = useCallback(
        (ids: number[], schoolId: number, toStatus: number) => {
            if (ids.length > 0) {
                setPending({ ids, schoolId, toStatus });
            }
        },
        [],
    );

    const needsReason =
        pending !== null &&
        (pending.toStatus === ADMISSION_STATUS_REJECTED ||
            pending.toStatus === ADMISSION_STATUS_WITHDRAWN);
    const body =
        pending === null
            ? ''
            : (pending.toStatus === ADMISSION_STATUS_ACCEPTED
                  ? a.followUpAcceptBody
                  : a.followUpConfirmBody
              )
                  .replace(':count', String(pending.ids.length))
                  .replace(':status', applicationStatusLabel(pending.toStatus));

    const dialogs = (
        <>
            <AdmissionStatusReasonDialog
                open={needsReason}
                kind={
                    pending?.toStatus === ADMISSION_STATUS_WITHDRAWN
                        ? 'withdraw'
                        : 'reject'
                }
                busy={busy}
                onOpenChange={(open) => {
                    if (!open && !busy) {
                        setPending(null);
                    }
                }}
                onConfirm={(reason) => pending && send(pending, reason)}
            />
            <ConfirmDialog
                open={pending !== null && !needsReason}
                title={a.followUpConfirmTitle}
                description={body}
                confirmLabel={
                    pending
                        ? applicationStatusLabel(pending.toStatus)
                        : undefined
                }
                confirmPending={busy}
                onConfirm={() => pending && send(pending, null)}
                onOpenChange={(open) => {
                    if (!open && !busy) {
                        setPending(null);
                    }
                }}
            />
        </>
    );

    return { request, busy, dialogs };
}
