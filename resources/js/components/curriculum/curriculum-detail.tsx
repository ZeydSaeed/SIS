import { router, usePage } from '@inertiajs/react';
import { BookOpen, ListChecks, Pencil, Plus } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { CurriculumPlanSheetDialog } from '@/components/curriculum/curriculum-plan-sheet';
import {
    LinkSubjectDialog,
    newIdempotencyKey,
    postWithIdempotency,
    statusLabel,
    type CurriculumFilterOptions,
    type CurriculumPagination,
    type CurriculumRow,
    type LinkedSubjectRow,
    type SubjectRow,
} from '@/components/curriculum/curriculum-workspace';
import { ConfirmDialog } from '@/components/sis/confirm-dialog';
import { DataTable, type DataTableColumn } from '@/components/sis/data-table';
import { OpsFormField, OpsTextInput } from '@/components/sis/ops-form-field';
import { PageHeader } from '@/components/sis/page-header';
import type { ChromeTabId } from '@/components/sis/chrome-tabs';
import {
    useActivePageRibbonTab,
    useRegisterPageRibbon,
    useSetActivePageRibbonTab,
    type PageRibbonGroup,
} from '@/components/sis/page-ribbon-context';
import { SisListSelect } from '@/components/sis/sis-list-select';
import { Button } from '@/components/ui/button';
import { t } from '@/i18n';
import {
    collectInertiaErrorMessages,
    resolveErrorMessage,
} from '@/lib/format-inertia-errors';

export type PrerequisiteRow = {
    id: number;
    subject_id: number;
    subject_name: string | null;
    prerequisite_subject_id: number;
    prerequisite_subject_name: string | null;
    status: number;
};

export type EnrollmentListRow = {
    id: number;
    enrollment_number: string | null;
    student_code: string | null;
    student_full_name: string | null;
    class_name: string | null;
    section_name: string | null;
    status: number;
};

export type EnrollmentSubjectRow = {
    id: number;
    enrollment_id: number;
    subject_id: number;
    subject_code: string | null;
    subject_name: string | null;
    is_elective: boolean;
    status: number;
};

export type CurriculumShowTab = 'plan' | 'subjects' | 'prerequisites' | 'students';

export type CurriculumShowProps = {
    curriculum: CurriculumRow;
    linkedSubjects: LinkedSubjectRow[];
    prerequisites: PrerequisiteRow[];
    subjectOptions: SubjectRow[];
    enrollments: { data: EnrollmentListRow[]; pagination: CurriculumPagination };
    selectedEnrollmentId: number | null;
    enrollmentSubjects: EnrollmentSubjectRow[];
    filterOptions: CurriculumFilterOptions;
    filters: {
        enrollment_q: string;
        enrollment_page: number;
        tab: string;
    };
    authorization: {
        canView: boolean;
        canManage: boolean;
        canAssignEnrollmentSubjects: boolean;
    };
};

type ConfirmState =
    | { kind: 'link-deactivate'; id: number }
    | { kind: 'link-reactivate'; id: number }
    | { kind: 'prereq-deactivate'; id: number }
    | { kind: 'prereq-reactivate'; id: number }
    | null;

function normalizeTab(tab: string): CurriculumShowTab {
    if (tab === 'plan' || tab === 'prerequisites' || tab === 'students') {
        return tab;
    }

    return 'subjects';
}

/** Content sections → chrome tab strip SSOT (same strip as enrollment). */
const CHROME_BY_CONTENT: Record<CurriculumShowTab, ChromeTabId> = {
    plan: 'home',
    subjects: 'lists',
    prerequisites: 'tools',
    students: 'reports',
};

const CONTENT_BY_CHROME: Partial<Record<ChromeTabId, CurriculumShowTab>> = {
    home: 'plan',
    lists: 'subjects',
    tools: 'prerequisites',
    reports: 'students',
};

function formatPaginationSummary(
    template: string,
    pagination: CurriculumPagination,
): string {
    return template
        .replace(':page', String(pagination.page))
        .replace(':last', String(pagination.last_page))
        .replace(':total', String(pagination.total));
}

