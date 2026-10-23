import { Head, router } from '@inertiajs/react';
import { GraduationCap } from 'lucide-react';
import { useState } from 'react';
import { ConfirmDialog } from '@/components/sis/confirm-dialog';
import { DataTable, type DataTableColumn } from '@/components/sis/data-table';
import { OpsYearFilter } from '@/components/sis/ops-year-filter';
import { PageHeader } from '@/components/sis/page-header';
import { t } from '@/i18n';
import AppLayout from '@/layouts/app-layout';
import { resolveUiMessage } from '@/lib/resolve-ui-message';
import type { BreadcrumbItem } from '@/types';

type Option = { id: number; name: string };
type Target = 'annual' | 'gpa';
type Mode = 'calculate' | 'finalize';
type ResultCell = {
    version: number;
    value: string | null;
    incomplete: boolean;
} | null;
type Pair = { operational: ResultCell; official: ResultCell };

type Row = {
    enrollment_id: number;
    student_name: string;
    student_code: string;
    section_name: string;
    term_results: { operational: number; official: number };
    annual: Pair;
    gpa: Pair;
};

type Filters = {
    academic_year_id: number | null;
    class_id: number | null;
    section_id: number | null;
};

type PageProps = {
    classes: (Option & { sections: Option[] })[];
    rows: Row[];
    filters: Filters;
    abilities: { calculate: boolean; finalize: boolean };
    outcomes: {
        target: Target;
        mode: Mode;
        counts: Record<string, number>;
        outcomes: { enrollment_id: number; status: string }[];
    } | null;
};

const SELECT_CLASS = 'sis-ops-hub__link min-h-11 px-3 py-2';
const BUTTON_CLASS =
    'sis-ops-hub__link min-h-11 px-4 py-2 text-sm disabled:opacity-50';

function newKey(): string {
    return `year-results-${Date.now()}-${Math.random().toString(36).slice(2, 10)}`;
}

