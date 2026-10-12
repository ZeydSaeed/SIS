import { router } from '@inertiajs/react';
import { Ban, FileSearch, PlusCircle, ShieldCheck } from 'lucide-react';
import { useMemo, useState } from 'react';
import {
    AttendancePage,
    AttendanceTable,
    AttendanceWindow,
    WindowFact,
    WindowTextArea,
    postEach,
    selectionGroup,
    useAttendanceEditRibbon,
    useAttendanceHomeFilters,
    useAttendanceSearch,
    useAttendanceSelection,
    useRowViewer,
    useYearFilterSpec,
    visitWith,
    type AttendanceColumn,
} from '@/components/attendance/attendance-chrome';
import {
    EXCUSE_STATUSES,
    StatusLabel,
    dateTime,
    newKey,
    statusCommands,
    toggleIn,
    type AttendanceAbilities,
} from '@/components/attendance/attendance-ui';
import {
    RegistryListField,
    RegistryTextField,
} from '@/components/organization/registry-sheet';
import type { ContextMenuItem } from '@/components/sis/context-menu';
import { usePageError } from '@/components/sis/page-error-context';
import type { PageRibbonGroup } from '@/components/sis/page-ribbon-context';
import type { FilterSpec } from '@/components/sis/ribbon-filter';
import { Button } from '@/components/ui/button';
import { t } from '@/i18n';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

type Excuse = {
    id: number;
    full_name: string;
    student_code: string;
    class_name: string | null;
    section_name: string | null;
    date_from: string;
    date_to: string;
    excuse_type: number;
    reason: string;
    status: number;
    decision_note: string | null;
    applied_records: number;
    version: number;
    document_file_id: number | null;
    submitted_at: string;
    submitted_by_name: string | null;
    reviewed_by_name: string | null;
    reviewed_at: string | null;
};

type StudentOption = {
    enrollment_id: number;
    full_name: string;
    student_code: string;
    class_name: string | null;
    section_name: string | null;
};

type Filters = {
    status: number[];
    excuse_type: number[];
    class_id: number[];
    section_id: number[];
    date_from?: string;
    date_to?: string;
    q: string;
    page: number;
    per_page?: number;
    student: string;
    academic_year_id: number;
};

type Props = {
    excuses: {
        data: Excuse[];
        meta: {
            page: number;
            per_page: number;
            last_page: number;
            total: number;
        };
        students: StudentOption[];
    };
    filters: Filters;
    lookups: {
        classes: Array<{
            id: number;
            name: string;
            sections: Array<{ id: number; name: string }>;
        }>;
    };
    abilities: AttendanceAbilities;
};

type Form = {
    enrollment_id: string;
    date_from: string;
    date_to: string;
    excuse_type: string;
    reason: string;
    document: File | null;
};

const EXCUSE_TYPES = [1, 2, 3, 4, 9];
const EMPTY_FORM: Form = {
    enrollment_id: '',
    date_from: '',
    date_to: '',
    excuse_type: '1',
    reason: '',
    document: null,
};

export default function AttendanceExcuses(props: Props) {
    const a = t().attendanceSystem;
    const breadcrumbs: BreadcrumbItem[] = [
        { title: t().attendance.title, href: '/attendance' },
        { title: a.excuses.title, href: '/attendance/excuses' },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <ExcusesPage {...props} />
        </AppLayout>
    );
}

