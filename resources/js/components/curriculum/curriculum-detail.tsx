import { router, usePage } from '@inertiajs/react';
import { Eye, ListChecks, Palette, Pencil, Trash2, XCircle } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { CurriculumCreateSheetDialog } from '@/components/curriculum/curriculum-create-sheet';
import { useCurriculumDetailHomeRibbon, type CurriculumPlace } from '@/components/curriculum/curriculum-detail-home-ribbon';
import { CurriculumPlaceHeading, CurriculumPlaceStatus, CurriculumSheet } from '@/components/curriculum/curriculum-sheet';
import {
    CURRICULUM_DISTRIBUTION_ACTIONS,
    CURRICULUM_STATUS_TABS,
    CURRICULUM_SUPERSEDED_ICON,
    editFilterEquals,
    matchesCurriculumEditFilter,
    type CurriculumEditFilter,
} from '@/components/curriculum/curriculum-edit-controls';
import { CurriculumSubjectSheetDialog } from '@/components/curriculum/curriculum-subject-sheet';
import {
    newIdempotencyKey,
    postWithIdempotency,
    statusLabel,
    type CurriculumFilterOptions,
    type CurriculumPagination,
    type CurriculumRow,
    type LinkedSubjectRow,
    type SubjectRow,
    type TeacherOption,
} from '@/components/curriculum/curriculum-workspace';
import { ConfirmDialog } from '@/components/sis/confirm-dialog';
import { DataTable, type DataTableColumn } from '@/components/sis/data-table';
import { OpsFormField, OpsTextInput } from '@/components/sis/ops-form-field';
import { sisListPageClass } from '@/components/sis/page-theme';
import type { ChromeTabId } from '@/components/sis/chrome-tabs';
import {
    useActivePageRibbonTab,
    useRegisterPageRibbon,
    useSetActivePageRibbonTab,
    type PageRibbonCommand,
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
    /** Every curriculum's place (all years) — «الصفحة الرئيسية» filters switch the curriculum shown here. */
    curriculumPlaces?: CurriculumPlace[];
    /** The school's teachers for the curriculum's year (the «إسناد مدرس» picker). */
    teacherOptions?: TeacherOption[];
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
    | { kind: 'link-delete'; id: number }
    | { kind: 'links-delete'; ids: number[] }
    | { kind: 'prereq-delete'; id: number }
    | { kind: 'plan-delete' }
    | { kind: 'link-deactivate'; id: number }
    | { kind: 'link-reactivate'; id: number }
    | { kind: 'prereq-deactivate'; id: number }
    | { kind: 'prereq-reactivate'; id: number }
    | { kind: 'apply-enrollments' }
    | { kind: 'teacher-remove'; lineId: number; assignmentId: number }
    | null;

function normalizeTab(tab: string): CurriculumShowTab {
    if (tab === 'plan' || tab === 'prerequisites' || tab === 'students') {
        return tab;
    }

    return 'subjects';
}

/** Content sections → chrome tab strip SSOT (same strip as enrollment). */
const CHROME_BY_CONTENT: Record<CurriculumShowTab, ChromeTabId | null> = {
    // The page opens on its subjects with no ribbon open.
    plan: null,
    subjects: null,
    prerequisites: 'tools',
    students: 'reports',
};

const CONTENT_BY_CHROME: Partial<Record<ChromeTabId, CurriculumShowTab>> = {
    // «الصفحة الرئيسية» / «قوائم» keep this page (its subjects) — no other page.
    home: 'subjects',
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
        teacherOptions = [],
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


    const subjectsRibbonGroups = useMemo((): PageRibbonGroup[] => {
        return [
            {
                id: 'curriculum-show-subjects',
                label: c.tabSubjects,
                commands: [],
            },
        ];
    }, [c.tabSubjects]);

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
                              onSelect: () => setConfirm({ kind: 'apply-enrollments' }),
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

    // «الصفحة الرئيسية»: the curricula page's own tab (تصفية المناهج + the ribbon's clipboard / font groups).
    useRegisterPageRibbon('home', useCurriculumDetailHomeRibbon(curriculum, props.curriculumPlaces ?? []));
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

    /** «تحديد» of the subjects table: the lines the «تحرير» commands act on. */
    const [selectedLineIds, setSelectedLineIds] = useState<number[]>([]);
    const selectedLines = useMemo(
        () => linkedSubjects.filter((row) => selectedLineIds.includes(row.id)),
        [linkedSubjects, selectedLineIds],
    );
    const allLinesSelected = selectedLines.length > 0 && selectedLines.length === linkedSubjects.length;
    const toggleLine = (id: number): void =>
        setSelectedLineIds((current) => (current.includes(id) ? current.filter((value) => value !== id) : [...current, id]));
    // Drop selections of lines that are gone (deleted / reloaded).
    useEffect(() => {
        setSelectedLineIds((current) => current.filter((id) => linkedSubjects.some((row) => row.id === id)));
    }, [linkedSubjects]);


    /** The subject (catalogue row) of each line — «عرض» / «تعديل» / «الاختصار واللون» / «توزيعات» act on it. */
    const subjectOfLine = (row: LinkedSubjectRow): SubjectRow | undefined =>
        subjectOptions.find((subject) => subject.id === row.subject_id);
    /** «حالة» of a line: an inactive line is inactive; otherwise its subject's status. */
    const lineFilterRow = (row: LinkedSubjectRow) => {
        const subject = subjectOfLine(row);

        return {
            status: row.status !== 1 ? row.status : (subject?.status ?? 1),
            subject_type: row.subject_type ?? subject?.subject_type ?? 1,
            subject_types: row.subject_types ?? subject?.subject_types,
        };
    };
    /** «تحرير» → «حالة» / «توزيع سابق»: the table shows the lines that match. */
    const [editFilter, setEditFilter] = useState<CurriculumEditFilter>({ kind: 'all' });
    const visibleLines = linkedSubjects.filter((row) => matchesCurriculumEditFilter(lineFilterRow(row), editFilter));
    const selectedSubjects = selectedLines
        .map(subjectOfLine)
        .filter((subject): subject is SubjectRow => subject !== undefined);
    /** «عرض» / «تعديل» of the selected subjects (the subjects page's sheet). */
    const [subjectSheet, setSubjectSheet] = useState<{ rows: SubjectRow[]; editing: boolean; appearance: boolean } | null>(null);
    const openSubjectSheet = (editing: boolean, appearance = false): void => {
        if (selectedSubjects.length > 0) {
            setSubjectSheet({ rows: selectedSubjects, editing, appearance });
        }
    };

    // «تحرير» — the curricula page's own tab: إجراءات · حالة · توزيعات · توزيع سابق, on this curriculum's subjects.
    const editRibbonGroups = useMemo((): PageRibbonGroup[] => {
        const hasSelection = selectedLines.length > 0;
        const actionCommands: PageRibbonCommand[] = [
            {
                id: 'subject-appearance',
                label: i18n.appearance.title,
                icon: Palette,
                disabled: !canManage || selectedSubjects.length !== 1,
                title: i18n.appearance.edit,
                onSelect: () => openSubjectSheet(true, true),
            },
            {
                id: 'view-subjects',
                label: c.view,
                icon: Eye,
                disabled: !hasSelection,
                title: c.viewSubjectTitle,
                onSelect: () => openSubjectSheet(false),
            },
            {
                // «تعديل»: the selected subjects; with none selected, the curriculum itself («تعديل منهج»).
                id: 'edit-selected-subject',
                label: i18n.common.edit,
                icon: Pencil,
                tone: 'edit',
                disabled: !canManage || (!hasSelection && curriculum.status !== 1),
                title: hasSelection ? i18n.common.edit : c.editPlan,
                onSelect: () => (hasSelection ? openSubjectSheet(true) : setPlanDialogOpen(true)),
            },
            {
                id: 'clear-selection',
                label: c.clearSelection,
                icon: XCircle,
                disabled: !hasSelection,
                onSelect: () => setSelectedLineIds([]),
            },
            {
                // «حذف»: the selected subjects leave this curriculum (the subjects themselves stay).
                id: 'delete-subject',
                label: i18n.common.delete,
                icon: Trash2,
                tone: 'delete',
                disabled: !canManage || !hasSelection,
                title: i18n.common.delete,
                onSelect: () => setConfirm({ kind: 'links-delete', ids: selectedLines.map((row) => row.id) }),
            },
        ];
        const statusCommands: PageRibbonCommand[] = CURRICULUM_STATUS_TABS.map((tab) => {
            const label = c[tab.labelKey];
            const count = linkedSubjects.filter((row) => matchesCurriculumEditFilter(lineFilterRow(row), tab.filter)).length;

            return {
                id: tab.id,
                label,
                title: `${label} (${count})`,
                icon: tab.icon,
                count,
                pressed: editFilterEquals(editFilter, tab.filter),
                onSelect: () => setEditFilter(tab.filter),
            };
        });
        const distributionCommands: PageRibbonCommand[] = CURRICULUM_DISTRIBUTION_ACTIONS.map((action) => ({
            id: action.id,
            label: c[action.labelKey],
            title: c[action.labelKey],
            icon: action.icon,
            disabled: !canManage || !hasSelection,
            onSelect: () => {
                // One request after another (a new Inertia visit would cancel the one still running).
                const steps: Array<(next: () => void) => void> = [];
                const send = (method: 'post' | 'patch', url: string, data: Record<string, string | number | null> = {}) =>
                    steps.push((next) =>
                        router[method](url, data, {
                            preserveScroll: true,
                            headers: { 'X-Idempotency-Key': newIdempotencyKey('curriculum') },
                            onFinish: next,
                        }),
                    );
                for (const target of selectedSubjects) {
                    if (action.apply.kind === 'status') {
                        if (action.apply.value === 1 && target.status !== 1) {
                            send('post', `/curriculum/subjects/${target.id}/reactivate`);
                        } else if (action.apply.value === 2 && target.status === 1) {
                            send('post', `/curriculum/subjects/${target.id}/deactivate`);
                        }
                    } else if ((target.subject_types ?? [target.subject_type]).join(',') !== String(action.apply.value)) {
                        send('patch', `/curriculum/subjects/${target.id}`, {
                            name: target.name,
                            subject_type: action.apply.value,
                            credit_hours: target.credit_hours,
                            max_grade: target.max_grade,
                            pass_grade: target.pass_grade,
                        });
                    }
                }
                const run = (index: number): void => steps[index]?.(() => run(index + 1));
                run(0);
                setSelectedLineIds([]);
            },
        }));
        const supersededCount = linkedSubjects.filter((row) =>
            matchesCurriculumEditFilter(lineFilterRow(row), { kind: 'superseded' }),
        ).length;

        return [
            { id: 'curriculum-actions', label: c.ribbonActions, commands: actionCommands },
            { id: 'curriculum-status-tabs', label: c.ribbonStatus, commands: statusCommands },
            { id: 'curriculum-distribution', label: c.ribbonDistribution, commands: distributionCommands },
            {
                id: 'curriculum-superseded',
                label: c.ribbonSuperseded,
                commands: [
                    {
                        id: 'curriculum-superseded-action',
                        label: c.ribbonSuperseded,
                        title: `${c.ribbonSuperseded} (${supersededCount})`,
                        icon: CURRICULUM_SUPERSEDED_ICON,
                        count: supersededCount,
                        pressed: editFilter.kind === 'superseded',
                        onSelect: () => setEditFilter({ kind: 'superseded' }),
                    },
                ],
            },
        ];
        // lineFilterRow / openSubjectSheet read the latest props when pressed.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [c, i18n, canManage, curriculum.status, editFilter, linkedSubjects, selectedLines, subjectOptions]);

    useRegisterPageRibbon('edit', editRibbonGroups);
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
                    <span className="flex flex-wrap gap-1">
                        {row.status === 1 ? (
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
                        )}
                        <Button
                            type="button"
                            variant="destructive"
                            size="sm"
                            onClick={() => setConfirm({ kind: 'prereq-delete', id: row.id })}
                        >
                            {i18n.common.delete}
                        </Button>
                    </span>
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
            // «حذف آمن» — the reason is shown when refused.
            case 'link-delete':
                router.post(`/curriculum/curriculum-subjects/${confirm.id}/delete`, {}, { preserveScroll: true, onFinish: done });
                break;
            case 'links-delete': {
                // One after another: each «حذف آمن» reloads the page; a refusal stops the rest.
                const queue = [...confirm.ids];
                const next = (): void => {
                    const id = queue.shift();
                    if (id === undefined) {
                        setSelectedLineIds([]);
                        done();

                        return;
                    }
                    router.post(`/curriculum/curriculum-subjects/${id}/delete`, {}, {
                        preserveScroll: true,
                        onSuccess: (visit) => {
                            const errors = (visit.props.errors ?? {}) as Record<string, unknown>;
                            if (Object.keys(errors).length > 0) {
                                done();

                                return;
                            }
                            next();
                        },
                        onError: () => done(),
                        onCancel: () => done(),
                    });
                };
                next();
                break;
            }
            case 'prereq-delete':
                router.post(`/curriculum/prerequisites/${confirm.id}/delete`, {}, { preserveScroll: true, onFinish: done });
                break;
            case 'plan-delete':
                router.post(`/curriculum/curricula/${curriculum.id}/delete`, {}, {
                    onSuccess: () => router.visit('/curriculum/curricula'),
                    onFinish: done,
                });
                break;
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
            case 'apply-enrollments':
                router.post(
                    `/curriculum/curricula/${curriculum.id}/apply-to-enrollments`,
                    {},
                    { preserveScroll: true, headers, onFinish: done },
                );
                break;
            case 'teacher-remove':
                router.post(
                    `/curriculum/curriculum-subjects/${confirm.lineId}/teachers/${confirm.assignmentId}/end`,
                    {},
                    { preserveScroll: true, onFinish: done },
                );
                break;
        }
    };

    const confirmCopy = useMemo(() => {
        if (!confirm) {
            return { title: '', description: '' };
        }
        switch (confirm.kind) {
            case 'link-delete':
                return { title: i18n.common.delete, description: c.deleteLinkConfirm };
            case 'links-delete':
                return {
                    title: c.removeLinkedSubject,
                    description:
                        confirm.ids.length === 1
                            ? c.deleteLinkConfirm
                            : c.deleteLinksConfirm.replace(':n', String(confirm.ids.length)),
                };
            case 'prereq-delete':
                return { title: i18n.common.delete, description: c.deletePrerequisiteConfirm };
            case 'plan-delete':
                return { title: c.deleteCurriculum, description: c.deleteCurriculumConfirm };
            case 'link-deactivate':
                return { title: c.deactivate, description: c.confirmDeactivateLink };
            case 'link-reactivate':
                return { title: c.reactivate, description: c.confirmReactivateLink };
            case 'prereq-deactivate':
                return { title: c.deactivate, description: c.confirmDeactivatePrerequisite };
            case 'prereq-reactivate':
                return { title: c.reactivate, description: c.confirmReactivatePrerequisite };
            case 'apply-enrollments':
                return { title: c.applyToEnrollments, description: c.confirmApplyToEnrollments };
            case 'teacher-remove':
                return { title: c.removeTeacher, description: c.confirmRemoveTeacher };
        }
    }, [c, confirm, i18n.common.delete]);

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
        <div className={`${sisListPageClass({ enrollments: false, hiddenScroll: true })} gap-4 p-4`} dir="rtl" lang="ar">
            {/* The subjects view holds the heading in its card; the other views show it above. */}
            {activeTab !== 'subjects' ? (
                <>
                    <CurriculumPlaceHeading curriculum={curriculum} />
                    <CurriculumPlaceStatus curriculum={curriculum} />
                </>
            ) : null}

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
                        <div>
                            <dt className="text-muted-foreground text-sm">{c.section}</dt>
                            <dd className="font-medium">{curriculum.section_name ?? '—'}</dd>
                        </div>
                    </dl>
                    {canManage ? (
                        <div className="flex flex-wrap gap-2 self-start">
                            {curriculum.status === 1 ? (
                                <Button type="button" onClick={() => setPlanDialogOpen(true)}>
                                    {c.editPlan}
                                </Button>
                            ) : null}
                            <Button
                                type="button"
                                variant="destructive"
                                onClick={() => setConfirm({ kind: 'plan-delete' })}
                            >
                                {c.deleteCurriculum}
                            </Button>
                        </div>
                    ) : null}
                </section>
            ) : null}

            {activeTab === 'subjects' ? (
                <div role="tabpanel" aria-label={c.linkedSubjectsTitle}>
                    {/* One card: the place heading, the subjects table, اكتمال · المواد · الحالة, the hour totals. */}
                    <CurriculumSheet
                        curriculum={curriculum}
                        lines={linkedSubjects}
                        shownLines={visibleLines}
                        selection={{
                            selectedIds: selectedLineIds,
                            onToggle: toggleLine,
                            onToggleAll: () => setSelectedLineIds(allLinesSelected ? [] : visibleLines.map((row) => row.id)),
                            allSelected: allLinesSelected,
                        }}
                    />
                </div>
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
                                        .filter((s) => s.status === 1 && String(s.id) !== prereqSubjectId)
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

            {subjectSheet ? (
                <CurriculumSubjectSheetDialog
                    subjects={subjectSheet.rows}
                    canManage={canManage}
                    initialEditing={subjectSheet.editing}
                    focusAppearance={subjectSheet.appearance}
                    onClose={() => setSubjectSheet(null)}
                />
            ) : null}

            {planDialogOpen ? (
                <CurriculumCreateSheetDialog
                    canManage={canManage}
                    filterOptions={filterOptions}
                    defaultAcademicYearId={curriculum.academic_year_id}
                    subjects={subjectOptions.filter(
                        (subject) => subject.status === 1 || linkedSubjects.some((line) => line.subject_id === subject.id),
                    )}
                    teacherOptions={teacherOptions}
                    editing={{ curriculum, lines: linkedSubjects }}
                    onClose={() => setPlanDialogOpen(false)}
                />
            ) : null}

            <ConfirmDialog
                open={confirm !== null}
                title={confirmCopy.title}
                description={confirmCopy.description}
                confirmPending={confirmPending}
                tone={
                    confirm?.kind === 'link-deactivate' ||
                    confirm?.kind === 'prereq-deactivate' ||
                    confirm?.kind === 'link-delete' ||
                    confirm?.kind === 'prereq-delete' ||
                    confirm?.kind === 'plan-delete' ||
                    confirm?.kind === 'teacher-remove'
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
