import { Head, Link } from '@inertiajs/react';
import { ScrollText } from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import { PageHeader } from '@/components/sis/page-header';
import type { ResultsPicker } from '@/components/results/results-student-picker';
import { t } from '@/i18n';
import type { BreadcrumbItem } from '@/types';

type PageProps = {
    term: {
        school_id: number;
        enrollment_id: number;
        academic_year_id: number;
        term_id: number;
        subject_id: number;
        term_result_id: number;
        result_version: number;
        weighted_total: string | null;
        incomplete: boolean;
        source_fingerprint: string;
    } | null;
    filters: {
        academic_year_id: number | null;
        enrollment_id: number | null;
        term_id: number | null;
        subject_id: number | null;
    };
    picker: ResultsPicker;
    names: { terms: Record<number, string>; subjects: Record<number, string> };
};

export default function ResultsTerm({ term, filters, picker, names }: PageProps) {
    const i18n = t();

    const breadcrumbs: BreadcrumbItem[] = [
        { title: i18n.modules.results, href: '/results' },
        { title: i18n.results.termDetailBreadcrumb, href: '/results/term' },
    ];


    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={i18n.results.termDetail} />
            <div className="sis-ops-hub flex flex-col gap-4 p-4" dir="rtl" lang="ar">
                <PageHeader
                    title={i18n.results.termSingleTitle}
                    description={i18n.results.termSingleDesc}
                    icon={<ScrollText className="size-6" aria-hidden />}
                />
                <p>
                    <Link
                        href={`/results/show?academic_year_id=${filters.academic_year_id ?? ''}&enrollment_id=${filters.enrollment_id ?? ''}`}
                        className="sis-ops-hub__link px-3 py-2 text-sm"
                        prefetch
                    >
                        {i18n.results.backSummary}
                    </Link>
                </p>
                <dl className="grid gap-3 text-sm sm:grid-cols-3" data-term-context>
                    <div>
                        <dt className="opacity-70">{i18n.results.picker.current}</dt>
                        <dd>{picker.student?.student_name ?? '—'}</dd>
                    </div>
                    <div>
                        <dt className="opacity-70">{i18n.results.term}</dt>
                        <dd>{filters.term_id !== null ? (names.terms[filters.term_id] ?? filters.term_id) : '—'}</dd>
                    </div>
                    <div>
                        <dt className="opacity-70">{i18n.results.subject}</dt>
                        <dd>{filters.subject_id !== null ? (names.subjects[filters.subject_id] ?? filters.subject_id) : '—'}</dd>
                    </div>
                </dl>
                {term ? (
                    <dl className="grid gap-3 text-sm sm:grid-cols-2">
                        <div>
                            <dt className="opacity-70">{i18n.results.termSubjectSlash}</dt>
                            <dd>
                                <span dir="ltr">
                                    {term.term_id} / {term.subject_id}
                                </span>
                            </dd>
                        </div>
                        <div>
                            <dt className="opacity-70">{i18n.results.weightedTotal}</dt>
                            <dd>
                                <span dir="ltr">{term.weighted_total ?? '—'}</span>
                            </dd>
                        </div>
                        <div>
                            <dt className="opacity-70">{i18n.results.version}</dt>
                            <dd>
                                <span dir="ltr">{term.result_version}</span>
                            </dd>
                        </div>
                        <div>
                            <dt className="opacity-70">{i18n.results.incomplete}</dt>
                            <dd>{term.incomplete ? i18n.common.yes : i18n.common.no}</dd>
                        </div>
                    </dl>
                ) : (
                    <p className="text-sm opacity-80">{i18n.results.noTerm}</p>
                )}
            </div>
        </AppLayout>
    );
}
