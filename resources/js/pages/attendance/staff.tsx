import { router, usePage } from '@inertiajs/react';
import {
    CalendarRange,
    History,
    KeyRound,
    LogIn,
    Nfc,
    QrCode,
    UserCog,
    UsersRound,
} from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import {
    AttendancePage,
    AttendanceTable,
    AttendanceWindow,
    WindowFact,
    WindowTextArea,
    WindowTimeField,
    postEach,
    selectionGroup,
    useAttendanceEditRibbon,
    useAttendanceHomeFilters,
    useAttendanceMessages,
    useAttendanceSearch,
    useAttendanceSelection,
    useRowViewer,
    useYearFilterSpec,
    visitWith,
    type AttendanceColumn,
    type AttendanceMessage,
} from '@/components/attendance/attendance-chrome';
import {
    STAFF_NOT_RECORDED,
    STAFF_STATUSES,
    StatusLabel,
    clock,
    dateTime,
    newKey,
    type AttendanceAbilities,
    type StatusMeta,
} from '@/components/attendance/attendance-ui';
import {
    RegistryListField,
    RegistryTextField,
} from '@/components/organization/registry-sheet';
import type { ContextMenuItem } from '@/components/sis/context-menu';
import { usePageError } from '@/components/sis/page-error-context';
import type { PageRibbonGroup } from '@/components/sis/page-ribbon-context';
import { Button } from '@/components/ui/button';
import { t } from '@/i18n';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

type StaffRow = {
    teacher_id: number;
    full_name: string;
    employee_code: string;
    account_email: string | null;
    record_id: number | null;
    status: number | null;
    check_in_at: string | null;
    check_out_at: string | null;
    minutes_late: number | null;
    method: number | null;
    notes: string | null;
    version: number | null;
    lessons_today: number;
};

type CoverLesson = {
    schedule_id: number;
    teacher_id: number;
    start_time: string;
    end_time: string;
    subject_name: string;
    class_name: string;
    section_name: string;
    teacher_name: string;
};
type HistoryRow = {
    id: number;
    work_date: string;
    status: number;
    check_in_at: string | null;
    check_out_at: string | null;
    minutes_late: number | null;
    method: number;
};

type Props = {
    day: {
        date: string;
        today: string;
        staff_day_start: string;
        grace_minutes: number;
        rows: StaffRow[];
        cover: CoverLesson[];
        history: HistoryRow[];
    };
    filters: { academic_year_id: number };
    linked: boolean;
    evidence: { qr_enabled: boolean; nfc_enabled: boolean };
    prefill: { code: string; via: 'code' | 'qr' } | null;
    abilities: AttendanceAbilities;
};

type Via = 'code' | 'qr' | 'nfc';
type Edit = {
    rows: StaffRow[];
    status: string;
    check_in: string;
    check_out: string;
    minutes_late: string;
    notes: string;
    reason: string;
};
type IssuedResult = {
    action?: string;
    code?: string | null;
    qr_svg?: string | null;
    token?: string | null;
    label?: string | null;
    valid_until?: string;
} | null;

/** Web NFC (Chrome on Android) — not in the TS DOM lib. */
type NdefRecord = { recordType: string; data?: DataView };
type NdefReader = {
    scan: (options?: { signal?: AbortSignal }) => Promise<void>;
    write: (message: {
        records: Array<{ recordType: 'text'; data: string }>;
    }) => Promise<void>;
    onreading: ((event: { message: { records: NdefRecord[] } }) => void) | null;
};
const ndefReader = (): NdefReader | null => {
    const ctor = (window as unknown as { NDEFReader?: new () => NdefReader })
        .NDEFReader;

    return ctor ? new ctor() : null;
};
const hasNfc = (): boolean =>
    typeof window !== 'undefined' && 'NDEFReader' in window;

const staffMeta = (status: number | null): StatusMeta =>
    status === null
        ? STAFF_NOT_RECORDED
        : (STAFF_STATUSES.find((s) => s.value === status) ??
          STAFF_NOT_RECORDED);

