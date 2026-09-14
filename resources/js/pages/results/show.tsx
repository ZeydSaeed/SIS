import { Head, Link, router } from '@inertiajs/react';
import { FormEvent, useState } from 'react';
import { ScrollText } from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import { PageHeader } from '@/components/sis/page-header';
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

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Results', href: '/results' },
    { title: 'Summary', href: '/results/show' },
];

export default function ResultsShow({ terms, annual, gpa, filters }: PageProps) {
    const [enrollmentId, setEnrollmentId] = useState(
        filters.enrollment_id?.toString() ?? '',
    );

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
            <Head title="Results summary" />
            <div className="sis-ops-hub flex flex-col gap-4 p-4">
                <PageHeader
                    title="Enrollment results summary"
                    description="Official term rows, annual result, and year GPA for one enrollment."
                    icon={<ScrollText className="size-6" aria-hidden />}
                />
                <form
                    onSubmit={onFilter}
                    className="flex flex-col gap-3 sm:flex-row sm:items-end"
                    aria-label="Filter enrollment results"
                >
                    <label className="flex min-w-0 flex-1 flex-col gap-1 text-sm">
                        <span>Enrollment ID</span>
                        <input
                            type="number"
                            min={1}
                            inputMode="numeric"
                            className="sis-ops-hub__link min-h-11 px-3 py-2"
                            value={enrollmentId}
                            onChange={(e) => setEnrollmentId(e.target.value)}
                        />
                    </label>
                    <button type="submit" className="sis-ops-hub__link min-h-11 px-4 py-2 text-sm">
                        Load summary
                    </button>
                </form>

                <section aria-labelledby="annual-heading" className="sis-ops-hub__section">
                    <h2 id="annual-heading" className="sis-ops-hub__section-title">
                        Annual result
                    </h2>
                    {annual ? (
                        <dl className="grid gap-2 text-sm sm:grid-cols-2">
                            <div>
                                <dt className="opacity-70">Average weighted total</dt>
                                <dd>{annual.average_weighted_total ?? '—'}</dd>
                            </div>
                            <div>
                                <dt className="opacity-70">Version</dt>
                                <dd>{annual.result_version}</dd>
                            </div>
                            <div>
                                <dt className="opacity-70">Incomplete</dt>
                                <dd>{annual.incomplete ? 'Yes' : 'No'}</dd>
                            </div>
                        </dl>
                    ) : (
                        <p className="text-sm opacity-80">No official annual result loaded.</p>
                    )}
                </section>

                <section aria-labelledby="gpa-heading" className="sis-ops-hub__section">
                    <h2 id="gpa-heading" className="sis-ops-hub__section-title">
                        Year GPA
                    </h2>
                    {gpa ? (
                        <dl className="grid gap-2 text-sm sm:grid-cols-2">
                            <div>
                                <dt className="opacity-70">GPA</dt>
                                <dd>
                                    {gpa.gpa_value ?? '—'} ({gpa.scale_code})
                                </dd>
                            </div>
                            <div>
                                <dt className="opacity-70">Version</dt>
                                <dd>{gpa.result_version}</dd>
                            </div>
                            <div>
                                <dt className="opacity-70">Incomplete</dt>
                                <dd>{gpa.incomplete ? 'Yes' : 'No'}</dd>
                            </div>
                        </dl>
                    ) : (
                        <p className="text-sm opacity-80">No official GPA loaded.</p>
                    )}
                </section>

                <section aria-labelledby="terms-heading" className="sis-ops-hub__section">
                    <h2 id="terms-heading" className="sis-ops-hub__section-title">
                        Term subjects
                    </h2>
                    {terms.length === 0 ? (
                        <p className="text-sm opacity-80">No term subject rows for this filter.</p>
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
                                            Term {row.term_id} · Subject {row.subject_id}
                                        </span>
                                        <span className="sis-ops-hub__link-desc">
                                            Total {row.weighted_total ?? '—'}
                                            {row.incomplete ? ' · Incomplete' : ''}
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
