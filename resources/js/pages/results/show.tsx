import { Head, Link } from '@inertiajs/react';
import { ScrollText } from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import { PageHeader } from '@/components/sis/page-header';
import { ResultsStudentPicker, type ResultsPicker } from '@/components/results/results-student-picker';
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
    picker: ResultsPicker;
    names: { terms: Record<number, string>; subjects: Record<number, string> };
};

export default function ResultsShow({ terms, annual, gpa, filters, picker, names }: PageProps) {
    const i18n = t();

    const breadcrumbs: BreadcrumbItem[] = [
        { title: i18n.modules.results, href: '/results' },
        { title: i18n.results.summaryBreadcrumb, href: '/results/show' },
    ];


    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={i18n.results.summaryHead} />
            <div className="sis-ops-hub flex flex-col gap-4 p-4" dir="rtl" lang="ar">
                <PageHeader
                    title={i18n.results.summaryTitle}
                    description={i18n.results.summaryDesc}
                    icon={<ScrollText className="size-6" aria-hidden />}
                />
                <ResultsStudentPicker path="/results/show" academicYearId={filters.academic_year_id} picker={picker} />

                <section aria-labelledby="annual-heading" className="sis-ops-hub__section" dir="rtl" lang="ar">
                    <h2 id="annual-heading" className="sis-ops-hub__section-title" dir="rtl" lang="ar">
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

                <section aria-labelledby="gpa-heading" className="sis-ops-hub__section" dir="rtl" lang="ar">
                    <h2 id="gpa-heading" className="sis-ops-hub__section-title" dir="rtl" lang="ar">
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

                <section aria-labelledby="terms-heading" className="sis-ops-hub__section" dir="rtl" lang="ar">
                    <h2 id="terms-heading" className="sis-ops-hub__section-title" dir="rtl" lang="ar">
                        {i18n.results.termSubjects}
                    </h2>
                    {terms.length === 0 ? (
                        <p className="text-sm opacity-80">{i18n.results.noTerms}</p>
                    ) : (
                        <ul className="sis-ops-hub__links" dir="rtl" lang="ar">
                            {terms.map((row) => (
                                <li key={row.term_result_id}>
                                    <Link
                                        href={`/results/term?enrollment_id=${filters.enrollment_id}&academic_year_id=${filters.academic_year_id}&term_id=${row.term_id}&subject_id=${row.subject_id}`}
                                        className="sis-ops-hub__link" dir="rtl" lang="ar"
                                        prefetch
                                    >
                                        <span className="sis-ops-hub__link-title" dir="rtl" lang="ar">
                                            {names.terms[row.term_id] ?? row.term_id} ·{' '}
                                            {names.subjects[row.subject_id] ?? row.subject_id}
                                        </span>
                                        <span className="sis-ops-hub__link-desc" dir="rtl" lang="ar">
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
