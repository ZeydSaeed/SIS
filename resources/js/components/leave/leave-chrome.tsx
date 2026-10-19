import { Head, router, usePage } from '@inertiajs/react';
import {
    Ban,
    BarChart3,
    BellRing,
    CalendarDays,
    CalendarRange,
    CalendarX2,
    ClipboardCheck,
    FileCheck2,
    FilePen,
    Hourglass,
    LayoutDashboard,
    ListChecks,
    MessageCircleQuestion,
    Paperclip,
    Percent,
    Scale,
    Settings2,
    ShieldCheck,
    Undo2,
    UserX,
    Users,
    XCircle,
    type LucideIcon,
} from 'lucide-react';
import type { ReactNode } from 'react';
import type { StatusMeta } from '@/components/attendance/attendance-ui';
import {
    DaySideFrame,
    type DayTotalRow,
} from '@/components/attendance/day-board';
import { newKey } from '@/components/attendance/attendance-ui';
import { usePageError } from '@/components/sis/page-error-context';
import { sisListPageClass } from '@/components/sis/page-theme';
import {
    useRegisterPageRibbon,
    type PageRibbonCommand,
    type PageRibbonGroup,
} from '@/components/sis/page-ribbon-context';
import { t } from '@/i18n';
import {
    useRailTitlebarToggle,
    SideStrip,
    useShellPanels,
} from '@/components/sis/shell-panels';

/** Server-computed permission hints (the server re-checks every action). */
export type LeaveAbilities = {
    view: boolean;
    request: boolean;
    manage: boolean;
    approve: boolean;
    cancel: boolean;
    policies: boolean;
    balances: boolean;
    adjust: boolean;
    sensitive: boolean;
    reports: boolean;
    assign_cover: boolean;
    student_excuses: boolean;
    holidays: boolean;
};

export type LeaveRequestRow = {
    id: number;
    request_no: number;
    academic_year_id: number;
    requester_kind: number;
    person_id: number;
    person_name: string | null;
    person_code: string | null;
    leave_type_id: number;
    policy_id: number;
    type_code: string | null;
    type_name: string | null;
    is_sensitive: boolean;
    masked: boolean;
    color_hue: number | null;
    date_from: string;
    date_to: string;
    quantity: number;
    unit: number;
    reason: string | null;
    status: number;
    approval_levels: number;
    approved_levels: number;
    created_by: number | null;
    created_by_name: string | null;
    decided_by_name: string | null;
    submitted_at: string | null;
    decided_at: string | null;
    decision_note: string | null;
    version: number;
    created_at: string;
};

export type LeavePolicy = {
    id: number;
    version_no: number;
    day_basis: number;
    requires_document: boolean;
    deducts_balance: boolean;
    approval_levels: number;
    max_per_request: number | null;
    annual_entitlement: number | null;
    effective_from: string;
    effective_to: string | null;
};

export type LeaveType = {
    id: number;
    code: string;
    name_ar: string;
    name_en: string | null;
    description: string | null;
    applies_to: number;
    unit: number;
    is_sensitive: boolean;
    color_hue: number | null;
    status: number;
    policy: LeavePolicy | null;
    history: LeavePolicy[];
};

export type StaffOption = {
    kind: number;
    id: number;
    name: string;
    own: boolean;
};

/** leave.requests.status — distinct icon per status (never colour alone) and the shared status-cell tone. */
export const LEAVE_STATUSES: StatusMeta[] = [
    { value: 1, icon: FilePen, iconTone: 'steel', tone: 0 },
    { value: 2, icon: Hourglass, iconTone: 'amber', tone: 2 },
    { value: 3, icon: MessageCircleQuestion, iconTone: 'warning', tone: 5 },
    { value: 4, icon: ShieldCheck, iconTone: 'ok', tone: 1 },
    { value: 5, icon: Ban, iconTone: 'danger', tone: 4 },
    { value: 6, icon: XCircle, iconTone: 'rose', tone: 4 },
    { value: 7, icon: Undo2, iconTone: 'steel', tone: 0 },
];

export function leaveStatusMeta(status: number): StatusMeta | null {
    return LEAVE_STATUSES.find((s) => s.value === status) ?? null;
}

export type LeaveSection =
    | 'dashboard'
    | 'requests'
    | 'balances'
    | 'types'
    | 'calendar'
    | 'reports'
    | 'holidays';

