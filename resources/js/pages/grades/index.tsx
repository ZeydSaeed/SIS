import { router } from '@inertiajs/react';
import { ClipboardPen, Wrench } from 'lucide-react';
import { useMemo } from 'react';
import {
    AttendanceTable,
    useAttendanceSelection,
    useYearFilterSpec,
    type AttendanceColumn,
} from '@/components/attendance/attendance-chrome';
import {
    ExamisPage,
    optionFilter,
    useExamisEditRibbon,
    useExamisHomeFilters,
} from '@/components/examis/examis-chrome';
import type { PageRibbonGroup } from '@/components/sis/page-ribbon-context';
import { t } from '@/i18n';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

type GradeRow = {
    id: number;
    academic_year_id: number;
    school_id: number;
    exam_enrollment_id: number;
    exam_session_id: number;
    enrollment_id: number;
    student_id: number;
    subject_id: number;
    score: string | null;
    max_score: string;
    is_absent: boolean;
    status: number;
    is_current: boolean;
};

type SessionOption = {
    id: number;
    exam_name: string;
    subject_name: string;
    session_date: string;
};

type PageProps = {
    grades: {
        data: GradeRow[];
    };
    sessions: SessionOption[];
    students: Record<number, { student_name: string; student_code: string }>;
    filters: {
        session_id: number | null;
        academic_year_id: number | null;
    };
};

/** exams.student_grades.status: entered steel, submitted amber, finalized ok, voided danger. */
const STATUS_TONE: Record<number, number> = { 1: 0, 2: 3, 3: 2, 4: 1, 5: 4 };

export default function GradesIndex(props: PageProps) {
    const i18n = t();
    const breadcrumbs: BreadcrumbItem[] = [
        { title: i18n.grades.title, href: '/grades' },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <GradesBody {...props} />
        </AppLayout>
    );
}

function GradesBody({ grades, sessions, students, filters }: PageProps) {
    const i18n = t();
    const g = i18n.grades;
    const rows = grades.data;
    const selection = useAttendanceSelection(rows, (row) => row.id);

    const visit = (patch: Partial<PageProps['filters']>) =>
        router.get(
            '/grades',
            Object.fromEntries(
                Object.entries({ ...filters, ...patch }).filter(
                    ([, value]) => value !== null && value !== undefined,
                ),
            ),
            { preserveScroll: true },
        );
    const manage = (row: GradeRow) =>
        router.visit(
            `/grades/actions?grade_id=${row.id}&academic_year_id=${row.academic_year_id}`,
        );

    const yearSpec = useYearFilterSpec(filters.academic_year_id, (id) =>
        visit({ academic_year_id: id, session_id: null }),
    );
    useExamisHomeFilters(
        [
            yearSpec,
            optionFilter(
                g.session,
                g.session,
                filters.session_id,
                sessions.map((session) => ({
                    id: session.id,
                    name: `${session.exam_name} — ${session.subject_name} — ${session.session_date}`,
                })),
                (id) => visit({ session_id: id }),
                {
                    emptyLabel:
                        sessions.length === 0
                            ? g.noSessions
                            : `${g.session}: —`,
                },
            ),
        ],
        [filters, sessions, yearSpec.options.length],
    );

    const ribbon = useMemo((): PageRibbonGroup[] => {
        const single =
            selection.targets.length === 1 ? selection.targets[0] : null;

        return [
            {
                id: 'grades-actions',
                label: i18n.examisChrome.actions,
                commands: [
                    {
                        id: 'grades-manage',
                        label: g.actionsTitle,
                        icon: Wrench,
                        iconTone: 'amber',
                        tone: 'edit',
                        disabled: single === null,
                        onSelect: () => single && manage(single),
                    },
                    {
                        id: 'grades-enter',
                        label: g.enterSessionGrades,
                        icon: ClipboardPen,
                        iconTone: 'growth',
                        disabled: filters.session_id === null,
                        onSelect: () =>
                            router.visit(
                                `/grades/sheet?academic_year_id=${filters.academic_year_id ?? ''}${filters.session_id ? `&session_id=${filters.session_id}` : ''}`,
                            ),
                    },
                ],
            },
        ];
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [selection.targets, filters]);
    useExamisEditRibbon('grades', ribbon);

    const columns: AttendanceColumn<GradeRow>[] = [
        {
            id: 'student',
            header: g.student,
            kind: 'name',
            cell: (row) =>
                students[row.enrollment_id]?.student_name ??
                String(row.student_id),
        },
        {
            id: 'code',
            header: g.studentCode,
            kind: 'text',
            cell: (row) => (
                <span dir="ltr">
                    {students[row.enrollment_id]?.student_code ?? '—'}
                </span>
            ),
        },
        {
            id: 'score',
            header: g.score,
            kind: 'text',
            cell: (row) =>
                row.is_absent ? (
                    g.absent
                ) : (
                    <span dir="ltr">
                        {row.score ?? '—'} / {row.max_score}
                    </span>
                ),
        },
        {
            id: 'status',
            header: i18n.common.status,
            kind: 'status',
            tone: (row) => STATUS_TONE[row.status] ?? 0,
            cell: (row) => g.statuses[row.status] ?? String(row.status),
        },
    ];

    return (
        <ExamisPage title={g.title} windowId="grades.index">
            <AttendanceTable
                caption={g.sessionGradesCaption}
                storageKey="grades.index"
                rows={rows}
                rowKey={(row) => row.id}
                columns={columns}
                selection={selection}
                onOpen={manage}
                emptyText={
                    filters.session_id === null
                        ? g.sheet.pickSessionHint
                        : g.emptyTitle
                }
            />
        </ExamisPage>
    );
}
