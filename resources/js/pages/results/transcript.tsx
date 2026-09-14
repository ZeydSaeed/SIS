import { Head, router } from '@inertiajs/react';
import { FormEvent, useState } from 'react';
import { FileBadge } from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import { PageHeader } from '@/components/sis/page-header';
import { t } from '@/i18n';
import type { BreadcrumbItem } from '@/types';

type PageProps = {
    transcript: {
        school_id: number;
        enrollment_id: number;
        student_id: number;
        academic_year_id: number;
        transcript_id: number;
        transcript_version: number;
        transcript_number: string;
        payload_hash: string;
        issued_at: string;
    } | null;
    filters: {
        academic_year_id: number | null;
        enrollment_id: number | null;
    };
};

export default function ResultsTranscript({ transcript, filters }: PageProps) {
    const i18n = t();
    const [enrollmentId, setEnrollmentId] = useState(filters.enrollment_id?.toString() ?? '');

    const breadcrumbs: BreadcrumbItem[] = [
        { title: i18n.modules.results, href: '/results' },
        { title: i18n.results.transcriptBreadcrumb, href: '/results/transcript' },
    ];

    const onFilter = (event: FormEvent) => {
        event.preventDefault();
        const parsed = Number.parseInt(enrollmentId, 10);
        router.get(
            '/results/transcript',
            {
                academic_year_id: filters.academic_year_id ?? undefined,
                enrollment_id: Number.isFinite(parsed) && parsed > 0 ? parsed : undefined,
            },
            { preserveState: true, preserveScroll: true },
        );
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={i18n.results.transcriptTitle} />
            <div className="sis-ops-hub flex flex-col gap-4 p-4">
                <PageHeader
                    title={i18n.results.transcriptTitle}
                    description={i18n.results.transcriptDesc}
                    icon={<FileBadge className="size-6" aria-hidden />}
                />
                <form
                    onSubmit={onFilter}
                    className="flex flex-col gap-3 sm:flex-row sm:items-end"
                    aria-label={i18n.common.filterTranscript}
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
                        {i18n.results.loadTranscript}
                    </button>
                </form>
                {transcript ? (
                    <dl className="grid gap-3 text-sm sm:grid-cols-2">
                        <div>
                            <dt className="opacity-70">{i18n.results.transcriptNumber}</dt>
                            <dd>
                                <span dir="ltr">{transcript.transcript_number}</span>
                            </dd>
                        </div>
                        <div>
                            <dt className="opacity-70">{i18n.enrollments.student}</dt>
                            <dd>
                                <span dir="ltr">{transcript.student_id}</span>
                            </dd>
                        </div>
                        <div>
                            <dt className="opacity-70">{i18n.results.version}</dt>
                            <dd>
                                <span dir="ltr">{transcript.transcript_version}</span>
                            </dd>
                        </div>
                        <div>
                            <dt className="opacity-70">{i18n.results.issuedAt}</dt>
                            <dd>
                                <span dir="ltr">{transcript.issued_at}</span>
                            </dd>
                        </div>
                    </dl>
                ) : (
                    <p className="text-sm opacity-80">
                        {filters.enrollment_id ? i18n.results.noTranscript : i18n.results.enterEnrollment}
                    </p>
                )}
            </div>
        </AppLayout>
    );
}