/** «أقسام الإجازات» — one icon and colour per section, on the «تحرير» tab of every leave page (as attendance). */
const SECTIONS: Array<{
    key: LeaveSection | 'students' | 'holidays';
    href: string;
    icon: typeof LayoutDashboard;
    tone: NonNullable<PageRibbonCommand['iconTone']>;
    show: (a: LeaveAbilities) => boolean;
}> = [
    {
        key: 'dashboard',
        href: '/leave',
        icon: LayoutDashboard,
        tone: 'sky',
        show: () => true,
    },
    {
        key: 'requests',
        href: '/leave/requests',
        icon: ListChecks,
        tone: 'growth',
        show: () => true,
    },
    {
        key: 'balances',
        href: '/leave/balances',
        icon: Scale,
        tone: 'forest',
        show: () => true,
    },
    {
        key: 'calendar',
        href: '/leave/calendar',
        icon: CalendarRange,
        tone: 'education',
        show: () => true,
    },
    {
        key: 'types',
        href: '/leave/types',
        icon: Settings2,
        tone: 'authority',
        show: () => true,
    },
    {
        key: 'reports',
        href: '/leave/reports',
        icon: BarChart3,
        tone: 'steel',
        show: (a) => a.reports,
    },
    {
        key: 'students',
        href: '/attendance/excuses',
        icon: FileCheck2,
        tone: 'rose',
        show: (a) => a.student_excuses,
    },
    {
        key: 'holidays',
        href: '/leave/holidays',
        icon: CalendarDays,
        tone: 'amber',
        show: () => true,
    },
];

/** «تحرير»: the page's own command groups (the leave sections live in the rail beside the page). */
export function useLeaveEditRibbon(pageGroups: PageRibbonGroup[]): void {
    useRegisterPageRibbon('edit', pageGroups);
}

export type LeaveBadges = { unread: number; awaiting: number };

/** One rail entry per leave section (what the viewer may open) — the rail's and the overview page's one list. */
export function leaveRailItems(abilities: LeaveAbilities, badges: LeaveBadges): Array<{
    key: string;
    href: string;
    label: string;
    icon: LucideIcon;
    tone: string;
    count?: number;
    show: boolean;
}> {
    const l = t().leave;

    return [
        {
            key: 'dashboard',
            href: '/leave',
            label: l.dashboard,
            icon: LayoutDashboard,
            tone: 'sky',
            show: true,
        },
        {
            key: 'requests',
            href: '/leave/requests',
            label: l.requests,
            icon: ListChecks,
            tone: 'growth',
            show: true,
        },
        {
            key: 'awaiting',
            href: '/leave/requests?awaiting=1',
            label: l.awaitingRail,
            icon: ClipboardCheck,
            tone: 'amber',
            count: badges.awaiting,
            show: abilities.approve,
        },
        {
            key: 'notifications',
            href: '/leave/notifications',
            label: l.notifications,
            icon: BellRing,
            tone: 'warning',
            count: badges.unread,
            show: true,
        },
        {
            key: 'balances',
            href: '/leave/balances',
            label: l.balances,
            icon: Scale,
            tone: 'forest',
            show: true,
        },
        {
            key: 'calendar',
            href: '/leave/calendar',
            label: l.calendar,
            icon: CalendarRange,
            tone: 'education',
            show: true,
        },
        {
            key: 'types',
            href: '/leave/types',
            label: l.types,
            icon: Settings2,
            tone: 'authority',
            show: true,
        },
        {
            key: 'reports',
            href: '/leave/reports',
            label: l.reports,
            icon: BarChart3,
            tone: 'steel',
            show: abilities.reports,
        },
        {
            key: 'students',
            href: '/attendance/excuses',
            label: l.studentLeave,
            icon: FileCheck2,
            tone: 'rose',
            show: abilities.student_excuses,
        },
        {
            key: 'holidays',
            href: '/leave/holidays',
            label: l.holidays,
            icon: CalendarDays,
            tone: 'amber',
            show: abilities.holidays,
        },
    ];
}

/** One rail entry per leave section — icon on top, short label, a count badge where something waits for the user. */
export function LeaveRail({
    current,
    abilities,
}: {
    current: LeaveSection | 'awaiting' | 'notifications';
    abilities: LeaveAbilities;
}) {
    const l = t().leave;
    const collapsed = useShellPanels().isHidden('rail');
    useRailTitlebarToggle();
    const badges = usePage<{ badges?: LeaveBadges }>().props.badges ?? {
        unread: 0,
        awaiting: 0,
    };
    const items = leaveRailItems(abilities, badges);

    return (
        <nav
            className={`sis-att-board__rail sis-rail-card sis-leave-rail${collapsed ? ' sis-att-board__rail--collapsed' : ''}`}
            aria-label={l.sections}
        >
            <>
                    <div className="sis-rail-card__list">
                        {items
                            .filter((item) => item.show)
                            .map((item) => (
                                <button
                                    key={item.key}
                                    type="button"
                                    className="sis-rail-row"
                                    title={
                                        item.key === 'students'
                                            ? l.studentLeaveHint
                                            : item.label
                                    }
                                    aria-current={
                                        item.key === current ? 'page' : undefined
                                    }
                                    onClick={() =>
                                        item.key !== current &&
                                        router.visit(item.href)
                                    }
                                >
                                    <item.icon
                                        className={`sis-att-tone sis-att-tone--${item.tone} size-4 shrink-0`}
                                        aria-hidden
                                    />
                                    <span className="sis-rail-row__label">
                                        {item.label}
                                    </span>
                                    {item.count !== undefined ? (
                                        <span
                                            className="sis-rail-row__count"
                                            dir="ltr"
                                            aria-label={`${item.label}: ${item.count}`}
                                        >
                                            {item.count > 99 ? '99+' : item.count}
                                        </span>
                                    ) : null}
                                </button>
                            ))}
                    </div>
            </>
        </nav>
    );
}

