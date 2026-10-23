import { Head } from '@inertiajs/react';
import { FileBadge } from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import { PageHeader } from '@/components/sis/page-header';
import { ResultsStudentPicker, type ResultsPicker } from '@/components/results/results-student-picker';
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
    picker: ResultsPicker;
};

export default function ResultsTranscript({ transcript, filters, picker }: PageProps) {
    const i18n = t();

    const breadcrumbs: BreadcrumbItem[] = [
        { title: i18n.modules.results, href: '/results' },
        { title: i18n.results.transcriptBreadcrumb, href: '/results/transcript' },
    ];


    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={i18n.results.transcriptTitle} />
            <div className="sis-ops-hub flex flex-col gap-4 p-4" dir="rtl" lang="ar">
                <PageHeader
                    title={i18n.results.transcriptTitle}
                    description={i18n.results.transcriptDesc}
                    icon={<FileBadge className="size-6" aria-hidden />}
                />
                <ResultsStudentPicker path="/results/transcript" academicYearId={filters.academic_year_id} picker={picker} />
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
