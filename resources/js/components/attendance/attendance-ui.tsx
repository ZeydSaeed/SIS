import {
    Ban,
    BriefcaseBusiness,
    CheckCircle2,
    CircleDashed,
    CircleHelp,
    Clock,
    FileCheck2,
    FilePen,
    Hourglass,
    LogOut,
    Plane,
    Send,
    ShieldCheck,
    Undo2,
    UserX,
    XCircle,
    type LucideIcon,
} from 'lucide-react';
import type { PageRibbonCommand } from '@/components/sis/page-ribbon-context';
import { t } from '@/i18n';

/** Server-computed permission hints (the server re-checks every action). */
export type AttendanceAbilities = {
    submit_sheet: boolean;
    review_sheet: boolean;
    correct: boolean;
    submit_excuse: boolean;
    review_excuse: boolean;
    record_staff: boolean;
    staff_checkin: boolean;
    view_reports: boolean;
    manage_alerts: boolean;
    manage_policy: boolean;
    link_account?: boolean;
};

export type StatusCounts = {
    expected?: number;
    present: number;
    absent: number;
    late: number;
    excused: number;
    left_early: number;
    unmarked?: number;
    marked?: number;
};

/**
 * A status vocabulary entry: distinct icon shape (never colour alone), ribbon icon colour, and the 0..5 tone of the
 * shared status-cell palette (`sis-students-table__status[data-status]`).
 */
export type StatusMeta = {
    value: number;
    icon: LucideIcon;
    iconTone: NonNullable<PageRibbonCommand['iconTone']>;
    tone: number;
};

/** attendance.records.status — 0 unmarked · 1 present · 2 absent · 3 late · 4 excused · 5 left early. */
export const RECORD_STATUSES: StatusMeta[] = [
    { value: 1, icon: CheckCircle2, iconTone: 'ok', tone: 1 },
    { value: 2, icon: XCircle, iconTone: 'danger', tone: 4 },
    { value: 3, icon: Clock, iconTone: 'amber', tone: 2 },
    { value: 4, icon: FileCheck2, iconTone: 'sky', tone: 3 },
    { value: 5, icon: LogOut, iconTone: 'rose', tone: 5 },
];
export const UNMARKED: StatusMeta = {
    value: 0,
    icon: CircleDashed,
    iconTone: 'steel',
    tone: 0,
};

export function recordStatusMeta(status: number): StatusMeta {
    return RECORD_STATUSES.find((s) => s.value === status) ?? UNMARKED;
}

/** attendance.sessions.sheet_status — 1 draft · 2 submitted · 3 under review · 4 approved · 5 returned · 6 rejected. */
export const SHEET_STATUSES: StatusMeta[] = [
    { value: 1, icon: FilePen, iconTone: 'steel', tone: 0 },
    { value: 2, icon: Send, iconTone: 'sky', tone: 2 },
    { value: 3, icon: Hourglass, iconTone: 'amber', tone: 2 },
    { value: 4, icon: ShieldCheck, iconTone: 'ok', tone: 1 },
    { value: 5, icon: Undo2, iconTone: 'warning', tone: 5 },
    { value: 6, icon: Ban, iconTone: 'danger', tone: 4 },
];

export function sheetStatusMeta(
    status: number | null | undefined,
): StatusMeta | null {
    return status === null || status === undefined
        ? null
        : (SHEET_STATUSES.find((s) => s.value === status) ?? null);
}

/** attendance.staff_records.status (1..7); 0 = not recorded yet. */
export const STAFF_STATUSES: StatusMeta[] = [
    { value: 1, icon: CheckCircle2, iconTone: 'ok', tone: 1 },
    { value: 2, icon: UserX, iconTone: 'danger', tone: 4 },
    { value: 3, icon: Clock, iconTone: 'amber', tone: 2 },
    { value: 4, icon: LogOut, iconTone: 'rose', tone: 5 },
    { value: 5, icon: Plane, iconTone: 'sky', tone: 3 },
    { value: 6, icon: BriefcaseBusiness, iconTone: 'authority', tone: 3 },
    { value: 7, icon: FileCheck2, iconTone: 'education', tone: 3 },
];
export const STAFF_NOT_RECORDED: StatusMeta = {
    value: 0,
    icon: CircleHelp,
    iconTone: 'steel',
    tone: 0,
};

