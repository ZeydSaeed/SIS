import { Head, router } from '@inertiajs/react';
import { Calculator } from 'lucide-react';
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
type ResultCell = {
    version: number;
    weighted_total: string | null;
    incomplete: boolean;
} | null;

type Row = {
    enrollment_id: number;
    student_name: string;
    student_code: string;
    section_name: string;
    seats: number;
    current_grades: number;
    finalized_grades: number;
    operational: ResultCell;
    official: ResultCell;
};

type Filters = {
    academic_year_id: number | null;
    term_id: number | null;
    subject_id: number | null;
    class_id: number | null;
    section_id: number | null;
};

type PageProps = {
    terms: Option[];
    classes: (Option & { sections: Option[] })[];
    subjects: Option[];
    rows: Row[];
    filters: Filters;
    abilities: { calculate: boolean; finalize: boolean };
    outcomes: {
        mode: 'calculate' | 'finalize';
        counts: Record<string, number>;
        outcomes: { enrollment_id: number; status: string }[];
    } | null;
};

const SELECT_CLASS = 'sis-ops-hub__link min-h-11 px-3 py-2';

function newKey(): string {
    return `term-results-${Date.now()}-${Math.random().toString(36).slice(2, 10)}`;
}

export default function ResultsSheet({
    terms,
    classes,
    subjects,
    rows,
    filters,
    abilities,
    outcomes,
}: PageProps) {
    const i18n = t();
    const s = i18n.results.sheet;
    const [working, setWorking] = useState(false);
    const [confirmFinalize, setConfirmFinalize] = useState(false);
    const sections =
        classes.find((c) => c.id === filters.class_id)?.sections ?? [];
    const ready =
        filters.term_id !== null &&
        filters.subject_id !== null &&
        filters.class_id !== null;
    const outcomeBySeat = new Map(
        (outcomes?.outcomes ?? []).map((o) => [o.enrollment_id, o.status]),
    );
    const statusLabel = (status: string): string =>
        (s.outcomeLabels as Record<string, string>)[status] ??
        resolveUiMessage(status);

    const visit = (patch: Partial<Filters>) => {
        const next = { ...filters, ...patch };
        router.get(
            '/results/sheet',
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

    const run = (mode: 'calculate' | 'finalize') => {
        setWorking(true);
        router.post(
            `/results/sheet/${mode}`,
            {
                academic_year_id: filters.academic_year_id,
                term_id: filters.term_id,
                subject_id: filters.subject_id,
                class_id: filters.class_id,
                section_id: filters.section_id,
            },
            {
                headers: { 'X-Idempotency-Key': newKey() },
                preserveScroll: true,
                onFinish: () => {
                    setWorking(false);
                    setConfirmFinalize(false);
                },
            },
        );
    };

    const result = (cell: ResultCell) =>
        cell === null ? (
            s.none
        ) : (
            <span>
                <span dir="ltr">{cell.weighted_total ?? s.none}</span>
                {cell.incomplete ? ` · ${s.incomplete}` : ''}
                {` · ${s.version.replace(':v', String(cell.version))}`}
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
            id: 'grades',
            header: s.grades,
            cell: (row) =>
                s.gradesValue
                    .replace(':finalized', String(row.finalized_grades))
                    .replace(':current', String(row.current_grades))
                    .replace(':seats', String(row.seats)),
        },
        {
            id: 'operational',
            header: s.operational,
            cell: (row) => result(row.operational),
        },
        {
            id: 'official',
            header: s.official,
            cell: (row) => result(row.official),
        },
        {
            id: 'outcome',
            header: s.outcome,
            cell: (row) => {
                const status = outcomeBySeat.get(row.enrollment_id);

                return status ? statusLabel(status) : '';
            },
            sort: false,
        },
    ];

    const breadcrumbs: BreadcrumbItem[] = [
        { title: i18n.results.title, href: '/results' },
        { title: s.title, href: '/results/sheet' },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={s.title} />
            <div
                className="sis-ops-hub flex flex-col gap-4 p-4"
                dir="rtl"
                lang="ar"
                data-window-id="results.sheet"
            >
                <PageHeader
                    title={s.title}
                    description={s.description}
                    icon={<Calculator className="size-6" aria-hidden />}
                />
                <OpsYearFilter
                    action="/results/sheet"
                    academicYearId={filters.academic_year_id}
                />
                <div className="flex flex-wrap items-end gap-3">
                    <label className="flex flex-col gap-1 text-sm">
                        <span>{s.term}</span>
                        <select
                            name="term_id"
                            className={SELECT_CLASS}
                            value={filters.term_id ?? ''}
                            onChange={(e) =>
                                visit({
                                    term_id: numberOrNull(e.target.value),
                                    subject_id: null,
                                })
                            }
                        >
                            <option value="">—</option>
                            {terms.map((term) => (
                                <option key={term.id} value={term.id}>
                                    {term.name}
                                </option>
                            ))}
                        </select>
                    </label>
                    <label className="flex flex-col gap-1 text-sm">
                        <span>{s.subject}</span>
                        <select
                            name="subject_id"
                            className={SELECT_CLASS}
                            value={filters.subject_id ?? ''}
                            disabled={filters.term_id === null}
                            onChange={(e) =>
                                visit({
                                    subject_id: numberOrNull(e.target.value),
                                })
                            }
                        >
                            <option value="">—</option>
                            {subjects.map((subject) => (
                                <option key={subject.id} value={subject.id}>
                                    {subject.name}
                                </option>
                            ))}
                        </select>
                    </label>
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

                {filters.term_id !== null && subjects.length === 0 ? (
                    <p className="text-sm">{s.noSubjects}</p>
                ) : null}
                {!ready ? <p className="text-sm">{s.pickHint}</p> : null}

                {ready ? (
                    <>
                        <div className="flex flex-wrap gap-2">
                            {abilities.calculate ? (
                                <button
                                    type="button"
                                    className="sis-ops-hub__link min-h-11 px-4 py-2 text-sm disabled:opacity-50"
                                    disabled={working || rows.length === 0}
                                    onClick={() => run('calculate')}
                                >
                                    {working ? s.working : s.calculate}
                                </button>
                            ) : null}
                            {abilities.finalize ? (
                                <button
                                    type="button"
                                    className="sis-ops-hub__link min-h-11 px-4 py-2 text-sm disabled:opacity-50"
                                    disabled={working || rows.length === 0}
                                    onClick={() => setConfirmFinalize(true)}
                                >
                                    {s.finalize}
                                </button>
                            ) : null}
                        </div>
                        {outcomes ? (
                            <p
                                className="text-sm"
                                role="status"
                                data-results-outcome={outcomes.mode}
                            >
                                {s.outcome}{' '}
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
                ) : null}

                <ConfirmDialog
                    open={confirmFinalize}
                    title={s.finalizeConfirmTitle}
                    description={s.finalizeConfirmDesc}
                    confirmLabel={s.finalize}
                    confirmPending={working}
                    tone="danger"
                    onConfirm={() => run('finalize')}
                    onOpenChange={(open) => {
                        if (!open) {
                            setConfirmFinalize(false);
                        }
                    }}
                />
            </div>
        </AppLayout>
    );
}