export default function AttendanceStaff(props: Props) {
    const a = t().attendanceSystem;
    const breadcrumbs: BreadcrumbItem[] = [
        { title: t().attendance.title, href: '/attendance' },
        { title: a.staff.title, href: '/attendance/staff' },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <StaffPage {...props} />
        </AppLayout>
    );
}

function StaffPage({
    day,
    filters,
    linked,
    evidence,
    prefill,
    abilities,
}: Props) {
    const a = t().attendanceSystem;
    const st = a.staff;
    const c = a.chrome;
    const { showInertiaErrors } = usePageError();
    const issued =
        (usePage().props as { flash?: { attendanceResult?: IssuedResult } })
            .flash?.attendanceResult ?? null;
    const [busy, setBusy] = useState(false);
    const [statuses, setStatuses] = useState<number[]>([]);
    const [query, setQuery] = useState('');
    const [edit, setEdit] = useState<Edit | null>(null);
    const [link, setLink] = useState<{ row: StaffRow; email: string } | null>(
        null,
    );
    const [checkIn, setCheckIn] = useState<{ code: string; via: Via } | null>(
        prefill !== null && linked && abilities.staff_checkin
            ? { ...prefill }
            : null,
    );
    const [shown, setShown] = useState<IssuedResult>(null);
    const [panel, setPanel] = useState<'history' | 'cover' | null>(null);
    const [nfcState, setNfcState] = useState<
        'idle' | 'scanning' | 'writing' | 'written' | 'failed'
    >('idle');

    useEffect(() => {
        if (issued?.action === 'code' || issued?.action === 'nfc_token') {
            setShown(issued);
            setNfcState('idle');
        }
    }, [issued]);

    const needle = query.trim().toLowerCase();
    const rows = useMemo(
        () =>
            day.rows.filter(
                (r) =>
                    (statuses.length === 0 ||
                        statuses.includes(r.status ?? 0)) &&
                    (needle === '' ||
                        r.full_name.toLowerCase().includes(needle) ||
                        r.employee_code.toLowerCase().includes(needle)),
            ),
        [day.rows, statuses, needle],
    );
    const selection = useAttendanceSelection(rows, (r) => r.teacher_id);
    const single = selection.targets.length === 1 ? selection.targets[0] : null;

    const post = (
        url: string,
        data: Record<string, unknown>,
        prefix: string,
        after?: () => void,
    ) => {
        setBusy(true);
        router.post(url, data as never, {
            preserveScroll: true,
            headers: { 'X-Idempotency-Key': newKey(prefix) },
            onSuccess: () => after?.(),
            onError: (errors) => showInertiaErrors(errors),
            onFinish: () => setBusy(false),
        });
    };

    const openEdit = (targets: StaffRow[]) => {
        const first = targets[0];
        setEdit({
            rows: targets,
            status: String(first.status ?? 1),
            check_in:
                targets.length === 1 && first.check_in_at
                    ? clock(first.check_in_at)
                    : '',
            check_out:
                targets.length === 1 && first.check_out_at
                    ? clock(first.check_out_at)
                    : '',
            minutes_late:
                targets.length === 1 && first.minutes_late !== null
                    ? String(first.minutes_late)
                    : '',
            notes: targets.length === 1 ? (first.notes ?? '') : '',
            reason: '',
        });
    };
    const correcting =
        edit !== null && edit.rows.some((r) => r.record_id !== null);

    /** Record / correct one or several teachers' day (one request each; a correction needs the reason). */
    const saveEdit = () => {
        if (edit === null) return;
        setBusy(true);
        postEach(
            edit.rows,
            (row) => ({
                url: '/attendance/staff',
                data: {
                    teacher_id: row.teacher_id,
                    work_date: day.date,
                    attendance_status: Number(edit.status),
                    check_in: edit.check_in || null,
                    check_out: edit.check_out || null,
                    minutes_late:
                        edit.status === '3' && edit.minutes_late !== ''
                            ? Number(edit.minutes_late)
                            : null,
                    notes: edit.notes.trim() || null,
                    reason: edit.reason.trim() || null,
                    version: row.version,
                },
                key: newKey(`staff-${row.teacher_id}`),
            }),
            {
                onError: (errors) => showInertiaErrors(errors),
                onDone: () => {
                    setEdit(null);
                },
                onFinish: () => setBusy(false),
            },
        );
    };

    /** «الجدول الدراسي» of the given teachers only (all teachers when none is selected). */
    const openTimetable = (teacherIds: number[]) =>
        router.get(
            '/timetable',
            teacherIds.length > 0
                ? { view: 'teacher', teacher_ids: teacherIds.join(',') }
                : {},
        );

    const submitCheckIn = (code: string, via: Via) =>
        post(
            '/attendance/staff/check-in',
            { code: code.trim(), via },
            'checkin',
            () => setCheckIn(null),
        );

    /** Read the station tag: its text record is the token; the server verifies it (policy must enable NFC). */
    const scanNfc = async () => {
        const reader = ndefReader();
        if (reader === null) return;
        const abort = new AbortController();
        setNfcState('scanning');
        reader.onreading = (event) => {
            const record = event.message.records.find(
                (r) => r.recordType === 'text',
            );
            abort.abort();
            setNfcState('idle');
            if (record?.data) {
                submitCheckIn(
                    new TextDecoder().decode(record.data).trim(),
                    'nfc',
                );
            }
        };
        try {
            await reader.scan({ signal: abort.signal });
        } catch {
            setNfcState('failed');
        }
    };

    const writeNfc = async (token: string) => {
        const reader = ndefReader();
        if (reader === null) return;
        setNfcState('writing');
        try {
            await reader.write({
                records: [{ recordType: 'text', data: token }],
            });
            setNfcState('written');
        } catch {
            setNfcState('failed');
        }
    };

    const statusColumn = <
        T extends { status: number | null },
    >(): AttendanceColumn<T> => ({
        id: 'status',
        header: st.status,
        kind: 'status',
        tone: (r) => staffMeta(r.status).tone,
        cell: (r) => (
            <StatusLabel
                meta={staffMeta(r.status)}
                label={
                    r.status === null ? st.notRecorded : st.statuses[r.status]
                }
            />
        ),
        text: (r) =>
            r.status === null ? st.notRecorded : st.statuses[r.status],
    });
    const columns: AttendanceColumn<StaffRow>[] = [
        {
            id: 'code',
            header: a.sheet.code,
            kind: 'text',
            cell: (r) => <span dir="ltr">{r.employee_code}</span>,
        },
        {
            id: 'teacher',
            header: st.teacher,
            kind: 'name',
            cell: (r) => r.full_name,
        },
        statusColumn<StaffRow>(),
        {
            id: 'in',
            header: st.checkInAt,
            kind: 'num',
            cell: (r) => (
                <span dir="ltr">
                    {r.check_in_at ? clock(r.check_in_at) : '—'}
                </span>
            ),
        },
        {
            id: 'out',
            header: st.checkOutAt,
            kind: 'num',
            cell: (r) => (
                <span dir="ltr">
                    {r.check_out_at ? clock(r.check_out_at) : '—'}
                </span>
            ),
        },
        {
            id: 'late',
            header: st.minutesLate,
            kind: 'num',
            cell: (r) => r.minutes_late ?? '—',
        },
        {
            id: 'method',
            header: st.method,
            kind: 'text',
            cell: (r) => (r.method ? st.methods[r.method] : '—'),
        },
        {
            id: 'lessons',
            header: st.lessonsToday,
            kind: 'num',
            cell: (r) => r.lessons_today,
        },
        {
            id: 'notes',
            header: st.notes,
            kind: 'text',
            cell: (r) => r.notes ?? '—',
        },
        ...(abilities.link_account
            ? [
                  {
                      id: 'account',
                      header: st.account,
                      kind: 'text' as const,
                      cell: (r: StaffRow) => (
                          <span dir="ltr">{r.account_email ?? '—'}</span>
                      ),
                  },
              ]
            : []),
    ];
    const historyColumns: AttendanceColumn<HistoryRow>[] = [
        {
            id: 'date',
            header: a.myDay.date,
            kind: 'text',
            cell: (h) => <span dir="ltr">{h.work_date}</span>,
        },
        statusColumn<HistoryRow>(),
        {
            id: 'in',
            header: st.checkInAt,
            kind: 'num',
            cell: (h) => (
                <span dir="ltr">
                    {h.check_in_at ? clock(h.check_in_at) : '—'}
                </span>
            ),
        },
        {
            id: 'out',
            header: st.checkOutAt,
            kind: 'num',
            cell: (h) => (
                <span dir="ltr">
                    {h.check_out_at ? clock(h.check_out_at) : '—'}
                </span>
            ),
        },
        {
            id: 'late',
            header: st.minutesLate,
            kind: 'num',
            cell: (h) => h.minutes_late ?? '—',
        },
        {
            id: 'method',
            header: st.method,
            kind: 'text',
            cell: (h) => st.methods[h.method] ?? '—',
        },
    ];
    const coverColumns: AttendanceColumn<CoverLesson>[] = [
        {
            id: 'time',
            header: a.myDay.time,
            kind: 'text',
            cell: (l) => (
                <span dir="ltr">{`${l.start_time}–${l.end_time}`}</span>
            ),
        },
        {
            id: 'subject',
            header: a.myDay.subject,
            kind: 'name',
            cell: (l) => l.subject_name,
        },
        {
            id: 'section',
            header: a.myDay.classSection,
            kind: 'text',
            cell: (l) => `${l.class_name} / ${l.section_name}`,
        },
        {
            id: 'teacher',
            header: st.teacher,
            kind: 'text',
            cell: (l) => l.teacher_name,
        },
    ];
    const viewer = useRowViewer(columns, (r) => r.full_name);
    const historySelection = useAttendanceSelection(
        day.history,
        (h) => h.id,
        'staff-history',
    );
    const historyViewer = useRowViewer(historyColumns, (h) => h.work_date);

    // A teacher without «تسجيل الدوام»: their own history is the page table.
    const ownOnly = !abilities.record_staff;

    const editGroups = useMemo((): PageRibbonGroup[] => {
        const groups: PageRibbonGroup[] = [
            ownOnly
                ? selectionGroup(
                      'attendance-staff-selection',
                      historySelection.targets,
                      historyViewer.open,
                      null,
                      { editTitle: st.ownHistoryReadOnly },
                  )
                : selectionGroup(
                      'attendance-staff-selection',
                      selection.targets,
                      viewer.open,
                      openEdit,
                      {
                          editLabel: single?.record_id ? st.edit : st.record,
                          extra: abilities.link_account
                              ? [
                                    {
                                        id: 'attendance-staff-link',
                                        label: st.linkAccount,
                                        icon: UserCog,
                                        iconTone: 'authority',
                                        disabled: single === null,
                                        title:
                                            single === null
                                                ? c.needsOne
                                                : undefined,
                                        onSelect: () =>
                                            single &&
                                            setLink({
                                                row: single,
                                                email:
                                                    single.account_email ?? '',
                                            }),
                                    },
                                ]
                              : [],
                      },
                  ),
        ];
        const actions: PageRibbonGroup['commands'] = [];
        if (abilities.staff_checkin) {
            actions.push({
                id: 'attendance-staff-checkin',
                label: st.checkIn,
                icon: LogIn,
                iconTone: 'ok',
                disabled: !linked,
                title: linked ? undefined : st.notLinked,
                onSelect: () => setCheckIn({ code: '', via: 'code' }),
            });
            if (!ownOnly) {
                actions.push({
                    id: 'attendance-staff-history',
                    label: st.history,
                    icon: History,
                    iconTone: 'steel',
                    count: day.history.length,
                    disabled: !linked,
                    onSelect: () => setPanel('history'),
                });
            }
        }
        if (abilities.record_staff) {
            actions.push({
                id: 'attendance-staff-cover',
                label: st.cover,
                icon: UsersRound,
                iconTone: 'warning',
                count: day.cover.length,
                onSelect: () => setPanel('cover'),
            });
        }
        if (actions.length > 0) {
            groups.push({
                id: 'attendance-staff-actions',
                label: c.actions,
                commands: actions,
            });
        }
        if (abilities.record_staff) {
            groups.push({
                id: 'attendance-staff-evidence',
                label: st.evidence,
                commands: [
                    {
                        id: 'attendance-staff-code',
                        label: st.issueCode,
                        icon: evidence.qr_enabled ? QrCode : KeyRound,
                        iconTone: 'sky',
                        disabled: busy,
                        onSelect: () =>
                            post('/attendance/staff/codes', {}, 'code'),
                    },
                    ...(evidence.nfc_enabled
                        ? [
                              {
                                  id: 'attendance-staff-nfc',
                                  label: st.issueNfc,
                                  icon: Nfc,
                                  iconTone: 'steel' as const,
                                  disabled: busy,
                                  onSelect: () =>
                                      post(
                                          '/attendance/staff/nfc-token',
                                          { label: null },
                                          'nfc-token',
                                      ),
                              },
                          ]
                        : []),
                    {
                        id: 'attendance-staff-timetable',
                        label: st.openTimetable,
                        icon: CalendarRange,
                        iconTone: 'growth',
                        title:
                            selection.targets.length > 0
                                ? `${st.openTimetable} (${selection.targets.length})`
                                : st.openTimetable,
                        onSelect: () =>
                            openTimetable(
                                selection.targets.map((r) => r.teacher_id),
                            ),
                    },
                ],
            });
        }

        return groups;
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [
        selection.targets,
        historySelection.targets,
        abilities,
        linked,
        evidence,
        busy,
        day.history.length,
        day.cover.length,
    ]);
    useAttendanceEditRibbon('staff', abilities, editGroups);

    const yearSpec = useYearFilterSpec(filters.academic_year_id, (year) =>
        visitWith('/attendance/staff', { academic_year_id: year }),
    );
    useAttendanceHomeFilters(
        [
            yearSpec,
            {
                key: 'date',
                date: true,
                label: a.myDay.date,
                value: day.date,
                onChange: (date: string) =>
                    visitWith('/attendance/staff', {
                        academic_year_id: filters.academic_year_id,
                        date,
                    }),
            },
            ...(abilities.record_staff
                ? [
                      {
                          key: 'status',
                          type: 'multi' as const,
                          label: st.status,
                          allLabel: c.allStatuses,
                          values: statuses.map(String),
                          options: [STAFF_NOT_RECORDED, ...STAFF_STATUSES].map(
                              (s) => ({
                                  value: String(s.value),
                                  label:
                                      s.value === 0
                                          ? st.notRecorded
                                          : st.statuses[s.value],
                              }),
                          ),
                          onChange: (values: string[]) =>
                              setStatuses(values.map(Number)),
                      },
                  ]
                : []),
        ],
        () => {
            setStatuses([]);
            setQuery('');
            visitWith('/attendance/staff', {
                academic_year_id: filters.academic_year_id,
            });
        },
        [
            filters.academic_year_id,
            day.date,
            statuses,
            abilities.record_staff,
            yearSpec.options.length,
        ],
    );
    useAttendanceSearch(query, a.sheet.search, setQuery, setQuery);

    const messages: AttendanceMessage[] =
        abilities.staff_checkin && !linked
            ? [
                  {
                      key: 'not-linked',
                      tone: 'warning' as const,
                      text: st.notLinked,
                  },
              ]
            : [];
    useAttendanceMessages(messages, st.title);

    const menuItems = (r: StaffRow): ContextMenuItem[] => [
        {
            id: 'view',
            label: c.view,
            icon: History,
            onSelect: () => viewer.open([r]),
        },
        {
            id: 'record',
            label: r.record_id === null ? st.record : st.edit,
            icon: LogIn,
            onSelect: () => openEdit([r]),
        },
        ...(abilities.link_account
            ? [
                  {
                      id: 'link',
                      label: st.linkAccount,
                      icon: UserCog,
                      onSelect: () =>
                          setLink({ row: r, email: r.account_email ?? '' }),
                  },
              ]
            : []),
    ];

    return (
        <AttendancePage title={st.title}>
            {ownOnly ? (
                <AttendanceTable
                    caption={st.history}
                    storageKey="staff.history"
                    rows={day.history}
                    rowKey={(h) => h.id}
                    columns={historyColumns}
                    emptyText={st.noHistory}
                    emptyAsDialog={linked}
                    selection={historySelection}
                    onOpen={(h) => historyViewer.open([h])}
                />
            ) : (
                <AttendanceTable
                    caption={st.title}
                    storageKey="staff.day"
                    rows={rows}
                    rowKey={(r) => r.teacher_id}
                    columns={columns}
                    emptyText={st.empty}
                    selection={selection}
                    onOpen={(r) => openEdit([r])}
                    menuItems={menuItems}
                />
            )}

            {viewer.element}
            {historyViewer.element}

            {panel !== null ? (
                <AttendanceWindow
                    wide
                    title={panel === 'history' ? st.history : st.cover}
                    onClose={() => setPanel(null)}
                    footer={
                        panel === 'cover' ? (
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() =>
                                    openTimetable(
                                        day.cover.length > 0
                                            ? [
                                                  ...new Set(
                                                      day.cover.map(
                                                          (l) => l.teacher_id,
                                                      ),
                                                  ),
                                              ]
                                            : [],
                                    )
                                }
                            >
                                {st.openTimetable}
                            </Button>
                        ) : null
                    }
                    sections={[
                        {
                            id: `att-staff-${panel}`,
                            title:
                                panel === 'history' ? st.history : st.coverHint,
                            plain: true,
                            content: (
                                <div className="sis-branches-field--wide">
                                    {panel === 'cover' ? (
                                        <p className="sis-attendance-window__hint">
                                            {st.coverHint}
                                        </p>
                                    ) : null}
                                    {panel === 'history' ? (
                                        <AttendanceTable
                                            caption={st.history}
                                            storageKey="staff.history"
                                            rows={day.history}
                                            rowKey={(h) => h.id}
                                            columns={historyColumns}
                                            emptyText={st.noHistory}
                                            fit={false}
                                            emptyAsDialog={false}
                                        />
                                    ) : (
                                        <AttendanceTable
                                            caption={st.cover}
                                            storageKey="staff.cover"
                                            rows={day.cover}
                                            rowKey={(l) => l.schedule_id}
                                            columns={coverColumns}
                                            emptyText={st.noCover}
                                            fit={false}
                                            emptyAsDialog={false}
                                        />
                                    )}
                                </div>
                            ),
                        },
                    ]}
                />
            ) : null}

            {edit !== null ? (
                <AttendanceWindow
                    title={
                        edit.rows.length > 1
                            ? `${correcting ? st.edit : st.record} (${edit.rows.length})`
                            : (edit.rows[0].record_id === null
                                  ? st.recordTitle
                                  : st.correctTitle
                              ).replace(':name', edit.rows[0].full_name)
                    }
                    columns={2}
                    pending={busy}
                    dirty
                    confirmLabel={correcting ? st.edit : st.record}
                    confirmDisabled={correcting && edit.reason.trim() === ''}
                    onConfirm={saveEdit}
                    onClose={() => setEdit(null)}
                    sections={[
                        {
                            id: 'att-staff-record',
                            title: `${edit.rows.length > 1 ? edit.rows.map((r) => r.full_name).join('، ') : edit.rows[0].full_name} — ${day.date}`,
                            content: (
                                <>
                                    <RegistryListField
                                        label={st.status}
                                        editing
                                        required
                                        value={edit.status}
                                        display={
                                            st.statuses[Number(edit.status)]
                                        }
                                        options={STAFF_STATUSES.map((s) => ({
                                            value: String(s.value),
                                            label: st.statuses[s.value],
                                        }))}
                                        onChange={(status) =>
                                            setEdit({ ...edit, status })
                                        }
                                    />
                                    {edit.status === '3' ? (
                                        <RegistryTextField
                                            label={st.minutesLate}
                                            editing
                                            type="number"
                                            dir="ltr"
                                            value={edit.minutes_late}
                                            onChange={(minutes_late) =>
                                                setEdit({
                                                    ...edit,
                                                    minutes_late,
                                                })
                                            }
                                        />
                                    ) : (
                                        <WindowFact
                                            label={st.dayStartLabel}
                                            value={st.dayStart
                                                .replace(
                                                    ':t',
                                                    day.staff_day_start,
                                                )
                                                .replace(
                                                    ':g',
                                                    String(day.grace_minutes),
                                                )}
                                        />
                                    )}
                                    <WindowTimeField
                                        label={st.checkInAt}
                                        value={edit.check_in}
                                        onChange={(check_in) =>
                                            setEdit({ ...edit, check_in })
                                        }
                                    />
                                    <WindowTimeField
                                        label={st.checkOutAt}
                                        value={edit.check_out}
                                        onChange={(check_out) =>
                                            setEdit({ ...edit, check_out })
                                        }
                                    />
                                    <RegistryTextField
                                        label={st.notes}
                                        editing
                                        fieldClassName="sis-branches-field--wide"
                                        value={edit.notes}
                                        onChange={(notes) =>
                                            setEdit({ ...edit, notes })
                                        }
                                    />
                                    {correcting ? (
                                        <WindowTextArea
                                            label={st.reason}
                                            required
                                            value={edit.reason}
                                            onChange={(reason) =>
                                                setEdit({ ...edit, reason })
                                            }
                                        />
                                    ) : null}
                                </>
                            ),
                        },
                    ]}
                />
            ) : null}

            {link !== null ? (
                <AttendanceWindow
                    title={st.linkAccount}
                    columns={2}
                    pending={busy}
                    dirty={link.email !== (link.row.account_email ?? '')}
                    confirmLabel={
                        link.email.trim() === ''
                            ? st.unlinkAccount
                            : st.linkAccount
                    }
                    danger={
                        link.email.trim() === '' &&
                        link.row.account_email !== null
                    }
                    confirmDisabled={
                        link.email.trim() === '' &&
                        link.row.account_email === null
                    }
                    onConfirm={() =>
                        post(
                            `/attendance/staff/teachers/${link.row.teacher_id}/account`,
                            { email: link.email.trim() || null },
                            'teacher-account',
                            () => setLink(null),
                        )
                    }
                    onClose={() => setLink(null)}
                    sections={[
                        {
                            id: 'att-staff-link',
                            title: link.row.full_name,
                            content: (
                                <>
                                    <WindowFact
                                        label={a.sheet.code}
                                        value={
                                            <span dir="ltr">
                                                {link.row.employee_code}
                                            </span>
                                        }
                                    />
                                    <WindowFact
                                        label={st.account}
                                        value={
                                            <span dir="ltr">
                                                {link.row.account_email ?? '—'}
                                            </span>
                                        }
                                    />
                                    <RegistryTextField
                                        label={st.email}
                                        editing
                                        type="email"
                                        dir="ltr"
                                        fieldClassName="sis-branches-field--wide"
                                        value={link.email}
                                        onChange={(email) =>
                                            setLink({ ...link, email })
                                        }
                                    />
                                    <WindowFact
                                        wide
                                        label={st.linkAccount}
                                        value={st.linkHint}
                                    />
                                </>
                            ),
                        },
                    ]}
                />
            ) : null}

            {checkIn !== null ? (
                <AttendanceWindow
                    title={st.checkInTitle}
                    pending={busy}
                    confirmLabel={st.checkIn}
                    confirmDisabled={!/^\d{4,10}$/.test(checkIn.code.trim())}
                    onConfirm={() => submitCheckIn(checkIn.code, checkIn.via)}
                    onClose={() => setCheckIn(null)}
                    sections={[
                        {
                            id: 'att-staff-checkin',
                            title:
                                checkIn.via === 'qr' ? st.qrScanned : st.code,
                            content: (
                                <>
                                    <label className="sis-admission-sheet__field sis-branches-field--wide">
                                        <span className="sis-admission-sheet__label">
                                            {st.code}
                                        </span>
                                        <input
                                            className="sis-admission-sheet__control text-lg tracking-widest"
                                            inputMode="numeric"
                                            autoComplete="one-time-code"
                                            dir="ltr"
                                            maxLength={10}
                                            autoFocus
                                            value={checkIn.code}
                                            onChange={(event) =>
                                                setCheckIn({
                                                    code: event.target.value.replace(
                                                        /\D/g,
                                                        '',
                                                    ),
                                                    via: 'code',
                                                })
                                            }
                                        />
                                    </label>
                                    <WindowFact
                                        wide
                                        label={st.method}
                                        value={st.checkInHint}
                                    />
                                    {evidence.nfc_enabled && hasNfc() ? (
                                        <div className="sis-admission-sheet__field sis-branches-field--wide">
                                            <Button
                                                type="button"
                                                variant="outline"
                                                disabled={
                                                    busy ||
                                                    nfcState === 'scanning'
                                                }
                                                onClick={() => void scanNfc()}
                                            >
                                                <Nfc aria-hidden />
                                                {nfcState === 'scanning'
                                                    ? st.nfcScanning
                                                    : st.nfcScan}
                                            </Button>
                                            {nfcState === 'failed' ? (
                                                <span className="text-sm">
                                                    {st.nfcFailed}
                                                </span>
                                            ) : null}
                                        </div>
                                    ) : null}
                                </>
                            ),
                        },
                    ]}
                />
            ) : null}

            {shown !== null ? (
                <AttendanceWindow
                    title={
                        shown.action === 'nfc_token'
                            ? st.nfcTokenTitle
                            : st.codeShown
                    }
                    onClose={() => setShown(null)}
                    sections={[
                        shown.action === 'nfc_token'
                            ? {
                                  id: 'att-staff-nfc-token',
                                  title: st.nfcTokenTitle,
                                  content: (
                                      <>
                                          <WindowFact
                                              wide
                                              label={st.nfcToken}
                                              value={
                                                  <span dir="ltr">
                                                      {shown.token ?? '—'}
                                                  </span>
                                              }
                                          />
                                          <WindowFact
                                              wide
                                              label={st.validUntil}
                                              value={dateTime(
                                                  shown.valid_until ?? null,
                                              )}
                                          />
                                          <WindowFact
                                              wide
                                              label={st.nfcToken}
                                              value={st.nfcTokenHint}
                                          />
                                          {shown.token && hasNfc() ? (
                                              <div className="sis-admission-sheet__field sis-branches-field--wide">
                                                  <Button
                                                      type="button"
                                                      variant="outline"
                                                      disabled={
                                                          nfcState === 'writing'
                                                      }
                                                      onClick={() =>
                                                          void writeNfc(
                                                              shown.token as string,
                                                          )
                                                      }
                                                  >
                                                      <Nfc aria-hidden />
                                                      {nfcState === 'writing'
                                                          ? st.nfcWriting
                                                          : st.nfcWrite}
                                                  </Button>
                                                  {nfcState === 'written' ? (
                                                      <span className="text-sm">
                                                          {st.nfcWritten}
                                                      </span>
                                                  ) : nfcState === 'failed' ? (
                                                      <span className="text-sm">
                                                          {st.nfcFailed}
                                                      </span>
                                                  ) : null}
                                              </div>
                                          ) : null}
                                      </>
                                  ),
                              }
                            : {
                                  id: 'att-staff-code',
                                  title: st.codeValidUntil.replace(
                                      ':t',
                                      dateTime(shown.valid_until ?? null),
                                  ),
                                  content: (
                                      <>
                                          <div
                                              className="sis-admission-sheet__field sis-branches-field--wide items-center text-center"
                                              role="status"
                                          >
                                              <span
                                                  className="text-4xl font-semibold tracking-widest"
                                                  dir="ltr"
                                              >
                                                  {shown.code ?? '—'}
                                              </span>
                                          </div>
                                          {shown.qr_svg ? (
                                              <div className="sis-admission-sheet__field sis-branches-field--wide items-center">
                                                  {/* Server-rendered SVG (bacon-qr-code) of a same-site URL — no user input. */}
                                                  <div
                                                      className="w-56 bg-white p-2"
                                                      aria-label={st.qrHint}
                                                      role="img"
                                                      dangerouslySetInnerHTML={{
                                                          __html: shown.qr_svg,
                                                      }}
                                                  />
                                                  <span className="text-sm">
                                                      {st.qrHint}
                                                  </span>
                                              </div>
                                          ) : null}
                                      </>
                                  ),
                              },
                    ]}
                />
            ) : null}
        </AttendancePage>
    );
}
