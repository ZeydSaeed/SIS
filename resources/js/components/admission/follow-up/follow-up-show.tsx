import { router } from '@inertiajs/react';
import { Printer } from 'lucide-react';
import { useCallback, useMemo, type ReactNode } from 'react';
import { documentTypeLabel } from '@/components/admission/admission-document-types';
import type { AdmissionAcceptedStudent } from '@/components/admission/admission-workspace';
import {
    categoryOf,
    categoryToken,
    followUpCategories,
} from '@/components/admission/follow-up/follow-up-data';
import {
    applicationStatusLabel,
    FOLLOW_UP_TRANSITIONS,
    useFollowUpTransitions,
} from '@/components/admission/follow-up/follow-up-transitions';
import {
    useRegisterPageRibbon,
    type PageRibbonCommand,
    type PageRibbonGroup,
} from '@/components/sis/page-ribbon-context';
import { t } from '@/i18n';

export type FollowUpApplication = {
    id: number;
    school_id: number;
    school_name: string;
    application_number: string;
    first_name: string;
    father_name: string | null;
    grandfather_name: string | null;
    great_grandfather_name: string | null;
    last_name: string;
    mother_name: string | null;
    maternal_father_name: string | null;
    maternal_grandfather_name: string | null;
    national_id: string | null;
    birth_date: string;
    birth_place: string | null;
    gender: number;
    governorate: string | null;
    neighborhood: string | null;
    request_kind: number;
    status: number;
    notes: string | null;
    rejection_reason: string | null;
    withdrawal_reason: string | null;
    submitted_at: string | null;
    reviewed_at: string | null;
    reviewer_name: string | null;
    student_id: number | null;
    created_at: string;
    period_name: string;
    academic_year_name: string;
    branch_name: string | null;
    department_name: string | null;
    specialization_name: string | null;
    grade_name: string | null;
    documents: Array<{
        id: number;
        document_type: number;
        file_name: string;
        created_at: string | null;
    }>;
    history: Array<{
        from_status: number;
        to_status: number;
        at: string;
        by: string | null;
    }>;
    allowed_transitions: number[];
};

type Props = {
    application: FollowUpApplication;
    canManage: boolean;
    canViewPii: boolean;
};

