import { useAttendanceMessages } from '@/components/attendance/attendance-chrome';
import { ExamisPage } from '@/components/examis/examis-chrome';
import {
    ResultsStudentPicker,
    useResultsStudentChrome,
    type ResultsPicker,
} from '@/components/results/results-student-picker';
import { t } from '@/i18n';
import AppLayout from '@/layouts/app-layout';
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

export default function ResultsTranscript(props: PageProps) {
    const i18n = t();
    const breadcrumbs: BreadcrumbItem[] = [
        { title: i18n.modules.results, href: '/results' },
        {
            title: i18n.results.transcriptBreadcrumb,
            href: '/results/transcript',
        },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <TranscriptBody {...props} />
        </AppLayout>
    );
}

/** The chosen student's current issued transcript (metadata; transcripts are issued from the annual / GPA sheet). */
function TranscriptBody({ transcript, filters, picker }: PageProps) {
    const r = t().results;
    useResultsStudentChrome('transcript', filters.academic_year_id, picker);
    useAttendanceMessages(
        picker.student === null &&
            picker.q === '' &&
            picker.candidates.length === 0
            ? [{ key: 'pick-student', text: r.pickStudent, tone: 'info' }]
            : [],
        r.transcriptTitle,
    );

    return (
        <ExamisPage title={r.transcriptTitle} windowId="results.transcript">
            <ResultsStudentPicker
                path="/results/transcript"
                academicYearId={filters.academic_year_id}
                picker={picker}
            />
            {picker.student ? (
                transcript ? (
                    <dl className="flex flex-wrap gap-x-6 gap-y-1 text-sm">
                        <div className="flex gap-1">
                            <dt className="font-semibold">
                                {r.transcriptNumber}:
                            </dt>
                            <dd dir="ltr">{transcript.transcript_number}</dd>
                        </div>
                        <div className="flex gap-1">
                            <dt className="font-semibold">{r.version}:</dt>
                            <dd dir="ltr">{transcript.transcript_version}</dd>
                        </div>
                        <div className="flex gap-1">
                            <dt className="font-semibold">{r.issuedAt}:</dt>
                            <dd dir="ltr">{transcript.issued_at}</dd>
                        </div>
                    </dl>
                ) : (
                    <p className="text-sm">{r.noTranscript}</p>
                )
            ) : null}
        </ExamisPage>
    );
}
