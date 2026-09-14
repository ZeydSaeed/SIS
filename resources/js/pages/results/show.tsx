import { Head, Link, router } from '@inertiajs/react';
import { FormEvent, useState } from 'react';
import { ScrollText } from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import { PageHeader } from '@/components/sis/page-header';
import { t } from '@/i18n';
import type { BreadcrumbItem } from '@/types';

type TermRow = {
    term_result_id: number;
    term_id: number;
    subject_id: number;
    weighted_total: string | null;
    pass_fail: number | null;
    incomplete: boolean;
};

type PageProps = {
    terms: TermRow[];
    annual: {
        annual_result_id: number;
        result_version: number;
        average_weighted_total: string | null;
        incomplete: boolean;
    } | null;
    gpa: {
        gpa_result_id: number;
        result_version: number;
        gpa_value: string | null;
        scale_code: string;
        incomplete: boolean;
    } | null;
    filters: {
        academic_year_id: number | null;
        enrollment_id: number | null;
    };
};

export default function ResultsShow({ terms, annual, gpa, filters }: PageProps) {
    const i18n = t();
    const [enrollmentId, setEnrollmentId] = useState(filters.enrollment_id?.toString() ?? '');

    const breadcrumbs: BreadcrumbItem[] = [
        { title: i18n.modules.results, href: '/results' },
        { title: i18n.results.summaryBreadcrumb, href: '/results/show' },
    ];

    const onFilter = (event: FormEvent) => {
        event.preventDefault();
        const parsed = Number.parseInt(enrollmentId, 10);
        router.get(
            '/results/show',
            {
                academic_year_id: filters.academic_year_id ?? undefined,
                enrollment_id: Number.isFinite(parsed) && parsed > 0 ? parsed : undefined,
            },
            { preserveState: true, preserveScroll: true },
        );
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={i18n.results.summaryHead} />
            <div className="sis-ops-hub flex flex-col gap-4 p-4">
                <PageHeader
                    title={i18n.results.summaryTitle}
                    description={i18n.results.summaryDesc}
                    icon={<ScrollText className="size-6" aria-hidden />}
                />
                <form
                    onSubmit={onFilter}
                    className="flex flex-col gap-3 sm:flex-row sm:items-end"
                    aria-label={i18n.common.filterEnrollmentResults}
                >
                    <label className="flex min-w-0 flex-1 flex-col gap-1 text-sm">
                        <span>{i18n.results.enrollmentId}</span>
                        <input
                            type="number"
                            min={1}
                            inputMode="numeric"
                            className="sis-ops-hub__link min-h-11 px-3 py-2"
                            value={enrollmentId}
                            onChange={(e) => setEnrollmentId(e.target.value)}
                            dir="ltr"
                        />
                    </label>
                    <button type="submit" className="sis-ops-hub__link min-h-11 px-4 py-2 text-sm">
                        {i18n.results.loadSummary}
                    </button>
                </form>

                <section aria-labelledby="annual-heading" className="sis-ops-hub__section">
                    <h2 id="annual-heading" className="sis-ops-hub__section-title">
                        {i18n.results.annual}
                    </h2>
                    {annual ? (
                        <dl className="grid gap-2 text-sm sm:grid-cols-2">
                            <div>
                                <dt className="opacity-70">{i18n.results.averageWeightedTotal}</dt>
                                <dd>
                                    <span dir="ltr">{annual.average_weighted_total ?? '—'}</span>
                                </dd>
                            </div>
                            <div>
                                <dt className="opacity-70">{i18n.results.version}</dt>
                                <dd>
                                    <span dir="ltr">{annual.result_version}</span>
                                </dd>
                            </div>
                            <div>
                                <dt className="opacity-70">{i18n.results.incomplete}</dt>
                                <dd>{annual.incomplete ? i18n.common.yes : i18n.common.no}</dd>
                            </div>
                        </dl>
                    ) : (
                        <p className="text-sm opacity-80">{i18n.results.noAnnual}</p>
                    )}
                </section>

                <section aria-labelledby="gpa-heading" className="sis-ops-hub__section">
                    <h2 id="gpa-heading" className="sis-ops-hub__section-title">
                        {i18n.results.yearGpa}
                    </h2>
                    {gpa ? (
                        <dl className="grid gap-2 text-sm sm:grid-cols-2">
                            <div>
                                <dt className="opacity-70">{i18n.results.gpaLabel}</dt>
                                <dd>
                                    <span dir="ltr">
                                        {gpa.gpa_value ?? '—'} ({gpa.scale_code})
                                    </span>
                                </dd>
                            </div>
                            <div>
                                <dt className="opacity-70">{i18n.results.version}</dt>
                                <dd>
                                    <span dir="ltr">{gpa.result_version}</span>
                                </dd>
                            </div>
                            <div>
                                <dt className="opacity-70">{i18n.results.incomplete}</dt>
                                <dd>{gpa.incomplete ? i18n.common.yes : i18n.common.no}</dd>
                            </div>
                        </dl>
                    ) : (
                        <p className="text-sm opacity-80">{i18n.results.noGpa}</p>
                    )}
                </section>

                <section aria-labelledby="terms-heading" className="sis-ops-hub__section">
                    <h2 id="terms-heading" className="sis-ops-hub__section-title">
                        {i18n.results.termSubjects}
                    </h2>
                    {terms.length === 0 ? (
                        <p className="text-sm opacity-80">{i18n.results.noTerms}</p>
                    ) : (
                        <ul className="sis-ops-hub__links">
                            {terms.map((row) => (
                                <li key={row.term_result_id}>
                                    <Link
                                        href={`/results/term?enrollment_id=${filters.enrollment_id}&academic_year_id=${filters.academic_year_id}&term_id=${row.term_id}&subject_id=${row.subject_id}`}
                                        className="sis-ops-hub__link"
                                        prefetch
                                    >
                                        <span className="sis-ops-hub__link-title">
                                            {i18n.results.term}{' '}
                                            <span dir="ltr">{row.term_id}</span> · {i18n.results.subject}{' '}
                                            <span dir="ltr">{row.subject_id}</span>
                                        </span>
                                        <span className="sis-ops-hub__link-desc">
                                            {i18n.results.totalShort}{' '}
                                            <span dir="ltr">{row.weighted_total ?? '—'}</span>
                                            {row.incomplete ? ` · ${i18n.results.incomplete}` : ''}
                                        </span>
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    )}
                </section>
            </div>
        </AppLayout>
    );
}
