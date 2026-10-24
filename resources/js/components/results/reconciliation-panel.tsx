import { t } from '@/i18n';

export type Reconciliation = {
    students: number;
    issues: { code: string; count: number; enrollment_ids: number[] }[];
    ready: Record<string, boolean>;
};

/**
 * Pre-finalize / pre-publish reconciliation of the sheet's scope: what still blocks (or differs) and which
 * publications are possible now. It only restates the table, computed on the server.
 */
export function ReconciliationPanel({
    reconciliation,
    namesById,
}: {
    reconciliation: Reconciliation;
    namesById: Map<number, string>;
}) {
    const r = t().results.reconciliation;
    if (reconciliation.students === 0) {
        return null;
    }
    const ready = Object.entries(reconciliation.ready);

    return (
        <section aria-label={r.title} className="text-sm" data-results-reconciliation>
            <p>
                <strong>{r.title}</strong> — {r.students.replace(':n', String(reconciliation.students))}
                {ready.length > 0
                    ? ` · ${ready.map(([kind, ok]) => `${r.kinds[kind as keyof typeof r.kinds] ?? kind}: ${ok ? r.ready : r.notReady}`).join(' · ')}`
                    : ''}
            </p>
            {reconciliation.issues.length === 0 ? (
                <p>{r.allClear}</p>
            ) : (
                <ul>
                    {reconciliation.issues.map((issue) => (
                        <li key={issue.code} data-reconciliation-issue={issue.code}>
                            {r.codes[issue.code as keyof typeof r.codes] ?? issue.code}: {issue.count}
                            {issue.enrollment_ids.length > 0
                                ? ` (${issue.enrollment_ids
                                      .slice(0, 5)
                                      .map((id) => namesById.get(id) ?? id)
                                      .join('، ')}${issue.count > 5 ? '…' : ''})`
                                : ''}
                        </li>
                    ))}
                </ul>
            )}
        </section>
    );
}
