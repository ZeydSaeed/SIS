import { Head, Link, router } from '@inertiajs/react';
import { FormEvent, useState } from 'react';
import { ScrollText } from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import { PageHeader } from '@/components/sis/page-header';
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

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Results', href: '/results' },
    { title: 'Term detail', href: '/results/term' },
];

export default function ResultsTerm({ term, filters }: PageProps) {
    const [enrollmentId, setEnrollmentId] = useState(filters.enrollment_id?.toString() ?? '');
    const [termId, setTermId] = useState(filters.term_id?.toString() ?? '');
    const [subjectId, setSubjectId] = useState(filters.subject_id?.toString() ?? '');

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
            <Head title="Term result detail" />
            <div className="sis-ops-hub flex flex-col gap-4 p-4">
                <PageHeader
                    title="Official term result"
                    description="Single official term-subject result for an enrollment."
                    icon={<ScrollText className="size-6" aria-hidden />}
                />
                <p>
                    <Link href="/results/show" className="sis-ops-hub__link px-3 py-2 text-sm" prefetch>
                        Back to summary
                    </Link>
                </p>
                <form
                    onSubmit={onFilter}
                    className="grid gap-3 sm:grid-cols-3"
                    aria-label="Filter term result"
                >
                    <label className="flex flex-col gap-1 text-sm">
                        <span>Enrollment ID</span>
                        <input
                            type="number"
                            min={1}
                            className="sis-ops-hub__link min-h-11 px-3 py-2"
                            value={enrollmentId}
                            onChange={(e) => setEnrollmentId(e.target.value)}
                        />
                    </label>
                    <label className="flex flex-col gap-1 text-sm">
                        <span>Term ID</span>
                        <input
                            type="number"
                            min={1}
                            className="sis-ops-hub__link min-h-11 px-3 py-2"
                            value={termId}
                            onChange={(e) => setTermId(e.target.value)}
                        />
                    </label>
                    <label className="flex flex-col gap-1 text-sm">
                        <span>Subject ID</span>
                        <input
                            type="number"
                            min={1}
                            className="sis-ops-hub__link min-h-11 px-3 py-2"
                            value={subjectId}
                            onChange={(e) => setSubjectId(e.target.value)}
                        />
                    </label>
                    <button type="submit" className="sis-ops-hub__link min-h-11 px-4 py-2 text-sm sm:col-span-3">
                        Load term result
                    </button>
                </form>
                {term ? (
                    <dl className="grid gap-3 text-sm sm:grid-cols-2">
                        <div>
                            <dt className="opacity-70">Term / Subject</dt>
                            <dd>
                                {term.term_id} / {term.subject_id}
                            </dd>
                        </div>
                        <div>
                            <dt className="opacity-70">Weighted total</dt>
                            <dd>{term.weighted_total ?? '—'}</dd>
                        </div>
                        <div>
                            <dt className="opacity-70">Version</dt>
                            <dd>{term.result_version}</dd>
                        </div>
                        <div>
                            <dt className="opacity-70">Incomplete</dt>
                            <dd>{term.incomplete ? 'Yes' : 'No'}</dd>
                        </div>
                    </dl>
                ) : (
                    <p className="text-sm opacity-80">No matching official term result.</p>
                )}
            </div>
        </AppLayout>
    );
}
