import { router } from '@inertiajs/react';
import { Ban, Eye, FileSearch, PlusCircle, ShieldCheck } from 'lucide-react';
import { useMemo, useState } from 'react';
import {
    AttendancePage,
    AttendanceTable,
    AttendanceWindow,
    PageNotice,
    WindowFact,
    WindowTextArea,
    useAttendanceEditRibbon,
    useAttendanceHomeFilters,
    useAttendanceSearch,
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
    const [selectedId, setSelectedId] = useState<number | null>(null);
    const [form, setForm] = useState<Form | null>(
        filters.student !== '' ? { ...EMPTY_FORM } : null,
    );
    const [studentQuery, setStudentQuery] = useState(filters.student);
    const [decision, setDecision] = useState<{
        excuse: Excuse;
        approve: boolean;
        note: string;
    } | null>(null);
    const [viewing, setViewing] = useState<Excuse | null>(null);
    const [busy, setBusy] = useState(false);
    const selected = excuses.data.find((e) => e.id === selectedId) ?? null;
    const pending =
        selected !== null && selected.status === 1 && abilities.review_excuse;

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

    const decide = () => {
        if (decision === null) return;
        setBusy(true);
        router.post(
            `/attendance/excuses/${decision.excuse.id}/decide`,
            {
                decision: decision.approve ? 'approve' : 'reject',
                version: decision.excuse.version,
                note: decision.note.trim() || null,
            },
            {
                preserveScroll: true,
                headers: { 'X-Idempotency-Key': newKey('excuse-decide') },
                onSuccess: () => setDecision(null),
                onError: (e) => showInertiaErrors(e),
                onFinish: () => setBusy(false),
            },
        );
    };

    const editGroups = useMemo(
        (): PageRibbonGroup[] => [
            {
                id: 'attendance-excuses-actions',
                label: c.actions,
                commands: [
                    {
                        id: 'attendance-excuses-view',
                        label: c.view,
                        icon: Eye,
                        iconTone: 'sky',
                        disabled: selected === null,
                        title: selected === null ? c.needsSelection : undefined,
                        onSelect: () => selected && setViewing(selected),
                    },
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
                                  disabled: !pending,
                                  onSelect: () =>
                                      selected &&
                                      setDecision({
                                          excuse: selected,
                                          approve: true,
                                          note: '',
                                      }),
                              },
                              {
                                  id: 'attendance-excuses-reject',
                                  label: x.reject,
                                  icon: Ban,
                                  iconTone: 'danger' as const,
                                  disabled: !pending,
                                  onSelect: () =>
                                      selected &&
                                      setDecision({
                                          excuse: selected,
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
        [selected, pending, abilities, filters],
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
        ],
        () =>
            visitWith('/attendance/excuses', {
                academic_year_id: filters.academic_year_id,
            }),
        [filters, lookups, yearSpec.options.length],
    );
    useAttendanceSearch(filters.q, x.searchHint, (q) => visit({ q }));

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
    ];

    const menuItems = (e: Excuse): ContextMenuItem[] => [
        { id: 'view', label: c.view, icon: Eye, onSelect: () => setViewing(e) },
        ...(abilities.review_excuse && e.status === 1
            ? [
                  {
                      id: 'approve',
                      label: x.approve,
                      icon: ShieldCheck,
                      separator: true,
                      onSelect: () =>
                          setDecision({ excuse: e, approve: true, note: '' }),
                  },
                  {
                      id: 'reject',
                      label: x.reject,
                      icon: Ban,
                      danger: true,
                      onSelect: () =>
                          setDecision({ excuse: e, approve: false, note: '' }),
                  },
              ]
            : []),
    ];

    const studentOptions = excuses.students.map((s) => ({
        value: String(s.enrollment_id),
        label: `${s.full_name} (${s.student_code}) — ${s.class_name ?? ''} / ${s.section_name ?? ''}`,
    }));

    return (
        <AttendancePage
            title={x.title}
            notices={<PageNotice>{x.description}</PageNotice>}
        >
            <AttendanceTable
                caption={x.title}
                rows={excuses.data}
                rowKey={(e) => e.id}
                columns={columns}
                emptyText={x.empty}
                selectedId={selectedId}
                onSelect={(e) => setSelectedId(e.id)}
                onOpen={setViewing}
                menuItems={menuItems}
                page={excuses.meta.page}
                lastPage={excuses.meta.last_page}
                onPage={(page) => visit({ page })}
                numberOffset={(excuses.meta.page - 1) * excuses.meta.per_page}
            />

            {form !== null ? (
                <AttendanceWindow
                    title={x.add}
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
                    narrow={false}
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
                                            )?.label ?? '—'
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
                                    <WindowTextArea
                                        label={x.reason}
                                        required
                                        value={form.reason}
                                        onChange={(reason) =>
                                            setForm({ ...form, reason })
                                        }
                                    />
                                    <label className="sis-admission-sheet__field sis-branches-field--wide">
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
                                </>
                            ),
                        },
                    ]}
                />
            ) : null}

            {decision !== null ? (
                <AttendanceWindow
                    title={x.decideTitle}
                    pending={busy}
                    danger={!decision.approve}
                    dirty={decision.note !== ''}
                    confirmLabel={decision.approve ? x.approve : x.reject}
                    confirmDisabled={
                        !decision.approve && decision.note.trim() === ''
                    }
                    onConfirm={decide}
                    onClose={() => setDecision(null)}
                    sections={[
                        {
                            id: 'att-excuse-decide',
                            title: `${decision.excuse.full_name} — ${decision.excuse.date_from} → ${decision.excuse.date_to}`,
                            content: (
                                <>
                                    <WindowFact
                                        label={x.type}
                                        value={
                                            x.types[decision.excuse.excuse_type]
                                        }
                                    />
                                    <WindowFact
                                        wide
                                        label={x.reason}
                                        value={decision.excuse.reason}
                                    />
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

            {viewing !== null ? (
                <AttendanceWindow
                    title={x.viewTitle}
                    onClose={() => setViewing(null)}
                    sections={[
                        {
                            id: 'att-excuse-view',
                            title: viewing.full_name,
                            content: (
                                <>
                                    <WindowFact
                                        label={a.sheet.code}
                                        value={
                                            <span dir="ltr">
                                                {viewing.student_code}
                                            </span>
                                        }
                                    />
                                    <WindowFact
                                        label={c.section}
                                        value={`${viewing.class_name ?? ''} / ${viewing.section_name ?? ''}`}
                                    />
                                    <WindowFact
                                        label={x.dateFrom}
                                        value={
                                            <span dir="ltr">
                                                {viewing.date_from}
                                            </span>
                                        }
                                    />
                                    <WindowFact
                                        label={x.dateTo}
                                        value={
                                            <span dir="ltr">
                                                {viewing.date_to}
                                            </span>
                                        }
                                    />
                                    <WindowFact
                                        label={x.type}
                                        value={x.types[viewing.excuse_type]}
                                    />
                                    <WindowFact
                                        label={x.status}
                                        value={x.statuses[viewing.status]}
                                    />
                                    <WindowFact
                                        wide
                                        label={x.reason}
                                        value={viewing.reason}
                                    />
                                    <WindowFact
                                        label={x.submittedBy}
                                        value={`${viewing.submitted_by_name ?? '—'} · ${dateTime(viewing.submitted_at)}`}
                                    />
                                    <WindowFact
                                        label={x.reviewedBy}
                                        value={`${viewing.reviewed_by_name ?? '—'} · ${dateTime(viewing.reviewed_at)}`}
                                    />
                                    <WindowFact
                                        wide
                                        label={x.decisionNote}
                                        value={viewing.decision_note ?? '—'}
                                    />
                                    <WindowFact
                                        label={x.documentId}
                                        value={viewing.document_file_id ?? '—'}
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
