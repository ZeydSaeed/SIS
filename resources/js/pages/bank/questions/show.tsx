import { Head } from '@inertiajs/react';
import {
    Archive,
    ArrowRight,
    Check,
    CopyPlus,
    Library,
    Pencil,
    Send,
    X,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import AppLayout from '@/layouts/app-layout';
import {
    NoteDialog,
    QuestionFormDialog,
    send,
    type QuestionType,
    type QuestionValues,
} from '@/components/bank/question-forms';
import {
    BankStatusBadge,
    formatInstant,
} from '@/components/bank/question-status';
import { EditRibbon } from '@/components/exams/edit-ribbon';
import { idempotencyKey } from '@/components/exams/exam-forms';
import { fill } from '@/components/results/active-publications';
import { ConfirmDialog } from '@/components/sis/confirm-dialog';
import { PageHeader } from '@/components/sis/page-header';
import { type PageRibbonGroup } from '@/components/sis/page-ribbon-context';
import { Button } from '@/components/ui/button';
import { t } from '@/i18n';
import type { BreadcrumbItem } from '@/types';

type Version = {
    id: number;
    version_no: number;
    type: QuestionType;
    stem: string;
    /** Without the answer key for view-only users: only `options`. */
    payload: {
        options?: { key: string; text: string }[];
        correct?: string[];
        answer?: boolean;
        guidance?: string;
    };
    max_mark: string;
    status: 'draft' | 'in_review' | 'approved' | 'rejected' | 'superseded';
    created_by: number;
    author_name: string | null;
    /** Instants are ISO 8601 in UTC; shown in `timezone`. */
    created_at: string;
    submitted_at: string | null;
    submitted_by_name: string | null;
    decided_by: number | null;
    decided_by_name: string | null;
    decided_at: string | null;
    decision_note: string | null;
    updated_at: string;
};

type PageProps = {
    question: {
        id: number;
        subject_id: number;
        subject_name: string;
        status: 'active' | 'retired';
        retired_at: string | null;
        retire_reason: string | null;
        retired_by_name: string | null;
        versions: Version[];
    };
    types: QuestionType[];
    abilities: { author: boolean; approve: boolean; view_answer_key: boolean };
    current_user_id: number;
    /** Display time zone of the instants (server: `attendance.default_timezone`). */
    timezone: string;
};

type Dialog = 'edit' | 'submit' | 'approve' | 'reject' | 'revise' | 'retire';

function valuesOf(version: Version): QuestionValues {
    const options = version.payload.options ?? [];
    const correct = version.payload.correct ?? [];

    return {
        subject_id: null,
        type: version.type,
        stem: version.stem,
        max_mark: version.max_mark,
        options: options.length > 0 ? options.map((o) => o.text) : ['', ''],
        correct: options
            .map((o, i) => (correct.includes(o.key) ? i : -1))
            .filter((i) => i >= 0),
        answer: version.payload.answer ?? null,
        guidance: version.payload.guidance ?? '',
    };
}

/** What happened to a version, oldest first: written, sent for review, decided (or replaced by a newer approval). */
function History({
    version,
    timeZone,
}: {
    version: Version;
    timeZone: string;
}) {
    const b = t().bank;
    const steps: { label: string; who: string | null; at: string | null }[] = [
        {
            label: b.historyCreated,
            who: version.author_name,
            at: version.created_at,
        },
    ];
    if (version.submitted_at) {
        steps.push({
            label: b.historySubmitted,
            who: version.submitted_by_name,
            at: version.submitted_at,
        });
    }
    if (version.decided_at) {
        steps.push({
            label:
                version.status === 'rejected'
                    ? b.historyRejected
                    : b.historyApproved,
            who: version.decided_by_name,
            at: version.decided_at,
        });
    }
    if (version.status === 'superseded') {
        // Superseding is the version's last change, so its update time is when it happened.
        steps.push({
            label: b.historySuperseded,
            who: null,
            at: version.updated_at,
        });
    }

    return (
        <ol
            className="m-0 flex list-none flex-col gap-1 p-0 text-sm"
            aria-label={b.history}
        >
            {steps.map((step) => (
                <li key={step.label} className="flex flex-wrap gap-x-2">
                    <span className="font-medium">{step.label}</span>
                    {step.who ? (
                        <span>{fill(b.byName, { name: step.who })}</span>
                    ) : null}
                    {/* No forced direction: the Arabic date carries its own marks and comma (L-F7). */}
                    <span className="sis-ops-hub__gate-note m-0">
                        {formatInstant(step.at, timeZone)}
                    </span>
                </li>
            ))}
        </ol>
    );
}

function VersionCard({
    version,
    showKey,
    latest,
    timeZone,
}: {
    version: Version;
    showKey: boolean;
    latest: boolean;
    timeZone: string;
}) {
    const b = t().bank;
    const correct = version.payload.correct ?? [];

    return (
        <li className="sis-ops-hub__gate">
            <article
                className="flex flex-col gap-3"
                aria-labelledby={`bank-version-${version.id}`}
            >
                <div className="sis-ops-hub__gate-head">
                    <h2
                        id={`bank-version-${version.id}`}
                        className="sis-ops-hub__gate-title m-0 text-base"
                    >
                        {b.versionShort}{' '}
                        <bdi dir="ltr">{version.version_no}</bdi> ·{' '}
                        {b.types[version.type]}
                    </h2>
                    {latest ? (
                        <span className="sis-ops-hub__gate-status">
                            {b.latestVersion}
                        </span>
                    ) : null}
                    <BankStatusBadge status={version.status} />
                </div>
                <p className="m-0 whitespace-pre-line">{version.stem}</p>
                {version.payload.options ? (
                    <ol className="flex flex-col gap-1" aria-label={b.options}>
                        {version.payload.options.map((option) => {
                            const isCorrect =
                                showKey && correct.includes(option.key);
                            return (
                                <li
                                    key={option.key}
                                    className={isCorrect ? 'font-semibold' : ''}
                                >
                                    <bdi dir="ltr">{option.key}</bdi>.{' '}
                                    {option.text}
                                    {isCorrect ? (
                                        <span className="ms-2">
                                            ({b.correctMark})
                                        </span>
                                    ) : null}
                                </li>
                            );
                        })}
                    </ol>
                ) : null}
                {showKey && version.type === 'true_false' ? (
                    <p>
                        {b.answer}:{' '}
                        {version.payload.answer ? b.answerTrue : b.answerFalse}
                    </p>
                ) : null}
                {showKey &&
                version.type === 'long_answer' &&
                version.payload.guidance ? (
                    <p className="whitespace-pre-line">
                        <span className="font-medium">{b.guidance}:</span>{' '}
                        {version.payload.guidance}
                    </p>
                ) : null}
                {!showKey && version.type !== 'long_answer' ? (
                    <p className="sis-ops-hub__gate-note">
                        {b.answerKeyHidden}
                    </p>
                ) : null}
                <dl className="m-0 flex flex-wrap gap-x-6 gap-y-1 text-sm">
                    <div>
                        <dt className="inline">{b.maxMark}: </dt>
                        <dd className="m-0 inline">
                            <bdi dir="ltr">{version.max_mark}</bdi>
                        </dd>
                    </div>
                    <div>
                        <dt className="inline">{b.author}: </dt>
                        <dd className="m-0 inline">
                            {version.author_name ?? '—'}
                        </dd>
                    </div>
                </dl>
                {version.decision_note ? (
                    <p className="m-0 text-sm whitespace-pre-line">
                        <span className="font-medium">{b.decisionNote}:</span>{' '}
                        {version.decision_note}
                    </p>
                ) : null}
                <History version={version} timeZone={timeZone} />
            </article>
        </li>
    );
}

/**
 * Why the user has no command on this question (the ribbon only offers what the server allows): retired, view only,
 * a colleague's draft, their own version under review (four eyes), or a version waiting for an approver.
 */
function notice(
    question: PageProps['question'],
    latest: Version | null,
    abilities: PageProps['abilities'],
    me: number,
    timeZone: string,
): string | null {
    const b = t().bank;
    if (question.status === 'retired') {
        return fill(b.retiredDetail, {
            reason: question.retire_reason ?? '—',
            name: question.retired_by_name ?? '—',
            at: formatInstant(question.retired_at, timeZone),
        });
    }
    if (!abilities.author && !abilities.approve) {
        return b.viewOnlyNotice;
    }
    if (latest?.status === 'draft' && latest.created_by !== me) {
        return b.colleagueDraftNotice;
    }
    if (latest?.status === 'in_review') {
        if (latest.created_by === me) {
            return abilities.approve ? b.ownVersionNotice : b.waitingForReview;
        }
        if (!abilities.approve) {
            return b.othersReviewNotice;
        }
    }

    return null;
}

export default function BankQuestionShow({
    question,
    types,
    abilities,
    current_user_id: me,
    timezone,
}: PageProps) {
    const i18n = t();
    const b = i18n.bank;
    const [dialog, setDialog] = useState<Dialog | null>(null);
    const [busy, setBusy] = useState(false);
    const latest = question.versions[0] ?? null;
    const active = question.status === 'active';
    const why = notice(question, latest, abilities, me, timezone);
    const open =
        latest !== null &&
        (latest.status === 'draft' || latest.status === 'in_review');

    const can = {
        edit:
            active &&
            abilities.author &&
            latest?.status === 'draft' &&
            latest.created_by === me,
        decide:
            active &&
            abilities.approve &&
            latest?.status === 'in_review' &&
            latest.created_by !== me,
        revise: active && abilities.author && !open,
        retire: active && abilities.approve,
    };

    const ribbon = useMemo((): PageRibbonGroup[] => {
        const commands: PageRibbonGroup['commands'] = [];
        if (can.edit) {
            commands.push(
                {
                    id: 'bank-edit',
                    label: b.editDraft,
                    icon: Pencil,
                    onSelect: () => setDialog('edit'),
                },
                {
                    id: 'bank-submit',
                    label: b.submit,
                    icon: Send,
                    onSelect: () => setDialog('submit'),
                },
            );
        }
        if (can.decide) {
            commands.push(
                {
                    id: 'bank-approve',
                    label: b.approve,
                    icon: Check,
                    iconTone: 'ok',
                    onSelect: () => setDialog('approve'),
                },
                {
                    id: 'bank-reject',
                    label: b.reject,
                    icon: X,
                    iconTone: 'danger',
                    onSelect: () => setDialog('reject'),
                },
            );
        }
        const more: PageRibbonGroup['commands'] = [];
        if (can.revise) {
            more.push({
                id: 'bank-revise',
                label: b.revise,
                icon: CopyPlus,
                onSelect: () => setDialog('revise'),
            });
        }
        if (can.retire) {
            more.push({
                id: 'bank-retire',
                label: b.retire,
                icon: Archive,
                tone: 'delete',
                onSelect: () => setDialog('retire'),
            });
        }

        return [
            ...(commands.length > 0
                ? [{ id: 'bank-version', label: b.currentVersion, commands }]
                : []),
            ...(more.length > 0
                ? [{ id: 'bank-question', label: b.question, commands: more }]
                : []),
        ];
    }, [b, can.decide, can.edit, can.retire, can.revise]);

    const post = (url: string) => {
        setBusy(true);
        send(url, {}, idempotencyKey('bank'), {
            onErrors: () => undefined,
            onFinish: () => {
                setBusy(false);
                setDialog(null);
            },
        });
    };

    const breadcrumbs: BreadcrumbItem[] = [
        { title: i18n.exams.title, href: '/exams' },
        { title: b.title, href: '/bank/questions' },
        { title: b.question, href: `/bank/questions/${question.id}` },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <EditRibbon groups={ribbon} />
            <Head title={`${b.question} — ${question.subject_name}`} />
            <div className="sis-ops-hub flex min-w-0 flex-col gap-4 p-4">
                <PageHeader
                    title={`${b.question} — ${question.subject_name}`}
                    description={b.showDescription}
                    icon={<Library className="size-6" aria-hidden />}
                />
                <div className="flex flex-wrap items-center gap-3">
                    <Button asChild variant="outline">
                        <a href="/bank/questions">
                            <ArrowRight
                                className="size-4 ltr:rotate-180"
                                aria-hidden
                            />
                            {i18n.common.backToList}
                        </a>
                    </Button>
                    <BankStatusBadge
                        status={
                            active ? (latest?.status ?? 'draft') : 'retired'
                        }
                    />
                </div>
                {why !== null ? (
                    <p role="status" className="sis-ops-hub__gate m-0 text-sm">
                        {why}
                    </p>
                ) : null}

                <section
                    aria-labelledby="bank-versions"
                    className="flex flex-col gap-2"
                >
                    <h2
                        id="bank-versions"
                        className="sis-ops-hub__section-title"
                    >
                        {b.versions}
                    </h2>
                    <p className="sis-ops-hub__gate-note m-0">
                        {fill(b.timesIn, { tz: timezone })}
                    </p>
                    <ol className="sis-ops-hub__gates">
                        {question.versions.map((version, index) => (
                            <VersionCard
                                key={version.id}
                                version={version}
                                showKey={abilities.view_answer_key}
                                latest={index === 0}
                                timeZone={timezone}
                            />
                        ))}
                    </ol>
                </section>
            </div>

            {latest !== null && can.edit ? (
                <QuestionFormDialog
                    open={dialog === 'edit'}
                    mode="edit"
                    url={`/bank/questions/versions/${latest.id}/update`}
                    initial={valuesOf(latest)}
                    subjects={[]}
                    types={types}
                    onClose={() => setDialog(null)}
                />
            ) : null}
            {latest !== null ? (
                <>
                    <ConfirmDialog
                        open={dialog === 'submit'}
                        title={b.submit}
                        description={b.submitConfirm}
                        confirmLabel={b.submit}
                        confirmPending={busy}
                        onConfirm={() =>
                            post(`/bank/questions/versions/${latest.id}/submit`)
                        }
                        onOpenChange={(next) => !next && setDialog(null)}
                    />
                    <NoteDialog
                        open={dialog === 'approve'}
                        title={b.approve}
                        label={b.noteOptional}
                        submitLabel={b.approve}
                        required={false}
                        url={`/bank/questions/versions/${latest.id}/decide`}
                        extra={{ decision: 'approve' }}
                        field="note"
                        onClose={() => setDialog(null)}
                    />
                    <NoteDialog
                        open={dialog === 'reject'}
                        title={b.reject}
                        label={b.noteRequired}
                        submitLabel={b.reject}
                        required
                        url={`/bank/questions/versions/${latest.id}/decide`}
                        extra={{ decision: 'reject' }}
                        field="note"
                        onClose={() => setDialog(null)}
                    />
                </>
            ) : null}
            <ConfirmDialog
                open={dialog === 'revise'}
                title={b.revise}
                description={b.reviseConfirm}
                confirmLabel={b.revise}
                confirmPending={busy}
                onConfirm={() => post(`/bank/questions/${question.id}/revise`)}
                onOpenChange={(next) => !next && setDialog(null)}
            />
            <NoteDialog
                open={dialog === 'retire'}
                title={b.retire}
                label={b.retireReason}
                submitLabel={b.retire}
                required
                url={`/bank/questions/${question.id}/retire`}
                extra={{}}
                field="reason"
                onClose={() => setDialog(null)}
            />
        </AppLayout>
    );
}
