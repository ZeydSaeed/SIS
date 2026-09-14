import { Head, Link, router } from '@inertiajs/react';
import { FormEvent, useState } from 'react';
import { ScrollText } from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import { PageHeader } from '@/components/sis/page-header';
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
};

export default function ResultsTerm({ term, filters }: PageProps) {
    const i18n = t();
    const [enrollmentId, setEnrollmentId] = useState(filters.enrollment_id?.toString() ?? '');
    const [termId, setTermId] = useState(filters.term_id?.toString() ?? '');
    const [subjectId, setSubjectId] = useState(filters.subject_id?.toString() ?? '');

    const breadcrumbs: BreadcrumbItem[] = [
        { title: i18n.modules.results, href: '/results' },
        { title: i18n.results.termDetailBreadcrumb, href: '/results/term' },
    ];

    const onFilter = (event: FormEvent) => {
        event.preventDefault();
        router.get(
            '/results/term',
            {
                academic_year_id: filters.academic_year_id ?? undefined,
                enrollment_id: Number.parseInt(enrollmentId, 10) || undefined,
                term_id: Number.parseInt(termId, 10) || undefined,
                subject_id: Number.parseInt(subjectId, 10) || undefined,
            },
            { preserveState: true, preserveScroll: true },
        );
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={i18n.results.termDetail} />
            <div className="sis-ops-hub flex flex-col gap-4 p-4">
                <PageHeader
                    title={i18n.results.termSingleTitle}
                    description={i18n.results.termSingleDesc}
                    icon={<ScrollText className="size-6" aria-hidden />}
                />
                <p>
                    <Link href="/results/show" className="sis-ops-hub__link px-3 py-2 text-sm" prefetch>
                        {i18n.results.backSummary}
                    </Link>
                </p>
                <form
                    onSubmit={onFilter}
                    className="grid gap-3 sm:grid-cols-3"
                    aria-label={i18n.common.filterTermResult}
                >
                    <label className="flex flex-col gap-1 text-sm">
                        <span>{i18n.results.enrollmentId}</span>
                        <input
                            type="number"
                            min={1}
                            className="sis-ops-hub__link min-h-11 px-3 py-2"
                            value={enrollmentId}
                            onChange={(e) => setEnrollmentId(e.target.value)}
                            dir="ltr"
                        />
                    </label>
                    <label className="flex flex-col gap-1 text-sm">
                        <span>{i18n.results.termId}</span>
                        <input
                            type="number"
                            min={1}
                            className="sis-ops-hub__link min-h-11 px-3 py-2"
                            value={termId}
                            onChange={(e) => setTermId(e.target.value)}
                            dir="ltr"
                        />
                    </label>
                    <label className="flex flex-col gap-1 text-sm">
                        <span>{i18n.results.subjectId}</span>
                        <input
                            type="number"
                            min={1}
                            className="sis-ops-hub__link min-h-11 px-3 py-2"
                            value={subjectId}
                            onChange={(e) => setSubjectId(e.target.value)}
                            dir="ltr"
                        />
                    </label>
                    <button type="submit" className="sis-ops-hub__link min-h-11 px-4 py-2 text-sm sm:col-span-3">
                        {i18n.results.loadTerm}
                    </button>
                </form>
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
