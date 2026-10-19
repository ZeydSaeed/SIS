import { router, usePage } from '@inertiajs/react';
import {
    BarChart3,
    BellRing,
    CalendarClock,
    CalendarDays,
    CalendarRange,
    ChevronLeft,
    ChevronRight,
    ClipboardCheck,
    ClipboardList,
    Clock3,
    ExternalLink,
    FileCheck2,
    LayoutDashboard,
    ListChecks,
    Percent,
    PlayCircle,
    Settings,
    UserCheck,
    UserRound,
    type LucideIcon,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import { visitWith } from '@/components/attendance/attendance-chrome';
import {
    RECORD_STATUSES,
    StatusLabel,
    newKey,
    sheetLabel,
    sheetStatusMeta,
    type AttendanceAbilities,
} from '@/components/attendance/attendance-ui';
import {
    SisContextMenu,
    isContextMenuKey,
    useContextMenu,
    type ContextMenuItem,
} from '@/components/sis/context-menu';
import { usePageError } from '@/components/sis/page-error-context';
import { dayOfWeekLabel } from '@/components/sis/status-chip';
import {
    PanelHead,
    SideStrip,
    useRailTitlebarToggle,
    useShellPanels,
} from '@/components/sis/shell-panels';
import { t } from '@/i18n';
import { searchIncludes, searchNeedle } from '@/lib/search-normalize';

export type BoardLesson = {
    schedule_id: number;
    date: string;
    section_id: number;
    period_id: number;
    subject_name: string;
    teacher_name: string;
    effective_teacher_id: number;
    substitute_teacher_id: number | null;
    co_teacher_id: number | null;
    room_name: string | null;
    group_name: string | null;
    lesson_type: number;
    start_time: string;
    end_time: string;
    ended: boolean;
    session: {
        session_id: number;
        sheet_status: number;
        counts: {
            present: number;
            absent: number;
            late: number;
            excused: number;
            left_early: number;
            expected?: number;
            unmarked?: number;
        };
        submitted_at: string | null;
        submitted_late: boolean;
        review_note: string | null;
    } | null;
};

export type Board = {
    date: string;
    today: string;
    now: string;
    weekday: number;
    timezone: string;
    holiday: boolean;
    periods: Array<{
        id: number;
        period_number: number;
        start_time: string;
        end_time: string;
    }>;
    classes: Array<{
        id: number;
        name: string;
        sections: Array<{ id: number; name: string }>;
    }>;
    cells: BoardLesson[];
    waiting: BoardLesson[];
};

export type Totals = {
    attendance_rate: number | null;
    present: number;
    absent: number;
    late: number;
    excused: number;
    left_early: number;
    decided: number;
    sheets: number;
};

const HEADER_COLORS = [
    '#809BCE',
    '#95B8D1',
    '#B8E0D2',
    '#D6EADF',
    '#EAC4D5',
] as const;
const MONTH_DAYS = 42;

const recordMeta = (status: number) =>
    RECORD_STATUSES.find((s) => s.value === status);

const shiftDay = (date: string, days: number): string => {
    const d = new Date(`${date}T12:00:00Z`);
    d.setUTCDate(d.getUTCDate() + days);

    return d.toISOString().slice(0, 10);
};

/** The small month calendar of the side panel: a click opens that day's board. */
function MiniCalendar({
    date,
    today,
    onPick,
}: {
    date: string;
    today: string;
    onPick: (date: string) => void;
}) {
    const [view, setView] = useState(() => date.slice(0, 7));
    const [year, month] = view.split('-').map(Number);
    const first = new Date(Date.UTC(year, month - 1, 1, 12));
    const lead = first.getUTCDay(); // Sunday-first week
    const days = Array.from({ length: MONTH_DAYS }, (_, i) => {
        const day = new Date(Date.UTC(year, month - 1, 1 - lead + i, 12));

        return {
            iso: day.toISOString().slice(0, 10),
            n: day.getUTCDate(),
            inMonth: day.getUTCMonth() === month - 1,
        };
    });
    const weekdays = Array.from({ length: 7 }, (_, i) =>
        new Date(Date.UTC(2026, 9, 11 + i, 12)).toLocaleDateString(
            'ar-u-nu-latn',
            { weekday: 'narrow', timeZone: 'UTC' },
        ),
    );
    const step = (delta: number) => {
        const next = new Date(Date.UTC(year, month - 1 + delta, 1, 12));
        setView(next.toISOString().slice(0, 7));
    };

    return (
        <div className="sis-att-board__calendar" dir="rtl">
            <div className="sis-att-board__calendar-head">
                <button
                    type="button"
                    className="sis-att-board__nav"
                    aria-label="‹"
                    onClick={() => step(-1)}
                >
                    <ChevronRight className="size-3.5" aria-hidden />
                </button>
                <strong>
                    {first.toLocaleDateString('ar-u-nu-latn', {
                        month: 'long',
                        year: 'numeric',
                        timeZone: 'UTC',
                    })}
                </strong>
                <button
                    type="button"
                    className="sis-att-board__nav"
                    aria-label="›"
                    onClick={() => step(1)}
                >
                    <ChevronLeft className="size-3.5" aria-hidden />
                </button>
            </div>
            <div className="sis-att-board__calendar-grid" role="grid">
                {weekdays.map((w, i) => (
                    <span key={`w${i}`} className="sis-att-board__calendar-dow">
                        {w}
                    </span>
                ))}
                {days.map((d) => (
                    <button
                        key={d.iso}
                        type="button"
                        className="sis-att-board__calendar-day"
                        data-out={d.inMonth ? undefined : 'yes'}
                        data-today={d.iso === today ? 'yes' : undefined}
                        data-selected={d.iso === date ? 'yes' : undefined}
                        aria-pressed={d.iso === date}
                        onClick={() => onPick(d.iso)}
                    >
                        <span dir="ltr">{d.n}</span>
                    </button>
                ))}
            </div>
        </div>
    );
}

/** A coloured icon: the colour is the meaning (tone classes of the board stylesheet). */
function ToneIcon({ icon: Icon, tone }: { icon: LucideIcon; tone: string }) {
    return (
        <Icon
            className={`sis-att-tone sis-att-tone--${tone} size-4 shrink-0`}
            aria-hidden
        />
    );
}

/** One line of the totals card: icon + name, then the value (and its share). */
export type DayTotalRow = {
    id: string;
    label: string;
    value: string | number;
    /** Share of the day's decided marks (the card shows count · percent). */
    share?: string;
    icon: LucideIcon;
    tone: string;
    main?: boolean;
};

/**
 * The frame of the side panel — day card, previous / today / next, the foldable month calendar and the foldable totals
 * card. The attendance pages fill the totals with attendance rows; the leave pages with leave rows.
 */
export function DaySideFrame({
    date,
    today,
    weekday,
    onGo,
    rows,
    totalsTitle,
}: {
    date: string;
    today: string;
    weekday: number;
    onGo: (date: string) => void;
    rows: DayTotalRow[];
    totalsTitle?: string;
}) {
    const panels = useShellPanels();
    const showCalendar = !panels.isHidden('calendar');
    const showTotals = !panels.isHidden('totals');
    const d = t().attendanceSystem.dashboard;
    const go = onGo;
    const totalRows = rows;

    return (
        <>
            <div className="sis-att-board__day">
                <span className="sis-att-board__day-name">
                    {dayOfWeekLabel(weekday)}
                </span>
                <span className="sis-att-board__day-date" dir="ltr">
                    {date}
                </span>
            </div>
            <div className="sis-att-board__nav-row">
                <button
                    type="button"
                    className="sis-att-board__btn"
                    onClick={() => go(shiftDay(date, -1))}
                >
                    <ChevronRight className="size-3.5" aria-hidden />
                    {d.prevDay}
                </button>
                <button
                    type="button"
                    className="sis-att-board__btn sis-att-board__btn--primary"
                    disabled={date === today}
                    onClick={() => go(today)}
                >
                    <CalendarDays className="size-3.5" aria-hidden />
                    {d.today}
                </button>
                <button
                    type="button"
                    className="sis-att-board__btn"
                    onClick={() => go(shiftDay(date, 1))}
                >
                    {d.nextDay}
                    <ChevronLeft className="size-3.5" aria-hidden />
                </button>
            </div>

            <PanelHead panel="calendar" />
            {showCalendar ? (
                <MiniCalendar
                    key={date.slice(0, 7)}
                    date={date}
                    today={today}
                    onPick={go}
                />
            ) : null}

            <PanelHead panel="totals" totalsTitle={totalsTitle} />
            {showTotals ? (
                <dl className="sis-att-board__totals">
                    {totalRows.map((row) => (
                        <div
                            key={row.id}
                            className={`sis-att-board__total${row.main ? ' sis-att-board__total--main' : ''}`}
                        >
                            <dt>
                                <ToneIcon icon={row.icon} tone={row.tone} />
                                {row.label}
                            </dt>
                            <dd dir="ltr">
                                {row.value}
                                {row.share !== undefined ? (
                                    <span className="sis-att-board__share">
                                        {' '}
                                        · {row.share}
                                    </span>
                                ) : null}
                            </dd>
                        </div>
                    ))}
                </dl>
            ) : null}
        </>
    );
}

/**
 * The side panel of the day: the weekday + date, previous / today / next, the month calendar and the day's totals
 * card (attendance rate + each status as count · share of the decided marks). Used by «لوحة الحضور» and «حصصي اليوم».
 */
export function DaySidePanel({
    date,
    today,
    weekday,
    now,
    totals,
    onGo,
}: {
    date: string;
    today: string;
    weekday: number;
    now: string;
    totals: Totals;
    onGo: (date: string) => void;
}) {
    const a = t().attendanceSystem;
    const d = a.dashboard;
    const b = d.board;
    const pct =
        totals.attendance_rate === null ? '—' : `${totals.attendance_rate}%`;
    /** «n٪» of the marks decided that day (— when nothing was marked). */
    const share = (count: number): string =>
        totals.decided > 0
            ? `${Math.round((count * 1000) / totals.decided) / 10}%`
            : '—';
    const totalRows: DayTotalRow[] = [
        {
            id: 'rate',
            label: d.attendanceRate,
            value: pct,
            icon: Percent,
            tone: 'ok',
            main: true,
        },
        {
            id: 'present',
            label: a.sheet.present,
            value: totals.present,
            share: share(totals.present),
            icon: recordMeta(1)?.icon ?? Percent,
            tone: recordMeta(1)?.iconTone ?? 'ok',
        },
        {
            id: 'absent',
            label: a.sheet.absent,
            value: totals.absent,
            share: share(totals.absent),
            icon: recordMeta(2)?.icon ?? Percent,
            tone: recordMeta(2)?.iconTone ?? 'danger',
        },
        {
            id: 'late',
            label: a.sheet.late,
            value: totals.late,
            share: share(totals.late),
            icon: recordMeta(3)?.icon ?? Percent,
            tone: recordMeta(3)?.iconTone ?? 'amber',
        },
        {
            id: 'excused',
            label: a.sheet.excused,
            value: totals.excused,
            share: share(totals.excused),
            icon: recordMeta(4)?.icon ?? Percent,
            tone: recordMeta(4)?.iconTone ?? 'sky',
        },
        {
            id: 'left_early',
            label: a.sheet.leftEarly,
            value: totals.left_early,
            share: share(totals.left_early),
            icon: recordMeta(5)?.icon ?? Percent,
            tone: recordMeta(5)?.iconTone ?? 'rose',
        },
        {
            id: 'sheets',
            label: d.sheets,
            value: totals.sheets,
            icon: ClipboardList,
            tone: 'steel',
        },
        {
            id: 'now',
            label: b.now,
            value: now,
            icon: Clock3,
            tone: 'education',
        },
    ];

    return (
        <DaySideFrame
            date={date}
            today={today}
            weekday={weekday}
            onGo={onGo}
            rows={totalRows}
        />
    );
}

/** The number shown on a rail icon (see AttendanceShellData::railCounts): every section has one. */
export function railCount(href: string, counts: Record<string, number>): number {
    const key: Record<string, string> = {
        '/attendance/my': 'my',
        '/attendance': 'sessions',
        '/attendance/review': 'review',
        '/attendance/dashboard': 'dashboard',
        '/attendance/staff': 'staff',
        '/attendance/excuses': 'excuses',
        '/attendance/reports': 'reports',
        '/attendance/alerts': 'alerts',
        '/attendance/settings': 'settings',
    };

    return counts[key[href] ?? ''] ?? 0;
}

/** The attendance sections of the quick-link rail (what the viewer may open) — the rail's and the overview page's one list. */
export function attendanceRailItems(abilities: AttendanceAbilities) {
    const a = t().attendanceSystem;

    return [
        {
            href: '/attendance/my',
            label: a.nav.myDay,
            icon: CalendarClock,
            tone: 'education',
            show: true,
        },
        {
            href: '/attendance',
            label: a.nav.sessions,
            icon: ListChecks,
            tone: 'sky',
            show: true,
        },
        {
            href: '/attendance/review',
            label: a.nav.review,
            icon: ClipboardCheck,
            tone: 'amber',
            show: abilities.review_sheet,
        },
        {
            href: '/attendance/dashboard',
            label: a.nav.dashboard,
            icon: LayoutDashboard,
            tone: 'ok',
            show: abilities.view_reports,
        },
        {
            href: '/attendance/staff',
            label: a.nav.staff,
            icon: UserCheck,
            tone: 'forest',
            show: true,
        },
        {
            href: '/attendance/excuses',
            label: a.nav.excuses,
            icon: FileCheck2,
            tone: 'rose',
            show: abilities.submit_excuse || abilities.review_excuse,
        },
        {
            href: '/attendance/reports',
            label: a.nav.reports,
            icon: BarChart3,
            tone: 'steel',
            show: abilities.view_reports,
        },
        {
            href: '/attendance/alerts',
            label: a.nav.alerts,
            icon: BellRing,
            tone: 'warning',
            show: abilities.manage_alerts,
        },
        {
            href: '/attendance/settings',
            label: a.nav.settings,
            icon: Settings,
            tone: 'authority',
            show: true,
        },
    ].filter((item) => item.show);
}

/** The quick-link rail (my day · review · excuses · alerts · staff · reports): the same on every attendance page. */
export function AttendanceRail({
    abilities,
    date = null,
}: {
    abilities: AttendanceAbilities;
    /** The day chosen in the side calendar: each page opens on that day. */
    date?: string | null;
}) {
    const a = t().attendanceSystem;
    const collapsed = useShellPanels().isHidden('rail');
    useRailTitlebarToggle();
    const page = usePage<{
        attendanceShell?: { counts?: Record<string, number> } | null;
    }>();
    const path = page.url.split('?')[0];
    const counts = page.props.attendanceShell?.counts ?? {};
    const rail = attendanceRailItems(abilities);
    // The selected item is the one whose address is the longest match of the current page.
    const current = rail
        .filter((item) => path === item.href || path.startsWith(`${item.href}/`))
        .sort((left, right) => right.href.length - left.href.length)[0]?.href;

    return (
        <nav
            className={`sis-att-board__rail sis-rail-card${collapsed ? ' sis-att-board__rail--collapsed' : ''}`}
            aria-label={a.nav.label}
        >
            <>
                    <div className="sis-rail-card__list">
                        {rail.map((item) => (
                            <button
                                key={item.href}
                                type="button"
                                className="sis-rail-row"
                                onClick={() =>
                                    router.visit(
                                        item.href,
                                        date
                                            ? {
                                                  data: {
                                                      date,
                                                      date_from: date,
                                                      date_to: date,
                                                  },
                                              }
                                            : {},
                                    )
                                }
                                title={`${item.label}: ${railCount(item.href, counts)}`}
                                aria-current={
                                    item.href === current ? 'page' : undefined
                                }
                            >
                                <ToneIcon icon={item.icon} tone={item.tone} />
                                <span className="sis-rail-row__label">
                                    {item.label}
                                </span>
                                <span
                                    className="sis-rail-row__count"
                                    dir="ltr"
                                    aria-label={`${item.label}: ${railCount(item.href, counts)}`}
                                >
                                    {railCount(item.href, counts) > 999
                                        ? '999+'
                                        : railCount(item.href, counts)}
                                </span>
                            </button>
                        ))}
                    </div>
            </>
        </nav>
    );
}

/**
 * «لوحة الحضور»: the day as a schedule board (periods down, sections across) — the layout of an appointment book.
 * A lesson is a coloured block (colour = sheet state); hover shows its card, click opens (or opens-then-marks) it,
 * right-click opens its menu. Side panel: the day, a month calendar, the day's totals and the lessons still waiting.
 */
export function DayBoard({
    board,
    abilities,
    yearId,
    scope,
    totals,
    classIds,
    statusIds,
    query = '',
}: {
    board: Board;
    abilities: AttendanceAbilities;
    yearId: number;
    scope: 'school' | 'central';
    totals: Totals;
    classIds: number[];
    statusIds: number[];
    /** Titlebar search: lessons that do not match (subject, teacher, room, group) are dimmed. */
    query?: string;
}) {
    const a = t().attendanceSystem;
    const d = a.dashboard;
    const b = d.board;
    const { showInertiaErrors } = usePageError();
    const panels = useShellPanels();
    const hideSide =
        panels.isHidden('calendar') && panels.isHidden('totals');
    const menu = useContextMenu<BoardLesson>();
    const [hover, setHover] = useState<{
        lesson: BoardLesson;
        x: number;
        y: number;
    } | null>(null);
    const [busy, setBusy] = useState(false);

    const go = (date: string) =>
        visitWith('/attendance/dashboard', {
            date,
            academic_year_id: yearId,
            scope: scope === 'central' ? 'central' : null,
        });

    const columns = useMemo(
        () =>
            board.classes
                .filter(
                    (cls) => classIds.length === 0 || classIds.includes(cls.id),
                )
                .flatMap((cls, ci) =>
                    cls.sections.map((section) => ({
                        id: section.id,
                        name: section.name,
                        className: cls.name,
                        color: HEADER_COLORS[ci % HEADER_COLORS.length],
                    })),
                ),
        [board.classes, classIds],
    );
    const periods = useMemo(() => {
        // Only the periods that have a lesson that day (breaks and idle bells are not rows); all of them when none has.
        const used = new Set(board.cells.map((c) => c.period_id));
        const list = board.periods.filter(
            (p) => used.size === 0 || used.has(p.id),
        );

        return list.sort(
            (x, y) =>
                x.start_time.localeCompare(y.start_time) ||
                x.period_number - y.period_number,
        );
    }, [board.periods, board.cells]);
    const bySlot = useMemo(() => {
        const map = new Map<string, BoardLesson[]>();
        board.cells.forEach((lesson) => {
            const key = `${lesson.section_id}:${lesson.period_id}`;
            map.set(key, [...(map.get(key) ?? []), lesson]);
        });

        return map;
    }, [board.cells]);

    const canOpen =
        (abilities.submit_sheet || abilities.review_sheet) && !board.holiday;
    const open = (lesson: BoardLesson) => {
        if (lesson.session !== null) {
            router.visit(`/attendance/sheets/${lesson.session.session_id}`);

            return;
        }
        if (!canOpen || busy) {
            return;
        }
        setBusy(true);
        router.post(
            '/attendance/lessons/open',
            { schedule_id: lesson.schedule_id, date: lesson.date },
            {
                headers: { 'X-Idempotency-Key': newKey('open') },
                onError: (e) => showInertiaErrors(e),
                onFinish: () => setBusy(false),
            },
        );
    };

    /** The right-click menu of a lesson (also Shift+F10): open it, then everything related to it. */
    const menuItems = (lesson: BoardLesson): ContextMenuItem[] => {
        const range = `date_from=${lesson.date}&date_to=${lesson.date}`;

        return [
            {
                id: 'open',
                label: lesson.session ? b.menu.openSheet : b.menu.openLesson,
                icon: lesson.session ? ExternalLink : PlayCircle,
                disabled: lesson.session === null && !canOpen,
                onSelect: () => open(lesson),
            },
            ...(abilities.review_sheet
                ? [
                      {
                          id: 'teacher-day',
                          label: b.menu.teacherDay,
                          icon: UserRound,
                          separator: true,
                          onSelect: () =>
                              router.visit(
                                  `/attendance/my?date=${lesson.date}&teacher_id=${lesson.effective_teacher_id}`,
                              ),
                      },
                  ]
                : []),
            {
                id: 'timetable',
                label: b.menu.teacherTimetable,
                icon: CalendarRange,
                separator: !abilities.review_sheet,
                onSelect: () =>
                    router.visit(
                        `/timetable?view=teacher&teacher_ids=${lesson.effective_teacher_id}`,
                    ),
            },
            ...(abilities.review_sheet
                ? [
                      {
                          id: 'section-sheets',
                          label: b.menu.sectionSheets,
                          icon: ListChecks,
                          separator: true,
                          onSelect: () =>
                              router.visit(
                                  `/attendance/review?section_id[]=${lesson.section_id}&${range}&sheet_status=`,
                              ),
                      },
                  ]
                : []),
            ...(abilities.view_reports
                ? [
                      {
                          id: 'section-report',
                          label: b.menu.sectionReport,
                          icon: BarChart3,
                          onSelect: () =>
                              router.visit(
                                  `/attendance/reports?type=students&section_id[]=${lesson.section_id}&${range}`,
                              ),
                      },
                  ]
                : []),
        ];
    };

    const statusOf = (lesson: BoardLesson): number =>
        lesson.session?.sheet_status ?? 0;
    const needle = searchNeedle(query);
    const dimmed = (lesson: BoardLesson) =>
        (statusIds.length > 0 && !statusIds.includes(statusOf(lesson))) ||
        (needle !== '' &&
            ![
                lesson.subject_name,
                lesson.teacher_name,
                lesson.room_name,
                lesson.group_name,
                lesson.start_time,
            ].some((v) => searchIncludes(v ?? '', needle)));


    return (
        <div
            className={`sis-att-board${panels.isHidden('rail') ? ' sis-att-board--rail-collapsed' : ''}${hideSide ? ' sis-att-board--side-collapsed' : ''}`}
            dir="rtl"
            lang="ar"
        >
            <aside className="sis-att-board__side" aria-label={b.side}>
                {hideSide ? (
                    <SideStrip />
                ) : (
                    <>
                <DaySidePanel
                    date={board.date}
                    today={board.today}
                    weekday={board.weekday}
                    now={board.now}
                    totals={totals}
                    onGo={go}
                />

                <section
                    className="sis-att-board__waiting"
                    aria-label={b.waiting}
                >
                    <h2>
                        {b.waiting}{' '}
                        <span dir="ltr">({board.waiting.length})</span>
                    </h2>
                    <ul>
                        {board.waiting.map((lesson) => {
                            const meta = sheetStatusMeta(
                                lesson.session?.sheet_status,
                            );

                            return (
                                <li
                                    key={lesson.schedule_id}
                                    data-status={statusOf(lesson)}
                                >
                                    <button
                                        type="button"
                                        onClick={() => open(lesson)}
                                        onContextMenu={(event) =>
                                            menu.open(event, lesson)
                                        }
                                        onKeyDown={(event) => {
                                            if (isContextMenuKey(event)) {
                                                event.preventDefault();
                                                menu.openAt(
                                                    event.currentTarget,
                                                    lesson,
                                                );
                                            }
                                        }}
                                        title={`${lesson.subject_name} — ${lesson.teacher_name}`}
                                    >
                                        <span
                                            dir="ltr"
                                            className="sis-att-board__waiting-time"
                                        >
                                            {lesson.start_time}
                                        </span>
                                        <span className="sis-att-board__waiting-text">
                                            {lesson.subject_name}
                                        </span>
                                        <span className="sis-att-board__waiting-state">
                                            {meta ? (
                                                <ToneIcon
                                                    icon={meta.icon}
                                                    tone={meta.iconTone}
                                                />
                                            ) : (
                                                <ToneIcon
                                                    icon={PlayCircle}
                                                    tone="steel"
                                                />
                                            )}
                                            {lesson.session
                                                ? sheetLabel(
                                                      lesson.session
                                                          .sheet_status,
                                                  )
                                                : b.notOpened}
                                        </span>
                                    </button>
                                </li>
                            );
                        })}
                    </ul>
                </section>
                    </>
                )}
            </aside>

            <div className="sis-att-board__center">
            <section
                className="sis-att-board__main"
                aria-label={d.title}
                data-allow-x-scroll
            >
                <div
                    className="sis-att-board__grid"
                    role="grid"
                    style={{
                        gridTemplateColumns: `4.6rem repeat(${Math.max(1, columns.length)}, minmax(8.6rem, 1fr))`,
                    }}
                    onMouseLeave={() => setHover(null)}
                >
                    <div className="sis-att-board__corner" role="columnheader">
                        {b.time}
                    </div>
                    {columns.map((column) => (
                        <div
                            key={column.id}
                            className="sis-att-board__column"
                            role="columnheader"
                            style={{ backgroundColor: column.color }}
                        >
                            <span>{column.className}</span>
                            <strong>{column.name}</strong>
                        </div>
                    ))}

                    {periods.map((period) => (
                        <div
                            key={period.id}
                            className="sis-att-board__row"
                            role="row"
                            style={{ display: 'contents' }}
                        >
                            <div
                                className="sis-att-board__time"
                                role="rowheader"
                            >
                                <strong dir="ltr">
                                    {period.start_time.slice(0, 5)}
                                </strong>
                                <small dir="ltr">
                                    {period.end_time.slice(0, 5)}
                                </small>
                                <em>
                                    {b.period} {period.period_number}
                                </em>
                            </div>
                            {columns.map((column) => {
                                const lessons =
                                    bySlot.get(`${column.id}:${period.id}`) ??
                                    [];

                                return (
                                    <div
                                        key={column.id}
                                        className="sis-att-board__slot"
                                        role="gridcell"
                                        data-empty={
                                            lessons.length === 0
                                                ? 'yes'
                                                : undefined
                                        }
                                    >
                                        {lessons.map((lesson) => {
                                            const meta = sheetStatusMeta(
                                                lesson.session?.sheet_status,
                                            );
                                            const c = lesson.session?.counts;
                                            const counts = c
                                                ? (
                                                      [
                                                          [1, c.present],
                                                          [2, c.absent],
                                                          [3, c.late],
                                                          [4, c.excused],
                                                          [5, c.left_early],
                                                      ] as Array<
                                                          [number, number]
                                                      >
                                                  ).filter(
                                                      ([status, n]) =>
                                                          status <= 3 || n > 0,
                                                  )
                                                : [];

                                            return (
                                                <button
                                                    key={lesson.schedule_id}
                                                    type="button"
                                                    className="sis-att-board__lesson"
                                                    data-status={statusOf(
                                                        lesson,
                                                    )}
                                                    data-dim={
                                                        dimmed(lesson)
                                                            ? 'yes'
                                                            : undefined
                                                    }
                                                    data-late={
                                                        lesson.ended &&
                                                        (lesson.session ===
                                                            null ||
                                                            lesson.session
                                                                .sheet_status ===
                                                                1)
                                                            ? 'yes'
                                                            : undefined
                                                    }
                                                    onClick={() => open(lesson)}
                                                    onContextMenu={(event) => {
                                                        setHover(null);
                                                        menu.open(
                                                            event,
                                                            lesson,
                                                        );
                                                    }}
                                                    onKeyDown={(event) => {
                                                        if (
                                                            isContextMenuKey(
                                                                event,
                                                            )
                                                        ) {
                                                            event.preventDefault();
                                                            menu.openAt(
                                                                event.currentTarget,
                                                                lesson,
                                                            );
                                                        }
                                                    }}
                                                    onMouseMove={(event) =>
                                                        setHover({
                                                            lesson,
                                                            x: event.clientX,
                                                            y: event.clientY,
                                                        })
                                                    }
                                                    onMouseLeave={() =>
                                                        setHover(null)
                                                    }
                                                    onFocus={(event) => {
                                                        const r =
                                                            event.currentTarget.getBoundingClientRect();
                                                        setHover({
                                                            lesson,
                                                            x: r.left,
                                                            y: r.bottom,
                                                        });
                                                    }}
                                                    onBlur={() =>
                                                        setHover(null)
                                                    }
                                                >
                                                    <span className="sis-att-board__lesson-title">
                                                        {lesson.subject_name}
                                                    </span>
                                                    <span className="sis-att-board__lesson-sub">
                                                        {lesson.teacher_name}
                                                        {lesson.group_name
                                                            ? ` · ${lesson.group_name}`
                                                            : ''}
                                                    </span>
                                                    <span className="sis-att-board__lesson-state">
                                                        {lesson.session ===
                                                        null ? (
                                                            <>
                                                                <PlayCircle
                                                                    className="size-3.5 shrink-0"
                                                                    aria-hidden
                                                                />
                                                                {b.notOpened}
                                                            </>
                                                        ) : (
                                                            <StatusLabel
                                                                meta={meta}
                                                                label={sheetLabel(
                                                                    lesson
                                                                        .session
                                                                        .sheet_status,
                                                                )}
                                                            />
                                                        )}
                                                    </span>
                                                    {counts.length > 0 ? (
                                                        <span
                                                            className="sis-att-board__lesson-counts"
                                                            dir="ltr"
                                                        >
                                                            {counts.map(
                                                                ([
                                                                    status,
                                                                    n,
                                                                ]) => {
                                                                    const rm =
                                                                        recordMeta(
                                                                            status,
                                                                        );
                                                                    const Icon =
                                                                        rm?.icon ??
                                                                        Percent;

                                                                    return (
                                                                        <span
                                                                            key={
                                                                                status
                                                                            }
                                                                            className={`sis-att-tone sis-att-tone--${rm?.iconTone ?? 'steel'}`}
                                                                            title={
                                                                                a
                                                                                    .recordStatus[
                                                                                    status
                                                                                ]
                                                                            }
                                                                        >
                                                                            <Icon
                                                                                className="size-3 shrink-0"
                                                                                aria-hidden
                                                                            />
                                                                            {n}
                                                                        </span>
                                                                    );
                                                                },
                                                            )}
                                                        </span>
                                                    ) : null}
                                                </button>
                                            );
                                        })}
                                    </div>
                                );
                            })}
                        </div>
                    ))}
                </div>
            </section>
            </div>

            <AttendanceRail
                abilities={abilities}
                date={board.date === board.today ? null : board.date}
            />

            <SisContextMenu
                controller={menu}
                items={menuItems}
                label={b.menu.label}
            />

            {hover !== null && menu.state === null ? (
                <div
                    className="sis-att-board__hover"
                    style={{
                        left: Math.min(hover.x + 14, window.innerWidth - 300),
                        top: Math.min(hover.y + 14, window.innerHeight - 200),
                    }}
                    role="tooltip"
                    dir="rtl"
                >
                    <strong>{hover.lesson.subject_name}</strong>
                    <span>
                        {b.teacher}: {hover.lesson.teacher_name}
                    </span>
                    <span dir="ltr">
                        {hover.lesson.start_time} – {hover.lesson.end_time}
                    </span>
                    <span>
                        {a.myDay.type}: {a.lessonType[hover.lesson.lesson_type]}
                        {hover.lesson.room_name
                            ? ` · ${hover.lesson.room_name}`
                            : ''}
                    </span>
                    <span>
                        {b.status}:{' '}
                        {hover.lesson.session
                            ? sheetLabel(hover.lesson.session.sheet_status)
                            : b.notOpened}
                    </span>
                    {hover.lesson.session ? (
                        <span dir="ltr">
                            {a.sheet.present}{' '}
                            {hover.lesson.session.counts.present} ·{' '}
                            {a.sheet.absent}{' '}
                            {hover.lesson.session.counts.absent} ·{' '}
                            {a.sheet.late} {hover.lesson.session.counts.late}
                        </span>
                    ) : null}
                    {hover.lesson.session?.review_note ? (
                        <span>{hover.lesson.session.review_note}</span>
                    ) : null}
                </div>
            ) : null}
        </div>
    );
}