/** attendance.excuses.status — 1 pending · 2 approved · 3 rejected · 4 withdrawn. */
export const EXCUSE_STATUSES: StatusMeta[] = [
    { value: 1, icon: Hourglass, iconTone: 'amber', tone: 2 },
    { value: 2, icon: ShieldCheck, iconTone: 'ok', tone: 1 },
    { value: 3, icon: Ban, iconTone: 'danger', tone: 4 },
    { value: 4, icon: Undo2, iconTone: 'steel', tone: 0 },
];

/** attendance.alerts.status — 1 open · 2 acknowledged · 3 resolved · 4 dismissed. */
export const ALERT_STATUSES: StatusMeta[] = [
    { value: 1, icon: CircleHelp, iconTone: 'danger', tone: 4 },
    { value: 2, icon: Hourglass, iconTone: 'amber', tone: 2 },
    { value: 3, icon: ShieldCheck, iconTone: 'ok', tone: 1 },
    { value: 4, icon: Ban, iconTone: 'steel', tone: 0 },
];

/** Icon + text (inside a status cell, which carries the colour). */
export function StatusLabel({
    meta,
    label,
}: {
    meta: StatusMeta | null;
    label: string;
}) {
    const Icon = meta?.icon ?? CircleDashed;

    return (
        <span className="inline-flex items-center gap-1">
            <Icon className="size-3.5 shrink-0" aria-hidden />
            {label}
        </span>
    );
}

export function recordLabel(status: number): string {
    return t().attendanceSystem.recordStatus[status] ?? String(status);
}

export function sheetLabel(status: number | null | undefined): string {
    const a = t().attendanceSystem;

    return status === null || status === undefined
        ? a.noSheet
        : (a.sheetStatus[status] ?? String(status));
}

export function countsText(counts: StatusCounts | null | undefined): string {
    if (!counts) {
        return '—';
    }
    const s = t().attendanceSystem.sheet;

    return `${s.present} ${counts.present} · ${s.absent} ${counts.absent} · ${s.late} ${counts.late}${counts.excused ? ` · ${s.excused} ${counts.excused}` : ''}${counts.left_early ? ` · ${s.leftEarly} ${counts.left_early}` : ''}`;
}

/** «08:10» from an ISO timestamp (display only). */
export function clock(iso: string | null | undefined): string {
    if (!iso) {
        return '—';
    }
    const date = new Date(iso);

    return Number.isNaN(date.getTime())
        ? '—'
        : date.toLocaleTimeString('ar', {
              hour: '2-digit',
              minute: '2-digit',
              hour12: false,
              numberingSystem: 'latn',
          });
}

export function dateTime(iso: string | null | undefined): string {
    if (!iso) {
        return '—';
    }
    const date = new Date(iso);

    return Number.isNaN(date.getTime())
        ? '—'
        : date.toLocaleString('ar', {
              year: 'numeric',
              month: '2-digit',
              day: '2-digit',
              hour: '2-digit',
              minute: '2-digit',
              hour12: false,
              numberingSystem: 'latn',
          });
}

export function newKey(prefix: string): string {
    return typeof crypto !== 'undefined' && 'randomUUID' in crypto
        ? `${prefix}-${crypto.randomUUID()}`
        : `${prefix}-${Date.now()}-${Math.random().toString(16).slice(2)}`;
}

/** Ribbon status tabs (count + toggle), as on «الغرف الدراسية». */
export function statusCommands(
    prefix: string,
    metas: StatusMeta[],
    labels: Record<number, string>,
    counts: Record<number, number>,
    active: number[],
    onToggle: (value: number) => void,
): PageRibbonCommand[] {
    return metas.map((m) => ({
        id: `${prefix}-${m.value}`,
        label: labels[m.value] ?? String(m.value),
        icon: m.icon,
        iconTone: m.iconTone,
        count: counts[m.value] ?? 0,
        pressed: active.includes(m.value),
        onSelect: () => onToggle(m.value),
    }));
}

export function toggleIn(list: number[], value: number): number[] {
    return list.includes(value)
        ? list.filter((v) => v !== value)
        : [...list, value];
}
