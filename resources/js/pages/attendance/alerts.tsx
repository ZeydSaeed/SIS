import { router } from '@inertiajs/react';
import { Ban, CheckCheck, Eye, Radar, ShieldCheck } from 'lucide-react';
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
    useYearFilterSpec,
    visitWith,
    type AttendanceColumn,
} from '@/components/attendance/attendance-chrome';
import {
    ALERT_STATUSES,
    StatusLabel,
    dateTime,
    newKey,
    statusCommands,
    toggleIn,
    type AttendanceAbilities,
} from '@/components/attendance/attendance-ui';
import type { ContextMenuItem } from '@/components/sis/context-menu';
import { usePageError } from '@/components/sis/page-error-context';
import type { PageRibbonGroup } from '@/components/sis/page-ribbon-context';
import { t } from '@/i18n';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

type Alert = {
    id: number;
    rule_code: string;
    severity: number;
    subject_type: string;
    subject_id: number;
    metric: string | number;
    threshold: string | number;
    details: Record<string, unknown> | null;
    status: number;
    resolution_note: string | null;
    created_at: string;
    period_start: string;
    period_end: string;
    student_name: string | null;
    student_code: string | null;
    teacher_name: string | null;
    section_name: string | null;
    class_name: string | null;
};

type Filters = {
    status: number[];
    rule_code: string[];
    severity: number[];
    page: number;
    academic_year_id: number;
};

type Props = {
    alerts: {
        data: Alert[];
        meta: {
            page: number;
            per_page?: number;
            last_page: number;
            total: number;
        };
        open: number;
        rules: string[];
    };
    filters: Filters;
    abilities: AttendanceAbilities;
};

/** Severity → status-cell tone: low neutral · medium amber · high red. */
const SEVERITY_TONES: Record<number, number> = { 1: 0, 2: 2, 3: 4 };

export default function AttendanceAlerts(props: Props) {
    const a = t().attendanceSystem;
    const breadcrumbs: BreadcrumbItem[] = [
        { title: t().attendance.title, href: '/attendance' },
        { title: a.alerts.title, href: '/attendance/alerts' },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <AlertsPage {...props} />
        </AppLayout>
    );
}

