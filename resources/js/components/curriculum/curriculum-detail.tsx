import { router, usePage } from '@inertiajs/react';
import { BookOpen, CircleSlash, Clock, ListChecks, Pencil, Plus, RotateCcw, Trash2, UserMinus, UserPlus } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { CurriculumPlanSheetDialog } from '@/components/curriculum/curriculum-plan-sheet';
import { TypeHourTotalsBar } from '@/components/curriculum/type-hour-totals';
import {
    LinkSubjectDialog,
    newIdempotencyKey,
    orderSubjectTypes,
    subjectTypeLabel,
    patchWithIdempotency,
    postWithIdempotency,
    statusLabel,
    subjectTypesWithHoursLabel,
    type CurriculumFilterOptions,
    type CurriculumLineTeacher,
    type CurriculumPagination,
    type CurriculumRow,
    type LinkedSubjectRow,
    type SubjectRow,
    type TeacherOption,
} from '@/components/curriculum/curriculum-workspace';
import { ConfirmDialog } from '@/components/sis/confirm-dialog';
import { DataTable, type DataTableColumn } from '@/components/sis/data-table';
import { OpsFormField, OpsTextInput } from '@/components/sis/ops-form-field';
import { PageHeader } from '@/components/sis/page-header';
import { sisListPageClass } from '@/components/sis/page-theme';
import type { ChromeTabId } from '@/components/sis/chrome-tabs';
import {
    useActivePageRibbonTab,
    useRegisterPageRibbon,
    useSetActivePageRibbonTab,
    type PageRibbonGroup,
} from '@/components/sis/page-ribbon-context';
import { SisListSelect } from '@/components/sis/sis-list-select';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { t } from '@/i18n';
import {
    collectInertiaErrorMessages,
    resolveErrorMessage,
} from '@/lib/format-inertia-errors';
import { typeHourTotals } from '@/lib/subject-type-order';

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
    /** «الساعات» dialog of one subject in the plan (نظري / عملي). */
    const [hoursEdit, setHoursEdit] = useState<{
        id: number;
        name: string;
        theory: string;
        practical: string;
    } | null>(null);
    const [prereqSubjectId, setPrereqSubjectId] = useState('');
    const [prereqRequiredId, setPrereqRequiredId] = useState('');
    const [confirm, setConfirm] = useState<ConfirmState>(null);
    const [confirmPending, setConfirmPending] = useState(false);
    const [assignPendingId, setAssignPendingId] = useState<number | null>(null);
    /** «إسناد مدرس» of one subject line: the main (or only) teacher, an assistant, or a new main teacher. */
    const [teacherAssign, setTeacherAssign] = useState<{
        lineId: number;
        name: string;
        /** The subject's parts ('' = the whole subject) and the line's main teachers by part. */
        parts: string[];
        mains: string[];
        part: string;
        teacherId: string;
        mode: 'main' | 'assistant';
        /** The section taught ('' = all sections of the class). */
        section: string;
    } | null>(null);
    /** A part (or the whole subject, '') has its main teacher; a whole-subject main covers every part. */
    const partHasMain = (mains: string[], part: string): boolean => mains.includes(part) || mains.includes('');

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

    /** «نظري / عملي» of a row: the stored split, or the total as «نظري» for rows linked before the split existed. */
    const splitOf = (row: LinkedSubjectRow): { theory: number | null; practical: number | null } =>
        row.theory_hours != null || row.practical_hours != null
            ? { theory: row.theory_hours ?? null, practical: row.practical_hours ?? null }
            : { theory: row.weekly_hours, practical: null };
    /** «تحديد» of the subjects table: the lines the «تحرير» commands act on. */
    const [selectedLineIds, setSelectedLineIds] = useState<number[]>([]);
    /** «إلغاء إسناد مدرس»: the line whose teachers are listed. */
    const [teacherRemoveLineId, setTeacherRemoveLineId] = useState<number | null>(null);
    const selectedLines = useMemo(
        () => linkedSubjects.filter((row) => selectedLineIds.includes(row.id)),
        [linkedSubjects, selectedLineIds],
    );
    const singleLine = selectedLines.length === 1 ? (selectedLines[0] ?? null) : null;
    const allLinesSelected = linkedSubjects.length > 0 && selectedLines.length === linkedSubjects.length;
    const toggleLine = (id: number): void =>
        setSelectedLineIds((current) => (current.includes(id) ? current.filter((value) => value !== id) : [...current, id]));
    // Drop selections of lines that are gone (deleted / reloaded).
    useEffect(() => {
        setSelectedLineIds((current) => current.filter((id) => linkedSubjects.some((row) => row.id === id)));
    }, [linkedSubjects]);

    const teachersOf = (row: LinkedSubjectRow, role: 1 | 2): CurriculumLineTeacher[] =>
        (row.teachers ?? []).filter((teacher) => teacher.role === role);
    const teacherText = (teacher: CurriculumLineTeacher): string =>
        [
            teacher.name,
            teacher.subject_type ? subjectTypeLabel(teacher.subject_type, c) : null,
            teacher.section_name ?? null,
        ]
            .filter(Boolean)
            .join(' — ');

    const openTeacherAssign = (row: LinkedSubjectRow): void => {
        const mains = teachersOf(row, 1).map((teacher) => (teacher.subject_type ? String(teacher.subject_type) : ''));
        const types = row.subject_types ?? [];
        const parts = types.length > 1 ? ['', ...orderSubjectTypes(types).map(String)] : [''];
        const part = mains.length > 0 && !mains.includes('') ? (mains[0] ?? '') : parts.length > 1 ? (parts[1] ?? '') : '';
        setTeacherAssign({
            lineId: row.id,
            name: row.subject_name ?? String(row.subject_id),
            parts,
            mains,
            part,
            teacherId: '',
            mode: partHasMain(mains, part) ? 'assistant' : 'main',
            section: curriculum.section_id ? String(curriculum.section_id) : '',
        });
    };

    const openHoursEdit = (row: LinkedSubjectRow): void => {
        const split = splitOf(row);
        setHoursEdit({
            id: row.id,
            name: row.subject_name ?? String(row.subject_id),
            theory: split.theory === null ? '' : String(split.theory),
            practical: split.practical === null ? '' : String(split.practical),
        });
    };

    /** «نوع المادة مع الساعات» and the totals under the table: the subject's own types and hours. */
    const subjectHoursSource = (row: LinkedSubjectRow) => ({
        subject_type: row.subject_type ?? row.subject_types?.[0] ?? 1,
        subject_types: row.subject_types,
        subject_type_hours: row.subject_type_hours,
        credit_hours: row.credit_hours ?? row.weekly_hours,
    });
    const typeTotals = typeHourTotals(activeLinkedSubjects.map(subjectHoursSource));

    const editRibbonGroups = useMemo((): PageRibbonGroup[] => {
        if (!canManage) {
            return [];
        }
        const open = curriculum.status === 1;

        return [
            {
                id: 'curriculum-show-edit-plan',
                label: c.editRibbonCurriculum,
                commands: [
                    ...(open
                        ? [{ id: 'edit-plan', label: c.editPlan, icon: Pencil, tone: 'edit' as const, onSelect: () => setPlanDialogOpen(true) }]
                        : []),
                    {
                        id: 'delete-plan',
                        label: c.deleteCurriculum,
                        icon: Trash2,
                        tone: 'delete' as const,
                        onSelect: () => setConfirm({ kind: 'plan-delete' }),
                    },
                ],
            },
            {
                id: 'curriculum-show-edit-subjects',
                label: c.editRibbonSubjects,
                commands: [
                    ...(open
                        ? [{ id: 'link-subject', label: c.addLinkedSubject, icon: Plus, onSelect: () => setLinkDialogOpen(true) }]
                        : []),
                    {
                        id: 'edit-hours',
                        label: c.editHours,
                        icon: Clock,
                        disabled: singleLine === null || singleLine.status !== 1,
                        title: c.selectOneSubject,
                        onSelect: () => (singleLine ? openHoursEdit(singleLine) : undefined),
                    },
                    ...(open
                        ? [
                              {
                                  id: 'assign-teacher',
                                  label: c.assignTeacher,
                                  icon: UserPlus,
                                  disabled: singleLine === null || singleLine.status !== 1,
                                  title: c.selectOneSubject,
                                  onSelect: () => (singleLine ? openTeacherAssign(singleLine) : undefined),
                              },
                          ]
                        : []),
                    {
                        id: 'remove-teacher',
                        label: c.removeTeacher,
                        icon: UserMinus,
                        disabled: singleLine === null || (singleLine.teachers ?? []).length === 0,
                        title: c.selectOneSubject,
                        onSelect: () => (singleLine ? setTeacherRemoveLineId(singleLine.id) : undefined),
                    },
                    singleLine !== null && singleLine.status !== 1
                        ? {
                              id: 'reactivate-line',
                              label: c.reactivate,
                              icon: RotateCcw,
                              onSelect: () => setConfirm({ kind: 'link-reactivate', id: singleLine.id }),
                          }
                        : {
                              id: 'deactivate-line',
                              label: c.deactivate,
                              icon: CircleSlash,
                              disabled: singleLine === null,
                              title: c.selectOneSubject,
                              onSelect: () => (singleLine ? setConfirm({ kind: 'link-deactivate', id: singleLine.id }) : undefined),
                          },
                    {
                        id: 'delete-lines',
                        label: c.removeLinkedSubject,
                        icon: Trash2,
                        tone: 'delete' as const,
                        disabled: selectedLines.length === 0,
                        title: c.selectSubjects,
                        onSelect: () => setConfirm({ kind: 'links-delete', ids: selectedLines.map((row) => row.id) }),
                    },
                ],
            },
        ];
        // openHoursEdit / openTeacherAssign read the latest row when pressed.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [c, canManage, curriculum.status, curriculum.section_id, selectedLines, singleLine]);

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
            <PageHeader
                title={curriculum.name}
                description={c.showDescription}
                icon={<BookOpen className="size-6" aria-hidden />}
                actions={
                    <Button type="button" variant="outline" onClick={() => router.get('/curriculum/curricula')}>
                        {i18n.common.backToList}
                    </Button>
                }
            />

            {/* Above the table: الفرع · الاختصاص · الصف · الشعبة. */}
            <dl
                className="grid grid-cols-2 gap-2 rounded-md border border-[color:var(--sis-powder-blue)] p-2 text-center sm:grid-cols-4"
                aria-label={c.showTitle}
            >
                {[
                    [c.branch, curriculum.branch_name ?? '—'],
                    [c.department, curriculum.department_name ?? '—'],
                    [c.gradeLevel, curriculum.grade_level_name ?? String(curriculum.grade_level_id)],
                    [c.section, curriculum.section_name ?? '—'],
                ].map(([label, value]) => (
                    <div key={label}>
                        <dt className="text-muted-foreground text-xs">{label}</dt>
                        <dd className="text-base font-semibold">{value}</dd>
                    </div>
                ))}
            </dl>
            <div className="text-muted-foreground flex flex-wrap gap-3 text-sm">
                <span>
                    {c.planCompleteness}: {curriculum.is_complete ? c.planComplete : c.planIncomplete} (
                    {c.planCompletenessDetail
                        .replace(':staffed', String(curriculum.staffed_count ?? 0))
                        .replace(':total', String(curriculum.subject_count ?? 0))}
                    )
                </span>
                {curriculum.planned_subject_count ? (
                    <span>
                        {c.subjectsProgress
                            .replace(':n', String(curriculum.subject_count ?? 0))
                            .replace(':planned', String(curriculum.planned_subject_count))
                            .replace(':percent', String(curriculum.subjects_percent ?? 0))}
                    </span>
                ) : null}
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
                <section className="flex flex-col gap-2" role="tabpanel" aria-label={c.linkedSubjectsTitle}>
                    <h2 className="text-lg font-semibold">{c.linkedSubjectsTitle}</h2>
                    {linkedSubjects.length === 0 ? (
                        <p className="px-1 py-2 text-sm">
                            {c.linkedEmptyTitle} — {c.linkedEmptyDesc}
                        </p>
                    ) : (
                        <div className="sis-admission-periods-table sis-admission-drafts-table">
                            <div className="sis-admission-drafts-table__scroller">
                                <table>
                                    <caption className="sr-only">{c.linkedSubjectsCaption}</caption>
                                    <thead>
                                        <tr>
                                            <th className="sis-admission-drafts-table__select">
                                                <input
                                                    type="checkbox"
                                                    checked={allLinesSelected}
                                                    onChange={() =>
                                                        setSelectedLineIds(allLinesSelected ? [] : linkedSubjects.map((row) => row.id))
                                                    }
                                                    aria-label={c.selectAllVisible}
                                                />
                                            </th>
                                            <th className="sis-admission-drafts-table__name-head">{c.subjectName}</th>
                                            <th className="sis-admission-drafts-table__name-head">{c.subjectNameEn}</th>
                                            <th>{c.subjectTypeWithHours}</th>
                                            <th>{c.maxGrade}</th>
                                            <th>{c.passGrade}</th>
                                            <th>{c.mainTeacher}</th>
                                            <th>{c.assistantTeacherColumn}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {linkedSubjects.map((row) => {
                                            const selected = selectedLineIds.includes(row.id);
                                            const name = row.subject_name ?? String(row.subject_id);
                                            const mains = teachersOf(row, 1);
                                            const assistants = teachersOf(row, 2);

                                            return (
                                                <tr
                                                    key={row.id}
                                                    className={selected ? 'sis-admission-periods-table__row--selected' : undefined}
                                                    onClick={() => toggleLine(row.id)}
                                                >
                                                    <td className="sis-admission-drafts-table__select">
                                                        <input
                                                            type="checkbox"
                                                            checked={selected}
                                                            onChange={() => toggleLine(row.id)}
                                                            onClick={(event) => event.stopPropagation()}
                                                            aria-label={name}
                                                        />
                                                    </td>
                                                    <td className="sis-admission-drafts-table__name">
                                                        {name}
                                                        {row.status !== 1 ? ` ${c.inactiveSuffix}` : ''}
                                                    </td>
                                                    <td className="sis-admission-drafts-table__name" dir="ltr">
                                                        {row.subject_name_en?.trim() ? row.subject_name_en : '—'}
                                                    </td>
                                                    <td className="sis-admission-drafts-table__text">
                                                        {subjectTypesWithHoursLabel(subjectHoursSource(row), c)}
                                                    </td>
                                                    <td className="sis-admission-drafts-table__text" dir="ltr">
                                                        {row.max_grade ?? '—'}
                                                    </td>
                                                    <td className="sis-admission-drafts-table__text" dir="ltr">
                                                        {row.pass_grade ?? '—'}
                                                    </td>
                                                    <td className="sis-admission-drafts-table__text">
                                                        {mains.length === 0
                                                            ? c.noTeacher
                                                            : mains.map((teacher) => (
                                                                  <span key={teacher.assignment_id} className="block">
                                                                      {teacherText(teacher)}
                                                                  </span>
                                                              ))}
                                                    </td>
                                                    <td className="sis-admission-drafts-table__text">
                                                        {assistants.length === 0
                                                            ? c.noAssistant
                                                            : assistants.map((teacher) => (
                                                                  <span key={teacher.assignment_id} className="block">
                                                                      {teacherText(teacher)}
                                                                  </span>
                                                              ))}
                                                    </td>
                                                </tr>
                                            );
                                        })}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    )}
                    {/* Under the table: مجموع ساعات الأساسي · الاختصاص · النظري · العملي · الكلي (active subjects). */}
                    {activeLinkedSubjects.length > 0 ? <TypeHourTotalsBar totals={typeTotals} /> : null}
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

            <Dialog
                open={hoursEdit !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setHoursEdit(null);
                    }
                }}
            >
                <DialogContent className="sis-ops-hub sm:max-w-md" dir="rtl" lang="ar">
                    <DialogHeader>
                        <DialogTitle>{c.editHoursTitle}</DialogTitle>
                        <DialogDescription>{hoursEdit?.name}</DialogDescription>
                    </DialogHeader>
                    {hoursEdit !== null ? (
                        <form
                            className="flex flex-col gap-3"
                            onSubmit={(event) => {
                                event.preventDefault();
                                patchWithIdempotency(`/curriculum/curriculum-subjects/${hoursEdit.id}/hours`, {
                                    theory_hours: hoursEdit.theory === '' ? null : Number(hoursEdit.theory),
                                    practical_hours: hoursEdit.practical === '' ? null : Number(hoursEdit.practical),
                                });
                                setHoursEdit(null);
                            }}
                        >
                            <div className="grid grid-cols-3 items-end gap-3">
                                <OpsFormField label={c.theoryHours} name="theory_hours">
                                    <OpsTextInput
                                        name="theory_hours"
                                        type="number"
                                        min={0}
                                        max={40}
                                        dir="ltr"
                                        value={hoursEdit.theory}
                                        onChange={(e) => setHoursEdit({ ...hoursEdit, theory: e.target.value })}
                                    />
                                </OpsFormField>
                                <OpsFormField label={c.practicalHours} name="practical_hours">
                                    <OpsTextInput
                                        name="practical_hours"
                                        type="number"
                                        min={0}
                                        max={40}
                                        dir="ltr"
                                        value={hoursEdit.practical}
                                        onChange={(e) => setHoursEdit({ ...hoursEdit, practical: e.target.value })}
                                    />
                                </OpsFormField>
                                <OpsFormField label={c.totalHours} name="total_hours">
                                    <OpsTextInput
                                        name="total_hours"
                                        type="number"
                                        dir="ltr"
                                        readOnly
                                        value={(Number(hoursEdit.theory) || 0) + (Number(hoursEdit.practical) || 0)}
                                    />
                                </OpsFormField>
                            </div>
                            <div className="flex justify-end gap-2 pt-2">
                                <Button type="button" variant="outline" onClick={() => setHoursEdit(null)}>
                                    {c.cancel}
                                </Button>
                                <Button type="submit">{c.save}</Button>
                            </div>
                        </form>
                    ) : null}
                </DialogContent>
            </Dialog>

            <Dialog
                open={teacherAssign !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setTeacherAssign(null);
                    }
                }}
            >
                <DialogContent className="sis-ops-hub sm:max-w-md" dir="rtl" lang="ar">
                    <DialogHeader>
                        <DialogTitle>{c.assignTeacherTitle}</DialogTitle>
                        <DialogDescription>{teacherAssign?.name}</DialogDescription>
                    </DialogHeader>
                    {teacherAssign !== null ? (
                        <form
                            className="flex flex-col gap-3"
                            onSubmit={(event) => {
                                event.preventDefault();
                                if (teacherAssign.teacherId === '') {
                                    return;
                                }
                                postWithIdempotency(`/curriculum/curriculum-subjects/${teacherAssign.lineId}/teachers`, {
                                    teacher_id: Number(teacherAssign.teacherId),
                                    teacher_role: teacherAssign.mode === 'assistant' ? 2 : 1,
                                    subject_type: teacherAssign.part === '' ? null : Number(teacherAssign.part),
                                    section_id: teacherAssign.section === '' ? null : Number(teacherAssign.section),
                                });
                                setTeacherAssign(null);
                            }}
                        >
                            {teacherOptions.length === 0 ? (
                                <p className="text-muted-foreground text-sm">{c.noTeacherOptions}</p>
                            ) : null}
                            <OpsFormField label={c.teacherLabel} name="teacher_id">
                                <SisListSelect
                                    name="teacher_id"
                                    ariaLabel={c.teacherLabel}
                                    value={teacherAssign.teacherId}
                                    required
                                    includeBlank
                                    options={teacherOptions.map((teacher) => ({
                                        value: String(teacher.id),
                                        label: `${teacher.name} (${teacher.employee_code})`,
                                    }))}
                                    onChange={(value) => setTeacherAssign({ ...teacherAssign, teacherId: value })}
                                />
                            </OpsFormField>
                            {teacherAssign.parts.length > 1 ? (
                                <OpsFormField label={c.teachingPart} name="subject_type">
                                    <SisListSelect
                                        name="subject_type"
                                        ariaLabel={c.teachingPart}
                                        value={teacherAssign.part}
                                        options={teacherAssign.parts.map((part) => ({
                                            value: part,
                                            label: part === '' ? c.wholeSubject : subjectTypeLabel(Number(part), c),
                                        }))}
                                        onChange={(value) =>
                                            setTeacherAssign({
                                                ...teacherAssign,
                                                part: value,
                                                mode: partHasMain(teacherAssign.mains, value) ? 'assistant' : 'main',
                                            })
                                        }
                                    />
                                </OpsFormField>
                            ) : null}
                            {curriculum.section_id ? null : (
                                <OpsFormField label={c.section} name="section_id">
                                    <SisListSelect
                                        name="section_id"
                                        ariaLabel={c.section}
                                        value={teacherAssign.section}
                                        options={[
                                            { value: '', label: c.allSections },
                                            ...(filterOptions.sections ?? [])
                                                .filter((section) => section.class_id === curriculum.class_id)
                                                .map((section) => ({ value: String(section.id), label: section.name })),
                                        ]}
                                        onChange={(value) => setTeacherAssign({ ...teacherAssign, section: value })}
                                    />
                                </OpsFormField>
                            )}
                            <OpsFormField label={c.teacherRole} name="teacher_role">
                                <SisListSelect
                                    name="teacher_role"
                                    ariaLabel={c.teacherRole}
                                    value={teacherAssign.mode}
                                    options={[
                                        // Several main teachers are allowed; an assistant needs one (the server checks).
                                        { value: 'main', label: c.mainTeacher },
                                        { value: 'assistant', label: c.assistantTeacher },
                                    ]}
                                    onChange={(value) =>
                                        setTeacherAssign({ ...teacherAssign, mode: value === 'assistant' ? 'assistant' : 'main' })
                                    }
                                />
                            </OpsFormField>
                            <div className="flex justify-end gap-2 pt-2">
                                <Button type="button" variant="outline" onClick={() => setTeacherAssign(null)}>
                                    {c.cancel}
                                </Button>
                                <Button type="submit" disabled={teacherAssign.teacherId === ''}>
                                    {c.assignTeacher}
                                </Button>
                            </div>
                        </form>
                    ) : null}
                </DialogContent>
            </Dialog>

            {/* «إلغاء إسناد مدرس» (تحرير): the selected subject's teachers, each with its own «إلغاء الإسناد». */}
            <Dialog
                open={teacherRemoveLineId !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setTeacherRemoveLineId(null);
                    }
                }}
            >
                <DialogContent className="sis-ops-hub sm:max-w-md" dir="rtl" lang="ar">
                    {(() => {
                        const line = linkedSubjects.find((row) => row.id === teacherRemoveLineId);
                        const teachers = line?.teachers ?? [];

                        return (
                            <>
                                <DialogHeader>
                                    <DialogTitle>{c.removeTeacherTitle}</DialogTitle>
                                    <DialogDescription>{line?.subject_name ?? ''}</DialogDescription>
                                </DialogHeader>
                                {teachers.length === 0 ? (
                                    <p className="text-sm">{c.noTeacher}</p>
                                ) : (
                                    <ul className="flex flex-col gap-2">
                                        {teachers.map((teacher) => (
                                            <li key={teacher.assignment_id} className="flex items-center justify-between gap-2">
                                                <span className="text-sm">
                                                    {teacher.role === 1 ? c.mainTeacher : c.assistantTeacher}: {teacherText(teacher)}
                                                </span>
                                                <Button
                                                    type="button"
                                                    variant="destructive"
                                                    size="sm"
                                                    onClick={() => {
                                                        if (line) {
                                                            setTeacherRemoveLineId(null);
                                                            setConfirm({
                                                                kind: 'teacher-remove',
                                                                lineId: line.id,
                                                                assignmentId: teacher.assignment_id,
                                                            });
                                                        }
                                                    }}
                                                >
                                                    {c.removeTeacher}
                                                </Button>
                                            </li>
                                        ))}
                                    </ul>
                                )}
                            </>
                        );
                    })()}
                </DialogContent>
            </Dialog>

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
