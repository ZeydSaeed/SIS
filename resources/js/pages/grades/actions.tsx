import { router } from '@inertiajs/react';
import { BadgeCheck, Ban, ListChecks, MessageSquareWarning, Pencil } from 'lucide-react';
import { useMemo, useState } from 'react';
import {
    AttendanceWindow,
    useAttendanceMessages,
    WindowFact,
    WindowTextArea,
} from '@/components/attendance/attendance-chrome';
import {
    ExamisPage,
    useExamisEditRibbon,
    useExamisRequest,
} from '@/components/examis/examis-chrome';
import {
    RegistryListField,
    RegistryTextField,
} from '@/components/organization/registry-sheet';
import { ConfirmDialog } from '@/components/sis/confirm-dialog';
import type { PageRibbonGroup } from '@/components/sis/page-ribbon-context';
import { t } from '@/i18n';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

type Grade = {
    id: number;
    academic_year_id: number;
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
    finalized_at: string | null;
};

type PageProps = {
    grade: Grade | null;
    filters: {
        grade_id: number | null;
        academic_year_id: number | null;
    };
};

/** exams.student_grades.status — 4 finalized · 5 voided. */
const FINALIZED = 4;
const VOIDED = 5;

export default function GradesActions(props: PageProps) {
    const i18n = t();
    const breadcrumbs: BreadcrumbItem[] = [
        { title: i18n.grades.title, href: '/grades' },
        { title: i18n.grades.actionsBreadcrumb, href: '/grades/actions' },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <ActionsBody {...props} />
        </AppLayout>
    );
}

