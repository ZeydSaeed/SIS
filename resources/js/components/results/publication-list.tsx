import { router } from '@inertiajs/react';
import { Undo2 } from 'lucide-react';
import { useState } from 'react';
import { AttendanceWindow } from '@/components/attendance/attendance-chrome';
import { RegistryTextField } from '@/components/organization/registry-sheet';
import { t } from '@/i18n';

export type Publication = {
    id: number;
    kind: string;
    section_id: number | null;
    participants: number;
    published_at: string;
    drifted: number;
};

/**
 * The active publications of the scope on a results sheet, with a withdraw action (a reason is required) for who may
 * publish. The server re-checks permission, scope and state.
 */
export function PublicationList({
    publications,
    canWithdraw,
    kindLabel,
    sectionName,
}: {
    publications: Publication[];
    canWithdraw: boolean;
    kindLabel: (publication: Publication) => string;
    sectionName: (sectionId: number) => string;
}) {
    const s = t().results.yearSheet;
    const [withdrawing, setWithdrawing] = useState<Publication | null>(null);
    const [reason, setReason] = useState('');
    const [working, setWorking] = useState(false);

    return (
        <>
            <ul className="text-sm" data-results-publications>
                {publications.length === 0 ? (
                    <li>{s.notPublished}</li>
                ) : (
                    publications.map((p) => (
                        <li key={p.id}>
                            {s.publishedLine
                                .replace(':kind', kindLabel(p))
                                .replace(
                                    ':scope',
                                    p.section_id === null
                                        ? s.allSections
                                        : sectionName(p.section_id),
                                )
                                .replace(':n', String(p.participants))
                                .replace(':at', p.published_at.slice(0, 16))}{' '}
                            {p.drifted > 0
                                ? `${s.publishDrifted.replace(':n', String(p.drifted))} `
                                : ''}
                            {canWithdraw ? (
                                <button
                                    type="button"
                                    className="underline"
                                    onClick={() => {
                                        setReason('');
                                        setWithdrawing(p);
                                    }}
                                >
                                    <Undo2
                                        className="inline size-3.5 align-[-2px]"
                                        aria-hidden
                                    />{' '}
                                    {s.withdraw}
                                </button>
                            ) : null}
                        </li>
                    ))
                )}
            </ul>
            {withdrawing !== null ? (
                <AttendanceWindow
                    title={s.withdrawTitle}
                    danger
                    pending={working}
                    confirmLabel={s.withdraw}
                    confirmDisabled={reason.trim().length < 3}
                    onConfirm={() => {
                        setWorking(true);
                        router.post(
                            `/results/year-sheet/publications/${withdrawing.id}/withdraw`,
                            { reason },
                            {
                                headers: {
                                    'X-Idempotency-Key': `withdraw-${Date.now()}-${Math.random().toString(36).slice(2, 10)}`,
                                },
                                preserveScroll: true,
                                onFinish: () => {
                                    setWorking(false);
                                    setWithdrawing(null);
                                },
                            },
                        );
                    }}
                    onClose={() => setWithdrawing(null)}
                    sections={[
                        {
                            id: 'results-withdraw',
                            title: s.withdrawTitle,
                            content: (
                                <RegistryTextField
                                    label={s.withdrawReason}
                                    editing
                                    required
                                    value={reason}
                                    onChange={setReason}
                                />
                            ),
                        },
                    ]}
                />
            ) : null}
        </>
    );
}
