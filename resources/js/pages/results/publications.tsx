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

type Official = {
    version: number;
    value: string | null;
    incomplete: boolean;
} | null;

type Row = {
    enrollment_id: number;
    student_name: string;
    student_code: string;
    section_name: string;
    annual: Official;
    gpa: Official;
    transcript: { number: string; version: number; up_to_date: boolean } | null;
};

type ClassOption = {
    id: number;
    name: string;
    sections: { id: number; name: string }[];
};

type Kind = 'annual' | 'gpa' | 'transcript';

type PageProps = {
    filters: {
        academic_year_id: number | null;
        class_id: number | null;
        section_id: number | null;
    };
    classes: ClassOption[];
    rows: Row[];
    reconciliation: {
        students: number;
        issues: { code: string; count: number }[];
        ready: Partial<Record<Kind, boolean>>;
    };
    publications: ActivePublication[];
    abilities: { publish: boolean };
};

export default function ResultPublications({
    filters,
    classes,
    rows,
    reconciliation,
    publications,
    abilities,
}: PageProps) {
    const i18n = t();
    const p = i18n.results.publications;
    const codes = i18n.results.reconciliation.codes as Record<string, string>;
    const [publishing, setPublishing] = useState<Kind | null>(null);
    const [busy, setBusy] = useState(false);
    const publishLabel: Record<Kind, string> = {
        annual: p.publishAnnual,
        gpa: p.publishGpa,
        transcript: p.publishTranscript,
    };

    const breadcrumbs: BreadcrumbItem[] = [
        { title: i18n.modules.results, href: '/results' },
        { title: p.title, href: '/results/publications' },
    ];
    const selectedClass =
        classes.find((c) => c.id === filters.class_id) ?? null;
    const sectionName = (id: number | null): string =>
        id === null
            ? p.wholeClass
            : (selectedClass?.sections.find((s) => s.id === id)?.name ??
              String(id));

    const navigate = (next: {
        class_id?: number | null;
        section_id?: number | null;
    }) => {
        router.get(
            '/results/publications',
            {
                academic_year_id: filters.academic_year_id ?? undefined,
                class_id:
                    (next.class_id !== undefined
                        ? next.class_id
                        : filters.class_id) ?? undefined,
                section_id:
                    (next.section_id !== undefined
                        ? next.section_id
                        : filters.section_id) ?? undefined,
            },
            { preserveScroll: true },
        );
    };

    const publish = () => {
        if (
            publishing === null ||
            filters.academic_year_id === null ||
            filters.class_id === null
        )
            return;
        setBusy(true);
        router.post(
            '/results/publications',
            {
                academic_year_id: filters.academic_year_id,
                class_id: filters.class_id,
                section_id: filters.section_id,
                target: publishing,
            },
            {
                preserveScroll: true,
                onFinish: () => {
                    setBusy(false);
                    setPublishing(null);
                },
            },
        );
    };

    const officialCell = (o: Official) =>
        o === null ? (
            <span className="text-muted-foreground">{p.none}</span>
        ) : (
            <span>
                <bdi dir="ltr">{o.value ?? '—'}</bdi>
                {o.incomplete ? (
                    <span className="text-muted-foreground ms-2">
                        ({p.incomplete})
                    </span>
                ) : null}
            </span>
        );

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
        { id: 'annual', header: p.annual, cell: (r) => officialCell(r.annual) },
        { id: 'gpa', header: p.gpa, cell: (r) => officialCell(r.gpa) },
        {
            id: 'transcript',
            header: p.transcript,
            cell: (r) =>
                r.transcript === null ? (
                    <span className="text-muted-foreground">{p.none}</span>
                ) : (
                    <span>
                        <bdi dir="ltr">{r.transcript.number}</bdi>
                        {r.transcript.up_to_date ? null : (
                            <span className="text-muted-foreground ms-2">
                                ({p.transcriptOutdated})
                            </span>
                        )}
                    </span>
                ),
            hideOnMobile: true,
        },
    ];

    const publishButton = (kind: Kind) => {
        const ready = reconciliation.ready[kind] === true;
        return (
            <Button
                type="button"
                disabled={!abilities.publish || !ready || busy}
                onClick={() => setPublishing(kind)}
                aria-describedby="publications-publish-hint"
            >
                {publishLabel[kind]}
            </Button>
        );
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={p.title} />
            <div className="sis-table-theme sis-ops-hub flex min-w-0 flex-col gap-4 p-4">
                <PageHeader
                    title={p.title}
                    description={p.description}
                    icon={<Megaphone className="size-6" aria-hidden />}
                />
                <p>
                    <a
                        href="/results/term-publications"
                        className="sis-ops-hub__link px-3 py-2 text-sm"
                    >
                        {i18n.results.termPublications.link}
                    </a>
                </p>

                <div className="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-end">
                    <OpsYearFilter
                        action="/results/publications"
                        academicYearId={filters.academic_year_id}
                        label={i18n.context.year}
                    />
                    <label className="flex min-w-0 flex-col gap-1 text-sm sm:w-56">
                        <span>{p.classLabel}</span>
                        <SisListSelect
                            ariaLabel={p.classLabel}
                            value={filters.class_id?.toString() ?? ''}
                            includeBlank
                            options={classes.map((c) => ({
                                value: String(c.id),
                                label: c.name,
                            }))}
                            onChange={(v) =>
                                navigate({
                                    class_id: v === '' ? null : Number(v),
                                    section_id: null,
                                })
                            }
                        />
                    </label>
                    {selectedClass && selectedClass.sections.length > 0 ? (
                        <label className="flex min-w-0 flex-col gap-1 text-sm sm:w-56">
                            <span>{p.sectionLabel}</span>
                            <SisListSelect
                                ariaLabel={p.sectionLabel}
                                value={filters.section_id?.toString() ?? ''}
                                options={[
                                    { value: '', label: p.allSections },
                                    ...selectedClass.sections.map((s) => ({
                                        value: String(s.id),
                                        label: s.name,
                                    })),
                                ]}
                                onChange={(v) =>
                                    navigate({
                                        section_id: v === '' ? null : Number(v),
                                    })
                                }
                            />
                        </label>
                    ) : null}
                </div>

                {filters.class_id === null ? (
                    <p className="text-muted-foreground text-sm">
                        {p.chooseClass}
                    </p>
                ) : (
                    <>
                        <section
                            aria-labelledby="publications-reconciliation"
                            className="flex flex-col gap-2"
                        >
                            <h2
                                id="publications-reconciliation"
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
                                {publishButton('annual')}
                                {publishButton('gpa')}
                                {publishButton('transcript')}
                            </div>
                            <p
                                id="publications-publish-hint"
                                className="text-muted-foreground text-sm"
                            >
                                {abilities.publish
                                    ? p.notReadyHint
                                    : p.noPermission}
                            </p>
                        </section>

                        <ActivePublications
                            publications={publications}
                            canWithdraw={abilities.publish}
                            kindLabel={(pub) =>
                                (p.kinds as Record<string, string>)[pub.kind] ??
                                pub.kind
                            }
                            scopeLabel={sectionName}
                        />

                        <DataTable
                            columns={columns}
                            rows={rows}
                            rowKey={(r) => r.enrollment_id}
                            caption={p.tableCaption}
                            emptyTitle={p.chooseClass}
                        />
                    </>
                )}
            </div>

            <ConfirmDialog
                open={publishing !== null}
                title={p.confirmTitle}
                description={`${publishLabel[publishing ?? 'annual']} — ${sectionName(filters.section_id)}. ${p.confirmDescription}`}
                confirmLabel={publishLabel[publishing ?? 'annual']}
                confirmPending={busy}
                onConfirm={publish}
                onOpenChange={(open) => {
                    if (!open && !busy) setPublishing(null);
                }}
            />
        </AppLayout>
    );
}