export function CurriculumDetail(props: CurriculumShowProps) {
    const {
        curriculum,
        linkedSubjects,
        prerequisites,
        subjectOptions,
        enrollments,
        selectedEnrollmentId,
        enrollmentSubjects,
        filterOptions,
        filters,
        authorization,
    } = props;
    const i18n = t();
    const c = i18n.curriculum;
    const canManage = authorization.canManage;
    const canAssign = authorization.canAssignEnrollmentSubjects;
    const activeTab = normalizeTab(filters.tab);
    const page = usePage();
    const inertiaErrors = (page.props.errors ?? {}) as Record<string, string | string[] | undefined>;
    const chromeTab = useActivePageRibbonTab();
    const setActiveChromeTab = useSetActivePageRibbonTab();
    const syncingChromeRef = useRef(false);

    const [planDialogOpen, setPlanDialogOpen] = useState(false);
    const [linkDialogOpen, setLinkDialogOpen] = useState(false);
    const [prereqSubjectId, setPrereqSubjectId] = useState('');
    const [prereqRequiredId, setPrereqRequiredId] = useState('');
    const [confirm, setConfirm] = useState<ConfirmState>(null);
    const [confirmPending, setConfirmPending] = useState(false);
    const [assignPendingId, setAssignPendingId] = useState<number | null>(null);

    const visitShow = (overrides: Record<string, string | number | null | undefined> = {}): void => {
        const next = {
            tab: activeTab,
            enrollment_q: filters.enrollment_q,
            enrollment_page: filters.enrollment_page,
            enrollment_id: selectedEnrollmentId,
            ...overrides,
        };
        const params: Record<string, string | number> = {};
        for (const [key, value] of Object.entries(next)) {
            if (value === null || value === undefined || value === '') {
                continue;
            }
            params[key] = value;
        }
        router.get(`/curriculum/curricula/${curriculum.id}`, params, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const setTab = (tab: CurriculumShowTab): void => {
        visitShow({ tab, enrollment_page: tab === 'students' ? filters.enrollment_page : 1 });
    };

    useEffect(() => {
        syncingChromeRef.current = true;
        setActiveChromeTab(CHROME_BY_CONTENT[activeTab]);
        const timer = window.setTimeout(() => {
            syncingChromeRef.current = false;
        }, 0);

        return () => window.clearTimeout(timer);
    }, [activeTab, setActiveChromeTab]);

    useEffect(() => {
        if (syncingChromeRef.current || chromeTab === null) {
            return;
        }
        const next = CONTENT_BY_CHROME[chromeTab];
        if (next && next !== activeTab) {
            setTab(next);
        }
        // setTab closes over visitShow; intentional sync from chrome strip only
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [chromeTab]);

    const planRibbonGroups = useMemo((): PageRibbonGroup[] => {
        return [
            {
                id: 'curriculum-show-plan',
                label: c.tabPlan,
                commands: canManage && curriculum.status === 1
                    ? [
                          {
                              id: 'edit-plan',
                              label: c.editPlan,
                              icon: Pencil,
                              onSelect: () => setPlanDialogOpen(true),
                          },
                      ]
                    : [],
            },
        ];
    }, [c.editPlan, c.tabPlan, canManage, curriculum.status]);

    const subjectsRibbonGroups = useMemo((): PageRibbonGroup[] => {
        return [
            {
                id: 'curriculum-show-subjects',
                label: c.tabSubjects,
                commands: canManage && curriculum.status === 1
                    ? [
                          {
                              id: 'link-subject',
                              label: c.addLinkedSubject,
                              icon: Plus,
                              onSelect: () => setLinkDialogOpen(true),
                          },
                      ]
                    : [],
            },
        ];
    }, [c.addLinkedSubject, c.tabSubjects, canManage, curriculum.status]);

    const prerequisitesRibbonGroups = useMemo((): PageRibbonGroup[] => {
        return [
            {
                id: 'curriculum-show-prerequisites',
                label: c.tabPrerequisites,
                commands: [],
            },
        ];
    }, [c.tabPrerequisites]);

    const studentsRibbonGroups = useMemo((): PageRibbonGroup[] => {
        return [
            {
                id: 'curriculum-show-students',
                label: c.tabStudents,
                commands: canAssign && curriculum.status === 1
                    ? [
                          {
                              id: 'apply-curriculum-to-enrollments',
                              label: c.applyToEnrollments,
                              title: c.applyToEnrollmentsTitle,
                              icon: ListChecks,
                              onSelect: () =>
                                  postWithIdempotency(
                                      `/curriculum/curricula/${curriculum.id}/apply-to-enrollments`,
                                  ),
                          },
                      ]
                    : [],
            },
        ];
    }, [
        c.applyToEnrollments,
        c.applyToEnrollmentsTitle,
        c.tabStudents,
        canAssign,
        curriculum.id,
        curriculum.status,
    ]);

    useRegisterPageRibbon('home', planRibbonGroups);
    useRegisterPageRibbon('lists', subjectsRibbonGroups);
    useRegisterPageRibbon('tools', prerequisitesRibbonGroups);
    useRegisterPageRibbon('reports', studentsRibbonGroups);

    const assignedSubjectIds = useMemo(() => {
        return new Set(
            enrollmentSubjects.filter((row) => row.status === 1).map((row) => row.subject_id),
        );
    }, [enrollmentSubjects]);

    const activeLinkedSubjects = useMemo(
        () => linkedSubjects.filter((row) => row.status === 1),
        [linkedSubjects],
    );

    const assignErrors = useMemo(() => {
        const raw = collectInertiaErrorMessages({
            enrollment_subject: inertiaErrors.enrollment_subject,
            prerequisite: inertiaErrors.prerequisite,
        });

        return raw.map((message) => resolveErrorMessage(message, message));
    }, [inertiaErrors.enrollment_subject, inertiaErrors.prerequisite]);

    const linkedColumns: DataTableColumn<LinkedSubjectRow>[] = [
        {
            id: 'subject',
            header: c.subjectName,
            cell: (row) => row.subject_name ?? <span dir="ltr">{row.subject_id}</span>,
        },
        {
            id: 'code',
            header: c.subjectCode,
            cell: (row) =>
                row.subject_code ? <span dir="ltr">{row.subject_code}</span> : '—',
            hideOnMobile: true,
        },
        {
            id: 'hours',
            header: c.weeklyHours,
            cell: (row) =>
                row.weekly_hours !== null ? <span dir="ltr">{row.weekly_hours}</span> : '—',
        },
        {
            id: 'required',
            header: c.isRequired,
            cell: (row) => (row.is_required ? c.requiredYes : c.requiredNo),
        },
        {
            id: 'order',
            header: c.subjectOrder,
            cell: (row) => <span dir="ltr">{row.subject_order}</span>,
            hideOnMobile: true,
        },
        {
            id: 'status',
            header: i18n.common.status,
            cell: (row) => statusLabel(row.status, i18n.status.active, i18n.status.inactive),
        },
        {
            id: 'actions',
            header: c.actions,
            cell: (row) =>
                canManage ? (
                    row.status === 1 ? (
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={() => setConfirm({ kind: 'link-deactivate', id: row.id })}
                        >
                            {c.deactivate}
                        </Button>
                    ) : (
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={() => setConfirm({ kind: 'link-reactivate', id: row.id })}
                        >
                            {c.reactivate}
                        </Button>
                    )
                ) : (
                    '—'
                ),
        },
    ];

    const prereqColumns: DataTableColumn<PrerequisiteRow>[] = [
        {
            id: 'subject',
            header: c.prerequisiteSubject,
            cell: (row) => row.subject_name ?? <span dir="ltr">{row.subject_id}</span>,
        },
        {
            id: 'required',
            header: c.prerequisiteRequired,
            cell: (row) =>
                row.prerequisite_subject_name ?? (
                    <span dir="ltr">{row.prerequisite_subject_id}</span>
                ),
        },
        {
            id: 'status',
            header: i18n.common.status,
            cell: (row) => statusLabel(row.status, i18n.status.active, i18n.status.inactive),
        },
        {
            id: 'actions',
            header: c.actions,
            cell: (row) =>
                canManage ? (
                    row.status === 1 ? (
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={() => setConfirm({ kind: 'prereq-deactivate', id: row.id })}
                        >
                            {c.deactivate}
                        </Button>
                    ) : (
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={() => setConfirm({ kind: 'prereq-reactivate', id: row.id })}
                        >
                            {c.reactivate}
                        </Button>
                    )
                ) : (
                    '—'
                ),
        },
    ];

    const enrollmentColumns: DataTableColumn<EnrollmentListRow>[] = [
        {
            id: 'number',
            header: c.enrollmentNumber,
            cell: (row) => <span dir="ltr">{row.enrollment_number}</span>,
        },
        {
            id: 'student',
            header: c.studentName,
            cell: (row) => row.student_full_name ?? '—',
        },
        {
            id: 'class',
            header: c.classSection,
            cell: (row) =>
                [row.class_name, row.section_name].filter(Boolean).join(' · ') || '—',
            hideOnMobile: true,
        },
        {
            id: 'actions',
            header: c.actions,
            cell: (row) => (
                <Button
                    type="button"
                    variant={selectedEnrollmentId === row.id ? 'default' : 'outline'}
                    size="sm"
                    onClick={() =>
                        visitShow({
                            tab: 'students',
                            enrollment_id: row.id,
                            enrollment_page: filters.enrollment_page,
                        })
                    }
                >
                    {selectedEnrollmentId === row.id ? i18n.common.open : c.view}
                </Button>
            ),
        },
    ];

    const runConfirm = (): void => {
        if (!confirm) {
            return;
        }
        setConfirmPending(true);
        const done = (): void => {
            setConfirmPending(false);
            setConfirm(null);
        };
        const headers = { 'X-Idempotency-Key': newIdempotencyKey('curriculum') };

        switch (confirm.kind) {
            case 'link-deactivate':
                router.post(
                    `/curriculum/curriculum-subjects/${confirm.id}/deactivate`,
                    {},
                    { preserveScroll: true, headers, onFinish: done },
                );
                break;
            case 'link-reactivate':
                router.post(
                    `/curriculum/curriculum-subjects/${confirm.id}/reactivate`,
                    {},
                    { preserveScroll: true, headers, onFinish: done },
                );
                break;
            case 'prereq-deactivate':
                router.post(
                    `/curriculum/prerequisites/${confirm.id}/deactivate`,
                    {},
                    { preserveScroll: true, headers, onFinish: done },
                );
                break;
            case 'prereq-reactivate':
                router.post(
                    `/curriculum/prerequisites/${confirm.id}/reactivate`,
                    {},
                    { preserveScroll: true, headers, onFinish: done },
                );
                break;
        }
    };

    const confirmCopy = useMemo(() => {
        if (!confirm) {
            return { title: '', description: '' };
        }
        switch (confirm.kind) {
            case 'link-deactivate':
                return { title: c.deactivate, description: c.confirmDeactivateLink };
            case 'link-reactivate':
                return { title: c.reactivate, description: c.confirmReactivateLink };
            case 'prereq-deactivate':
                return { title: c.deactivate, description: c.confirmDeactivatePrerequisite };
            case 'prereq-reactivate':
                return { title: c.reactivate, description: c.confirmReactivatePrerequisite };
        }
    }, [c, confirm]);

    const assignSubject = (subjectId: number, isElective: boolean): void => {
        if (selectedEnrollmentId === null || !canAssign) {
            return;
        }
        setAssignPendingId(subjectId);
        router.post(
            `/curriculum/enrollments/${selectedEnrollmentId}/subjects`,
            { subject_id: subjectId, is_elective: isElective },
            {
                preserveScroll: true,
                headers: { 'X-Idempotency-Key': newIdempotencyKey('curriculum-enroll-subject') },
                onFinish: () => setAssignPendingId(null),
            },
        );
    };

    const tabs: Array<{ id: CurriculumShowTab; label: string }> = [
        { id: 'plan', label: c.tabPlan },
        { id: 'subjects', label: c.tabSubjects },
        { id: 'prerequisites', label: c.tabPrerequisites },
        { id: 'students', label: c.tabStudents },
    ];

    return (
        <div className="sis-ops-hub flex flex-col gap-4 p-4" dir="rtl" lang="ar">
            <PageHeader
                title={curriculum.name}
                description={c.showDescription}
                icon={<BookOpen className="size-6" aria-hidden />}
                actions={
                    <Button type="button" variant="outline" onClick={() => router.get('/curriculum')}>
                        {i18n.common.backToList}
                    </Button>
                }
            />

            <div className="text-muted-foreground flex flex-wrap gap-3 text-sm">
                <span>
                    {c.branch}: {curriculum.branch_name ?? '—'}
                </span>
                <span>
                    {c.department}: {curriculum.department_name ?? '—'}
                </span>
                <span>
                    {c.gradeLevel}: {curriculum.grade_level_name ?? curriculum.grade_level_id}
                </span>
                <span>
                    {i18n.common.status}:{' '}
                    {statusLabel(curriculum.status, i18n.status.active, i18n.status.inactive)}
                </span>
            </div>

            <p className="sr-only" aria-live="polite">
                {tabs.find((tab) => tab.id === activeTab)?.label ?? c.showTitle}
            </p>

            {activeTab === 'plan' ? (
                <section className="flex flex-col gap-3" role="tabpanel">
                    <dl className="grid gap-2 sm:grid-cols-2">
                        <div>
                            <dt className="text-muted-foreground text-sm">{c.planName}</dt>
                            <dd className="font-medium">{curriculum.name}</dd>
                        </div>
                        <div>
                            <dt className="text-muted-foreground text-sm">{c.department}</dt>
                            <dd className="font-medium">{curriculum.department_name ?? '—'}</dd>
                        </div>
                        <div>
                            <dt className="text-muted-foreground text-sm">{c.branch}</dt>
                            <dd className="font-medium">{curriculum.branch_name ?? '—'}</dd>
                        </div>
                        <div>
                            <dt className="text-muted-foreground text-sm">{c.gradeLevel}</dt>
                            <dd className="font-medium">
                                {curriculum.grade_level_name ?? curriculum.grade_level_id}
                            </dd>
                        </div>
                    </dl>
                    {canManage && curriculum.status === 1 ? (
                        <Button
                            type="button"
                            className="self-start"
                            onClick={() => setPlanDialogOpen(true)}
                        >
                            {c.editPlan}
                        </Button>
                    ) : null}
                </section>
            ) : null}

            {activeTab === 'subjects' ? (
                <section className="flex flex-col gap-2" role="tabpanel">
                    <div className="flex flex-wrap items-center justify-between gap-2">
                        <h2 className="text-lg font-semibold">{c.linkedSubjectsTitle}</h2>
                        {canManage && curriculum.status === 1 ? (
                            <Button type="button" size="sm" onClick={() => setLinkDialogOpen(true)}>
                                <Plus className="size-4" aria-hidden />
                                {c.addLinkedSubject}
                            </Button>
                        ) : null}
                    </div>
                    <DataTable
                        columns={linkedColumns}
                        rows={linkedSubjects}
                        rowKey={(row) => row.id}
                        emptyTitle={c.linkedEmptyTitle}
                        emptyDescription={c.linkedEmptyDesc}
                        caption={c.linkedSubjectsCaption}
                        mobileCard={(row) => (
                            <div className="rounded-md border border-[color:var(--sis-powder-blue)] bg-[color-mix(in_srgb,var(--sis-powder-blue)_18%,white)] p-3">
                                <div className="font-semibold">
                                    {row.subject_name ?? row.subject_id}
                                </div>
                                <div className="text-sm opacity-80">
                                    {row.is_required ? c.requiredYes : c.requiredNo}
                                </div>
                            </div>
                        )}
                    />
                </section>
            ) : null}

            {activeTab === 'prerequisites' ? (
                <section className="flex flex-col gap-3" role="tabpanel">
                    <h2 className="text-lg font-semibold">{c.prerequisitesTitle}</h2>
                    {canManage ? (
                        <form
                            className="flex flex-wrap items-end gap-3 rounded-md border border-[color:var(--sis-powder-blue)] p-3"
                            onSubmit={(event) => {
                                event.preventDefault();
                                if (prereqSubjectId === '' || prereqRequiredId === '') {
                                    return;
                                }
                                postWithIdempotency(
                                    `/curriculum/subjects/${prereqSubjectId}/prerequisites`,
                                    {
                                        prerequisite_subject_id: Number(prereqRequiredId),
                                    },
                                );
                                setPrereqSubjectId('');
                                setPrereqRequiredId('');
                            }}
                        >
                            <OpsFormField label={c.prerequisiteSubject} name="subject_id">
                                <SisListSelect
                                    name="subject_id"
                                    ariaLabel={c.prerequisiteSubject}
                                    value={prereqSubjectId}
                                    required
                                    onChange={setPrereqSubjectId}
                                    options={activeLinkedSubjects.map((s) => ({
                                        value: String(s.subject_id),
                                        label: s.subject_name ?? String(s.subject_id),
                                    }))}
                                />
                            </OpsFormField>
                            <OpsFormField label={c.prerequisiteRequired} name="prerequisite_subject_id">
                                <SisListSelect
                                    name="prerequisite_subject_id"
                                    ariaLabel={c.prerequisiteRequired}
                                    value={prereqRequiredId}
                                    required
                                    onChange={setPrereqRequiredId}
                                    options={subjectOptions
                                        .filter((s) => String(s.id) !== prereqSubjectId)
                                        .map((s) => ({
                                            value: String(s.id),
                                            label: `${s.name} (${s.code})`,
                                        }))}
                                />
                            </OpsFormField>
                            <Button
                                type="submit"
                                disabled={prereqSubjectId === '' || prereqRequiredId === ''}
                            >
                                {c.addPrerequisite}
                            </Button>
                        </form>
                    ) : null}
                    <DataTable
                        columns={prereqColumns}
                        rows={prerequisites}
                        rowKey={(row) => row.id}
                        emptyTitle={c.prerequisitesEmptyTitle}
                        emptyDescription={c.prerequisitesEmptyDesc}
                        caption={c.prerequisitesCaption}
                        mobileCard={(row) => (
                            <div className="rounded-md border border-[color:var(--sis-powder-blue)] bg-[color-mix(in_srgb,var(--sis-powder-blue)_18%,white)] p-3">
                                <div className="font-semibold">
                                    {row.subject_name ?? row.subject_id}
                                </div>
                                <div className="text-sm opacity-80">
                                    ← {row.prerequisite_subject_name ?? row.prerequisite_subject_id}
                                </div>
                            </div>
                        )}
                    />
                </section>
            ) : null}

            {activeTab === 'students' ? (
                <section className="flex flex-col gap-3" role="tabpanel">
                    <div className="flex flex-wrap items-end justify-between gap-3">
                        <h2 className="text-lg font-semibold">{c.enrollmentsTitle}</h2>
                        <form
                            className="flex flex-wrap items-end gap-2"
                            onSubmit={(event) => {
                                event.preventDefault();
                                const form = event.currentTarget;
                                const q = new FormData(form).get('enrollment_q');
                                visitShow({
                                    tab: 'students',
                                    enrollment_q: typeof q === 'string' ? q : '',
                                    enrollment_page: 1,
                                });
                            }}
                        >
                            <OpsFormField label={c.searchEnrollments} name="enrollment_q">
                                <OpsTextInput
                                    name="enrollment_q"
                                    dir="rtl"
                                    defaultValue={filters.enrollment_q}
                                    placeholder={c.searchEnrollmentsAria}
                                />
                            </OpsFormField>
                            <Button type="submit" variant="outline" size="sm">
                                {i18n.common.load}
                            </Button>
                        </form>
                    </div>
                    <p className="text-muted-foreground text-sm">{c.selectEnrollmentHint}</p>
                    <DataTable
                        columns={enrollmentColumns}
                        rows={enrollments.data}
                        rowKey={(row) => row.id}
                        emptyTitle={
                            filters.enrollment_q.trim() !== ''
                                ? c.emptySearch
                                : c.enrollmentsEmptyTitle
                        }
                        emptyDescription={
                            filters.enrollment_q.trim() !== ''
                                ? undefined
                                : c.enrollmentsEmptyDesc
                        }
                        caption={c.enrollmentsCaption}
                        mobileCard={(row) => (
                            <button
                                type="button"
                                className="w-full rounded-md border border-[color:var(--sis-powder-blue)] bg-[color-mix(in_srgb,var(--sis-powder-blue)_18%,white)] p-3 text-start"
                                onClick={() =>
                                    visitShow({
                                        tab: 'students',
                                        enrollment_id: row.id,
                                    })
                                }
                            >
                                <div className="font-semibold">
                                    {row.student_full_name ?? row.enrollment_number}
                                </div>
                                <div className="text-sm opacity-80" dir="ltr">
                                    {row.enrollment_number}
                                </div>
                            </button>
                        )}
                    />
                    {enrollments.pagination.last_page > 1 ? (
                        <div className="flex flex-wrap items-center gap-2">
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                disabled={enrollments.pagination.page <= 1}
                                onClick={() =>
                                    visitShow({
                                        tab: 'students',
                                        enrollment_page: enrollments.pagination.page - 1,
                                    })
                                }
                            >
                                {i18n.common.previous}
                            </Button>
                            <span className="text-sm">
                                {formatPaginationSummary(
                                    c.paginationSummary,
                                    enrollments.pagination,
                                )}
                            </span>
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                disabled={
                                    enrollments.pagination.page >=
                                    enrollments.pagination.last_page
                                }
                                onClick={() =>
                                    visitShow({
                                        tab: 'students',
                                        enrollment_page: enrollments.pagination.page + 1,
                                    })
                                }
                            >
                                {i18n.common.next}
                            </Button>
                        </div>
                    ) : null}

                    {selectedEnrollmentId !== null ? (
                        <div className="flex flex-col gap-2 rounded-md border border-[color:var(--sis-powder-blue)] p-3">
                            <h3 className="font-semibold">{c.assignedSubjectsTitle}</h3>
                            {assignErrors.length > 0 ? (
                                <div
                                    className="rounded-md border border-[color:var(--sis-powder-blush)] bg-[color-mix(in_srgb,var(--sis-powder-blush)_12%,white)] p-3 text-sm"
                                    role="alert"
                                >
                                    <div className="font-medium">{c.assignErrors}</div>
                                    <ul className="mt-1 list-inside list-disc">
                                        {assignErrors.map((message) => (
                                            <li key={message}>{message}</li>
                                        ))}
                                    </ul>
                                </div>
                            ) : null}
                            <ul className="flex flex-col gap-2">
                                {activeLinkedSubjects.map((link) => {
                                    const assigned = assignedSubjectIds.has(link.subject_id);

                                    return (
                                        <li
                                            key={link.id}
                                            className="flex flex-wrap items-center justify-between gap-2 border-b border-[color:var(--sis-powder-blue)] py-2 last:border-b-0"
                                        >
                                            <label className="flex items-center gap-2 text-sm">
                                                <input
                                                    type="checkbox"
                                                    className="size-4"
                                                    checked={assigned}
                                                    disabled={
                                                        assigned ||
                                                        !canAssign ||
                                                        assignPendingId === link.subject_id
                                                    }
                                                    onChange={() => {
                                                        if (!assigned) {
                                                            assignSubject(
                                                                link.subject_id,
                                                                !link.is_required,
                                                            );
                                                        }
                                                    }}
                                                />
                                                <span>
                                                    {link.subject_name ?? link.subject_id}
                                                    {link.subject_code ? (
                                                        <span className="text-muted-foreground ms-2" dir="ltr">
                                                            ({link.subject_code})
                                                        </span>
                                                    ) : null}
                                                </span>
                                            </label>
                                            <span className="text-muted-foreground text-xs">
                                                {assigned ? c.assignedYes : c.assignedNo}
                                            </span>
                                        </li>
                                    );
                                })}
                            </ul>
                            {activeLinkedSubjects.length === 0 ? (
                                <p className="text-muted-foreground text-sm">{c.linkedEmptyDesc}</p>
                            ) : null}
                        </div>
                    ) : null}
                </section>
            ) : null}

            {planDialogOpen ? (
                <CurriculumPlanSheetDialog
                    mode="edit"
                    plan={curriculum}
                    canManage={canManage}
                    initialEditing
                    filterOptions={filterOptions}
                    defaultAcademicYearId={curriculum.academic_year_id}
                    onClose={() => setPlanDialogOpen(false)}
                />
            ) : null}

            <LinkSubjectDialog
                open={linkDialogOpen}
                onOpenChange={setLinkDialogOpen}
                curriculum={curriculum}
                subjects={subjectOptions.filter((s) => s.status === 1)}
                existingSubjectIds={new Set(linkedSubjects.map((l) => l.subject_id))}
            />

            <ConfirmDialog
                open={confirm !== null}
                title={confirmCopy.title}
                description={confirmCopy.description}
                confirmPending={confirmPending}
                tone={
                    confirm?.kind === 'link-deactivate' || confirm?.kind === 'prereq-deactivate'
                        ? 'danger'
                        : 'default'
                }
                onConfirm={runConfirm}
                onOpenChange={(open) => {
                    if (!open) {
                        setConfirm(null);
                    }
                }}
            />
        </div>
    );
}
