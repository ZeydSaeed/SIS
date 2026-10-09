import { Head, router } from '@inertiajs/react';
import { CheckCircle2, Download, FileSpreadsheet, RefreshCw, Upload, XCircle } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { newIdempotencyKey } from '@/components/organization/registry-sheet';
import { SheetSection } from '@/components/sis/admission-sheet';
import { ConfirmDialog } from '@/components/sis/confirm-dialog';
import { usePageError } from '@/components/sis/page-error-context';
import { useRegisterPageRibbon, type PageRibbonGroup } from '@/components/sis/page-ribbon-context';
import { Button } from '@/components/ui/button';
import { t } from '@/i18n';
import AppLayout from '@/layouts/app-layout';
import { resolveUiMessage } from '@/lib/resolve-ui-message';
import type { BreadcrumbItem } from '@/types';

type Column = { key: string; label: string; required: boolean; aliases: string[]; example: string; hint?: string };
type Kind = { kind: string; needs_year: boolean; columns: Column[] };
type Batch = {
    id: number;
    kind: string;
    status: number;
    file_name: string;
    total_rows: number;
    valid_rows: number;
    error_rows: number;
    duplicate_rows: number;
    result: { created: number; updated: number; skipped: number; failed: number; errors: number } | null;
    error: string | null;
    created_at: string;
    committed_at: string | null;
};
type Row = { id: number; row_number: number; data: Record<string, unknown> & { _source?: Record<string, string> }; action: number; status: number; errors: string[]; entity_id: number | null };
type Props = {
    kinds: Kind[];
    batches: Batch[];
    batch: Batch | null;
    rows: { rows: Row[]; total: number; page: number; per_page: number } | null;
    filters: { academic_year_id: number | null; row_status: number | null };
};

/** Batch statuses (ImportBatchRepositoryInterface). */
const PARSING = 1;
const PREVIEWED = 2;
const COMMITTING = 3;
const ROW_TONE: Record<number, 'info' | 'error' | 'warning'> = { 1: 'info', 2: 'error', 3: 'warning', 4: 'info', 5: 'error' };

export default function ImportsIndex(props: Props) {
    const breadcrumbs: BreadcrumbItem[] = [{ title: t().imports.title, href: '/imports' }];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <ImportsPage {...props} />
        </AppLayout>
    );
}

