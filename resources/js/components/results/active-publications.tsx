import { router } from '@inertiajs/react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogTitle } from '@/components/ui/dialog';
import { t } from '@/i18n';

/** An active publication as `ResultPublicationRepositoryInterface::activeForClass` returns it. */
export type ActivePublication = {
    id: number;
    kind: string;
    section_id: number | null;
    participants: number;
    published_at: string;
    published_by_name: string | null;
    drifted: number;
};

/** Replace `:key` placeholders of an i18n template. */
export function fill(
    template: string,
    params: Record<string, string | number>,
): string {
    return Object.entries(params).reduce(
        (text, [key, value]) => text.replace(`:${key}`, String(value)),
        template,
    );
}

function idempotencyKey(prefix: string): string {
    return typeof crypto !== 'undefined' && 'randomUUID' in crypto
        ? `${prefix}-${crypto.randomUUID()}`
        : `${prefix}-${Date.now()}-${Math.random().toString(16).slice(2)}`;
}

type Props = {
    publications: ActivePublication[];
    canWithdraw: boolean;
    /** Label of the publication's kind (annual, GPA, a term and subject…). */
    kindLabel: (publication: ActivePublication) => string;
    /** Label of the publication's scope (whole class or a section). */
    scopeLabel: (sectionId: number | null) => string;
};

/**
 * «النشرات الفعّالة» of a class with their drift warning, and the withdrawal with a written reason
 * (POST /results/publications/{id}/withdraw — the rules live in WithdrawResultPublicationHandler).
 */
export function ActivePublications({
    publications,
    canWithdraw,
    kindLabel,
    scopeLabel,
}: Props) {
    const i18n = t();
    const p = i18n.results.publications;
    const [withdrawing, setWithdrawing] = useState<ActivePublication | null>(
        null,
    );
    const [reason, setReason] = useState('');
    const [busy, setBusy] = useState(false);

    const withdraw = () => {
        const trimmed = reason.trim();
        if (withdrawing === null || trimmed.length < 3) return;
        setBusy(true);
        router.post(
            `/results/publications/${withdrawing.id}/withdraw`,
            { reason: trimmed },
            {
                preserveScroll: true,
                headers: {
                    'X-Idempotency-Key': idempotencyKey(
                        `withdraw-${withdrawing.id}`,
                    ),
                },
                onSuccess: () => setWithdrawing(null),
                onFinish: () => setBusy(false),
            },
        );
    };

    return (
        <section
            aria-labelledby="publications-active"
            className="flex flex-col gap-2"
        >
            <h2 id="publications-active" className="text-base font-semibold">
                {p.active}
            </h2>
            {publications.length === 0 ? (
                <p className="text-muted-foreground text-sm">{p.noActive}</p>
            ) : (
                <ul className="flex flex-col gap-2">
                    {publications.map((pub) => (
                        <li
                            key={pub.id}
                            className="flex flex-col gap-1 text-sm sm:flex-row sm:items-center sm:justify-between"
                        >
                            <span>
                                {fill(p.publishedLine, {
                                    kind: kindLabel(pub),
                                    scope: scopeLabel(pub.section_id),
                                    n: pub.participants,
                                    at: pub.published_at
                                        .slice(0, 16)
                                        .replace('T', ' '),
                                    by: pub.published_by_name ?? '—',
                                })}
                                {pub.drifted > 0 ? (
                                    <span
                                        role="status"
                                        className="ms-2 font-medium"
                                    >
                                        {fill(p.drifted, { n: pub.drifted })}
                                    </span>
                                ) : null}
                            </span>
                            {canWithdraw ? (
                                <Button
                                    type="button"
                                    variant="outline"
                                    disabled={busy}
                                    onClick={() => {
                                        setReason('');
                                        setWithdrawing(pub);
                                    }}
                                    aria-label={`${p.withdraw}: ${kindLabel(pub)} — ${scopeLabel(pub.section_id)}`}
                                >
                                    {p.withdraw}
                                </Button>
                            ) : null}
                        </li>
                    ))}
                </ul>
            )}

            <Dialog
                open={withdrawing !== null}
                onOpenChange={(open) => {
                    if (!open && !busy) setWithdrawing(null);
                }}
                modal={false}
            >
                <DialogContent
                    className="sis-admission-status-reason-dialog sm:max-w-md"
                    showOverlay={false}
                >
                    <DialogTitle>{p.withdrawTitle}</DialogTitle>
                    <label className="sis-admission-status-reason-dialog__field">
                        <span>{p.withdrawReason}</span>
                        <textarea
                            className="sis-admission-status-reason-dialog__input"
                            rows={4}
                            value={reason}
                            maxLength={1000}
                            disabled={busy}
                            onChange={(e) => setReason(e.target.value)}
                        />
                    </label>
                    <div className="sis-admission-status-reason-dialog__actions">
                        <Button
                            type="button"
                            variant="outline"
                            disabled={busy}
                            onClick={() => setWithdrawing(null)}
                        >
                            {i18n.dialog.cancel}
                        </Button>
                        <Button
                            type="button"
                            disabled={busy || reason.trim().length < 3}
                            onClick={withdraw}
                        >
                            {p.withdrawConfirm}
                        </Button>
                    </div>
                </DialogContent>
            </Dialog>
        </section>
    );
}
