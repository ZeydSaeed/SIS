import { router } from '@inertiajs/react';
import {
    AttendanceTable,
    useAttendanceMessages,
    type AttendanceColumn,
} from '@/components/attendance/attendance-chrome';
import { ExamisPage } from '@/components/examis/examis-chrome';
import {
    ResultsStudentPicker,
    useResultsStudentChrome,
    type ResultsPicker,
} from '@/components/results/results-student-picker';
import { t } from '@/i18n';
import AppLayout from '@/layouts/app-layout';
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

export default function ResultsShow(props: PageProps) {
    const i18n = t();
    const breadcrumbs: BreadcrumbItem[] = [
        { title: i18n.modules.results, href: '/results' },
        { title: i18n.results.summaryBreadcrumb, href: '/results/show' },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <SummaryBody {...props} />
        </AppLayout>
    );
}

/** The chosen student's official annual result, year GPA and term results. */
function SummaryBody({
    terms,
    annual,
    gpa,
    filters,
    picker,
    names,
}: PageProps) {
    const i18n = t();
    const r = i18n.results;
    useResultsStudentChrome('summary', filters.academic_year_id, picker);
    useAttendanceMessages(
        picker.student === null &&
            picker.q === '' &&
            picker.candidates.length === 0
            ? [{ key: 'pick-student', text: r.pickStudent, tone: 'info' }]
            : [],
        r.summaryTitle,
    );

    const columns: AttendanceColumn<TermRow>[] = [
        {
            id: 'term',
            header: r.term,
            kind: 'text',
            cell: (row) => names.terms[row.term_id] ?? String(row.term_id),
        },
        {
            id: 'subject',
            header: r.subject,
            kind: 'name',
            cell: (row) =>
                names.subjects[row.subject_id] ?? String(row.subject_id),
        },
        {
            id: 'total',
            header: r.weightedTotal,
            kind: 'num',
            cell: (row) => <span dir="ltr">{row.weighted_total ?? '—'}</span>,
        },
        {
            id: 'incomplete',
            header: r.incomplete,
            kind: 'text',
            cell: (row) => (row.incomplete ? i18n.common.yes : i18n.common.no),
        },
    ];

    return (
        <ExamisPage title={r.summaryTitle} windowId="results.show">
            <ResultsStudentPicker
                path="/results/show"
                academicYearId={filters.academic_year_id}
                picker={picker}
            />
            {picker.student ? (
                <>
                    <dl className="flex flex-wrap gap-x-6 gap-y-1 text-sm">
                        <div className="flex gap-1" data-annual>
                            <dt className="font-semibold">{r.annual}:</dt>
                            <dd>
                                {annual ? (
                                    <span dir="ltr">
                                        {annual.average_weighted_total ?? '—'}
                                    </span>
                                ) : (
                                    r.noAnnual
                                )}
                                {annual
                                    ? ` · ${r.version} ${annual.result_version}${annual.incomplete ? ` · ${r.incomplete}` : ''}`
                                    : ''}
                            </dd>
                        </div>
                        <div className="flex gap-1" data-gpa>
                            <dt className="font-semibold">{r.yearGpa}:</dt>
                            <dd>
                                {gpa ? (
                                    <span dir="ltr">
                                        {gpa.gpa_value ?? '—'} ({gpa.scale_code}
                                        )
                                    </span>
                                ) : (
                                    r.noGpa
                                )}
                                {gpa
                                    ? ` · ${r.version} ${gpa.result_version}${gpa.incomplete ? ` · ${r.incomplete}` : ''}`
                                    : ''}
                            </dd>
                        </div>
                    </dl>
                    <AttendanceTable
                        caption={r.termSubjects}
                        storageKey="results.show"
                        rows={terms}
                        rowKey={(row) => row.term_result_id}
                        columns={columns}
                        fit={false}
                        onOpen={(row) =>
                            router.visit(
                                `/results/term?enrollment_id=${filters.enrollment_id}&academic_year_id=${filters.academic_year_id}&term_id=${row.term_id}&subject_id=${row.subject_id}`,
                            )
                        }
                        emptyText={r.noTerms}
                    />
                </>
            ) : null}
        </ExamisPage>
    );
}
