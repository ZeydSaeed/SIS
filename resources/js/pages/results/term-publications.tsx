import { Head, router } from '@inertiajs/react';
import { Megaphone } from 'lucide-react';
import { useState } from 'react';
import AppLayout from '@/layouts/app-layout';
import {
    ActivePublications,
    fill,
    type ActivePublication,
} from '@/components/results/active-publications';
import { ConfirmDialog } from '@/components/sis/confirm-dialog';
import { DataTable, type DataTableColumn } from '@/components/sis/data-table';
import { OpsYearFilter } from '@/components/sis/ops-year-filter';
import { PageHeader } from '@/components/sis/page-header';
import { SisListSelect } from '@/components/sis/sis-list-select';
import { Button } from '@/components/ui/button';
import { t } from '@/i18n';
import type { BreadcrumbItem } from '@/types';

type Option = { id: number; name: string };

type Row = {
    enrollment_id: number;
    student_name: string;
    student_code: string;
    section_name: string;
    official: {
        version: number;
        weighted_total: string | null;
        incomplete: boolean;
    } | null;
};

type Filters = {
    academic_year_id: number | null;
    term_id: number | null;
    subject_id: number | null;
    class_id: number | null;
    section_id: number | null;
};

type PageProps = {
    filters: Filters;
    terms: Option[];
    subjects: Option[];
    classes: (Option & { sections: Option[] })[];
    rows: Row[];
    reconciliation: {
        students: number;
        issues: { code: string; count: number }[];
        ready: { term?: boolean };
    };
    publications: ActivePublication[];
    abilities: { publish: boolean };
};

