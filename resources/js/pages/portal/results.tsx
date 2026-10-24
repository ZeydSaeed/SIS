import {
    AttendanceTable,
    useAttendanceMessages,
    type AttendanceColumn,
} from '@/components/attendance/attendance-chrome';
import { ExamisPage } from '@/components/examis/examis-chrome';
import { t } from '@/i18n';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

type Published = {
    value: string | null;
    version: number;
    incomplete: boolean;
    published_at: string;
} | null;

type Row = {
    enrollment_id: number;
    student_name: string;
    student_code: string;
    year_name: string;
    class_name: string;
    annual: Published;
    gpa: Published;
    transcript: Published;
    terms: {
        term_name: string;
        subject_name: string;
        value: string | null;
        incomplete: boolean;
    }[];
};

type PageProps = { rows: Row[] };

export default function PortalResults(props: PageProps) {
    const i18n = t();
    const breadcrumbs: BreadcrumbItem[] = [
        { title: i18n.portalResults.title, href: '/portal/results' },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <PortalResultsBody {...props} />
        </AppLayout>
    );
}

/** «نتائجي» — only results the school has published; nothing else is ever sent to this page. */
function PortalResultsBody({ rows }: PageProps) {
    const p = t().portalResults;
    useAttendanceMessages(
        rows.length === 0 ? [{ key: 'none', text: p.empty, tone: 'info' }] : [],
        p.title,
    );

    const cell = (value: Published) =>
        value === null ? (
            p.none
        ) : (
            <span>
                <span dir="ltr">{value.value ?? p.none}</span>
                {value.incomplete ? ` · ${p.incomplete}` : ''}
                {` · ${p.publishedAt.replace(':at', value.published_at.slice(0, 10))}`}
            </span>
        );

    const columns: AttendanceColumn<Row>[] = [
        { id: 'student', header: p.student, cell: (row) => row.student_name },
        {
            id: 'code',
            header: p.code,
            kind: 'text',
            cell: (row) => <span dir="ltr">{row.student_code}</span>,
        },
        { id: 'year', header: p.year, kind: 'text', cell: (row) => row.year_name },
        {
            id: 'class',
            header: p.class,
            kind: 'text',
            cell: (row) => row.class_name,
        },
        {
            id: 'annual',
            header: p.annual,
            kind: 'text',
            cell: (row) => cell(row.annual),
        },
        { id: 'gpa', header: p.gpa, kind: 'text', cell: (row) => cell(row.gpa) },
        {
            id: 'transcript',
            header: p.transcript,
            kind: 'text',
            cell: (row) =>
                row.transcript === null ? (
                    p.none
                ) : (
                    <span dir="ltr">{row.transcript.value}</span>
                ),
        },
        {
            id: 'terms',
            header: p.terms,
            kind: 'text',
            cell: (row) =>
                row.terms.length === 0
                    ? p.none
                    : row.terms
                          .map(
                              (x) =>
                                  `${x.term_name} · ${x.subject_name}: ${x.value ?? p.none}${x.incomplete ? ` (${p.incomplete})` : ''}`,
                          )
                          .join(' — '),
        },
    ];

    return (
        <ExamisPage title={p.title} windowId="portal.results">
            <AttendanceTable
                caption={p.title}
                storageKey="portal.results"
                columns={columns}
                rows={rows}
                rowKey={(row) => row.enrollment_id}
                emptyText={p.empty}
            />
        </ExamisPage>
    );
}
