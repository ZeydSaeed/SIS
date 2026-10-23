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

type ResultRow = {
    school_id: number;
    enrollment_id: number;
    academic_year_id: number;
    term_result_id: number;
    term_id: number;
    subject_id: number;
    weighted_total: string | null;
    pass_fail: number | null;
    incomplete: boolean;
};

type PageProps = {
    results: {
        data: ResultRow[];
    };
    filters: {
        academic_year_id: number | null;
        enrollment_id: number | null;
    };
    picker: ResultsPicker;
    names: { terms: Record<number, string>; subjects: Record<number, string> };
};

export default function ResultsIndex(props: PageProps) {
    const i18n = t();
    const breadcrumbs: BreadcrumbItem[] = [
        { title: i18n.modules.results, href: '/results' },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <ResultsBody {...props} />
        </AppLayout>
    );
}

/** «نتائج الطالب» — the chosen student's official term results; double-click opens one term / subject. */
function ResultsBody({ results, filters, picker, names }: PageProps) {
    const i18n = t();
    const r = i18n.results;
    useResultsStudentChrome('terms', filters.academic_year_id, picker);
    useAttendanceMessages(
        picker.student === null && picker.q === ''
            ? [{ key: 'pick-student', text: r.pickStudent, tone: 'info' }]
            : [],
        r.title,
    );

    const columns: AttendanceColumn<ResultRow>[] = [
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
            id: 'pass',
            header: r.passFail,
            kind: 'num',
            cell: (row) => (row.pass_fail === null ? '—' : row.pass_fail),
        },
        {
            id: 'incomplete',
            header: r.incomplete,
            kind: 'text',
            cell: (row) => (row.incomplete ? i18n.common.yes : i18n.common.no),
        },
    ];

    return (
        <ExamisPage title={r.title} windowId="results.index">
            <ResultsStudentPicker
                path="/results"
                academicYearId={filters.academic_year_id}
                picker={picker}
            />
            {picker.student ? (
                <AttendanceTable
                    caption={r.termCaption}
                    storageKey="results.index"
                    rows={results.data}
                    rowKey={(row) => row.term_result_id}
                    columns={columns}
                    fit={false}
                    onOpen={(row) =>
                        router.visit(
                            `/results/term?enrollment_id=${row.enrollment_id}&academic_year_id=${row.academic_year_id}&term_id=${row.term_id}&subject_id=${row.subject_id}`,
                        )
                    }
                    emptyText={r.noResultsDesc}
                />
            ) : null}
        </ExamisPage>
    );
}