/** «نشر نتائج الفصول» — official term results of one term and subject for a class or section (EXAMIS U-03). */
export default function TermResultPublications({
    filters,
    terms,
    subjects,
    classes,
    rows,
    reconciliation,
    publications,
    abilities,
}: PageProps) {
    const i18n = t();
    const p = i18n.results.publications;
    const tp = i18n.results.termPublications;
    const codes = i18n.results.reconciliation.codes as Record<string, string>;
    const [confirming, setConfirming] = useState(false);
    const [busy, setBusy] = useState(false);

    const breadcrumbs: BreadcrumbItem[] = [
        { title: i18n.modules.results, href: '/results' },
        { title: tp.title, href: '/results/term-publications' },
    ];
    const selectedClass =
        classes.find((c) => c.id === filters.class_id) ?? null;
    const sectionName = (id: number | null): string =>
        id === null
            ? p.wholeClass
            : (selectedClass?.sections.find((s) => s.id === id)?.name ??
              String(id));
    const termName = terms.find((x) => x.id === filters.term_id)?.name ?? '';
    const subjectName =
        subjects.find((x) => x.id === filters.subject_id)?.name ?? '';
    const scopeChosen =
        filters.term_id !== null &&
        filters.subject_id !== null &&
        filters.class_id !== null;

    const navigate = (next: Partial<Filters>) => {
        const merged = { ...filters, ...next };
        router.get(
            '/results/term-publications',
            Object.fromEntries(
                Object.entries(merged).filter(([, v]) => v !== null),
            ),
            { preserveScroll: true },
        );
    };
    const pick = (
        label: string,
        value: number | null,
        options: Option[],
        onChange: (v: number | null) => void,
        blank?: string,
    ) => (
        <label className="flex min-w-0 flex-col gap-1 text-sm sm:w-56">
            <span>{label}</span>
            <SisListSelect
                ariaLabel={label}
                value={value?.toString() ?? ''}
                includeBlank={blank === undefined}
                options={[
                    ...(blank !== undefined
                        ? [{ value: '', label: blank }]
                        : []),
                    ...options.map((o) => ({
                        value: String(o.id),
                        label: o.name,
                    })),
                ]}
                onChange={(v) => onChange(v === '' ? null : Number(v))}
            />
        </label>
    );

    const publish = () => {
        if (!scopeChosen || filters.academic_year_id === null) return;
        setBusy(true);
        router.post(
            '/results/term-publications',
            {
                academic_year_id: filters.academic_year_id,
                term_id: filters.term_id,
                subject_id: filters.subject_id,
                class_id: filters.class_id,
                section_id: filters.section_id,
            },
            {
                preserveScroll: true,
                onFinish: () => {
                    setBusy(false);
                    setConfirming(false);
                },
            },
        );
    };

    const columns: DataTableColumn<Row>[] = [
        {
            id: 'student',
            header: p.student,
            cell: (r) => r.student_name,
            sort: (r) => r.student_name,
        },
        {
            id: 'code',
            header: p.code,
            cell: (r) => <bdi dir="ltr">{r.student_code}</bdi>,
            hideOnMobile: true,
        },
        {
            id: 'section',
            header: p.section,
            cell: (r) => r.section_name,
            hideOnMobile: true,
        },
        {
            id: 'official',
            header: tp.official,
            cell: (r) =>
                r.official === null ? (
                    <span className="text-muted-foreground">{p.none}</span>
                ) : (
                    <span>
                        <bdi dir="ltr">{r.official.weighted_total ?? '—'}</bdi>
                        {r.official.incomplete ? (
                            <span className="text-muted-foreground ms-2">
                                ({p.incomplete})
                            </span>
                        ) : null}
                    </span>
                ),
        },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={tp.title} />
            <div className="sis-table-theme sis-ops-hub flex min-w-0 flex-col gap-4 p-4">
                <PageHeader
                    title={tp.title}
                    description={tp.description}
                    icon={<Megaphone className="size-6" aria-hidden />}
                />
                <p>
                    <a
                        href="/results/publications"
                        className="sis-ops-hub__link px-3 py-2 text-sm"
                    >
                        {p.link}
                    </a>
                </p>

                <div className="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-end">
                    <OpsYearFilter
                        action="/results/term-publications"
                        academicYearId={filters.academic_year_id}
                        label={i18n.context.year}
                    />
                    {pick(tp.termLabel, filters.term_id, terms, (v) =>
                        navigate({ term_id: v, subject_id: null }),
                    )}
                    {pick(tp.subjectLabel, filters.subject_id, subjects, (v) =>
                        navigate({ subject_id: v }),
                    )}
                    {pick(p.classLabel, filters.class_id, classes, (v) =>
                        navigate({ class_id: v, section_id: null }),
                    )}
                    {selectedClass && selectedClass.sections.length > 0
                        ? pick(
                              p.sectionLabel,
                              filters.section_id,
                              selectedClass.sections,
                              (v) => navigate({ section_id: v }),
                              p.allSections,
                          )
                        : null}
                </div>

                {!scopeChosen ? (
                    <p className="text-muted-foreground text-sm">
                        {tp.chooseScope}
                    </p>
                ) : (
                    <>
                        <section
                            aria-labelledby="term-publications-reconciliation"
                            className="flex flex-col gap-2"
                        >
                            <h2
                                id="term-publications-reconciliation"
                                className="text-base font-semibold"
                            >
                                {i18n.results.reconciliation.title}
                            </h2>
                            <p className="text-sm">
                                {fill(i18n.results.reconciliation.students, {
                                    n: reconciliation.students,
                                })}
                            </p>
                            {reconciliation.issues.length === 0 ? (
                                <p className="text-sm">
                                    {i18n.results.reconciliation.allClear}
                                </p>
                            ) : (
                                <ul className="list-inside list-disc text-sm">
                                    {reconciliation.issues.map((issue) => (
                                        <li key={issue.code}>
                                            {codes[issue.code] ?? issue.code}:{' '}
                                            <bdi dir="ltr">{issue.count}</bdi>
                                        </li>
                                    ))}
                                </ul>
                            )}
                            <div className="flex flex-wrap gap-2">
                                <Button
                                    type="button"
                                    disabled={
                                        !abilities.publish ||
                                        reconciliation.ready.term !== true ||
                                        busy
                                    }
                                    onClick={() => setConfirming(true)}
                                    aria-describedby="term-publications-hint"
                                >
                                    {tp.publish}
                                </Button>
                            </div>
                            <p
                                id="term-publications-hint"
                                className="text-muted-foreground text-sm"
                            >
                                {abilities.publish
                                    ? tp.notReadyHint
                                    : p.noPermission}
                            </p>
                        </section>

                        <ActivePublications
                            publications={publications}
                            canWithdraw={abilities.publish}
                            kindLabel={() =>
                                fill(tp.kind, {
                                    term: termName,
                                    subject: subjectName,
                                })
                            }
                            scopeLabel={sectionName}
                        />

                        <DataTable
                            columns={columns}
                            rows={rows}
                            rowKey={(r) => r.enrollment_id}
                            caption={tp.tableCaption}
                            emptyTitle={tp.noStudents}
                        />
                    </>
                )}
            </div>

            <ConfirmDialog
                open={confirming}
                title={p.confirmTitle}
                description={`${fill(tp.kind, { term: termName, subject: subjectName })} — ${sectionName(filters.section_id)}. ${p.confirmDescription}`}
                confirmLabel={tp.publish}
                confirmPending={busy}
                onConfirm={publish}
                onOpenChange={(open) => {
                    if (!open && !busy) setConfirming(false);
                }}
            />
        </AppLayout>
    );
}
