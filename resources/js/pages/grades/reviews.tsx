import { router } from '@inertiajs/react';
import { Gavel, Hand, Undo2 } from 'lucide-react';
import { useMemo, useState } from 'react';
import {
    AttendanceTable,
    AttendanceWindow,
    useAttendanceSelection,
    useYearFilterSpec,
    WindowTextArea,
    type AttendanceColumn,
} from '@/components/attendance/attendance-chrome';
import {
    ExamisPage,
    optionFilter,
    useExamisEditRibbon,
    useExamisHomeFilters,
    useExamisRequest,
} from '@/components/examis/examis-chrome';
import {
    RegistryListField,
    RegistryTextField,
} from '@/components/organization/registry-sheet';
import type { PageRibbonGroup } from '@/components/sis/page-ribbon-context';
import { t } from '@/i18n';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

type Status =
    | 'submitted'
    | 'under_review'
    | 'accepted'
    | 'rejected'
    | 'withdrawn';

type ReviewRow = {
    id: number;
    review_number: string;
    grade_id: number;
    academic_year_id: number;
    status: Status;
    reason: string;
    score_at_request: string | null;
    absent_at_request: boolean;
    max_score: string;
    requested_at: string;
    decided_at: string | null;
    decision_note: string | null;
    requested_by: number | null;
    assigned_to: number | null;
    student_name: string;
    student_code: string;
    subject_name: string;
    requester_name: string | null;
    assignee_name: string | null;
};

type PageProps = {
    rows: ReviewRow[];
    filters: { academic_year_id: number | null; status: Status | null };
    abilities: { request: boolean; decide: boolean };
    userId: number;
};

const STATUSES: Status[] = [
    'submitted',
    'under_review',
    'accepted',
    'rejected',
    'withdrawn',
];

const STATUS_TONE: Record<Status, number> = {
    submitted: 3,
    under_review: 0,
    accepted: 2,
    rejected: 1,
    withdrawn: 4,
};

export default function GradeReviews(props: PageProps) {
    const i18n = t();
    const breadcrumbs: BreadcrumbItem[] = [
        { title: i18n.grades.title, href: '/grades' },
        { title: i18n.gradeReviews.title, href: '/grades/reviews' },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <ReviewsBody {...props} />
        </AppLayout>
    );
}

