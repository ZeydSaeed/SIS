import { router } from '@inertiajs/react';
import {
    Ban,
    CalendarClock,
    Eye,
    FileUp,
    MessageCircleQuestion,
    Paperclip,
    Pencil,
    PlusCircle,
    Send,
    ShieldCheck,
    Undo2,
    UserCheck,
    Users,
    XCircle,
} from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import {
    AttendanceTable,
    AttendanceWindow,
    WindowFact,
    useAttendanceHomeFilters,
    useAttendanceSearch,
    useAttendanceSelection,
    useYearFilterSpec,
    visitWith,
    type AttendanceColumn,
} from '@/components/attendance/attendance-chrome';
import { StatusLabel, dateTime } from '@/components/attendance/attendance-ui';
import {
    LeaveFilePicker,
    LeaveInfoNote,
    LeaveTextArea,
    leaveStatusMeta,
    periodText,
    quantityText,
    typeText,
    LeavePage,
    useLeaveEditRibbon,
    useLeaveWrite,
    type LeaveAbilities,
    type LeavePolicy,
    type LeaveRequestRow,
    type LeaveType,
    type StaffOption,
} from '@/components/leave/leave-chrome';
import {
    RegistryListField,
    RegistryTextField,
} from '@/components/organization/registry-sheet';
import type { ContextMenuItem } from '@/components/sis/context-menu';
import type {
    PageRibbonCommand,
    PageRibbonGroup,
} from '@/components/sis/page-ribbon-context';
import type { FilterSpec } from '@/components/sis/ribbon-filter';
import { Button } from '@/components/ui/button';
import { t } from '@/i18n';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

type Filters = {
    academic_year_id: number;
    state: number[];
    kind: number[];
    type: number[];
    q: string;
    from: string | null;
    to: string | null;
    mine: boolean;
    awaiting: boolean;
    sort: string;
    direction: 'asc' | 'desc';
};

type Detail = LeaveRequestRow & {
    own: boolean;
    policy: LeavePolicy | null;
    balance: {
        entitled: number;
        adjusted: number;
        used: number;
        reserved: number;
        balance: number;
        available: number;
    } | null;
    events: Array<{
        id: number;
        event_type: number;
        from_status: number | null;
        to_status: number;
        level: number | null;
        note: string | null;
        actor_name: string | null;
        created_at: string;
    }>;
    attachments: Array<{
        id: number;
        file_name: string;
        mime_type: string;
        file_size: number;
        created_at: string;
    }>;
};

type CoverageLesson = {
    schedule_id: number;
    date: string;
    period_number: number;
    start_time: string;
    end_time: string;
    class_name: string;
    section_name: string;
    subject_name: string;
    room_name: string | null;
    role: 'lead' | 'co';
    substitute_teacher_id: number | null;
    substitute_name: string | null;
    covered: boolean;
};

type Coverage = {
    request_id: number;
    status: number;
    academic_year_id: number;
    lessons: CoverageLesson[];
    page: number;
    pages: number;
    working_days: number;
} | null;

type Candidate = {
    teacher_id: number;
    name: string;
    compatibility: number;
    qualified: boolean;
    knows_section: boolean;
    lessons_that_day: number;
    over_limit: boolean;
};

type Props = {
    requests: {
        items: LeaveRequestRow[];
        total: number;
        page: number;
        per_page: number;
    };
    types: LeaveType[];
    staff: StaffOption[];
    filters: Filters;
    abilities: LeaveAbilities;
    detail?: Detail | null;
    coverage?: Coverage;
    substitutes?: { candidates: Candidate[]; reason: string | null } | null;
};

type Form = {
    id: number | null;
    version: number | null;
    person: string;
    leave_type_id: string;
    date_from: string;
    date_to: string;
    hours: string;
    reason: string;
    document: File | null;
};

type Decision = 'approve' | 'reject' | 'info';

const PATH = '/leave/requests';

export default function LeaveRequests(props: Props) {
    const l = t().leave;
    const breadcrumbs: BreadcrumbItem[] = [
        { title: l.title, href: '/leave' },
        { title: l.requests, href: PATH },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <RequestsPage {...props} />
        </AppLayout>
    );
}