export default function ResultsYearSheet({
    classes,
    rows,
    filters,
    abilities,
    outcomes,
}: PageProps) {
    const i18n = t();
    const s = i18n.results.yearSheet;
    const labels = i18n.results.sheet;
    const [working, setWorking] = useState(false);
    const [confirmTarget, setConfirmTarget] = useState<Target | null>(null);
    const sections =
        classes.find((c) => c.id === filters.class_id)?.sections ?? [];
    const outcomeBySeat = new Map(
        (outcomes?.outcomes ?? []).map((o) => [o.enrollment_id, o.status]),
    );
    const statusLabel = (status: string): string =>
        (labels.outcomeLabels as Record<string, string>)[status] ??
        resolveUiMessage(status);

    const visit = (patch: Partial<Filters>) => {
        const next = { ...filters, ...patch };
        router.get(
            '/results/year-sheet',
            Object.fromEntries(
                Object.entries(next).filter(
                    ([, value]) => value !== null && value !== undefined,
                ),
            ),
            { preserveScroll: true },
        );
    };
    const numberOrNull = (value: string): number | null =>
        value === '' ? null : Number(value);

    const run = (target: Target, mode: Mode) => {
        setWorking(true);
        router.post(
            `/results/year-sheet/${mode}`,
            {
                academic_year_id: filters.academic_year_id,
                class_id: filters.class_id,
                section_id: filters.section_id,
                target,
            },
            {
                headers: { 'X-Idempotency-Key': newKey() },
                preserveScroll: true,
                onFinish: () => {
                    setWorking(false);
                    setConfirmTarget(null);
                },
            },
        );
    };

    const result = (cell: ResultCell) =>
        cell === null ? (
            labels.none
        ) : (
            <span>
                <span dir="ltr">{cell.value ?? labels.none}</span>
                {cell.incomplete ? ` · ${labels.incomplete}` : ''}
                {` · ${labels.version.replace(':v', String(cell.version))}`}
            </span>
        );

    const columns: DataTableColumn<Row>[] = [
        { id: 'student', header: s.student, cell: (row) => row.student_name },
        {
            id: 'code',
            header: s.code,
            cell: (row) => <span dir="ltr">{row.student_code}</span>,
            hideOnMobile: true,
        },
        {
            id: 'section',
            header: s.section,
            cell: (row) => row.section_name,
            hideOnMobile: true,
        },
        {
            id: 'terms',
            header: s.termResults,
            cell: (row) =>
                s.termResultsValue
                    .replace(':official', String(row.term_results.official))
                    .replace(
                        ':operational',
                        String(row.term_results.operational),
                    ),
        },
        {
            id: 'annual_operational',
            header: s.annualOperational,
            cell: (row) => result(row.annual.operational),
        },
        {
            id: 'annual_official',
            header: s.annualOfficial,
            cell: (row) => result(row.annual.official),
        },
        {
            id: 'gpa_operational',
            header: s.gpaOperational,
            cell: (row) => result(row.gpa.operational),
        },
        {
            id: 'gpa_official',
            header: s.gpaOfficial,
            cell: (row) => result(row.gpa.official),
        },
        {
            id: 'outcome',
            header: labels.outcome,
            cell: (row) => {
                const status = outcomeBySeat.get(row.enrollment_id);

                return status ? statusLabel(status) : '';
            },
            sort: false,
        },
    ];

    const actions = (target: Target, title: string) => (
        <div className="flex flex-wrap items-center gap-2">
            <span className="text-sm font-semibold">{title}</span>
            {abilities.calculate ? (
                <button
                    type="button"
                    className={BUTTON_CLASS}
                    disabled={working || rows.length === 0}
                    onClick={() => run(target, 'calculate')}
                >
                    {working ? labels.working : s.calculate}
                </button>
            ) : null}
            {abilities.finalize ? (
                <button
                    type="button"
                    className={BUTTON_CLASS}
                    disabled={working || rows.length === 0}
                    onClick={() => setConfirmTarget(target)}
                >
                    {s.finalize}
                </button>
            ) : null}
        </div>
    );

    const breadcrumbs: BreadcrumbItem[] = [
        { title: i18n.results.title, href: '/results' },
        { title: s.title, href: '/results/year-sheet' },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={s.title} />
            <div
                className="sis-ops-hub flex flex-col gap-4 p-4"
                dir="rtl"
                lang="ar"
                data-window-id="results.year-sheet"
            >
                <PageHeader
                    title={s.title}
                    description={s.description}
                    icon={<GraduationCap className="size-6" aria-hidden />}
                />
                <OpsYearFilter
                    action="/results/year-sheet"
                    academicYearId={filters.academic_year_id}
                />
                <div className="flex flex-wrap items-end gap-3">
                    <label className="flex flex-col gap-1 text-sm">
                        <span>{s.class}</span>
                        <select
                            name="class_id"
                            className={SELECT_CLASS}
                            value={filters.class_id ?? ''}
                            onChange={(e) =>
                                visit({
                                    class_id: numberOrNull(e.target.value),
                                    section_id: null,
                                })
                            }
                        >
                            <option value="">—</option>
                            {classes.map((c) => (
                                <option key={c.id} value={c.id}>
                                    {c.name}
                                </option>
                            ))}
                        </select>
                    </label>
                    <label className="flex flex-col gap-1 text-sm">
                        <span>{s.section}</span>
                        <select
                            name="section_id"
                            className={SELECT_CLASS}
                            value={filters.section_id ?? ''}
                            disabled={filters.class_id === null}
                            onChange={(e) =>
                                visit({
                                    section_id: numberOrNull(e.target.value),
                                })
                            }
                        >
                            <option value="">{s.allSections}</option>
                            {sections.map((section) => (
                                <option key={section.id} value={section.id}>
                                    {section.name}
                                </option>
                            ))}
                        </select>
                    </label>
                </div>

                {filters.class_id === null ? (
                    <p className="text-sm">{s.pickHint}</p>
                ) : (
                    <>
                        <p className="text-sm">{s.orderHint}</p>
                        <div className="flex flex-wrap gap-4">
                            {actions('annual', s.annualActions)}
                            {actions('gpa', s.gpaActions)}
                        </div>
                        {outcomes ? (
                            <p
                                className="text-sm"
                                role="status"
                                data-results-outcome={`${outcomes.target}.${outcomes.mode}`}
                            >
                                {labels.outcome}{' '}
                                {s.outcomeTargets[outcomes.target]} —{' '}
                                {Object.entries(outcomes.counts)
                                    .map(
                                        ([status, count]) =>
                                            `${statusLabel(status)}: ${count}`,
                                    )
                                    .join(' · ')}
                            </p>
                        ) : null}
                        <DataTable
                            columns={columns}
                            rows={rows}
                            rowKey={(row) => row.enrollment_id}
                            emptyTitle={s.noRows}
                            caption={s.title}
                        />
                    </>
                )}

                <ConfirmDialog
                    open={confirmTarget !== null}
                    title={s.finalizeConfirmTitle}
                    description={s.finalizeConfirmDesc}
                    confirmLabel={`${s.finalize} — ${confirmTarget ? s.outcomeTargets[confirmTarget] : ''}`}
                    confirmPending={working}
                    tone="danger"
                    onConfirm={() => {
                        if (confirmTarget) {
                            run(confirmTarget, 'finalize');
                        }
                    }}
                    onOpenChange={(open) => {
                        if (!open) {
                            setConfirmTarget(null);
                        }
                    }}
                />
            </div>
        </AppLayout>
    );
}