/** «مراجعات الدرجات» — take a review, decide it (accept = official correction, reject) or withdraw your own. */
function ReviewsBody({ rows, filters, abilities, userId }: PageProps) {
    const i18n = t();
    const r = i18n.gradeReviews;
    const request = useExamisRequest();
    const selection = useAttendanceSelection(rows, (row) => row.id);
    const [busy, setBusy] = useState(false);
    const [deciding, setDeciding] = useState<{
        decision: string;
        score: string;
        absent: string;
        note: string;
    } | null>(null);
    const row =
        selection.targets.length === 1 ? (selection.targets[0] ?? null) : null;

    const visit = (patch: Partial<PageProps['filters']>) =>
        router.get(
            '/grades/reviews',
            Object.fromEntries(
                Object.entries({ ...filters, ...patch }).filter(
                    ([, value]) => value !== null && value !== undefined,
                ),
            ),
            { preserveScroll: true },
        );
    const yearSpec = useYearFilterSpec(filters.academic_year_id, (id) =>
        visit({ academic_year_id: id }),
    );
    useExamisHomeFilters(
        [
            yearSpec,
            optionFilter(
                r.status,
                r.status,
                filters.status === null
                    ? null
                    : STATUSES.indexOf(filters.status) + 1,
                STATUSES.map((key, index) => ({
                    id: index + 1,
                    name: r.statuses[key],
                })),
                (id) => visit({ status: id === null ? null : STATUSES[id - 1] }),
                { emptyLabel: r.allStatuses },
            ),
        ],
        [filters, yearSpec.options.length],
    );

    const post = async (
        action: 'start' | 'decide' | 'withdraw',
        data: Record<string, unknown>,
    ): Promise<boolean> => {
        if (row === null) {
            return false;
        }
        setBusy(true);
        const ok = await request(
            `/grades/reviews/${row.id}/${action}`,
            data,
            `review-${action}`,
        );
        setBusy(false);

        return ok;
    };

    const ribbon = useMemo((): PageRibbonGroup[] => {
        const mine = row !== null && row.requested_by === userId;

        return [
            {
                id: 'grade-reviews',
                label: r.actions,
                commands: [
                    {
                        id: 'grade-review-start',
                        label: r.start,
                        icon: Hand,
                        iconTone: 'sky',
                        disabled:
                            busy ||
                            !abilities.decide ||
                            row === null ||
                            row.status !== 'submitted' ||
                            mine,
                        title: `${r.start} — ${r.startHint}`,
                        onSelect: () => void post('start', {}),
                    },
                    {
                        id: 'grade-review-decide',
                        label: r.decide,
                        icon: Gavel,
                        iconTone: 'amber',
                        disabled:
                            busy ||
                            !abilities.decide ||
                            row === null ||
                            row.status !== 'under_review' ||
                            row.assigned_to !== userId,
                        title: `${r.decide} — ${r.decideHint}`,
                        onSelect: () =>
                            setDeciding({
                                decision: 'reject',
                                score: '',
                                absent: '0',
                                note: '',
                            }),
                    },
                    {
                        id: 'grade-review-withdraw',
                        label: r.withdraw,
                        icon: Undo2,
                        iconTone: 'danger',
                        disabled:
                            busy ||
                            !abilities.request ||
                            !mine ||
                            row === null ||
                            !['submitted', 'under_review'].includes(row.status),
                        onSelect: () => void post('withdraw', {}),
                    },
                ],
            },
        ];
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [row, busy, abilities, userId]);
    useExamisEditRibbon('reviews', ribbon);

    const columns: AttendanceColumn<ReviewRow>[] = [
        {
            id: 'number',
            header: r.number,
            kind: 'text',
            cell: (x) => <span dir="ltr">{x.review_number}</span>,
        },
        { id: 'student', header: r.student, kind: 'name', cell: (x) => x.student_name },
        {
            id: 'code',
            header: r.code,
            kind: 'text',
            cell: (x) => <span dir="ltr">{x.student_code}</span>,
        },
        { id: 'subject', header: r.subject, kind: 'text', cell: (x) => x.subject_name },
        {
            id: 'score',
            header: r.scoreAtRequest,
            kind: 'text',
            cell: (x) =>
                x.absent_at_request ? (
                    r.absent
                ) : (
                    <span dir="ltr">
                        {x.score_at_request ?? '—'} / {x.max_score}
                    </span>
                ),
        },
        {
            id: 'status',
            header: i18n.common.status,
            kind: 'status',
            tone: (x) => STATUS_TONE[x.status],
            cell: (x) => r.statuses[x.status],
        },
        { id: 'reason', header: r.reason, kind: 'text', cell: (x) => x.reason },
        {
            id: 'requester',
            header: r.requester,
            kind: 'text',
            cell: (x) => x.requester_name ?? '—',
        },
        {
            id: 'reviewer',
            header: r.reviewer,
            kind: 'text',
            cell: (x) => x.assignee_name ?? '—',
        },
        {
            id: 'decision',
            header: r.decisionNote,
            kind: 'text',
            cell: (x) => x.decision_note ?? '—',
        },
    ];

    return (
        <ExamisPage title={r.title} windowId="grades.reviews">
            <AttendanceTable
                caption={r.title}
                storageKey="grades.reviews"
                rows={rows}
                rowKey={(x) => x.id}
                columns={columns}
                selection={selection}
                emptyText={r.empty}
            />

            {deciding !== null && row !== null ? (
                <AttendanceWindow
                    title={`${r.decide} — ${row.review_number}`}
                    pending={busy}
                    danger={deciding.decision === 'accept'}
                    confirmLabel={r.decide}
                    confirmDisabled={
                        deciding.note.trim().length < 3 ||
                        (deciding.decision === 'accept' &&
                            deciding.absent !== '1' &&
                            deciding.score === '')
                    }
                    onConfirm={() =>
                        void post('decide', {
                            decision: deciding.decision,
                            note: deciding.note,
                            score:
                                deciding.decision === 'accept' &&
                                deciding.absent !== '1' &&
                                deciding.score !== ''
                                    ? deciding.score
                                    : null,
                            is_absent:
                                deciding.decision === 'accept' &&
                                deciding.absent === '1',
                        }).then((ok) => ok && setDeciding(null))
                    }
                    onClose={() => setDeciding(null)}
                    sections={[
                        {
                            id: 'grade-review-decide',
                            title: r.decide,
                            content: (
                                <>
                                    <RegistryListField
                                        label={r.decision}
                                        editing
                                        value={deciding.decision}
                                        display={
                                            deciding.decision === 'accept'
                                                ? r.accept
                                                : r.reject
                                        }
                                        options={[
                                            { value: 'reject', label: r.reject },
                                            { value: 'accept', label: r.accept },
                                        ]}
                                        onChange={(decision) =>
                                            setDeciding({
                                                ...deciding,
                                                decision,
                                            })
                                        }
                                    />
                                    {deciding.decision === 'accept' ? (
                                        <>
                                            <RegistryListField
                                                label={r.absentQuestion}
                                                editing
                                                value={deciding.absent}
                                                display={
                                                    deciding.absent === '1'
                                                        ? i18n.common.yes
                                                        : i18n.common.no
                                                }
                                                options={[
                                                    {
                                                        value: '0',
                                                        label: i18n.common.no,
                                                    },
                                                    {
                                                        value: '1',
                                                        label: i18n.common.yes,
                                                    },
                                                ]}
                                                onChange={(absent) =>
                                                    setDeciding({
                                                        ...deciding,
                                                        absent,
                                                    })
                                                }
                                            />
                                            {deciding.absent === '1' ? null : (
                                                <RegistryTextField
                                                    label={r.newScore}
                                                    editing
                                                    required
                                                    type="number"
                                                    dir="ltr"
                                                    value={deciding.score}
                                                    onChange={(score) =>
                                                        setDeciding({
                                                            ...deciding,
                                                            score,
                                                        })
                                                    }
                                                />
                                            )}
                                        </>
                                    ) : null}
                                    <WindowTextArea
                                        label={r.decisionNote}
                                        value={deciding.note}
                                        onChange={(note) =>
                                            setDeciding({ ...deciding, note })
                                        }
                                    />
                                    <p className="text-sm">
                                        {deciding.decision === 'accept'
                                            ? r.acceptHint
                                            : r.rejectHint}
                                    </p>
                                </>
                            ),
                        },
                    ]}
                />
            ) : null}
        </ExamisPage>
    );
}