/** «إجراءات الدرجة» — correct (void + new version), void or finalize one grade; the server re-checks each. */
function ActionsBody({ grade, filters }: PageProps) {
    const i18n = t();
    const g = i18n.grades;
    const request = useExamisRequest();
    const [busy, setBusy] = useState(false);
    const [correcting, setCorrecting] = useState<{
        score: string;
        absent: string;
        reason: string;
    } | null>(null);
    const [voidReason, setVoidReason] = useState<string | null>(null);
    const [confirmFinalize, setConfirmFinalize] = useState(false);
    const [reviewReason, setReviewReason] = useState<string | null>(null);
    const open = grade !== null && grade.is_current && grade.status !== VOIDED;

    const ribbon = useMemo((): PageRibbonGroup[] => {
        const none = grade === null || !open || busy;

        return [
            {
                id: 'grade-actions',
                label: g.actionsTitle,
                commands: [
                    {
                        id: 'grade-correct-edit',
                        label: g.correct,
                        icon: Pencil,
                        iconTone: 'amber',
                        tone: 'edit',
                        disabled: none,
                        onSelect: () =>
                            grade &&
                            setCorrecting({
                                score: grade.score ?? '',
                                absent: grade.is_absent ? '1' : '0',
                                reason: '',
                            }),
                    },
                    {
                        id: 'grade-void-delete',
                        label: g.voidGradeAction,
                        icon: Ban,
                        iconTone: 'danger',
                        tone: 'delete',
                        disabled: none,
                        onSelect: () => setVoidReason(''),
                    },
                    {
                        id: 'grade-finalize',
                        label: g.finalizeGradeAction,
                        icon: BadgeCheck,
                        iconTone: 'ok',
                        disabled: none || grade?.status === FINALIZED,
                        title: `${g.finalizeGradeAction} — ${g.finalizeDesc}`,
                        onSelect: () => setConfirmFinalize(true),
                    },
                    {
                        id: 'grade-review-open',
                        label: t().gradeReviews.open,
                        icon: MessageSquareWarning,
                        iconTone: 'rose',
                        disabled: none,
                        title: `${t().gradeReviews.open} — ${t().gradeReviews.openHint}`,
                        onSelect: () => setReviewReason(''),
                    },
                    {
                        id: 'grade-back',
                        label: i18n.common.backToList,
                        icon: ListChecks,
                        iconTone: 'forest',
                        onSelect: () =>
                            router.visit(
                                `/grades?academic_year_id=${filters.academic_year_id ?? ''}${grade ? `&session_id=${grade.exam_session_id}` : ''}`,
                            ),
                    },
                ],
            },
        ];
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [grade, open, busy]);
    useExamisEditRibbon('grades', ribbon);

    useAttendanceMessages(
        grade === null
            ? [{ key: 'pick-grade', text: g.enterGradeIdHint, tone: 'info' }]
            : [],
        g.actionsTitle,
    );

    const openReview = async (): Promise<boolean> => {
        if (grade === null || reviewReason === null) {
            return false;
        }
        setBusy(true);
        const ok = await request(
            '/grades/reviews',
            {
                grade_id: grade.id,
                academic_year_id: grade.academic_year_id,
                reason: reviewReason,
            },
            'review-open',
        );
        setBusy(false);

        return ok;
    };

    const post = async (
        action: 'correct' | 'void' | 'finalize',
        data: Record<string, unknown>,
    ): Promise<boolean> => {
        if (grade === null) {
            return false;
        }
        setBusy(true);
        const ok = await request(
            `/grades/${grade.id}/${action}`,
            { academic_year_id: grade.academic_year_id, ...data },
            `grade-${action}`,
        );
        setBusy(false);

        return ok;
    };

    return (
        <ExamisPage title={g.actionsTitle} windowId="grades.actions">
            {grade ? (
                <dl className="flex flex-wrap gap-x-6 gap-y-1 text-sm">
                    <div className="flex gap-1">
                        <dt className="font-semibold">{g.student}:</dt>
                        <dd dir="ltr">{grade.student_id}</dd>
                    </div>
                    <div className="flex gap-1">
                        <dt className="font-semibold">{g.score}:</dt>
                        <dd dir="ltr">
                            {grade.is_absent
                                ? g.absent
                                : `${grade.score ?? '—'} / ${grade.max_score}`}
                        </dd>
                    </div>
                    <div className="flex gap-1">
                        <dt className="font-semibold">{i18n.common.status}:</dt>
                        <dd>{g.statuses[grade.status] ?? grade.status}</dd>
                    </div>
                    <div className="flex gap-1">
                        <dt className="font-semibold">{g.finalized}:</dt>
                        <dd dir="ltr">
                            {grade.finalized_at ?? g.notFinalized}
                        </dd>
                    </div>
                </dl>
            ) : null}

            {correcting !== null && grade !== null ? (
                <AttendanceWindow
                    title={g.correct}
                    pending={busy}
                    confirmLabel={g.correct}
                    confirmDisabled={correcting.reason.trim().length < 3}
                    onConfirm={() =>
                        void post('correct', {
                            score:
                                correcting.absent === '1' ||
                                correcting.score === ''
                                    ? null
                                    : correcting.score,
                            is_absent: correcting.absent === '1',
                            reason: correcting.reason,
                        }).then((ok) => ok && setCorrecting(null))
                    }
                    onClose={() => setCorrecting(null)}
                    sections={[
                        {
                            id: 'grade-correct',
                            title: g.correct,
                            content: (
                                <>
                                    <WindowFact
                                        label={g.score}
                                        value={
                                            <span dir="ltr">
                                                {grade.is_absent
                                                    ? g.absent
                                                    : (grade.score ?? '—')}
                                            </span>
                                        }
                                    />
                                    <RegistryListField
                                        label={g.absentQuestion}
                                        editing
                                        value={correcting.absent}
                                        display={
                                            correcting.absent === '1'
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
                                            setCorrecting({
                                                ...correcting,
                                                absent,
                                            })
                                        }
                                    />
                                    {correcting.absent === '1' ? null : (
                                        <RegistryTextField
                                            label={g.newScore}
                                            editing
                                            type="number"
                                            dir="ltr"
                                            value={correcting.score}
                                            onChange={(score) =>
                                                setCorrecting({
                                                    ...correcting,
                                                    score,
                                                })
                                            }
                                        />
                                    )}
                                    <WindowTextArea
                                        label={g.reason}
                                        value={correcting.reason}
                                        onChange={(reason) =>
                                            setCorrecting({
                                                ...correcting,
                                                reason,
                                            })
                                        }
                                    />
                                </>
                            ),
                        },
                    ]}
                />
            ) : null}

            {voidReason !== null ? (
                <AttendanceWindow
                    title={g.voidConfirmTitle}
                    pending={busy}
                    danger
                    confirmLabel={g.voidGradeAction}
                    confirmDisabled={voidReason.trim().length < 3}
                    onConfirm={() =>
                        void post('void', { reason: voidReason }).then(
                            (ok) => ok && setVoidReason(null),
                        )
                    }
                    onClose={() => setVoidReason(null)}
                    sections={[
                        {
                            id: 'grade-void',
                            title: g.voidConfirmDesc,
                            content: (
                                <WindowTextArea
                                    label={g.reason}
                                    value={voidReason}
                                    onChange={setVoidReason}
                                />
                            ),
                        },
                    ]}
                />
            ) : null}

            {reviewReason !== null ? (
                <AttendanceWindow
                    title={t().gradeReviews.open}
                    pending={busy}
                    confirmLabel={t().gradeReviews.open}
                    confirmDisabled={reviewReason.trim().length < 3}
                    onConfirm={() =>
                        void openReview().then(
                            (ok) => ok && setReviewReason(null),
                        )
                    }
                    onClose={() => setReviewReason(null)}
                    sections={[
                        {
                            id: 'grade-review-open',
                            title: t().gradeReviews.openHint,
                            content: (
                                <WindowTextArea
                                    label={t().gradeReviews.reason}
                                    value={reviewReason}
                                    onChange={setReviewReason}
                                />
                            ),
                        },
                    ]}
                />
            ) : null}

            <ConfirmDialog
                open={confirmFinalize}
                title={g.finalizeConfirmTitle}
                description={g.finalizeConfirmDesc}
                confirmLabel={g.finalizeGradeAction}
                confirmPending={busy}
                onConfirm={() =>
                    void post('finalize', {}).then(() =>
                        setConfirmFinalize(false),
                    )
                }
                onOpenChange={setConfirmFinalize}
            />
        </ExamisPage>
    );
}