function ExcusesPage({ excuses, filters, lookups, abilities }: Props) {
    const a = t().attendanceSystem;
    const x = a.excuses;
    const c = a.chrome;
    const { showInertiaErrors } = usePageError();
    const selection = useAttendanceSelection(excuses.data, (e) => e.id);
    const [form, setForm] = useState<Form | null>(
        filters.student !== '' ? { ...EMPTY_FORM } : null,
    );
    const [studentQuery, setStudentQuery] = useState(filters.student);
    const [decision, setDecision] = useState<{
        items: Excuse[];
        approve: boolean;
        note: string;
    } | null>(null);
    const [busy, setBusy] = useState(false);
    const pendingTargets = selection.targets.filter((e) => e.status === 1);

    const visit = (next: Partial<Filters>) => {
        const merged = { ...filters, ...next, page: next.page ?? 1 };
        visitWith('/attendance/excuses', {
            ...merged,
            status: merged.status.length === 0 ? [''] : merged.status,
            student: next.student ?? null,
        });
    };

    const submit = () => {
        if (form === null) return;
        setBusy(true);
        router.post(
            '/attendance/excuses',
            {
                enrollment_id: Number(form.enrollment_id),
                date_from: form.date_from,
                date_to: form.date_to || form.date_from,
                excuse_type: Number(form.excuse_type),
                reason: form.reason.trim(),
                ...(form.document ? { document: form.document } : {}),
            },
            {
                forceFormData: form.document !== null,
                preserveScroll: true,
                headers: { 'X-Idempotency-Key': newKey('excuse') },
                onSuccess: () => setForm(null),
                onError: (errors) => showInertiaErrors(errors),
                onFinish: () => setBusy(false),
            },
        );
    };

    /** Approve / reject one or several pending excuses (one request each, stops at the first refusal). */
    const decide = () => {
        if (decision === null) return;
        setBusy(true);
        postEach(
            decision.items,
            (e) => ({
                url: `/attendance/excuses/${e.id}/decide`,
                data: {
                    decision: decision.approve ? 'approve' : 'reject',
                    version: e.version,
                    note: decision.note.trim() || null,
                },
                key: newKey(`excuse-decide-${e.id}`),
            }),
            {
                onError: (errors) => showInertiaErrors(errors),
                onDone: () => {
                    setDecision(null);
                },
                onFinish: () => setBusy(false),
            },
        );
    };

    const columns: AttendanceColumn<Excuse>[] = [
        {
            id: 'student',
            header: x.student,
            kind: 'name',
            cell: (e) => e.full_name,
        },
        {
            id: 'code',
            header: a.sheet.code,
            kind: 'text',
            cell: (e) => <span dir="ltr">{e.student_code}</span>,
        },
        {
            id: 'class',
            header: c.section,
            kind: 'text',
            cell: (e) => `${e.class_name ?? ''} / ${e.section_name ?? ''}`,
        },
        {
            id: 'dates',
            header: `${x.dateFrom} – ${x.dateTo}`,
            kind: 'text',
            cell: (e) => (
                <span dir="ltr">
                    {e.date_from === e.date_to
                        ? e.date_from
                        : `${e.date_from} → ${e.date_to}`}
                </span>
            ),
        },
        {
            id: 'type',
            header: x.type,
            kind: 'text',
            cell: (e) => x.types[e.excuse_type] ?? e.excuse_type,
        },
        { id: 'reason', header: x.reason, kind: 'text', cell: (e) => e.reason },
        {
            id: 'status',
            header: x.status,
            kind: 'status',
            tone: (e) =>
                EXCUSE_STATUSES.find((s) => s.value === e.status)?.tone ?? 0,
            cell: (e) => (
                <StatusLabel
                    meta={
                        EXCUSE_STATUSES.find((s) => s.value === e.status) ??
                        null
                    }
                    label={x.statuses[e.status]}
                />
            ),
            text: (e) => x.statuses[e.status],
        },
        {
            id: 'applied',
            header: x.applied,
            kind: 'num',
            cell: (e) => (e.status === 2 ? e.applied_records : '—'),
        },
        {
            id: 'by',
            header: x.submittedBy,
            kind: 'text',
            cell: (e) =>
                `${e.submitted_by_name ?? '—'} · ${dateTime(e.submitted_at)}`,
        },
        {
            id: 'reviewed',
            header: x.reviewedBy,
            kind: 'text',
            cell: (e) =>
                `${e.reviewed_by_name ?? '—'} · ${dateTime(e.reviewed_at)}`,
        },
        {
            id: 'decision',
            header: x.decisionNote,
            kind: 'text',
            cell: (e) => e.decision_note ?? '—',
        },
        {
            id: 'document',
            header: x.documentId,
            kind: 'num',
            cell: (e) => e.document_file_id ?? '—',
        },
    ];
    const viewer = useRowViewer(
        columns,
        (e) => `${x.viewTitle} — ${e.full_name}`,
    );

    const editGroups = useMemo(
        (): PageRibbonGroup[] => [
            selectionGroup(
                'attendance-excuses-selection',
                selection.targets,
                viewer.open,
                abilities.review_excuse
                    ? () =>
                          setDecision({
                              items: pendingTargets,
                              approve: true,
                              note: '',
                          })
                    : null,
                {
                    editLabel: x.decideTitle,
                    editDisabled: pendingTargets.length === 0,
                    editTitle:
                        pendingTargets.length === 0 ? x.onlyPending : undefined,
                },
            ),
            {
                id: 'attendance-excuses-actions',
                label: c.actions,
                commands: [
                    ...(abilities.submit_excuse
                        ? [
                              {
                                  id: 'attendance-excuses-add',
                                  label: x.add,
                                  icon: PlusCircle,
                                  iconTone: 'ok' as const,
                                  onSelect: () => setForm({ ...EMPTY_FORM }),
                              },
                          ]
                        : []),
                    ...(abilities.review_excuse
                        ? [
                              {
                                  id: 'attendance-excuses-approve',
                                  label: x.approve,
                                  icon: ShieldCheck,
                                  iconTone: 'ok' as const,
                                  count: pendingTargets.length || undefined,
                                  disabled: pendingTargets.length === 0,
                                  title:
                                      pendingTargets.length === 0
                                          ? x.onlyPending
                                          : undefined,
                                  onSelect: () =>
                                      setDecision({
                                          items: pendingTargets,
                                          approve: true,
                                          note: '',
                                      }),
                              },
                              {
                                  id: 'attendance-excuses-reject',
                                  label: x.reject,
                                  icon: Ban,
                                  iconTone: 'danger' as const,
                                  disabled: pendingTargets.length === 0,
                                  title:
                                      pendingTargets.length === 0
                                          ? x.onlyPending
                                          : undefined,
                                  onSelect: () =>
                                      setDecision({
                                          items: pendingTargets,
                                          approve: false,
                                          note: '',
                                      }),
                              },
                          ]
                        : []),
                ],
            },
            {
                id: 'attendance-excuses-status',
                label: c.statuses,
                commands: statusCommands(
                    'attendance-excuses-status',
                    EXCUSE_STATUSES,
                    x.statuses,
                    {},
                    filters.status,
                    (v) => visit({ status: toggleIn(filters.status, v) }),
                ).map((cmd) => ({ ...cmd, count: undefined })),
            },
        ],
        // eslint-disable-next-line react-hooks/exhaustive-deps
        [selection.targets, abilities, filters],
    );
    useAttendanceEditRibbon('excuses', abilities, editGroups);

    const multi = (
        key: 'excuse_type' | 'class_id' | 'section_id',
        label: string,
        allLabel: string,
        options: Array<{ value: string; label: string }>,
    ): FilterSpec => ({
        key,
        type: 'multi',
        label,
        allLabel,
        values: filters[key].map(String),
        options,
        onChange: (values: string[]) => visit({ [key]: values.map(Number) }),
    });
    const yearSpec = useYearFilterSpec(filters.academic_year_id, (year) =>
        visitWith('/attendance/excuses', { academic_year_id: year }),
    );
    useAttendanceHomeFilters(
        [
            yearSpec,
            {
                key: 'date_from',
                date: true,
                label: x.dateFrom,
                value: filters.date_from ?? '',
                onChange: (date_from: string) => visit({ date_from }),
            },
            {
                key: 'date_to',
                date: true,
                label: x.dateTo,
                value: filters.date_to ?? '',
                onChange: (date_to: string) => visit({ date_to }),
            },
            multi(
                'excuse_type',
                x.type,
                c.allTypes,
                EXCUSE_TYPES.map((v) => ({
                    value: String(v),
                    label: x.types[v],
                })),
            ),
            multi(
                'class_id',
                c.class,
                c.allClasses,
                lookups.classes.map((cls) => ({
                    value: String(cls.id),
                    label: cls.name,
                })),
            ),
            multi(
                'section_id',
                c.section,
                c.allSections,
                lookups.classes.flatMap((cls) =>
                    cls.sections.map((s) => ({
                        value: String(s.id),
                        label: `${cls.name} / ${s.name}`,
                    })),
                ),
            ),
        ],
        () =>
            visitWith('/attendance/excuses', {
                academic_year_id: filters.academic_year_id,
            }),
        [filters, lookups, yearSpec.options.length],
    );
    useAttendanceSearch(filters.q, x.searchHint, (q) => visit({ q }));

    const menuItems = (e: Excuse): ContextMenuItem[] => [
        {
            id: 'view',
            label: c.view,
            icon: FileSearch,
            onSelect: () => viewer.open([e]),
        },
        ...(abilities.review_excuse && e.status === 1
            ? [
                  {
                      id: 'approve',
                      label: x.approve,
                      icon: ShieldCheck,
                      separator: true,
                      onSelect: () =>
                          setDecision({ items: [e], approve: true, note: '' }),
                  },
                  {
                      id: 'reject',
                      label: x.reject,
                      icon: Ban,
                      danger: true,
                      onSelect: () =>
                          setDecision({ items: [e], approve: false, note: '' }),
                  },
              ]
            : []),
    ];

    const studentOptions = excuses.students.map((s) => ({
        value: String(s.enrollment_id),
        label: `${s.full_name} (${s.student_code}) — ${s.class_name ?? ''} / ${s.section_name ?? ''}`,
    }));

    return (
        <AttendancePage title={x.title}>
            <AttendanceTable
                caption={x.title}
                storageKey="excuses"
                rows={excuses.data}
                rowKey={(e) => e.id}
                columns={columns}
                emptyText={x.empty}
                selection={selection}
                onOpen={(e) => viewer.open([e])}
                menuItems={menuItems}
                server={{
                    page: excuses.meta.page,
                    perPage: excuses.meta.per_page,
                    total: excuses.meta.total,
                    lastPage: excuses.meta.last_page,
                    onPage: (page, per_page) => visit({ page, per_page }),
                }}
            />

            {viewer.element}

            {form !== null ? (
                <AttendanceWindow
                    title={x.add}
                    columns={2}
                    pending={busy}
                    dirty={form.reason !== '' || form.enrollment_id !== ''}
                    confirmLabel={x.add}
                    confirmDisabled={
                        form.enrollment_id === '' ||
                        form.date_from === '' ||
                        form.reason.trim().length < 3
                    }
                    onConfirm={submit}
                    onClose={() => setForm(null)}
                    sections={[
                        {
                            id: 'att-excuse-student',
                            title: x.student,
                            content: (
                                <>
                                    <div className="sis-admission-sheet__field sis-branches-field--wide">
                                        <span className="sis-admission-sheet__label">
                                            {x.searchStudent}
                                        </span>
                                        <span className="flex gap-2">
                                            <input
                                                className="sis-admission-sheet__control flex-1"
                                                value={studentQuery}
                                                onChange={(event) =>
                                                    setStudentQuery(
                                                        event.target.value,
                                                    )
                                                }
                                            />
                                            <Button
                                                type="button"
                                                variant="outline"
                                                disabled={
                                                    studentQuery.trim().length <
                                                    2
                                                }
                                                onClick={() =>
                                                    router.reload({
                                                        data: {
                                                            student:
                                                                studentQuery.trim(),
                                                        },
                                                        only: [
                                                            'excuses',
                                                            'filters',
                                                        ],
                                                    })
                                                }
                                            >
                                                <FileSearch aria-hidden />
                                                {c.search}
                                            </Button>
                                        </span>
                                    </div>
                                    <RegistryListField
                                        label={x.student}
                                        editing
                                        required
                                        fieldClassName="sis-branches-field--wide"
                                        value={form.enrollment_id}
                                        display={
                                            studentOptions.find(
                                                (o) =>
                                                    o.value ===
                                                    form.enrollment_id,
                                            )?.label ??
                                            (studentOptions.length === 0
                                                ? x.searchFirst
                                                : '—')
                                        }
                                        options={studentOptions}
                                        onChange={(enrollment_id) =>
                                            setForm({ ...form, enrollment_id })
                                        }
                                    />
                                </>
                            ),
                        },
                        {
                            id: 'att-excuse-details',
                            title: x.details,
                            content: (
                                <>
                                    <RegistryTextField
                                        label={x.dateFrom}
                                        editing
                                        required
                                        type="date"
                                        dir="ltr"
                                        value={form.date_from}
                                        onChange={(date_from) =>
                                            setForm({ ...form, date_from })
                                        }
                                    />
                                    <RegistryTextField
                                        label={x.dateTo}
                                        editing
                                        type="date"
                                        dir="ltr"
                                        value={form.date_to}
                                        onChange={(date_to) =>
                                            setForm({ ...form, date_to })
                                        }
                                    />
                                    <RegistryListField
                                        label={x.type}
                                        editing
                                        required
                                        value={form.excuse_type}
                                        display={
                                            x.types[Number(form.excuse_type)]
                                        }
                                        options={EXCUSE_TYPES.map((v) => ({
                                            value: String(v),
                                            label: x.types[v],
                                        }))}
                                        onChange={(excuse_type) =>
                                            setForm({ ...form, excuse_type })
                                        }
                                    />
                                    <label className="sis-admission-sheet__field">
                                        <span className="sis-admission-sheet__label">
                                            {x.document}
                                        </span>
                                        <input
                                            className="sis-admission-sheet__control"
                                            type="file"
                                            accept=".pdf,.jpg,.jpeg,.png,.webp"
                                            onChange={(event) =>
                                                setForm({
                                                    ...form,
                                                    document:
                                                        event.target
                                                            .files?.[0] ?? null,
                                                })
                                            }
                                        />
                                    </label>
                                    <WindowTextArea
                                        label={x.reason}
                                        required
                                        value={form.reason}
                                        onChange={(reason) =>
                                            setForm({ ...form, reason })
                                        }
                                    />
                                </>
                            ),
                        },
                    ]}
                />
            ) : null}

            {decision !== null ? (
                <AttendanceWindow
                    title={
                        decision.items.length > 1
                            ? `${x.decideTitle} (${decision.items.length})`
                            : x.decideTitle
                    }
                    columns={2}
                    pending={busy}
                    danger={!decision.approve}
                    dirty={decision.note !== ''}
                    confirmLabel={decision.approve ? x.approve : x.reject}
                    confirmDisabled={
                        decision.items.length === 0 ||
                        (!decision.approve && decision.note.trim() === '')
                    }
                    onConfirm={decide}
                    onClose={() => setDecision(null)}
                    sections={[
                        {
                            id: 'att-excuse-decide',
                            title:
                                decision.items.length === 1
                                    ? `${decision.items[0].full_name} — ${decision.items[0].date_from} → ${decision.items[0].date_to}`
                                    : `${x.decideTitle} (${decision.items.length})`,
                            content: (
                                <>
                                    <RegistryListField
                                        label={x.decision}
                                        editing
                                        required
                                        value={
                                            decision.approve
                                                ? 'approve'
                                                : 'reject'
                                        }
                                        display={
                                            decision.approve
                                                ? x.approve
                                                : x.reject
                                        }
                                        options={[
                                            {
                                                value: 'approve',
                                                label: x.approve,
                                            },
                                            {
                                                value: 'reject',
                                                label: x.reject,
                                            },
                                        ]}
                                        onChange={(value) =>
                                            setDecision({
                                                ...decision,
                                                approve: value === 'approve',
                                            })
                                        }
                                    />
                                    {decision.items.length === 1 ? (
                                        <WindowFact
                                            label={x.type}
                                            value={
                                                x.types[
                                                    decision.items[0]
                                                        .excuse_type
                                                ]
                                            }
                                        />
                                    ) : (
                                        <WindowFact
                                            label={x.student}
                                            value={decision.items
                                                .map((e) => e.full_name)
                                                .join('، ')}
                                        />
                                    )}
                                    {decision.items.length === 1 ? (
                                        <WindowFact
                                            wide
                                            label={x.reason}
                                            value={decision.items[0].reason}
                                        />
                                    ) : null}
                                    {decision.approve ? (
                                        <WindowFact
                                            wide
                                            label={x.approve}
                                            value={x.approveHint}
                                        />
                                    ) : null}
                                    <WindowTextArea
                                        label={
                                            decision.approve
                                                ? x.approveNote
                                                : x.rejectNote
                                        }
                                        required={!decision.approve}
                                        value={decision.note}
                                        onChange={(note) =>
                                            setDecision({ ...decision, note })
                                        }
                                    />
                                </>
                            ),
                        },
                    ]}
                />
            ) : null}
        </AttendancePage>
    );
}