function AlertsPage({ alerts, filters, abilities }: Props) {
    const a = t().attendanceSystem;
    const al = a.alerts;
    const c = a.chrome;
    const { showInertiaErrors } = usePageError();
    const [selectedId, setSelectedId] = useState<number | null>(null);
    const [action, setAction] = useState<{
        alert: Alert;
        status: 2 | 3 | 4;
        note: string;
    } | null>(null);
    const [viewing, setViewing] = useState<Alert | null>(null);
    const [busy, setBusy] = useState(false);
    const selected = alerts.data.find((x) => x.id === selectedId) ?? null;
    const active =
        selected !== null && (selected.status === 1 || selected.status === 2);

    const visit = (next: Partial<Filters>) => {
        const merged = { ...filters, ...next, page: next.page ?? 1 };
        visitWith('/attendance/alerts', {
            ...merged,
            status: merged.status.length === 0 ? [''] : merged.status,
        });
    };

    const subject = (x: Alert) => {
        if (x.subject_type === 'student')
            return `${x.student_name ?? x.subject_id} (${x.student_code ?? ''})`;
        if (x.subject_type === 'teacher')
            return x.teacher_name ?? String(x.subject_id);
        if (x.subject_type === 'section')
            return `${x.class_name ?? ''} / ${x.section_name ?? x.subject_id}`;

        return `${x.subject_type} #${x.subject_id}`;
    };

    const update = () => {
        if (action === null) return;
        setBusy(true);
        router.post(
            `/attendance/alerts/${action.alert.id}`,
            { alert_status: action.status, note: action.note.trim() || null },
            {
                preserveScroll: true,
                headers: { 'X-Idempotency-Key': newKey('alert') },
                onSuccess: () => setAction(null),
                onError: (e) => showInertiaErrors(e),
                onFinish: () => setBusy(false),
            },
        );
    };

    const editGroups = useMemo(
        (): PageRibbonGroup[] => [
            {
                id: 'attendance-alerts-actions',
                label: c.actions,
                commands: [
                    {
                        id: 'attendance-alerts-view',
                        label: c.view,
                        icon: Eye,
                        iconTone: 'sky',
                        disabled: selected === null,
                        title: selected === null ? c.needsSelection : undefined,
                        onSelect: () => selected && setViewing(selected),
                    },
                    {
                        id: 'attendance-alerts-ack',
                        label: al.acknowledge,
                        icon: CheckCheck,
                        iconTone: 'amber',
                        disabled: selected?.status !== 1,
                        onSelect: () =>
                            selected &&
                            setAction({ alert: selected, status: 2, note: '' }),
                    },
                    {
                        id: 'attendance-alerts-resolve',
                        label: al.resolve,
                        icon: ShieldCheck,
                        iconTone: 'ok',
                        disabled: !active,
                        onSelect: () =>
                            selected &&
                            setAction({ alert: selected, status: 3, note: '' }),
                    },
                    {
                        id: 'attendance-alerts-dismiss',
                        label: al.dismiss,
                        icon: Ban,
                        iconTone: 'danger',
                        disabled: !active,
                        onSelect: () =>
                            selected &&
                            setAction({ alert: selected, status: 4, note: '' }),
                    },
                    {
                        id: 'attendance-alerts-detect',
                        label: al.detect,
                        icon: Radar,
                        iconTone: 'authority',
                        onSelect: () =>
                            router.post(
                                '/attendance/alerts/detect',
                                {},
                                {
                                    preserveScroll: true,
                                    headers: {
                                        'X-Idempotency-Key':
                                            newKey('alert-detect'),
                                    },
                                    onError: (e) => showInertiaErrors(e),
                                },
                            ),
                    },
                ],
            },
            {
                id: 'attendance-alerts-status',
                label: c.statuses,
                commands: statusCommands(
                    'attendance-alerts-status',
                    ALERT_STATUSES,
                    al.statuses,
                    { 1: alerts.open },
                    filters.status,
                    (v) => visit({ status: toggleIn(filters.status, v) }),
                ).map((cmd) =>
                    cmd.id.endsWith('-1') ? cmd : { ...cmd, count: undefined },
                ),
            },
        ],
        // eslint-disable-next-line react-hooks/exhaustive-deps
        [selected, active, filters, alerts.open],
    );
    useAttendanceEditRibbon('alerts', abilities, editGroups);

    const yearSpec = useYearFilterSpec(filters.academic_year_id, (year) =>
        visitWith('/attendance/alerts', { academic_year_id: year }),
    );
    useAttendanceHomeFilters(
        [
            yearSpec,
            {
                key: 'rule_code',
                type: 'multi',
                label: al.rule,
                allLabel: c.allRules,
                values: filters.rule_code,
                options: alerts.rules.map((r) => ({
                    value: r,
                    label: al.rules[r] ?? r,
                })),
                onChange: (rule_code: string[]) => visit({ rule_code }),
            },
            {
                key: 'severity',
                type: 'multi',
                label: al.severity,
                allLabel: c.allSeverities,
                values: filters.severity.map(String),
                options: [3, 2, 1].map((v) => ({
                    value: String(v),
                    label: al.severities[v],
                })),
                onChange: (values: string[]) =>
                    visit({ severity: values.map(Number) }),
            },
        ],
        () =>
            visitWith('/attendance/alerts', {
                academic_year_id: filters.academic_year_id,
            }),
        [filters, alerts.rules, yearSpec.options.length],
    );

    const columns: AttendanceColumn<Alert>[] = [
        {
            id: 'rule',
            header: al.rule,
            kind: 'name',
            cell: (x) => al.rules[x.rule_code] ?? x.rule_code,
        },
        { id: 'subject', header: al.subject, kind: 'text', cell: subject },
        {
            id: 'metric',
            header: `${al.metric} / ${al.threshold}`,
            kind: 'num',
            cell: (x) => (
                <span dir="ltr">{`${Number(x.metric)} / ${Number(x.threshold)}`}</span>
            ),
        },
        {
            id: 'severity',
            header: al.severity,
            kind: 'status',
            tone: (x) => SEVERITY_TONES[x.severity] ?? 0,
            cell: (x) => al.severities[x.severity] ?? x.severity,
        },
        {
            id: 'period',
            header: al.period,
            kind: 'text',
            cell: (x) => (
                <span dir="ltr">{`${x.period_start} → ${x.period_end}`}</span>
            ),
        },
        {
            id: 'status',
            header: al.status,
            kind: 'status',
            tone: (x) =>
                ALERT_STATUSES.find((s) => s.value === x.status)?.tone ?? 0,
            cell: (x) => (
                <StatusLabel
                    meta={
                        ALERT_STATUSES.find((s) => s.value === x.status) ?? null
                    }
                    label={al.statuses[x.status]}
                />
            ),
        },
        {
            id: 'created',
            header: c.when,
            kind: 'text',
            cell: (x) => dateTime(x.created_at),
        },
    ];

    const menuItems = (x: Alert): ContextMenuItem[] => [
        { id: 'view', label: c.view, icon: Eye, onSelect: () => setViewing(x) },
        ...(x.status === 1
            ? [
                  {
                      id: 'ack',
                      label: al.acknowledge,
                      icon: CheckCheck,
                      separator: true,
                      onSelect: () =>
                          setAction({ alert: x, status: 2, note: '' }),
                  },
              ]
            : []),
        ...(x.status === 1 || x.status === 2
            ? [
                  {
                      id: 'resolve',
                      label: al.resolve,
                      icon: ShieldCheck,
                      onSelect: () =>
                          setAction({ alert: x, status: 3, note: '' }),
                  },
                  {
                      id: 'dismiss',
                      label: al.dismiss,
                      icon: Ban,
                      danger: true,
                      onSelect: () =>
                          setAction({ alert: x, status: 4, note: '' }),
                  },
              ]
            : []),
    ];

    const actionLabel = (status: 2 | 3 | 4) =>
        status === 2 ? al.acknowledge : status === 3 ? al.resolve : al.dismiss;

    return (
        <AttendancePage
            title={al.title}
            notices={
                <PageNotice>{`${al.description} ${al.disclaimer}`}</PageNotice>
            }
        >
            <AttendanceTable
                caption={al.title}
                rows={alerts.data}
                rowKey={(x) => x.id}
                columns={columns}
                emptyText={al.empty}
                selectedId={selectedId}
                onSelect={(x) => setSelectedId(x.id)}
                onOpen={setViewing}
                menuItems={menuItems}
                page={alerts.meta.page}
                lastPage={alerts.meta.last_page}
                onPage={(page) => visit({ page })}
                numberOffset={
                    (alerts.meta.page - 1) * (alerts.meta.per_page ?? 25)
                }
            />

            {action !== null ? (
                <AttendanceWindow
                    title={actionLabel(action.status)}
                    pending={busy}
                    danger={action.status === 4}
                    dirty={action.note !== ''}
                    confirmLabel={actionLabel(action.status)}
                    confirmDisabled={
                        action.status === 4 && action.note.trim() === ''
                    }
                    onConfirm={update}
                    onClose={() => setAction(null)}
                    sections={[
                        {
                            id: 'att-alert-action',
                            title: `${al.rules[action.alert.rule_code] ?? action.alert.rule_code} — ${subject(action.alert)}`,
                            content: (
                                <>
                                    <WindowFact
                                        label={`${al.metric} / ${al.threshold}`}
                                        value={
                                            <span dir="ltr">{`${Number(action.alert.metric)} / ${Number(action.alert.threshold)}`}</span>
                                        }
                                    />
                                    <WindowFact
                                        label={al.severity}
                                        value={
                                            al.severities[action.alert.severity]
                                        }
                                    />
                                    <WindowTextArea
                                        label={
                                            action.status === 4
                                                ? al.dismissNote
                                                : al.note
                                        }
                                        required={action.status === 4}
                                        value={action.note}
                                        onChange={(note) =>
                                            setAction({ ...action, note })
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
                    title={al.rules[viewing.rule_code] ?? viewing.rule_code}
                    onClose={() => setViewing(null)}
                    sections={[
                        {
                            id: 'att-alert-view',
                            title: subject(viewing),
                            content: (
                                <>
                                    <WindowFact
                                        label={`${al.metric} / ${al.threshold}`}
                                        value={
                                            <span dir="ltr">{`${Number(viewing.metric)} / ${Number(viewing.threshold)}`}</span>
                                        }
                                    />
                                    <WindowFact
                                        label={al.severity}
                                        value={al.severities[viewing.severity]}
                                    />
                                    <WindowFact
                                        label={al.period}
                                        value={
                                            <span dir="ltr">{`${viewing.period_start} → ${viewing.period_end}`}</span>
                                        }
                                    />
                                    <WindowFact
                                        label={al.status}
                                        value={al.statuses[viewing.status]}
                                    />
                                    <WindowFact
                                        label={c.when}
                                        value={dateTime(viewing.created_at)}
                                    />
                                    <WindowFact
                                        wide
                                        label={al.note}
                                        value={viewing.resolution_note ?? '—'}
                                    />
                                    {viewing.details ? (
                                        <WindowFact
                                            wide
                                            label={al.details}
                                            value={
                                                <span
                                                    dir="ltr"
                                                    className="text-xs"
                                                >
                                                    {Object.entries(
                                                        viewing.details,
                                                    )
                                                        .map(
                                                            ([key, value]) =>
                                                                `${key}: ${typeof value === 'object' ? JSON.stringify(value) : String(value)}`,
                                                        )
                                                        .join(' · ')}
                                                </span>
                                            }
                                        />
                                    ) : null}
                                    <WindowFact
                                        wide
                                        label={al.disclaimerTitle}
                                        value={al.disclaimer}
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
