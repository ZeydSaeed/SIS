import { Head, router } from '@inertiajs/react';
import { FormEvent, useState } from 'react';
import { FileBadge } from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import { PageHeader } from '@/components/sis/page-header';
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

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Results', href: '/results' },
    { title: 'Transcript', href: '/results/transcript' },
];

export default function ResultsTranscript({ transcript, filters }: PageProps) {
    const [enrollmentId, setEnrollmentId] = useState(filters.enrollment_id?.toString() ?? '');

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
            <Head title="Issued transcript" />
            <div className="sis-ops-hub flex flex-col gap-4 p-4">
                <PageHeader
                    title="Issued transcript metadata"
                    description="Official issued transcript identity only. PDF download remains on HOLD."
                    icon={<FileBadge className="size-6" aria-hidden />}
                />
                <form
                    onSubmit={onFilter}
                    className="flex flex-col gap-3 sm:flex-row sm:items-end"
                    aria-label="Filter transcript"
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
                        Load transcript
                    </button>
                </form>
                {transcript ? (
                    <dl className="grid gap-3 text-sm sm:grid-cols-2">
                        <div>
                            <dt className="opacity-70">Transcript number</dt>
                            <dd>{transcript.transcript_number}</dd>
                        </div>
                        <div>
                            <dt className="opacity-70">Student</dt>
                            <dd>{transcript.student_id}</dd>
                        </div>
                        <div>
                            <dt className="opacity-70">Version</dt>
                            <dd>{transcript.transcript_version}</dd>
                        </div>
                        <div>
                            <dt className="opacity-70">Issued at</dt>
                            <dd>{transcript.issued_at}</dd>
                        </div>
                    </dl>
                ) : (
                    <p className="text-sm opacity-80">
                        {filters.enrollment_id
                            ? 'No issued transcript for this enrollment and year.'
                            : 'Enter an enrollment ID to load issued transcript metadata.'}
                    </p>
                )}
            </div>
        </AppLayout>
    );
}