function RequestsPage({
    requests,
    types,
    staff,
    filters,
    abilities,
    detail,
    coverage,
    substitutes,
}: Props) {
    const l = t().leave;
    const write = useLeaveWrite();
    const selection = useAttendanceSelection(requests.items, (r) => r.id);
    const [form, setForm] = useState<Form | null>(null);
    const [viewing, setViewing] = useState<number | null>(null);
    const [decision, setDecision] = useState<{
        items: LeaveRequestRow[];
        decision: Decision;
        note: string;
    } | null>(null);
    const [closing, setClosing] = useState<{
        item: LeaveRequestRow;
        action: 'withdraw' | 'cancel';
        note: string;
    } | null>(null);
    const [attaching, setAttaching] = useState<{
        item: LeaveRequestRow;
        file: File | null;
    } | null>(null);
    const [covering, setCovering] = useState<number | null>(null);
    const [suggestFor, setSuggestFor] = useState<CoverageLesson | null>(null);
    const [busy, setBusy] = useState(false);

    const targets = selection.targets;
    const one = targets.length === 1 ? targets[0] : null;
    const pending = targets.filter((r) => r.status === 2);
    const submittable = targets.filter((r) => r.status === 1 || r.status === 3);
    const canWrite = abilities.request || abilities.manage;

    const visit = (
        next: Partial<Filters> & { page?: number; per_page?: number },
    ) =>
        visitWith(PATH, {
            ...filters,
            ...next,
            page: next.page ?? 1,
            state:
                (next.state ?? filters.state).length === 0
                    ? null
                    : (next.state ?? filters.state),
        });

    /** Reads one optional prop (detail / coverage / substitutes) without leaving the page. */
    const load = (only: string[], data: Record<string, unknown>) =>
        router.reload({
            only,
            data: data as never,
            preserveUrl: true,
        } as never);

    const openDetail = (row: LeaveRequestRow) => {
        setViewing(row.id);
        load(['detail'], { open: row.id });
    };
    const refresh = () => {
        selection.clear();
        if (viewing !== null) {
            load(['detail', 'requests'], { open: viewing });
        }
    };

    const blankForm = (): Form => {
        const own = staff.filter((s) => s.own);
        const only = !abilities.manage && own.length === 1 ? own[0] : null;

        return {
            id: null,
            version: null,
            person: only ? `${only.kind}:${only.id}` : '',
            leave_type_id: '',
            date_from: '',
            date_to: '',
            hours: '',
            reason: '',
            document: null,
        };
    };
    const editForm = (row: LeaveRequestRow): Form => ({
        id: row.id,
        version: row.version,
        person: `${row.requester_kind}:${row.person_id}`,
        leave_type_id: String(row.leave_type_id),
        date_from: row.date_from,
        date_to: row.date_to,
        hours: row.unit === 2 ? String(row.quantity) : '',
        reason: row.reason ?? '',
        document: null,
    });

    const saveForm = (submit: boolean) => {
        if (form === null) return;
        const [kind, person] = form.person.split(':').map(Number);
        const type = types.find((x) => String(x.id) === form.leave_type_id);
        setBusy(true);
        // A PATCH with a file goes as POST + _method (multipart).
        write(
            'post',
            form.id === null ? PATH : `${PATH}/${form.id}`,
            {
                ...(form.id === null
                    ? {}
                    : { _method: 'patch', version: form.version }),
                academic_year_id: filters.academic_year_id,
                requester_kind: kind,
                person_id: person,
                leave_type_id: Number(form.leave_type_id),
                date_from: form.date_from,
                date_to:
                    type?.unit === 2
                        ? form.date_from
                        : form.date_to || form.date_from,
                hours: type?.unit === 2 ? Number(form.hours) : null,
                reason: form.reason.trim(),
                submit: submit ? 1 : 0,
                ...(form.document ? { document: form.document } : {}),
            },
            {
                files: true,
                onDone: () => {
                    setForm(null);
                    refresh();
                },
                onFinish: () => setBusy(false),
            },
        );
    };

    const runEach = (
        items: LeaveRequestRow[],
        url: (r: LeaveRequestRow) => string,
        data: (r: LeaveRequestRow) => Record<string, unknown>,
        after: () => void,
    ) => {
        setBusy(true);
        const step = (index: number) => {
            if (index >= items.length) {
                setBusy(false);
                after();
                refresh();
                return;
            }
            let succeeded = false;
            write('post', url(items[index]), data(items[index]), {
                onDone: () => {
                    succeeded = true;
                    step(index + 1);
                },
                // A refusal stops the run (its message is already shown).
                onFinish: () => succeeded || setBusy(false),
            });
        };
        step(0);
    };

    const decide = () => {
        if (decision === null) return;
        runEach(
            decision.items,
            (r) => `${PATH}/${r.id}/decide`,
            (r) => ({
                version: r.version,
                decision: decision.decision,
                note: decision.note.trim() || null,
            }),
            () => setDecision(null),
        );
    };
    const submitMany = (items: LeaveRequestRow[]) =>
        runEach(
            items,
            (r) => `${PATH}/${r.id}/submit`,
            (r) => ({ version: r.version }),
            () => undefined,
        );
    const close = () => {
        if (closing === null) return;
        runEach(
            [closing.item],
            (r) => `${PATH}/${r.id}/${closing.action}`,
            (r) => ({ version: r.version, note: closing.note.trim() || null }),
            () => setClosing(null),
        );
    };
    const attach = () => {
        if (attaching === null || attaching.file === null) return;
        setBusy(true);
        write(
            'post',
            `${PATH}/${attaching.item.id}/attachments`,
            { document: attaching.file },
            {
                files: true,
                onDone: () => {
                    setAttaching(null);
                    refresh();
                },
                onFinish: () => setBusy(false),
            },
        );
    };
    const openCoverage = (row: LeaveRequestRow, page = 1) => {
        setCovering(row.id);
        setSuggestFor(null);
        load(['coverage'], { coverage: row.id, coverage_page: page });
    };
    const suggest = (lesson: CoverageLesson) => {
        setSuggestFor(lesson);
        load(['substitutes'], {
            substitute: lesson.schedule_id,
            date: lesson.date,
        });
    };
    const assign = (lesson: CoverageLesson, candidate: Candidate) => {
        setBusy(true);
        write(
            'post',
            `/timetable/schedules/${lesson.schedule_id}/substitute`,
            {
                exception_date: lesson.date,
                substitute_teacher_id: candidate.teacher_id,
                reason: `${l.title} #${requests.items.find((r) => r.id === covering)?.request_no ?? ''}`,
            },
            {
                onDone: () => {
                    setSuggestFor(null);
                    if (covering !== null)
                        load(['coverage'], {
                            coverage: covering,
                            coverage_page: coverage?.page ?? 1,
                        });
                },
                onFinish: () => setBusy(false),
            },
        );
    };

    /**
     * The same substitute for every uncovered day of this lesson shown in the coverage window: one timetable
     * substitution per day, in order — each keeps the timetable's own checks (clashes, permission, audit), and the run
     * stops at the first refusal (its message is already shown).
     */
    const assignAll = (lesson: CoverageLesson, candidate: Candidate) => {
        const days = (coverage?.lessons ?? []).filter(
            (x) =>
                x.schedule_id === lesson.schedule_id &&
                x.role === 'lead' &&
                !x.covered,
        );
        const reason = `${l.title} #${requests.items.find((r) => r.id === covering)?.request_no ?? ''}`;
        setBusy(true);
        const step = (index: number) => {
            if (index >= days.length) {
                setBusy(false);
                setSuggestFor(null);
                if (covering !== null)
                    load(['coverage'], {
                        coverage: covering,
                        coverage_page: coverage?.page ?? 1,
                    });
                return;
            }
            let succeeded = false;
            write(
                'post',
                `/timetable/schedules/${days[index].schedule_id}/substitute`,
                {
                    exception_date: days[index].date,
                    substitute_teacher_id: candidate.teacher_id,
                    reason,
                },
                {
                    onDone: () => {
                        succeeded = true;
                        step(index + 1);
                    },
                    onFinish: () => succeeded || setBusy(false),
                },
            );
        };
        step(0);
    };

    // ── Table ──────────────────────────────────────────────────────────────────────────────────────────────────
    const columns: AttendanceColumn<LeaveRequestRow>[] = [
        {
            id: 'no',
            header: l.requestNo,
            kind: 'num',
            cell: (r) => <span dir="ltr">{r.request_no}</span>,
            sort: (r) => r.request_no,
        },
        {
            id: 'person',
            header: l.person,
            kind: 'name',
            cell: (r) => r.person_name ?? '—',
        },
        {
            id: 'kind',
            header: l.kind,
            kind: 'text',
            cell: (r) => l.kinds[r.requester_kind],
        },
        { id: 'type', header: l.type, kind: 'text', cell: (r) => typeText(r) },
        {
            id: 'period',
            header: l.period,
            kind: 'text',
            cell: (r) => (
                <span className="sis-leave-period" dir="ltr">
                    {periodText(r.date_from, r.date_to)}
                </span>
            ),
            sort: (r) => r.date_from,
        },
        {
            id: 'quantity',
            header: l.quantity,
            kind: 'text',
            cell: (r) => quantityText(r.quantity, r.unit),
            sort: (r) => r.quantity,
        },
        {
            id: 'status',
            header: l.status,
            kind: 'status',
            tone: (r) => leaveStatusMeta(r.status)?.tone ?? 0,
            cell: (r) => (
                <StatusLabel
                    meta={leaveStatusMeta(r.status)}
                    label={l.statuses[r.status]}
                />
            ),
            text: (r) => l.statuses[r.status],
            sort: (r) => r.status,
        },
        {
            id: 'stage',
            header: l.stage,
            kind: 'text',
            cell: (r) =>
                r.status === 2
                    ? l.stageOf
                          .replace(':done', String(r.approved_levels + 1))
                          .replace(':total', String(r.approval_levels))
                    : '—',
        },
        {
            id: 'submitted',
            header: l.submittedAt,
            kind: 'text',
            cell: (r) => dateTime(r.submitted_at),
            sort: (r) => r.submitted_at ?? '',
        },
    ];

    const actionsFor = (
        row: LeaveRequestRow | null,
        rows: LeaveRequestRow[],
    ): Array<PageRibbonCommand & { danger?: boolean }> => {
        const none = rows.length === 0;
        const editable = row !== null && (row.status === 1 || row.status === 3);
        const rowsPending = rows.filter((r) => r.status === 2);
        const rowsSubmittable = rows.filter(
            (r) => r.status === 1 || r.status === 3,
        );

        return [
            {
                id: 'leave-view',
                label: l.viewTitle,
                icon: Eye,
                iconTone: 'sky' as const,
                disabled: row === null,
                title: row === null ? l.onlySelected : undefined,
                onSelect: () => row && openDetail(row),
            },
            ...(canWrite
                ? [
                      {
                          id: 'leave-edit',
                          label: t().common.edit,
                          icon: Pencil,
                          iconTone: 'amber' as const,
                          tone: 'edit' as const,
                          disabled: !editable,
                          title: editable ? undefined : l.onlyEditable,
                          onSelect: () => row && setForm(editForm(row)),
                      },
                      {
                          id: 'leave-submit',
                          label: l.submit,
                          icon: Send,
                          iconTone: 'education' as const,
                          disabled: rowsSubmittable.length === 0 || busy,
                          count: rowsSubmittable.length || undefined,
                          onSelect: () => submitMany(rowsSubmittable),
                      },
                      {
                          id: 'leave-attach',
                          label: l.attach,
                          icon: Paperclip,
                          iconTone: 'steel' as const,
                          disabled:
                              row === null ||
                              ![1, 2, 3, 4].includes(row.status),
                          onSelect: () =>
                              row && setAttaching({ item: row, file: null }),
                      },
                  ]
                : []),
            ...(abilities.approve
                ? [
                      {
                          id: 'leave-approve',
                          label: l.approve,
                          icon: ShieldCheck,
                          iconTone: 'ok' as const,
                          disabled: rowsPending.length === 0,
                          title:
                              rowsPending.length === 0
                                  ? l.onlyPending
                                  : undefined,
                          count: rowsPending.length || undefined,
                          onSelect: () =>
                              setDecision({
                                  items: rowsPending,
                                  decision: 'approve',
                                  note: '',
                              }),
                      },
                      {
                          id: 'leave-reject',
                          label: l.reject,
                          icon: Ban,
                          iconTone: 'danger' as const,
                          disabled: rowsPending.length === 0,
                          title:
                              rowsPending.length === 0
                                  ? l.onlyPending
                                  : undefined,
                          onSelect: () =>
                              setDecision({
                                  items: rowsPending,
                                  decision: 'reject',
                                  note: '',
                              }),
                          danger: true,
                      },
                      {
                          id: 'leave-info',
                          label: l.requestInfo,
                          icon: MessageCircleQuestion,
                          iconTone: 'warning' as const,
                          disabled: rowsPending.length === 0,
                          onSelect: () =>
                              setDecision({
                                  items: rowsPending,
                                  decision: 'info',
                                  note: '',
                              }),
                      },
                  ]
                : []),
            ...(canWrite
                ? [
                      {
                          id: 'leave-withdraw',
                          label: l.withdraw,
                          icon: Undo2,
                          iconTone: 'steel' as const,
                          disabled:
                              row === null || ![1, 2, 3].includes(row.status),
                          title: l.onlyUndecided,
                          onSelect: () =>
                              row &&
                              setClosing({
                                  item: row,
                                  action: 'withdraw',
                                  note: '',
                              }),
                      },
                  ]
                : []),
            ...(abilities.cancel
                ? [
                      {
                          id: 'leave-cancel',
                          label: l.cancel,
                          icon: XCircle,
                          iconTone: 'rose' as const,
                          tone: 'delete' as const,
                          disabled: row === null || row.status !== 4,
                          title: l.onlyApproved,
                          onSelect: () =>
                              row &&
                              setClosing({
                                  item: row,
                                  action: 'cancel',
                                  note: '',
                              }),
                          danger: true,
                      },
                  ]
                : []),
            ...(abilities.view
                ? [
                      {
                          id: 'leave-coverage',
                          label: l.coverage,
                          icon: CalendarClock,
                          iconTone: 'growth' as const,
                          disabled:
                              row === null ||
                              row.requester_kind !== 1 ||
                              ![2, 3, 4].includes(row.status),
                          title: l.onlyTeachers,
                          onSelect: () => row && openCoverage(row),
                      },
                  ]
                : []),
        ].map((c) => ({ ...c, disabled: c.disabled || none }));
    };

    // Groups of at most five commands, so every group stays on ONE row of the ribbon with its icons and labels in view.
    const editGroups = useMemo((): PageRibbonGroup[] => {
        const byId = new Map(
            [
                ...(canWrite
                    ? [
                          {
                              id: 'leave-add',
                              label: l.add,
                              icon: PlusCircle,
                              iconTone: 'ok' as const,
                              onSelect: () => setForm(blankForm()),
                          },
                      ]
                    : []),
                ...actionsFor(one, targets),
            ].map((c) => [c.id, c] as const),
        );
        const pick = (...ids: string[]) =>
            ids.flatMap((id) => {
                const c = byId.get(id);

                return c === undefined ? [] : [c];
            });

        return [
            {
                id: 'leave-requests-request',
                label:
                    targets.length > 1
                        ? `${l.groupRequest} (${targets.length})`
                        : l.groupRequest,
                commands: pick(
                    'leave-add',
                    'leave-view',
                    'leave-edit',
                    'leave-submit',
                    'leave-attach',
                ),
            },
            {
                id: 'leave-requests-decision',
                label: l.groupDecision,
                commands: pick('leave-approve', 'leave-reject', 'leave-info'),
            },
            {
                id: 'leave-requests-close',
                label: l.groupClose,
                commands: pick(
                    'leave-withdraw',
                    'leave-cancel',
                    'leave-coverage',
                ),
            },
            {
                id: 'leave-requests-scope',
                label: l.filters,
                commands: [
                    {
                        id: 'leave-mine',
                        label: l.mine,
                        icon: UserCheck,
                        iconTone: 'education' as const,
                        pressed: filters.mine,
                        onSelect: () => visit({ mine: !filters.mine }),
                    },
                    ...(abilities.approve
                        ? [
                              {
                                  id: 'leave-awaiting',
                                  label: l.awaiting,
                                  icon: Users,
                                  iconTone: 'amber' as const,
                                  pressed: filters.awaiting,
                                  onSelect: () =>
                                      visit({ awaiting: !filters.awaiting }),
                              },
                          ]
                        : []),
                ],
            },
        ].filter((g) => g.commands.length > 0);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [targets, filters, abilities, busy, staff, types]);
    useLeaveEditRibbon(editGroups);

    const yearSpec = useYearFilterSpec(filters.academic_year_id, (year) =>
        visitWith(PATH, { academic_year_id: year }),
    );
    useAttendanceHomeFilters(
        [
            yearSpec,
            {
                key: 'from',
                date: true,
                label: l.dateFrom,
                value: filters.from ?? '',
                onChange: (from: string) => visit({ from }),
            },
            {
                key: 'to',
                date: true,
                label: l.dateTo,
                value: filters.to ?? '',
                onChange: (to: string) => visit({ to }),
            },
            {
                key: 'kind',
                type: 'multi',
                label: l.kind,
                allLabel: l.allKinds,
                values: filters.kind.map(String),
                options: [
                    { value: '1', label: l.kindsPlural[1] },
                    { value: '2', label: l.kindsPlural[2] },
                ],
                onChange: (values: string[]) =>
                    visit({ kind: values.map(Number) }),
            } as FilterSpec,
            {
                key: 'state',
                type: 'multi',
                label: l.status,
                allLabel: l.allStatuses,
                values: filters.state.map(String),
                options: [1, 2, 3, 4, 5, 6, 7].map((v) => ({
                    value: String(v),
                    label: l.statuses[v],
                })),
                onChange: (values: string[]) =>
                    visit({ state: values.map(Number) }),
            } as FilterSpec,
            {
                key: 'type',
                type: 'multi',
                label: l.type,
                allLabel: l.allTypes,
                values: filters.type.map(String),
                options: types.map((x) => ({
                    value: String(x.id),
                    label: x.name_ar,
                })),
                onChange: (values: string[]) =>
                    visit({ type: values.map(Number) }),
            } as FilterSpec,
        ],
        () => visitWith(PATH, { academic_year_id: filters.academic_year_id }),
        [filters, types, yearSpec.options.length],
    );
    useAttendanceSearch(filters.q, l.search, (q) => visit({ q }));

    const menuItems = (row: LeaveRequestRow): ContextMenuItem[] =>
        actionsFor(row, [row])
            .filter((c) => !c.disabled)
            .map((c, index) => ({
                id: c.id,
                label: c.label,
                icon: c.icon,
                danger: c.danger,
                separator: index === 1,
                onSelect: () => c.onSelect?.(),
            }));

    // Close the detail window when its request leaves the page's reach (e.g. another school).
    useEffect(() => {
        if (viewing !== null && detail === null) setViewing(null);
    }, [detail, viewing]);

    const kindOfForm = form?.person ? Number(form.person.split(':')[0]) : null;
    const formTypes = types.filter(
        (x) =>
            x.status === 1 &&
            x.policy !== null &&
            (kindOfForm === null ||
                x.applies_to === 3 ||
                x.applies_to === kindOfForm),
    );
    const formType =
        types.find((x) => String(x.id) === form?.leave_type_id) ?? null;
    const staffOptions = staff.map((s) => ({
        value: `${s.kind}:${s.id}`,
        label: `${s.name} — ${l.kinds[s.kind]}${s.own ? ' ★' : ''}`,
    }));
    const formValid =
        form !== null &&
        form.person !== '' &&
        form.leave_type_id !== '' &&
        form.date_from !== '' &&
        form.reason.trim() !== '' &&
        (formType?.unit !== 2 || Number(form.hours) > 0) &&
        (formType?.unit === 2 ||
            form.date_to === '' ||
            form.date_to >= form.date_from);
    const lessonsByDay = (coverage?.lessons ?? []).reduce<
        Record<string, CoverageLesson[]>
    >(
        (acc, lesson) => ({
            ...acc,
            [lesson.date]: [...(acc[lesson.date] ?? []), lesson],
        }),
        {},
    );

    return (
        <LeavePage
            title={l.requests}
            current={filters.awaiting ? 'awaiting' : 'requests'}
            abilities={abilities}
        >
            {!canWrite && !abilities.view ? (
                <p className="sis-branches-page__notice">{l.readOnly}</p>
            ) : null}
            <AttendanceTable
                caption={l.requests}
                storageKey="leave.requests"
                rows={requests.items}
                rowKey={(r) => r.id}
                columns={columns}
                emptyText={l.empty}
                selection={selection}
                onOpen={openDetail}
                menuItems={menuItems}
                menuLabel={l.actions}
                server={{
                    page: requests.page,
                    perPage: requests.per_page,
                    total: requests.total,
                    lastPage: Math.max(
                        1,
                        Math.ceil(requests.total / requests.per_page),
                    ),
                    onPage: (page, per_page) => visit({ page, per_page }),
                }}
            />

            {viewing !== null && detail ? (
                <AttendanceWindow
                    fit
                    title={`${l.viewTitle} #${detail.request_no}`}
                    className="sis-leave-view"
                    columns={2}
                    wide
                    onClose={() => setViewing(null)}
                    footer={
                        <>
                            {actionsFor(detail, [detail])
                                .filter(
                                    (c) => !c.disabled && c.id !== 'leave-view',
                                )
                                .map((c) => (
                                    <Button
                                        key={c.id}
                                        type="button"
                                        variant={
                                            c.danger ? 'destructive' : 'outline'
                                        }
                                        disabled={busy}
                                        onClick={() => c.onSelect?.()}
                                    >
                                        {c.icon ? <c.icon aria-hidden /> : null}
                                        {c.label}
                                    </Button>
                                ))}
                        </>
                    }
                    sections={[
                        {
                            id: 'leave-detail-main',
                            title: l.sectionLeave,
                            content: (
                                <>
                                    <WindowFact
                                        label={l.person}
                                        value={`${detail.person_name ?? '—'} (${detail.person_code ?? '—'}) — ${l.kinds[detail.requester_kind]}`}
                                        wide
                                    />
                                    <WindowFact
                                        label={l.type}
                                        value={typeText(detail)}
                                    />
                                    <WindowFact
                                        label={l.status}
                                        value={
                                            <StatusLabel
                                                meta={leaveStatusMeta(
                                                    detail.status,
                                                )}
                                                label={
                                                    l.statuses[detail.status]
                                                }
                                            />
                                        }
                                    />
                                    <WindowFact
                                        label={l.period}
                                        value={
                                            <span dir="ltr">
                                                {periodText(
                                                    detail.date_from,
                                                    detail.date_to,
                                                )}
                                            </span>
                                        }
                                    />
                                    <WindowFact
                                        label={l.quantity}
                                        value={quantityText(
                                            detail.quantity,
                                            detail.unit,
                                        )}
                                    />
                                    <WindowFact
                                        label={l.stage}
                                        value={`${detail.approved_levels} / ${detail.approval_levels}`}
                                    />
                                    <WindowFact
                                        label={l.submittedAt}
                                        value={dateTime(detail.submitted_at)}
                                    />
                                    <WindowFact
                                        label={l.createdBy}
                                        value={detail.created_by_name ?? '—'}
                                    />
                                    <WindowFact
                                        label={l.decidedBy}
                                        value={
                                            detail.decided_by_name
                                                ? `${detail.decided_by_name} · ${dateTime(detail.decided_at)}`
                                                : '—'
                                        }
                                    />
                                    <WindowFact
                                        wide
                                        label={l.reason}
                                        value={
                                            detail.masked
                                                ? l.maskedHint
                                                : (detail.reason ?? '—')
                                        }
                                    />
                                    {detail.decision_note ? (
                                        <WindowFact
                                            wide
                                            label={l.decisionNote}
                                            value={detail.decision_note}
                                        />
                                    ) : null}
                                </>
                            ),
                        },
                        {
                            id: 'leave-detail-balance',
                            title: l.sectionSummary,
                            content: (
                                <>
                                    <WindowFact
                                        label={l.sectionPolicy}
                                        value={
                                            detail.policy
                                                ? `${l.version} ${detail.policy.version_no} · ${l.dayBasis[detail.policy.day_basis]}`
                                                : '—'
                                        }
                                        wide
                                    />
                                    {detail.balance &&
                                    detail.policy?.deducts_balance ? (
                                        <>
                                            <WindowFact
                                                label={l.entitled}
                                                value={detail.balance.entitled}
                                            />
                                            <WindowFact
                                                label={l.used}
                                                value={detail.balance.used}
                                            />
                                            <WindowFact
                                                label={l.reserved}
                                                value={detail.balance.reserved}
                                            />
                                            <WindowFact
                                                label={l.available}
                                                value={detail.balance.available}
                                            />
                                        </>
                                    ) : (
                                        <WindowFact
                                            label={l.balance}
                                            value={l.noBalance}
                                            wide
                                        />
                                    )}
                                </>
                            ),
                        },
                        {
                            id: 'leave-detail-files',
                            title: l.sectionAttachments,
                            content: (
                                <div className="sis-branches-field--wide">
                                    {detail.masked ? (
                                        <p className="sis-attendance-window__hint">
                                            {l.maskedHint}
                                        </p>
                                    ) : detail.attachments.length === 0 ? (
                                        <p className="sis-attendance-window__hint">
                                            {l.noAttachments}
                                        </p>
                                    ) : (
                                        <ul className="space-y-1">
                                            {detail.attachments.map((a) => (
                                                <li key={a.id}>
                                                    <a
                                                        className="underline"
                                                        href={`${PATH}/${detail.id}/attachments/${a.id}`}
                                                    >
                                                        <FileUp
                                                            className="me-1 inline size-4"
                                                            aria-hidden
                                                        />
                                                        {a.file_name}
                                                    </a>{' '}
                                                    <span
                                                        className="text-xs"
                                                        dir="ltr"
                                                    >
                                                        (
                                                        {Math.ceil(
                                                            a.file_size / 1024,
                                                        )}{' '}
                                                        KB ·{' '}
                                                        {dateTime(a.created_at)}
                                                        )
                                                    </span>
                                                </li>
                                            ))}
                                        </ul>
                                    )}
                                </div>
                            ),
                        },
                        {
                            id: 'leave-detail-history',
                            title: l.sectionHistory,
                            plain: true,
                            content: (
                                <AttendanceTable
                                    caption={l.sectionHistory}
                                    storageKey="leave.history"
                                    rows={detail.events}
                                    rowKey={(e) => e.id}
                                    fit={false}
                                    emptyText={l.noHistory}
                                    columns={[
                                        {
                                            id: 'at',
                                            header: l.entryAt,
                                            kind: 'text',
                                            cell: (e) => dateTime(e.created_at),
                                        },
                                        {
                                            id: 'event',
                                            header: l.history,
                                            kind: 'text',
                                            cell: (e) =>
                                                `${l.events[e.event_type]}${e.level ? ` (${e.level})` : ''}`,
                                        },
                                        {
                                            id: 'to',
                                            header: l.status,
                                            kind: 'text',
                                            cell: (e) =>
                                                l.statuses[e.to_status],
                                        },
                                        {
                                            id: 'by',
                                            header: l.entryBy,
                                            kind: 'text',
                                            cell: (e) => e.actor_name ?? '—',
                                        },
                                        {
                                            id: 'note',
                                            header: l.note,
                                            kind: 'text',
                                            cell: (e) => e.note ?? '—',
                                        },
                                    ]}
                                />
                            ),
                        },
                    ]}
                />
            ) : null}

            {form !== null ? (
                <AttendanceWindow
                    fit
                    title={form.id === null ? l.addTitle : l.editTitle}
                    columns={2}
                    wide
                    pending={busy}
                    confirmLabel={l.saveAndSubmit}
                    confirmDisabled={!formValid}
                    onConfirm={() => saveForm(true)}
                    onClose={() => setForm(null)}
                    footer={
                        <Button
                            type="button"
                            variant="outline"
                            disabled={busy || !formValid}
                            onClick={() => saveForm(false)}
                        >
                            {l.save}
                        </Button>
                    }
                    sections={[
                        {
                            id: 'leave-form-person',
                            title: l.sectionRequester,
                            content: (
                                <>
                                    {staffOptions.length === 0 ? (
                                        <WindowFact
                                            wide
                                            label={l.person}
                                            value={l.noStaff}
                                        />
                                    ) : (
                                        <RegistryListField
                                            label={l.person}
                                            editing={form.id === null}
                                            required
                                            fieldClassName="sis-branches-field--wide"
                                            value={form.person}
                                            display={
                                                staffOptions.find(
                                                    (o) =>
                                                        o.value === form.person,
                                                )?.label ?? l.choosePerson
                                            }
                                            options={staffOptions}
                                            onChange={(person) =>
                                                setForm({
                                                    ...form,
                                                    person,
                                                    leave_type_id: '',
                                                })
                                            }
                                        />
                                    )}
                                    <RegistryListField
                                        label={l.type}
                                        editing
                                        required
                                        fieldClassName="sis-branches-field--wide"
                                        value={form.leave_type_id}
                                        display={
                                            formType?.name_ar ?? l.chooseType
                                        }
                                        options={formTypes.map((x) => ({
                                            value: String(x.id),
                                            label: `${x.name_ar} — ${l.units[x.unit]}`,
                                        }))}
                                        onChange={(leave_type_id) =>
                                            setForm({ ...form, leave_type_id })
                                        }
                                    />
                                    {formType?.policy ? (
                                        <LeaveInfoNote
                                            items={[
                                                l.dayBasis[
                                                    formType.policy.day_basis
                                                ],
                                                `${l.approvalLevels}: ${formType.policy.approval_levels}`,
                                                formType.policy.max_per_request
                                                    ? `${l.maxPerRequest}: ${formType.policy.max_per_request}`
                                                    : null,
                                                formType.policy.deducts_balance
                                                    ? null
                                                    : l.noBalance,
                                                formType.policy
                                                    .requires_document
                                                    ? l.requiresDocument
                                                    : null,
                                                formType.is_sensitive
                                                    ? l.sensitive
                                                    : null,
                                            ]}
                                        />
                                    ) : null}
                                </>
                            ),
                        },
                        {
                            id: 'leave-form-period',
                            title: l.period,
                            content: (
                                <>
                                    <RegistryTextField
                                        label={l.dateFrom}
                                        editing
                                        required
                                        type="date"
                                        dir="ltr"
                                        value={form.date_from}
                                        onChange={(date_from) =>
                                            setForm({ ...form, date_from })
                                        }
                                    />
                                    {formType?.unit === 2 ? (
                                        <RegistryTextField
                                            label={l.hours}
                                            editing
                                            required
                                            type="number"
                                            dir="ltr"
                                            value={form.hours}
                                            onChange={(hours) =>
                                                setForm({ ...form, hours })
                                            }
                                        />
                                    ) : (
                                        <RegistryTextField
                                            label={l.dateTo}
                                            editing
                                            type="date"
                                            dir="ltr"
                                            value={form.date_to}
                                            onChange={(date_to) =>
                                                setForm({ ...form, date_to })
                                            }
                                        />
                                    )}
                                    <LeaveInfoNote
                                        items={[l.durationPreview]}
                                    />
                                </>
                            ),
                        },
                        {
                            id: 'leave-form-reason',
                            title: l.sectionReason,
                            content: (
                                <>
                                    <LeaveTextArea
                                        label={l.reason}
                                        required
                                        value={form.reason}
                                        onChange={(reason) =>
                                            setForm({ ...form, reason })
                                        }
                                    />
                                    <LeaveFilePicker
                                        label={l.attach}
                                        required={
                                            formType?.policy
                                                ?.requires_document === true
                                        }
                                        file={form.document}
                                        onChange={(document) =>
                                            setForm({ ...form, document })
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
                    fit
                    title={
                        decision.items.length > 1
                            ? `${l.decideTitle} (${decision.items.length})`
                            : l.decideTitle
                    }
                    columns={2}
                    pending={busy}
                    danger={decision.decision === 'reject'}
                    confirmLabel={
                        decision.decision === 'approve'
                            ? l.approve
                            : decision.decision === 'reject'
                              ? l.reject
                              : l.confirmInfo
                    }
                    confirmDisabled={
                        decision.items.length === 0 ||
                        (decision.decision !== 'approve' &&
                            decision.note.trim() === '')
                    }
                    onConfirm={decide}
                    onClose={() => setDecision(null)}
                    sections={[
                        {
                            id: 'leave-decide',
                            title: decision.items
                                .map(
                                    (r) =>
                                        `#${r.request_no} ${r.person_name ?? ''}`,
                                )
                                .join('، '),
                            content: (
                                <>
                                    <RegistryListField
                                        label={l.decision}
                                        editing
                                        required
                                        value={decision.decision}
                                        display={
                                            decision.decision === 'approve'
                                                ? l.approve
                                                : decision.decision === 'reject'
                                                  ? l.reject
                                                  : l.confirmInfo
                                        }
                                        options={[
                                            {
                                                value: 'approve',
                                                label: l.approve,
                                            },
                                            {
                                                value: 'reject',
                                                label: l.reject,
                                            },
                                            {
                                                value: 'info',
                                                label: l.requestInfo,
                                            },
                                        ]}
                                        onChange={(value) =>
                                            setDecision({
                                                ...decision,
                                                decision: value as Decision,
                                            })
                                        }
                                    />
                                    {decision.items.length === 1 ? (
                                        <>
                                            <WindowFact
                                                label={l.type}
                                                value={typeText(
                                                    decision.items[0],
                                                )}
                                            />
                                            <WindowFact
                                                label={l.period}
                                                value={
                                                    <span dir="ltr">
                                                        {periodText(
                                                            decision.items[0]
                                                                .date_from,
                                                            decision.items[0]
                                                                .date_to,
                                                        )}
                                                    </span>
                                                }
                                            />
                                            <WindowFact
                                                label={l.quantity}
                                                value={quantityText(
                                                    decision.items[0].quantity,
                                                    decision.items[0].unit,
                                                )}
                                            />
                                            <WindowFact
                                                wide
                                                label={l.reason}
                                                value={
                                                    decision.items[0].masked
                                                        ? l.maskedHint
                                                        : (decision.items[0]
                                                              .reason ?? '—')
                                                }
                                            />
                                        </>
                                    ) : null}
                                    <LeaveTextArea
                                        label={l.note}
                                        required={
                                            decision.decision !== 'approve'
                                        }
                                        value={decision.note}
                                        onChange={(note) =>
                                            setDecision({ ...decision, note })
                                        }
                                    />
                                    <p className="sis-attendance-window__hint sis-branches-field--wide">
                                        {l.notePlaceholder}
                                    </p>
                                </>
                            ),
                        },
                    ]}
                />
            ) : null}

            {closing !== null ? (
                <AttendanceWindow
                    fit
                    title={
                        closing.action === 'cancel'
                            ? l.cancelTitle
                            : l.withdrawTitle
                    }
                    pending={busy}
                    danger={closing.action === 'cancel'}
                    confirmLabel={
                        closing.action === 'cancel'
                            ? l.confirmCancel
                            : l.confirmWithdraw
                    }
                    confirmDisabled={
                        closing.action === 'cancel' &&
                        closing.note.trim() === ''
                    }
                    onConfirm={close}
                    onClose={() => setClosing(null)}
                    sections={[
                        {
                            id: 'leave-close',
                            title: `#${closing.item.request_no} ${closing.item.person_name ?? ''}`,
                            content: (
                                <>
                                    <p className="sis-attendance-window__hint sis-branches-field--wide">
                                        {closing.action === 'cancel'
                                            ? l.cancelHint
                                            : l.withdrawHint}
                                    </p>
                                    <LeaveTextArea
                                        label={l.note}
                                        required={closing.action === 'cancel'}
                                        value={closing.note}
                                        onChange={(note) =>
                                            setClosing({ ...closing, note })
                                        }
                                    />
                                </>
                            ),
                        },
                    ]}
                />
            ) : null}

            {attaching !== null ? (
                <AttendanceWindow
                    fit
                    title={l.attachTitle}
                    pending={busy}
                    confirmLabel={l.attach}
                    confirmDisabled={attaching.file === null}
                    onConfirm={attach}
                    onClose={() => setAttaching(null)}
                    sections={[
                        {
                            id: 'leave-attach',
                            title: `#${attaching.item.request_no} ${attaching.item.person_name ?? ''}`,
                            content: (
                                <>
                                    <label className="sis-admission-sheet__field sis-branches-field--wide">
                                        <span className="sis-admission-sheet__label">
                                            {l.file} *
                                        </span>
                                        <input
                                            className="sis-admission-sheet__control"
                                            type="file"
                                            accept=".pdf,.jpg,.jpeg,.png,.webp"
                                            onChange={(e) =>
                                                setAttaching({
                                                    ...attaching,
                                                    file:
                                                        e.target.files?.[0] ??
                                                        null,
                                                })
                                            }
                                        />
                                    </label>
                                    <p className="sis-attendance-window__hint sis-branches-field--wide">
                                        {l.attachHint}
                                    </p>
                                </>
                            ),
                        },
                    ]}
                />
            ) : null}

            {covering !== null && coverage ? (
                <AttendanceWindow
                    fit
                    title={l.coverageTitle}
                    columns={2}
                    wide
                    onClose={() => {
                        setCovering(null);
                        setSuggestFor(null);
                    }}
                    footer={
                        coverage.pages > 1 ? (
                            <>
                                <Button
                                    type="button"
                                    variant="outline"
                                    disabled={coverage.page <= 1}
                                    onClick={() => {
                                        const row = requests.items.find(
                                            (r) => r.id === covering,
                                        );
                                        if (row)
                                            openCoverage(
                                                row,
                                                coverage.page - 1,
                                            );
                                    }}
                                >
                                    {l.previous}
                                </Button>
                                <span dir="ltr">
                                    {coverage.page} / {coverage.pages}
                                </span>
                                <Button
                                    type="button"
                                    variant="outline"
                                    disabled={coverage.page >= coverage.pages}
                                    onClick={() => {
                                        const row = requests.items.find(
                                            (r) => r.id === covering,
                                        );
                                        if (row)
                                            openCoverage(
                                                row,
                                                coverage.page + 1,
                                            );
                                    }}
                                >
                                    {l.next}
                                </Button>
                            </>
                        ) : null
                    }
                    sections={[
                        {
                            id: 'leave-coverage',
                            title: `${l.workingDays}: ${coverage.working_days}`,
                            plain: true,
                            content: (
                                <div className="sis-branches-field--wide">
                                    <p className="sis-attendance-window__hint">
                                        {l.coverageHint}
                                    </p>
                                    {coverage.lessons.length === 0 ? (
                                        <p className="sis-attendance-window__hint">
                                            {l.coverageEmpty}
                                        </p>
                                    ) : null}
                                    {Object.entries(lessonsByDay).map(
                                        ([day, lessons]) => (
                                            <section
                                                key={day}
                                                className="sis-leave-coverage-day"
                                            >
                                            <h3 className="sis-leave-coverage-day__title">
                                                <span dir="ltr">{day}</span>
                                                <span>{lessons.length}</span>
                                            </h3>
                                            <AttendanceTable
                                                caption={day}
                                                storageKey="leave.coverage"
                                                rows={lessons}
                                                rowKey={(x) => x.schedule_id}
                                                fit={false}
                                                emptyText={l.coverageEmpty}
                                                columns={[
                                                    {
                                                        id: 'date',
                                                        header: l.coverageDate,
                                                        kind: 'text',
                                                        cell: (x) => (
                                                            <span dir="ltr">
                                                                {x.date}
                                                            </span>
                                                        ),
                                                    },
                                                    {
                                                        id: 'period',
                                                        header: l.coveragePeriod,
                                                        kind: 'text',
                                                        cell: (x) => (
                                                            <span dir="ltr">{`${x.period_number} · ${x.start_time}–${x.end_time}`}</span>
                                                        ),
                                                    },
                                                    {
                                                        id: 'section',
                                                        header: l.coverageSection,
                                                        kind: 'text',
                                                        cell: (x) =>
                                                            `${x.class_name} / ${x.section_name}`,
                                                    },
                                                    {
                                                        id: 'subject',
                                                        header: l.coverageSubject,
                                                        kind: 'text',
                                                        cell: (x) =>
                                                            x.subject_name,
                                                    },
                                                    {
                                                        id: 'room',
                                                        header: l.coverageRoom,
                                                        kind: 'text',
                                                        cell: (x) =>
                                                            x.room_name ?? '—',
                                                    },
                                                    {
                                                        id: 'role',
                                                        header: l.coverageRole,
                                                        kind: 'text',
                                                        cell: (x) =>
                                                            l.coverageRoles[
                                                                x.role
                                                            ],
                                                    },
                                                    {
                                                        id: 'sub',
                                                        header: l.coverageSubstitute,
                                                        kind: 'status',
                                                        tone: (x) =>
                                                            x.covered ? 1 : 4,
                                                        cell: (x) =>
                                                            x.covered
                                                                ? `${l.coverageCovered}: ${x.substitute_name ?? '—'}`
                                                                : l.coverageNone,
                                                    },
                                                    {
                                                        id: 'act',
                                                        header: l.suggest,
                                                        kind: 'plain',
                                                        cell: (x) =>
                                                            x.role ===
                                                            'lead' ? (
                                                                <Button
                                                                    type="button"
                                                                    size="sm"
                                                                    variant="outline"
                                                                    onClick={() =>
                                                                        suggest(
                                                                            x,
                                                                        )
                                                                    }
                                                                >
                                                                    {l.suggest}
                                                                </Button>
                                                            ) : (
                                                                '—'
                                                            ),
                                                    },
                                                ]}
                                            />
                                            </section>
                                        ),
                                    )}
                                </div>
                            ),
                        },
                    ]}
                />
            ) : null}

            {suggestFor !== null ? (
                <AttendanceWindow
                    fit
                    title={`${l.suggestTitle} — ${suggestFor.subject_name} · ${suggestFor.date}`}
                    columns={2}
                    pending={busy}
                    onClose={() => setSuggestFor(null)}
                    sections={[
                        {
                            id: 'leave-suggest',
                            title: l.candidates,
                            plain: true,
                            content: (
                                <AttendanceTable
                                    caption={l.candidates}
                                    storageKey="leave.candidates"
                                    rows={substitutes?.candidates ?? []}
                                    rowKey={(c) => c.teacher_id}
                                    fit={false}
                                    emptyText={l.noCandidates}
                                    columns={[
                                        {
                                            id: 'name',
                                            header: l.person,
                                            kind: 'name',
                                            cell: (c) => c.name,
                                        },
                                        {
                                            id: 'score',
                                            header: '%',
                                            kind: 'num',
                                            cell: (c) => c.compatibility,
                                        },
                                        {
                                            id: 'qualified',
                                            header: l.qualified,
                                            kind: 'text',
                                            cell: (c) =>
                                                c.qualified ? l.yes : l.no,
                                        },
                                        {
                                            id: 'load',
                                            header: l.lessonsThatDay,
                                            kind: 'num',
                                            cell: (c) => c.lessons_that_day,
                                        },
                                        {
                                            id: 'assign',
                                            header: l.assign,
                                            kind: 'plain',
                                            cell: (c) =>
                                                abilities.assign_cover ? (
                                                    <>
                                                        <Button
                                                            type="button"
                                                            size="sm"
                                                            disabled={busy}
                                                            onClick={() =>
                                                                assign(
                                                                    suggestFor,
                                                                    c,
                                                                )
                                                            }
                                                        >
                                                            {l.assign}
                                                        </Button>{' '}
                                                        <Button
                                                            type="button"
                                                            size="sm"
                                                            variant="outline"
                                                            disabled={busy}
                                                            onClick={() =>
                                                                assignAll(
                                                                    suggestFor,
                                                                    c,
                                                                )
                                                            }
                                                        >
                                                            {l.assignAllDays}
                                                        </Button>
                                                    </>
                                                ) : (
                                                    '—'
                                                ),
                                        },
                                    ]}
                                />
                            ),
                        },
                    ]}
                />
            ) : null}
        </LeavePage>
    );
}