/** Page shell of every leave page: the content, and the section rail on the side (same shell as attendance). */
export function LeavePage({
    title,
    current,
    abilities,
    children,
}: {
    title: string;
    current: LeaveSection | 'awaiting' | 'notifications';
    abilities: LeaveAbilities;
    children: ReactNode;
}) {
    const panels = useShellPanels();
    const shell = usePage<{ leaveShell?: LeaveShell | null }>().props
        .leaveShell;
    const hideSide = panels.isHidden('calendar') && panels.isHidden('totals');

    return (
        <>
            <Head title={title} />
            <div
                className={sisListPageClass({ hiddenScroll: true, flush: true })}
                dir="rtl"
                lang="ar"
            >
                <div className="sis-admission-page-body">
                    <div
                        className={`sis-att-shell sis-leave-shell${panels.isHidden('rail') ? ' sis-att-shell--rail-collapsed' : ''}${shell ? '' : ' sis-leave-shell--no-side'}${shell && hideSide ? ' sis-att-shell--side-collapsed' : ''}`}
                    >
                        {shell ? (
                            <aside
                                className="sis-att-board__side"
                                aria-label={t().leave.sideLabel}
                            >
                                {hideSide ? (
                                    <SideStrip />
                                ) : (
                                    <DaySideFrame
                                        date={shell.date}
                                        today={shell.today}
                                        weekday={shell.weekday}
                                        onGo={goToDate}
                                        rows={leaveRows(shell.totals)}
                                        totalsTitle={t().shellPanels.leaveRate}
                                    />
                                )}
                            </aside>
                        ) : null}
                        <div className="sis-att-shell__main">{children}</div>
                        <LeaveRail current={current} abilities={abilities} />
                    </div>
                </div>
            </div>
        </>
    );
}

/** The leave side panel's server payload (one source: LeaveShellData). */
type LeaveShell = {
    date: string;
    today: string;
    weekday: number;
    totals: {
        staff: number;
        away: number;
        teachers_away: number;
        pending: number;
        rate: number | null;
    };
};

/** A day picked in the side calendar: the same leave page, the same filters, another `date`. */
function goToDate(date: string): void {
    const params: Record<string, string | string[]> = {};
    const query = new URLSearchParams(window.location.search);
    query.forEach((_, key) => {
        params[key] = query.getAll(key);
    });
    params.date = date;
    router.get(window.location.pathname, params, {
        preserveScroll: true,
        preserveState: true,
    });
}

/** The leave-rate card: the share of the staff on approved leave that day, and the counts behind it. */
function leaveRows(totals: LeaveShell['totals']): DayTotalRow[] {
    const text = t().shellPanels;

    return [
        {
            id: 'rate',
            label: text.leaveRate,
            value: totals.rate === null ? '—' : `${totals.rate}%`,
            icon: Percent,
            tone: 'ok',
            main: true,
        },
        {
            id: 'away',
            label: text.onLeave,
            value: totals.away,
            icon: UserX,
            tone: 'danger',
        },
        {
            id: 'teachers',
            label: text.teachersOnLeave,
            value: totals.teachers_away,
            icon: CalendarX2,
            tone: 'amber',
        },
        {
            id: 'pending',
            label: text.pending,
            value: totals.pending,
            icon: Hourglass,
            tone: 'sky',
        },
        {
            id: 'staff',
            label: text.staffTotal,
            value: totals.staff,
            icon: Users,
            tone: 'steel',
        },
    ];
}

/** «4 أيام» / «2.5 ساعة». */
export function quantityText(quantity: number, unit: number): string {
    const l = t().leave;
    const n = Number.isInteger(quantity)
        ? String(quantity)
        : quantity.toFixed(2).replace(/0$/, '');

    return `${n} ${quantity === 1 ? l.units[unit] : l.unitsPlural[unit]}`;
}

export function periodText(from: string, to: string): string {
    return from === to ? from : `${from} → ${to}`;
}