function ImportsPage({ kinds, batches, batch, rows, filters }: Props) {
    const i18n = t();
    const x = i18n.imports;
    const { showInertiaErrors } = usePageError();
    const [kind, setKind] = useState(batch?.kind ?? kinds[0]?.kind ?? '');
    const [busy, setBusy] = useState(false);
    const [confirmCommit, setConfirmCommit] = useState(false);
    const fileRef = useRef<HTMLInputElement | null>(null);
    const current = kinds.find((k) => k.kind === kind) ?? null;

    // While a batch is being parsed or committed, refresh until it settles (the work runs in the queue).
    useEffect(() => {
        if (batch === null || (batch.status !== PARSING && batch.status !== COMMITTING)) {
            return;
        }
        const timer = window.setInterval(() => router.reload({ only: ['batch', 'rows', 'batches'] }), 2000);

        return () => window.clearInterval(timer);
    }, [batch?.id, batch?.status]); // eslint-disable-line react-hooks/exhaustive-deps

    const visit = (query: Record<string, string | number | undefined>) => router.get('/imports', query, { preserveScroll: true, preserveState: true });
    const post = (url: string, data: Record<string, unknown> | FormData, after?: () => void) => {
        setBusy(true);
        router.post(url, data as never, {
            preserveScroll: true,
            forceFormData: data instanceof FormData,
            headers: { 'X-Idempotency-Key': newIdempotencyKey('import') },
            onError: (errors) => showInertiaErrors(errors, i18n.errors.saveFailed),
            onFinish: () => {
                setBusy(false);
                after?.();
            },
        });
    };
    const upload = () => {
        const file = fileRef.current?.files?.[0];
        if (!file || current === null) {
            return;
        }
        const form = new FormData();
        form.append('file', file);
        if (filters.academic_year_id !== null) {
            form.append('academic_year_id', String(filters.academic_year_id));
        }
        post(`/imports/${current.kind}`, form, () => {
            if (fileRef.current) {
                fileRef.current.value = '';
            }
        });
    };
    const message = (code: string) => {
        const [key, detail] = code.split(/:(.+)/);
        const base = resolveUiMessage(key ?? code, code);

        return detail ? `${base} (${detail})` : base;
    };
    const columnLabel = (key: string) => current?.columns.find((c) => c.key === key)?.label ?? key;

    const ribbon = useMemo((): PageRibbonGroup[] => [
        {
            id: 'imports-kinds',
            label: x.kinds,
            commands: kinds.map((k) => ({
                id: `imports-kind-${k.kind}`,
                label: (x.kindNames as Record<string, string>)[k.kind] ?? k.kind,
                icon: FileSpreadsheet,
                pressed: kind === k.kind,
                onSelect: () => setKind(k.kind),
            })),
        },
        {
            id: 'imports-actions',
            label: i18n.common.actions,
            commands: [
                { id: 'imports-template', label: x.template, icon: Download, disabled: current === null, onSelect: () => (window.location.href = `/imports/templates/${kind}`) },
                { id: 'imports-upload', label: x.upload, icon: Upload, disabled: current === null || busy, onSelect: () => fileRef.current?.click() },
            ],
        },
        // eslint-disable-next-line react-hooks/exhaustive-deps
    ], [kinds, kind, busy, i18n]);
    useRegisterPageRibbon('edit', ribbon);

    const lastPage = rows === null ? 1 : Math.max(1, Math.ceil(rows.total / rows.per_page));
    const sourceKeys = current?.columns.filter((c) => c.required || ['abbreviation', 'status'].includes(c.key)).map((c) => c.key).slice(0, 5) ?? [];

    return (
        <>
            <Head title={x.title} />
            <div className="sis-ops-hub sis-admission-page sis-students-page sis-enrollments-page flex h-full min-h-0 flex-col overflow-auto pb-4" dir="rtl" lang="ar">
                <div className="sis-admission-page-body">
                    {kinds.length === 0 ? <p className="sis-branches-page__notice">{x.noAccess}</p> : null}
                    {current !== null ? (
                        <SheetSection id="imports-kind" title={`${(x.kindNames as Record<string, string>)[current.kind] ?? current.kind} — ${x.columns}`}>
                            <p className="sis-timetable-sheet__hint">{(x.kindHints as Record<string, string>)[current.kind] ?? ''}</p>
                            <div className="sis-admission-periods-table sis-admission-drafts-table">
                                <div className="sis-admission-drafts-table__scroller" data-allow-x-scroll>
                                    <table>
                                        <thead>
                                            <tr>
                                                <th>{x.column}</th>
                                                <th>{x.required}</th>
                                                <th>{x.example}</th>
                                                <th>{x.hint}</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {current.columns.map((column) => (
                                                <tr key={column.key}>
                                                    <td>{column.label}</td>
                                                    <td>{column.required ? x.yes : '—'}</td>
                                                    <td>{column.example || '—'}</td>
                                                    <td>{column.hint ?? ''}</td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <div className="sis-admission-sheet__actions">
                                <Button type="button" variant="outline" onClick={() => (window.location.href = `/imports/templates/${current.kind}`)}>
                                    <Download aria-hidden />
                                    {x.template}
                                </Button>
                                <input ref={fileRef} type="file" accept=".xlsx,.csv" aria-label={x.file} className="sis-admission-sheet__control" onChange={() => upload()} />
                                <Button type="button" disabled={busy} onClick={() => fileRef.current?.click()}>
                                    <Upload aria-hidden />
                                    {x.upload}
                                </Button>
                            </div>
                            <p className="sis-timetable-sheet__hint">{x.flowHint}</p>
                        </SheetSection>
                    ) : null}

                    {batch !== null ? (
                        <SheetSection id="imports-batch" title={`${x.preview} #${batch.id} — ${batch.file_name}`}>
                            <div className="sis-timetable-audit__bar">
                                <span className={`sis-timetable-badge sis-timetable-badge--${batch.status === 5 ? 'error' : 'info'}`}>{(x.statuses as Record<string, string>)[batch.status]}</span>
                                <span className="sis-timetable-badge sis-timetable-badge--info">
                                    {x.total}: <bdi dir="ltr">{batch.total_rows}</bdi>
                                </span>
                                {[1, 2, 3].map((s) => (
                                    <button key={s} type="button" className={`sis-timetable-badge sis-timetable-badge--${ROW_TONE[s]}`} aria-pressed={filters.row_status === s} onClick={() => visit({ batch: batch.id, row_status: filters.row_status === s ? undefined : s })}>
                                        {(x.rowStatuses as Record<string, string>)[s]}: <bdi dir="ltr">{s === 1 ? batch.valid_rows : s === 2 ? batch.error_rows : batch.duplicate_rows}</bdi>
                                    </button>
                                ))}
                            </div>
                            {batch.error !== null ? <p className="sis-branches-page__notice">{message(batch.error)}</p> : null}
                            {batch.result !== null ? (
                                <ul className="sis-timetable-audit__items">
                                    {(['created', 'updated', 'skipped', 'failed', 'errors'] as const).map((k) => (
                                        <li key={k} className={`sis-timetable-audit__item${k === 'failed' && batch.result!.failed > 0 ? ' sis-timetable-audit__item--error' : ''}`}>
                                            <span className="sis-timetable-audit__text">{(x.report as Record<string, string>)[k]}</span>
                                            <bdi dir="ltr">{batch.result![k]}</bdi>
                                        </li>
                                    ))}
                                </ul>
                            ) : null}
                            {rows !== null && rows.rows.length > 0 ? (
                                <div className="sis-admission-periods-table sis-admission-drafts-table">
                                    <div className="sis-admission-drafts-table__scroller" data-allow-x-scroll>
                                        <table>
                                            <thead>
                                                <tr>
                                                    <th className="sis-admission-drafts-table__num">{x.row}</th>
                                                    {sourceKeys.map((key) => (
                                                        <th key={key}>{columnLabel(key)}</th>
                                                    ))}
                                                    <th>{x.action}</th>
                                                    <th>{x.status}</th>
                                                    <th>{x.errors}</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                {rows.rows.map((row) => (
                                                    <tr key={row.id}>
                                                        <td className="sis-admission-drafts-table__num">{row.row_number}</td>
                                                        {sourceKeys.map((key) => (
                                                            <td key={key}>{row.data._source?.[key] ?? ''}</td>
                                                        ))}
                                                        <td>{(x.actions as Record<string, string>)[row.action]}</td>
                                                        <td>
                                                            <span className={`sis-timetable-badge sis-timetable-badge--${ROW_TONE[row.status]}`}>{(x.rowStatuses as Record<string, string>)[row.status]}</span>
                                                        </td>
                                                        <td>{row.errors.map(message).join(' · ')}</td>
                                                    </tr>
                                                ))}
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            ) : null}
                            {lastPage > 1 && rows !== null ? (
                                <div className="sis-timetable-audit__bar">
                                    <Button type="button" size="sm" variant="outline" disabled={rows.page <= 1} onClick={() => visit({ batch: batch.id, row_status: filters.row_status ?? undefined, page: rows.page - 1 })}>
                                        {i18n.common.previous}
                                    </Button>
                                    <bdi dir="ltr">
                                        {rows.page} / {lastPage}
                                    </bdi>
                                    <Button type="button" size="sm" variant="outline" disabled={rows.page >= lastPage} onClick={() => visit({ batch: batch.id, row_status: filters.row_status ?? undefined, page: rows.page + 1 })}>
                                        {i18n.common.next}
                                    </Button>
                                </div>
                            ) : null}
                            <div className="sis-admission-sheet__actions">
                                <Button type="button" variant="outline" onClick={() => router.reload({ only: ['batch', 'rows', 'batches'] })}>
                                    <RefreshCw aria-hidden />
                                    {x.refresh}
                                </Button>
                                <Button type="button" variant="outline" disabled={batch.error_rows + batch.duplicate_rows === 0 && (batch.result?.failed ?? 0) === 0} onClick={() => (window.location.href = `/imports/batches/${batch.id}/errors`)}>
                                    <Download aria-hidden />
                                    {x.errorReport}
                                </Button>
                                {batch.status === PREVIEWED || batch.status === 5 ? (
                                    <Button type="button" variant="outline" disabled={busy} onClick={() => post(`/imports/batches/${batch.id}/cancel`, {})}>
                                        <XCircle aria-hidden />
                                        {x.cancel}
                                    </Button>
                                ) : null}
                                {batch.status === PREVIEWED ? (
                                    <Button type="button" disabled={busy || batch.valid_rows === 0} onClick={() => setConfirmCommit(true)}>
                                        <CheckCircle2 aria-hidden />
                                        {x.commit.replace('{n}', String(batch.valid_rows))}
                                    </Button>
                                ) : null}
                            </div>
                        </SheetSection>
                    ) : null}

                    <SheetSection id="imports-batches" title={x.history}>
                        {batches.length === 0 ? (
                            <p className="sis-timetable-sheet__hint">{x.noBatches}</p>
                        ) : (
                            <div className="sis-admission-periods-table sis-admission-drafts-table">
                                <div className="sis-admission-drafts-table__scroller" data-allow-x-scroll>
                                    <table>
                                        <thead>
                                            <tr>
                                                <th className="sis-admission-drafts-table__num">#</th>
                                                <th>{x.kind}</th>
                                                <th>{x.file}</th>
                                                <th>{x.status}</th>
                                                <th className="sis-admission-drafts-table__num">{x.total}</th>
                                                <th className="sis-admission-drafts-table__num">{(x.rowStatuses as Record<string, string>)[1]}</th>
                                                <th className="sis-admission-drafts-table__num">{(x.rowStatuses as Record<string, string>)[2]}</th>
                                                <th>{x.date}</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {batches.map((b) => (
                                                <tr key={b.id} className={batch?.id === b.id ? 'sis-admission-periods-table__row--selected' : undefined} onClick={() => visit({ batch: b.id })}>
                                                    <td className="sis-admission-drafts-table__num">{b.id}</td>
                                                    <td>{(x.kindNames as Record<string, string>)[b.kind] ?? b.kind}</td>
                                                    <td>{b.file_name}</td>
                                                    <td>{(x.statuses as Record<string, string>)[b.status]}</td>
                                                    <td className="sis-admission-drafts-table__num">{b.total_rows}</td>
                                                    <td className="sis-admission-drafts-table__num">{b.valid_rows}</td>
                                                    <td className="sis-admission-drafts-table__num">{b.error_rows}</td>
                                                    <td dir="ltr">{b.created_at.slice(0, 16)}</td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        )}
                    </SheetSection>
                </div>
            </div>
            <ConfirmDialog
                open={confirmCommit && batch !== null}
                title={x.commitTitle}
                description={x.commitConfirm.replace('{n}', String(batch?.valid_rows ?? 0))}
                confirmLabel={x.commitTitle}
                onConfirm={() => {
                    setConfirmCommit(false);
                    if (batch !== null) {
                        post(`/imports/batches/${batch.id}/commit`, {});
                    }
                }}
                onOpenChange={(open) => {
                    if (!open) {
                        setConfirmCommit(false);
                    }
                }}
            />
        </>
    );
}