/** «2026-09-05 10:12» — fixed digits and order, so it reads the same inside right-to-left text. */
function when(value: string | null): string {
    if (value === null || value === '') {
        return '—';
    }
    const date = new Date(value.replace(' ', 'T'));
    if (Number.isNaN(date.getTime())) {
        return value;
    }
    const pad = (part: number) => String(part).padStart(2, '0');

    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())} ${pad(date.getHours())}:${pad(date.getMinutes())}`;
}

/** The count beside a table's title in its title band. */
export function CaptionCount({ count }: { count: number }) {
    return (
        <span className="sis-follow-up-table__caption-count" dir="ltr">
            {count}
        </span>
    );
}

/** Label/value rows of a data section (the EXAMIS application summary table). */
function SummaryTable({
    caption,
    rows,
}: {
    caption: string;
    rows: Array<[string, ReactNode]>;
}) {
    return (
        <div className="sis-follow-up-table sis-follow-up-table--summary">
            <table>
                <caption className="sis-follow-up-table__caption">
                    <span>{caption}</span>
                    <CaptionCount count={rows.length} />
                </caption>
                <tbody>
                    {rows.map(([label, value]) => (
                        <tr key={label}>
                            <th scope="row">{label}</th>
                            <td>
                                {value === null || value === '' ? '—' : value}
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

/**
 * «متابعة طلبات التقديم» › one application — laid out like the EXAMIS staff application page: the applicant's
 * name with the application number, period and submission date; the status and a print button; the status
 * actions the current status allows; the applicant and request data with the documents; and, at the side, the
 * last review and the history of the application.
 */
export function FollowUpShow({ application, canManage, canViewPii }: Props) {
    const i18n = t();
    const a = i18n.admission;
    const categories = useMemo(() => followUpCategories(), []);
    const category = categoryOf(
        application as unknown as AdmissionAcceptedStudent,
        categories,
    );
    const fullName = [
        application.first_name,
        application.father_name,
        application.grandfather_name,
        application.great_grandfather_name,
        application.last_name,
    ]
        .filter(Boolean)
        .join(' ');

    const reload = useCallback(() => router.reload(), []);
    const { request, busy, dialogs } = useFollowUpTransitions(reload);
    const moves = useMemo(
        () =>
            FOLLOW_UP_TRANSITIONS.filter((item) =>
                application.allowed_transitions.includes(item.status),
            ),
        [application.allowed_transitions],
    );
    const move = useCallback(
        (status: number) =>
            request([application.id], application.school_id, status),
        [application.id, application.school_id, request],
    );

    const ribbonGroups = useMemo((): PageRibbonGroup[] => {
        const groups: PageRibbonGroup[] = [
            {
                id: 'follow-up-actions',
                label: i18n.common.actions,
                commands: [
                    {
                        id: 'follow-up-print',
                        label: a.followUpPrint,
                        icon: Printer,
                        onSelect: () => window.print(),
                    },
                ],
            },
        ];
        if (canManage && moves.length > 0) {
            groups.push({
                id: 'follow-up-status',
                label: a.followUpTransitions,
                commands: moves.map((item): PageRibbonCommand => ({
                    id: `follow-up-status-${item.status}`,
                    label: applicationStatusLabel(item.status),
                    icon: item.icon,
                    tone: item.tone,
                    disabled: busy,
                    onSelect: () => move(item.status),
                })),
            });
        }

        return groups;
    }, [
        a.followUpPrint,
        a.followUpTransitions,
        busy,
        canManage,
        i18n.common.actions,
        move,
        moves,
    ]);
    useRegisterPageRibbon('edit', ribbonGroups);

    const applicantRows: Array<[string, ReactNode]> = [
        [
            a.applicationNumber,
            <bdi dir="ltr">{application.application_number}</bdi>,
        ],
        [a.firstName, application.first_name],
        [a.fatherName, application.father_name],
        [a.grandfatherName, application.grandfather_name],
        [a.greatGrandfatherName, application.great_grandfather_name],
        [a.lastName, application.last_name],
        [a.motherName, application.mother_name],
        [a.maternalFatherName, application.maternal_father_name],
        [a.maternalGrandfatherName, application.maternal_grandfather_name],
        [
            a.nationalId,
            canViewPii ? (
                application.national_id && (
                    <bdi dir="ltr">{application.national_id}</bdi>
                )
            ) : (
                <span className="sis-follow-up-muted">
                    {a.followUpPiiHidden}
                </span>
            ),
        ],
        [a.birthDate, <bdi dir="ltr">{application.birth_date}</bdi>],
        [a.birthPlace, application.birth_place],
        [a.gender, application.gender === 2 ? a.genderFemale : a.genderMale],
        [a.governorate, application.governorate],
        [a.neighborhood, application.neighborhood],
    ];
    const requestRows: Array<[string, ReactNode]> = [
        [
            a.followUpChannel,
            application.request_kind === 1
                ? a.followUpChannelTransfer
                : a.followUpChannelVocational,
        ],
        [a.filterBySchool, application.school_name],
        [a.academicYear, <bdi dir="ltr">{application.academic_year_name}</bdi>],
        [a.followUpPeriod, application.period_name],
        [a.gradeLevel, application.grade_name],
        [a.branch, application.branch_name],
        [
            a.department,
            application.department_name ?? application.specialization_name,
        ],
        [a.notes, application.notes],
    ];
    if (application.rejection_reason) {
        requestRows.push([
            a.acceptedStatsRejectionReason,
            application.rejection_reason,
        ]);
    }
    if (application.withdrawal_reason) {
        requestRows.push([
            a.acceptedStatsWithdrawalReason,
            application.withdrawal_reason,
        ]);
    }
    if (application.student_id !== null) {
        requestRows.push([a.statusConverted, a.followUpStudentLinked]);
    }

    const timeline: Array<{
        key: string;
        title: string;
        at: string | null;
        by: string | null;
    }> = [
        {
            key: 'created',
            title: a.followUpCreated,
            at: application.created_at,
            by: null,
        },
        ...(application.submitted_at &&
        !application.history.some((event) => event.to_status === 2)
            ? [
                  {
                      key: 'submitted',
                      title: a.followUpSubmitted,
                      at: application.submitted_at,
                      by: null,
                  },
              ]
            : []),
        ...application.history.map((event, index) => ({
            key: `move-${index}`,
            title: `${applicationStatusLabel(event.from_status)} ← ${applicationStatusLabel(event.to_status)}`,
            at: event.at,
            by: event.by,
        })),
    ];

    return (
        <div className="sis-follow-up-show">
            <header className="sis-follow-up-show__header">
                <div className="sis-follow-up-show__heading">
                    <h1 className="sis-follow-up-show__title">
                        {fullName || '—'}
                    </h1>
                    <p className="sis-follow-up-show__meta">
                        <bdi dir="ltr" className="sis-follow-up-show__number">
                            {application.application_number}
                        </bdi>
                        {' · '}
                        {application.period_name}
                        {' · '}
                        {a.followUpSubmittedAt}:{' '}
                        <bdi dir="ltr">{when(application.submitted_at)}</bdi>
                    </p>
                </div>
                <div className="sis-follow-up-show__header-actions">
                    <span
                        className={`sis-follow-up-status${category ? ` sis-follow-up-tone--${categoryToken(category.key)}` : ''}`}
                    >
                        <span
                            className="sis-follow-up-badge__dot"
                            aria-hidden
                        />
                        {applicationStatusLabel(application.status)}
                    </span>
                    <button
                        type="button"
                        className="sis-follow-up-button"
                        onClick={() => window.print()}
                    >
                        <Printer className="size-4" aria-hidden />
                        {a.followUpPrint}
                    </button>
                </div>
            </header>

            {canManage ? (
                <section
                    className="sis-follow-up-card-box sis-follow-up-show__actions"
                    aria-label={a.followUpActions}
                >
                    <span className="sis-follow-up-show__actions-label">
                        {a.followUpActions}:
                    </span>
                    {moves.length === 0 ? (
                        <span className="sis-follow-up-muted">
                            {a.followUpNoActions}
                        </span>
                    ) : (
                        moves.map((item) => {
                            const Icon = item.icon;

                            return (
                                <button
                                    key={item.status}
                                    type="button"
                                    className={`sis-follow-up-button${item.tone ? ` sis-follow-up-button--${item.tone}` : ''}`}
                                    disabled={busy}
                                    onClick={() => move(item.status)}
                                >
                                    <Icon className="size-4" aria-hidden />
                                    {applicationStatusLabel(item.status)}
                                </button>
                            );
                        })
                    )}
                </section>
            ) : null}

            <div className="sis-follow-up-show__grid">
                <div className="sis-follow-up-show__main">
                    <SummaryTable
                        caption={a.followUpApplicantData}
                        rows={applicantRows}
                    />
                    <SummaryTable
                        caption={a.followUpRequestData}
                        rows={requestRows}
                    />
                    <div className="sis-follow-up-table sis-follow-up-table--summary">
                        <table>
                            {application.documents.length === 0 ? (
                                <tbody>
                                    <tr>
                                        <td className="sis-follow-up-muted">
                                            {a.followUpNoDocuments}
                                        </td>
                                    </tr>
                                </tbody>
                            ) : (
                                <>
                                    <thead>
                                        <tr>
                                            <th
                                                scope="col"
                                                className="sis-follow-up-table__num"
                                            >
                                                #
                                            </th>
                                            <th scope="col">
                                                {a.documentType}
                                            </th>
                                            <th scope="col">{a.fileName}</th>
                                            <th scope="col">
                                                {a.followUpSubmittedAt}
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {application.documents.map(
                                            (document, index) => (
                                                <tr key={document.id}>
                                                    <td
                                                        className="sis-follow-up-table__num"
                                                        dir="ltr"
                                                    >
                                                        {index + 1}
                                                    </td>
                                                    <td>
                                                        {documentTypeLabel(
                                                            document.document_type,
                                                        )}
                                                    </td>
                                                    <td dir="ltr">
                                                        {document.file_name}
                                                    </td>
                                                    <td dir="ltr">
                                                        {when(
                                                            document.created_at,
                                                        )}
                                                    </td>
                                                </tr>
                                            ),
                                        )}
                                    </tbody>
                                </>
                            )}
                        </table>
                    </div>
                </div>

                <aside className="sis-follow-up-show__side">
                    <section
                        className="sis-follow-up-card-box"
                        aria-labelledby="follow-up-review"
                    >
                        <h2
                            id="follow-up-review"
                            className="sis-follow-up-card-box__title"
                        >
                            {a.followUpReviewer}
                        </h2>
                        {application.reviewer_name ||
                        application.reviewed_at ? (
                            <p className="sis-follow-up-show__review">
                                <span>{application.reviewer_name ?? '—'}</span>
                                <bdi dir="ltr" className="sis-follow-up-muted">
                                    {when(application.reviewed_at)}
                                </bdi>
                            </p>
                        ) : (
                            <p className="sis-follow-up-muted">
                                {a.followUpNotReviewed}
                            </p>
                        )}
                    </section>
                    <section
                        className="sis-follow-up-card-box"
                        aria-labelledby="follow-up-history"
                    >
                        <h2
                            id="follow-up-history"
                            className="sis-follow-up-card-box__title"
                        >
                            {a.followUpHistory}
                        </h2>
                        <ol className="sis-follow-up-timeline">
                            {timeline.map((step) => (
                                <li
                                    key={step.key}
                                    className="sis-follow-up-timeline__item"
                                >
                                    <span
                                        className="sis-follow-up-timeline__dot"
                                        aria-hidden
                                    />
                                    <span className="sis-follow-up-timeline__title">
                                        {step.title}
                                    </span>
                                    <span className="sis-follow-up-muted">
                                        <bdi dir="ltr">{when(step.at)}</bdi>
                                        {step.by ? ` · ${step.by}` : ''}
                                    </span>
                                </li>
                            ))}
                        </ol>
                    </section>
                </aside>
            </div>

            {dialogs}
        </div>
    );
}
