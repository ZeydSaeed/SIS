import { router } from '@inertiajs/react';
import {
    BellRing,
    Clock3,
    ClipboardPen,
    Fingerprint,
    RotateCcw,
    UserCheck,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import {
    AttendancePage,
    AttendanceTable,
    AttendanceWindow,
    WindowFact,
    WindowTimeField,
    selectionGroup,
    useAttendanceEditRibbon,
    useAttendanceHomeFilters,
    useAttendanceMessages,
    useAttendanceSelection,
    useRowViewer,
    useYearFilterSpec,
    visitWith,
    type AttendanceColumn,
} from '@/components/attendance/attendance-chrome';
import {
    newKey,
    type AttendanceAbilities,
} from '@/components/attendance/attendance-ui';
import {
    RegistryListField,
    RegistryTextField,
} from '@/components/organization/registry-sheet';
import { usePageError } from '@/components/sis/page-error-context';
import type { PageRibbonGroup } from '@/components/sis/page-ribbon-context';
import { t } from '@/i18n';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

type Policy = {
    id: number | null;
    version_no: number;
    timezone: string;
    open_before_minutes: number;
    submit_deadline_minutes: number;
    edit_window_days: number;
    late_grace_minutes: number;
    late_as_absent_minutes: number | null;
    require_complete: boolean;
    default_mark: number;
    teacher_can_mark_excused: boolean;
    absence_alert_count: number;
    absence_alert_window_days: number;
    absence_rate_alert_percent: number;
    late_alert_count: number;
    returned_alert_count: number;
    staff_day_start: string;
    staff_late_grace_minutes: number;
    checkin_code_ttl_minutes: number;
    qr_enabled: boolean;
    nfc_enabled: boolean;
};

type Props = {
    settings: { policy: Policy; is_default: boolean; default_timezone: string };
    filters: { academic_year_id: number };
    abilities: AttendanceAbilities;
};

type Key = Exclude<keyof Policy, 'id' | 'version_no'>;
type GroupKey = 'time' | 'marking' | 'alerts' | 'staff' | 'evidence';
type Row = { id: number; key: Key; group: GroupKey };

const TIMEZONES = [
    'Asia/Baghdad',
    'Asia/Riyadh',
    'Asia/Kuwait',
    'Asia/Amman',
    'Asia/Damascus',
    'Asia/Beirut',
    'Asia/Dubai',
    'Asia/Aden',
    'Africa/Cairo',
    'UTC',
];

const GROUPS: Record<GroupKey, Key[]> = {
    time: [
        'timezone',
        'open_before_minutes',
        'submit_deadline_minutes',
        'edit_window_days',
    ],
    marking: [
        'late_grace_minutes',
        'late_as_absent_minutes',
        'require_complete',
        'default_mark',
        'teacher_can_mark_excused',
    ],
    alerts: [
        'absence_alert_count',
        'absence_alert_window_days',
        'absence_rate_alert_percent',
        'late_alert_count',
        'returned_alert_count',
    ],
    staff: [
        'staff_day_start',
        'staff_late_grace_minutes',
        'checkin_code_ttl_minutes',
    ],
    evidence: ['qr_enabled', 'nfc_enabled'],
};

const GROUP_ICONS = {
    time: Clock3,
    marking: ClipboardPen,
    alerts: BellRing,
    staff: UserCheck,
    evidence: Fingerprint,
} as const;
const GROUP_TONES = {
    time: 'education',
    marking: 'growth',
    alerts: 'warning',
    staff: 'forest',
    evidence: 'authority',
} as const;

/** Group → status-cell tone (shared 0..5 palette). */
const GROUP_STATUS_TONES: Record<GroupKey, number> = {
    time: 3,
    marking: 1,
    alerts: 2,
    staff: 5,
    evidence: 0,
};

const BOOLEANS = new Set<Key>([
    'require_complete',
    'teacher_can_mark_excused',
    'qr_enabled',
    'nfc_enabled',
]);

/** Every setting is one table row (grouped); «تحرير» edits the selected rows and saves a new policy version. */
const ROWS: Row[] = (Object.keys(GROUPS) as GroupKey[])
    .flatMap((group) => GROUPS[group].map((key) => ({ key, group })))
    .map((row, i) => ({ ...row, id: i + 1 }));

export default function AttendanceSettings(props: Props) {
    const a = t().attendanceSystem;
    const breadcrumbs: BreadcrumbItem[] = [
        { title: t().attendance.title, href: '/attendance' },
        { title: a.settings.title, href: '/attendance/settings' },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <SettingsPage {...props} />
        </AppLayout>
    );
}

function SettingsPage({ settings, filters, abilities }: Props) {
    const a = t().attendanceSystem;
    const st = a.settings;
    const c = a.chrome;
    const { showInertiaErrors } = usePageError();
    const policy = settings.policy;
    const editable = abilities.manage_policy;
    const [groupFilter, setGroupFilter] = useState<GroupKey[]>([]);
    const [edit, setEdit] = useState<{ keys: Key[]; form: Policy } | null>(
        null,
    );
    const [busy, setBusy] = useState(false);
    const rows = useMemo(
        () =>
            ROWS.filter(
                (r) =>
                    groupFilter.length === 0 || groupFilter.includes(r.group),
            ),
        [groupFilter],
    );
    const selection = useAttendanceSelection(rows, (r) => r.id);
    const groupLabel = (g: GroupKey) =>
        (st.groups as Record<GroupKey, string>)[g];

    const display = (key: Key, p: Policy): string => {
        const value = p[key];
        if (key === 'default_mark')
            return st.defaultMarks[Number(value)] ?? String(value);
        if (BOOLEANS.has(key)) return value ? st.yes : st.no;
        if (value === null || value === '') return '—';

        return String(value);
    };

    const save = () => {
        if (edit === null) return;
        const { id: _id, version_no: _v, ...payload } = edit.form;
        setBusy(true);
        router.post('/attendance/settings', payload, {
            preserveScroll: true,
            headers: { 'X-Idempotency-Key': newKey('policy') },
            onSuccess: () => {
                setEdit(null);
            },
            onError: (errors) => showInertiaErrors(errors),
            onFinish: () => setBusy(false),
        });
    };

    /** «فترة السماح للتأخير (دقائق)» → name «فترة السماح للتأخير» + unit «دقائق». */
    const splitLabel = (key: Key): { name: string; unit: string } => {
        const label = st.fields[key] ?? key;
        const match = /^(.*?)\s*\(([^)]*)\)\s*$/.exec(label);

        return match
            ? { name: match[1], unit: match[2] }
            : {
                  name: label,
                  unit: BOOLEANS.has(key)
                      ? st.yesNo
                      : key === 'staff_day_start'
                        ? st.time
                        : '—',
              };
    };
    const columns: AttendanceColumn<Row>[] = [
        {
            id: 'group',
            header: st.group,
            kind: 'status',
            tone: (r) => GROUP_STATUS_TONES[r.group],
            cell: (r) => {
                const Icon = GROUP_ICONS[r.group];

                return (
                    <span className="inline-flex items-center gap-1">
                        <Icon className="size-3.5 shrink-0" aria-hidden />
                        {groupLabel(r.group)}
                    </span>
                );
            },
            text: (r) => groupLabel(r.group),
        },
        {
            id: 'setting',
            header: st.setting,
            kind: 'name',
            cell: (r) => splitLabel(r.key).name,
        },
        {
            id: 'value',
            header: st.value,
            kind: 'num',
            cell: (r) => (
                <span
                    dir={
                        r.key === 'timezone' ||
                        r.key === 'staff_day_start' ||
                        !BOOLEANS.has(r.key)
                            ? 'ltr'
                            : undefined
                    }
                >
                    {display(r.key, policy)}
                </span>
            ),
        },
        {
            id: 'unit',
            header: st.unit,
            kind: 'text',
            cell: (r) => splitLabel(r.key).unit,
        },
    ];
    const viewer = useRowViewer(columns, (r) => st.fields[r.key] ?? r.key);
    const openEdit = (targets: Row[]) =>
        setEdit({ keys: targets.map((r) => r.key), form: { ...policy } });

    const editGroups = useMemo(
        (): PageRibbonGroup[] => [
            selectionGroup(
                'attendance-settings-selection',
                selection.targets,
                viewer.open,
                editable ? openEdit : null,
                { editTitle: editable ? undefined : st.readOnly },
            ),
            {
                id: 'attendance-settings-actions',
                label: c.actions,
                commands: [
                    {
                        id: 'attendance-settings-edit-all',
                        label: st.editAll,
                        icon: ClipboardPen,
                        iconTone: 'amber',
                        disabled: !editable,
                        title: editable ? st.editAll : st.readOnly,
                        onSelect: () => openEdit(ROWS),
                    },
                    {
                        id: 'attendance-settings-reload',
                        label: st.reload,
                        icon: RotateCcw,
                        iconTone: 'steel',
                        onSelect: () => router.reload(),
                    },
                ],
            },
            {
                id: 'attendance-settings-groups',
                label: st.groupsLabel,
                commands: (Object.keys(GROUPS) as GroupKey[]).map((g) => ({
                    id: `attendance-settings-group-${g}`,
                    label: groupLabel(g),
                    icon: GROUP_ICONS[g],
                    iconTone: GROUP_TONES[g],
                    count: GROUPS[g].length,
                    pressed: groupFilter.includes(g),
                    onSelect: () =>
                        setGroupFilter((cur) =>
                            cur.includes(g)
                                ? cur.filter((x) => x !== g)
                                : [...cur, g],
                        ),
                })),
            },
        ],
        // eslint-disable-next-line react-hooks/exhaustive-deps
        [selection.targets, editable, groupFilter],
    );
    useAttendanceEditRibbon('settings', abilities, editGroups);

    const yearSpec = useYearFilterSpec(filters.academic_year_id, (year) =>
        visitWith('/attendance/settings', { academic_year_id: year }),
    );
    useAttendanceHomeFilters(
        [
            yearSpec,
            {
                key: 'group',
                type: 'multi',
                label: st.group,
                allLabel: st.allGroups,
                values: groupFilter,
                options: (Object.keys(GROUPS) as GroupKey[]).map((g) => ({
                    value: g,
                    label: groupLabel(g),
                })),
                onChange: (values: string[]) =>
                    setGroupFilter(values as GroupKey[]),
            },
        ],
        groupFilter.length > 0 ? () => setGroupFilter([]) : null,
        [filters.academic_year_id, groupFilter, yearSpec.options.length],
    );

    useAttendanceMessages(
        [
            ...(settings.is_default
                ? [{ key: 'default', text: st.isDefault }]
                : []),
            ...(!editable ? [{ key: 'read-only', text: st.readOnly }] : []),
        ],
        st.title,
    );

    /** One field of the edit window (the control matches the setting's type). */
    const field = (key: Key, form: Policy, set: (form: Policy) => void) => {
        const label = st.fields[key] ?? key;
        if (key === 'timezone') {
            const zones = TIMEZONES.includes(form.timezone)
                ? TIMEZONES
                : [form.timezone, ...TIMEZONES];

            return (
                <RegistryListField
                    key={key}
                    label={label}
                    editing
                    required
                    value={form.timezone}
                    display={form.timezone}
                    options={zones.map((z) => ({ value: z, label: z }))}
                    onChange={(timezone) => set({ ...form, timezone })}
                />
            );
        }
        if (key === 'default_mark') {
            return (
                <RegistryListField
                    key={key}
                    label={label}
                    editing
                    required
                    value={String(form.default_mark)}
                    display={st.defaultMarks[form.default_mark]}
                    options={[0, 1].map((v) => ({
                        value: String(v),
                        label: st.defaultMarks[v],
                    }))}
                    onChange={(v) => set({ ...form, default_mark: Number(v) })}
                />
            );
        }
        if (BOOLEANS.has(key)) {
            return (
                <RegistryListField
                    key={key}
                    label={label}
                    editing
                    required
                    value={form[key] ? '1' : '0'}
                    display={form[key] ? st.yes : st.no}
                    options={[
                        { value: '1', label: st.yes },
                        { value: '0', label: st.no },
                    ]}
                    onChange={(v) => set({ ...form, [key]: v === '1' })}
                />
            );
        }
        if (key === 'staff_day_start') {
            return (
                <WindowTimeField
                    key={key}
                    label={label}
                    value={form.staff_day_start}
                    onChange={(staff_day_start) =>
                        set({ ...form, staff_day_start })
                    }
                />
            );
        }
        const value = form[key];

        return (
            <RegistryTextField
                key={key}
                label={label}
                editing
                required={key !== 'late_as_absent_minutes'}
                type="number"
                dir="ltr"
                value={value === null ? '' : String(value)}
                onChange={(v) =>
                    set({
                        ...form,
                        [key]:
                            v === ''
                                ? key === 'late_as_absent_minutes'
                                    ? null
                                    : 0
                                : Number(v),
                    })
                }
            />
        );
    };

    return (
        <AttendancePage
            title={`${st.title} — ${settings.is_default ? st.isDefaultShort : st.version.replace(':v', String(policy.version_no))}`}
        >
            <AttendanceTable
                caption={st.title}
                storageKey="settings"
                rows={rows}
                rowKey={(r) => r.id}
                columns={columns}
                emptyText="—"
                selection={selection}
                onOpen={(r) => (editable ? openEdit([r]) : viewer.open([r]))}
            />

            {viewer.element}

            {edit !== null ? (
                <AttendanceWindow
                    title={
                        edit.keys.length === ROWS.length
                            ? st.editAll
                            : `${st.title} (${edit.keys.length})`
                    }
                    columns={2}
                    pending={busy}
                    dirty={JSON.stringify(edit.form) !== JSON.stringify(policy)}
                    confirmLabel={st.save}
                    confirmDisabled={
                        JSON.stringify(edit.form) === JSON.stringify(policy)
                    }
                    onConfirm={save}
                    onClose={() => setEdit(null)}
                    sections={(Object.keys(GROUPS) as GroupKey[])
                        .filter((g) =>
                            GROUPS[g].some((k) => edit.keys.includes(k)),
                        )
                        .map((g) => ({
                            id: `att-settings-${g}`,
                            title: groupLabel(g),
                            content: (
                                <>
                                    {GROUPS[g]
                                        .filter((k) => edit.keys.includes(k))
                                        .map((k) =>
                                            field(k, edit.form, (form) =>
                                                setEdit({ ...edit, form }),
                                            ),
                                        )}
                                </>
                            ),
                        }))
                        .concat([
                            {
                                id: 'att-settings-version',
                                title: st.versionTitle,
                                content: (
                                    <WindowFact
                                        wide
                                        label={st.versionTitle}
                                        value={st.versionHint}
                                    />
                                ),
                            },
                        ])}
                />
            ) : null}
        </AttendancePage>
    );
}
