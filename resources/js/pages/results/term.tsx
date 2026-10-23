import { ExamisPage } from '@/components/examis/examis-chrome';
import {
    useResultsStudentChrome,
    type ResultsPicker,
} from '@/components/results/results-student-picker';
import { t } from '@/i18n';
import AppLayout from '@/layouts/app-layout';
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

export default function ResultsTerm(props: PageProps) {
    const i18n = t();
    const breadcrumbs: BreadcrumbItem[] = [
        { title: i18n.modules.results, href: '/results' },
        { title: i18n.results.termDetailBreadcrumb, href: '/results/term' },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <TermBody {...props} />
        </AppLayout>
    );
}

/** One official term result of the chosen student (opened from the term list or the summary). */
function TermBody({ term, filters, picker, names }: PageProps) {
    const i18n = t();
    const r = i18n.results;
    useResultsStudentChrome('terms', filters.academic_year_id, picker);

    return (
        <ExamisPage title={r.termSingleTitle} windowId="results.term">
            <dl
                className="flex flex-wrap gap-x-6 gap-y-1 text-sm"
                data-term-context
            >
                <div className="flex gap-1">
                    <dt className="font-semibold">{r.picker.current}:</dt>
                    <dd>{picker.student?.student_name ?? '—'}</dd>
                </div>
                <div className="flex gap-1">
                    <dt className="font-semibold">{r.term}:</dt>
                    <dd>
                        {filters.term_id !== null
                            ? (names.terms[filters.term_id] ?? filters.term_id)
                            : '—'}
                    </dd>
                </div>
                <div className="flex gap-1">
                    <dt className="font-semibold">{r.subject}:</dt>
                    <dd>
                        {filters.subject_id !== null
                            ? (names.subjects[filters.subject_id] ??
                              filters.subject_id)
                            : '—'}
                    </dd>
                </div>
            </dl>
            {term ? (
                <dl className="flex flex-wrap gap-x-6 gap-y-1 text-sm">
                    <div className="flex gap-1">
                        <dt className="font-semibold">{r.weightedTotal}:</dt>
                        <dd dir="ltr">{term.weighted_total ?? '—'}</dd>
                    </div>
                    <div className="flex gap-1">
                        <dt className="font-semibold">{r.version}:</dt>
                        <dd dir="ltr">{term.result_version}</dd>
                    </div>
                    <div className="flex gap-1">
                        <dt className="font-semibold">{r.incomplete}:</dt>
                        <dd>
                            {term.incomplete ? i18n.common.yes : i18n.common.no}
                        </dd>
                    </div>
                </dl>
            ) : (
                <p className="text-sm">{r.noTerm}</p>
            )}
        </ExamisPage>
    );
}
