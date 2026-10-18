import { router } from '@inertiajs/react';
import {
    Ban,
    BarChart3,
    CalendarDays,
    CalendarRange,
    ClipboardCheck,
    FileCheck2,
    FilePen,
    Hourglass,
    LayoutDashboard,
    ListChecks,
    MessageCircleQuestion,
    Scale,
    Settings2,
    ShieldCheck,
    Undo2,
    XCircle,
} from 'lucide-react';
import { useMemo } from 'react';
import type { StatusMeta } from '@/components/attendance/attendance-ui';
import { newKey } from '@/components/attendance/attendance-ui';
import { usePageError } from '@/components/sis/page-error-context';
import {
    useRegisterPageRibbon,
    type PageRibbonCommand,
    type PageRibbonGroup,
} from '@/components/sis/page-ribbon-context';
import { t } from '@/i18n';

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
    | 'reports';

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
        href: '/holidays',
        icon: CalendarDays,
        tone: 'amber',
        show: () => true,
    },
];

/** «تحرير»: the page's own groups, then the leave sections (students' leave and holidays open their own pages). */
export function useLeaveEditRibbon(
    current: LeaveSection,
    abilities: LeaveAbilities,
    pageGroups: PageRibbonGroup[],
): void {
    const groups = useMemo((): PageRibbonGroup[] => {
        const l = t().leave;
        const label: Record<string, string> = {
            dashboard: l.dashboard,
            requests: l.requests,
            balances: l.balances,
            calendar: l.calendar,
            types: l.types,
            reports: l.reports,
            students: l.studentLeave,
            holidays: l.holidays,
        };

        return [
            ...pageGroups,
            {
                id: 'leave-sections',
                label: l.sections,
                commands: SECTIONS.filter((s) => s.show(abilities)).map(
                    (s): PageRibbonCommand => ({
                        id: `leave-section-${s.key}`,
                        label: label[s.key],
                        icon: s.icon,
                        iconTone: s.tone,
                        pressed: s.key === current,
                        title:
                            s.key === 'students'
                                ? l.studentLeaveHint
                                : undefined,
                        onSelect: () =>
                            s.key === current
                                ? undefined
                                : router.visit(s.href),
                    }),
                ),
            },
        ];
    }, [abilities, current, pageGroups]);
    useRegisterPageRibbon('edit', groups);
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
        label: l.statuses[m.value],
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