/** The type name, or «محمية» for a sensitive leave the viewer may not read. */
export function typeText(row: {
    masked?: boolean;
    type_name: string | null;
}): string {
    return row.masked || row.type_name === null
        ? t().leave.masked
        : row.type_name;
}

/** Inertia write with an idempotency key; refusals (validation or domain) open in the page's message dialog. */
export function useLeaveWrite() {
    const { showInertiaErrors } = usePageError();

    return (
        method: 'post' | 'patch',
        url: string,
        data: Record<string, unknown>,
        options: {
            onDone?: () => void;
            onFinish?: () => void;
            files?: boolean;
        } = {},
    ) =>
        router[method](url, data as never, {
            preserveScroll: true,
            preserveState: true,
            forceFormData: options.files === true,
            headers: { 'X-Idempotency-Key': newKey('leave') },
            onSuccess: (page) => {
                if (
                    !(page.props as { flash?: { error?: string | null } }).flash
                        ?.error
                ) {
                    options.onDone?.();
                }
            },
            onError: (errors) =>
                showInertiaErrors(errors, t().errors.saveFailed),
            onFinish: () => options.onFinish?.(),
        });
}

/** Ribbon status toggles (as on the attendance lists): a pressed status filters the list. */
export function statusToggleCommands(
    active: number[],
    onToggle: (value: number) => void,
    values: number[] = [1, 2, 3, 4, 5, 6, 7],
): PageRibbonCommand[] {
    const l = t().leave;

    return LEAVE_STATUSES.filter((m) => values.includes(m.value)).map((m) => ({
        id: `leave-status-${m.value}`,
        label: l.statusShort[m.value],
        title: l.statuses[m.value],
        icon: m.icon,
        iconTone: m.iconTone,
        pressed: active.includes(m.value),
        onSelect: () => onToggle(m.value),
    }));
}

export const APPROVE_ICON = ClipboardCheck;

/** A visible title line over a table (the follow-up title-line style), with an optional count. */
export function LeaveSectionTitle({
    title,
    count,
}: {
    title: string;
    count?: number;
}) {
    return (
        <div className="sis-follow-up-table__caption sis-follow-up-roster__caption">
            <span>{title}</span>
            {count === undefined ? null : (
                <span className="sis-follow-up-table__caption-count" dir="ltr">
                    {count}
                </span>
            )}
        </div>
    );
}

/** A multi-line field (registry field look) tall enough to write in. */
export function LeaveTextArea({
    label,
    value,
    onChange,
    required = false,
}: {
    label: string;
    value: string;
    onChange: (value: string) => void;
    required?: boolean;
}) {
    return (
        <label className="sis-admission-sheet__field sis-branches-field--wide">
            <span className="sis-admission-sheet__label">
                {label}
                {required ? <span aria-hidden="true"> *</span> : null}
            </span>
            <textarea
                className={`sis-admission-sheet__control sis-leave-textarea${value.trim() !== '' ? ' sis-admission-draft-field--filled' : ''}`}
                value={value}
                rows={4}
                maxLength={2000}
                required={required}
                onChange={(event) => onChange(event.target.value)}
            />
            <span className="sis-leave-counter" dir="ltr" aria-hidden>
                {value.length} / 2000
            </span>
        </label>
    );
}

/** A file field as a button + the chosen file's name (the browser's own English picker text is never shown). */
export function LeaveFilePicker({
    label,
    file,
    onChange,
    required = false,
}: {
    label: string;
    file: File | null;
    onChange: (file: File | null) => void;
    required?: boolean;
}) {
    const l = t().leave;

    return (
        <div className="sis-admission-sheet__field sis-branches-field--wide">
            <span className="sis-admission-sheet__label">
                {label}
                {required ? <span aria-hidden="true"> *</span> : null}
            </span>
            <label className="sis-leave-file">
                <input
                    className="sr-only"
                    type="file"
                    accept=".pdf,.jpg,.jpeg,.png,.webp"
                    onChange={(e) => onChange(e.target.files?.[0] ?? null)}
                />
                <span className="sis-leave-file__button">
                    <Paperclip className="size-4" aria-hidden />
                    {file === null ? l.chooseFile : l.changeFile}
                </span>
                <span className="sis-leave-file__name" dir="auto">
                    {file === null ? l.noFileChosen : file.name}
                </span>
            </label>
            <span className="sis-leave-counter">{l.attachHint}</span>
        </div>
    );
}

/** A quiet info strip (policy summary, hints): the facts side by side instead of a sentence. */
export function LeaveInfoNote({ items }: { items: Array<string | null> }) {
    const shown = items.filter((x): x is string => x !== null && x !== '');
    if (shown.length === 0) {
        return null;
    }

    return (
        <ul className="sis-leave-note sis-branches-field--wide">
            {shown.map((x) => (
                <li key={x}>{x}</li>
            ))}
        </ul>
    );
}
