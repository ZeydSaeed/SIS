import { router } from '@inertiajs/react';
import {
    CalendarDays,
    CalendarRange,
    ChevronLeft,
    ChevronRight,
    Columns3,
    GraduationCap,
    List,
    Square,
    Users,
    Umbrella,
} from 'lucide-react';
import { useMemo, useState, type CSSProperties } from 'react';
import {
    AttendanceTable,
    AttendanceWindow,
    WindowFact,
    useAttendanceHomeFilters,
    useYearFilterSpec,
    visitWith,
} from '@/components/attendance/attendance-chrome';
import {
    periodText,
    quantityText,
    typeText,
    LeavePage,
    useLeaveEditRibbon,
    type LeaveAbilities,
    type LeaveRequestRow,
    type LeaveType,
} from '@/components/leave/leave-chrome';
import type { PageRibbonGroup } from '@/components/sis/page-ribbon-context';
import type { FilterSpec } from '@/components/sis/ribbon-filter';
import { Button } from '@/components/ui/button';
import { t } from '@/i18n';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

type Holiday = {
    id: number;
    name: string;
    start_date: string;
    end_date: string;
    holiday_type: number;
    global: boolean;
};
type StudentExcuse = {
    id: number;
    full_name: string;
    student_code: string;
    date_from: string;
    date_to: string;
    excuse_type: number;
    status: number;
};
type View = 'month' | 'week' | 'day' | 'list';
type Layer = 'holidays' | 'staff' | 'students';

type Props = {
    calendar: {
        from: string;
        to: string;
        working_days: number[];
        holidays: Holiday[];
        leave: LeaveRequestRow[];
        students: StudentExcuse[];
    };
    types: LeaveType[];
    filters: {
        academic_year_id: number;
        kind: number[];
        type: number[];
        state: number[];
        month: string;
        view: View;
        layers: Layer[];
    };
    abilities: LeaveAbilities;
};

type CalEvent = {
    key: string;
    from: string;
    to: string;
    label: string;
    hue: number;
    pending: boolean;
    kind: 'holiday' | 'leave' | 'student';
    leave?: LeaveRequestRow;
    holiday?: Holiday;
    student?: StudentExcuse;
};

const PATH = '/leave/calendar';
const HOLIDAY_HUE = 30;
const STUDENT_HUE = 320;
const MAX_PER_DAY = 4;

const iso = (d: Date) =>
    `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
const parse = (s: string) => {
    const [y, m, d] = s.split('-').map(Number);

    return new Date(y, m - 1, d);
};
const addDays = (s: string, n: number) => {
    const d = parse(s);
    d.setDate(d.getDate() + n);

    return iso(d);
};

export default function LeaveCalendar(props: Props) {
    const l = t().leave;
    const breadcrumbs: BreadcrumbItem[] = [
        { title: l.title, href: '/leave' },
        { title: l.calendar, href: PATH },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <CalendarPage {...props} />
        </AppLayout>
    );
}

function CalendarPage({ calendar, types, filters, abilities }: Props) {
    const l = t().leave;
    const today = iso(new Date());
    const monthStart = `${filters.month}-01`;
    const [view, setView] = useState<View>(filters.view);
    const [focus, setFocus] = useState<string>(
        today >= calendar.from &&
            today <= calendar.to &&
            today.startsWith(filters.month)
            ? today
            : monthStart,
    );
    const [open, setOpen] = useState<CalEvent | null>(null);
    const typeHue = useMemo(
        () => new Map(types.map((x) => [x.id, x.color_hue ?? 210])),
        [types],
    );

    const visit = (next: Record<string, unknown>) =>
        visitWith(PATH, {
            ...filters,
            view,
            ...next,
            layer: (next.layer as Layer[] | undefined) ?? filters.layers,
        });
    const shiftMonth = (n: number) => {
        const d = parse(monthStart);
        d.setMonth(d.getMonth() + n);
        visit({ month: iso(d).slice(0, 7) });
    };
    const toggleLayer = (layer: Layer) =>
        visit({
            layer: filters.layers.includes(layer)
                ? filters.layers.filter((x) => x !== layer)
                : [...filters.layers, layer],
        });

    const events: CalEvent[] = useMemo(
        () => [
            ...calendar.holidays.map((h): CalEvent => ({
                key: `h${h.id}`,
                from: h.start_date,
                to: h.end_date,
                label: `${l.holiday}: ${h.name}`,
                hue: HOLIDAY_HUE,
                pending: false,
                kind: 'holiday',
                holiday: h,
            })),
            ...calendar.leave.map((r): CalEvent => ({
                key: `l${r.id}`,
                from: r.date_from,
                to: r.date_to,
                label: `${r.person_name ?? '—'} · ${typeText(r)}`,
                hue: r.masked ? 210 : (typeHue.get(r.leave_type_id) ?? 210),
                pending: r.status !== 4,
                kind: 'leave',
                leave: r,
            })),
            ...calendar.students.map((x): CalEvent => ({
                key: `s${x.id}`,
                from: x.date_from,
                to: x.date_to,
                label: `${l.student}: ${x.full_name}`,
                hue: STUDENT_HUE,
                pending: x.status !== 2,
                kind: 'student',
                student: x,
            })),
        ],
        // eslint-disable-next-line react-hooks/exhaustive-deps
        [calendar, typeHue],
    );
    const on = (day: string) =>
        events.filter((e) => e.from <= day && e.to >= day);

    const rawGroups = useMemo(
        (): PageRibbonGroup[] => [
            {
                id: 'leave-calendar-view',
                label: l.calendar,
                commands: [
                    {
                        id: 'leave-cal-prev',
                        label: l.previous,
                        icon: ChevronRight,
                        iconTone: 'steel',
                        onSelect: () => shiftMonth(-1),
                    },
                    {
                        id: 'leave-cal-today',
                        label: l.today,
                        icon: CalendarDays,
                        iconTone: 'sky',
                        onSelect: () => {
                            setFocus(today);
                            visit({ month: today.slice(0, 7) });
                        },
                    },
                    {
                        id: 'leave-cal-next',
                        label: l.next,
                        icon: ChevronLeft,
                        iconTone: 'steel',
                        onSelect: () => shiftMonth(1),
                    },
                    ...(['month', 'week', 'day', 'list'] as View[]).map(
                        (v) => ({
                            id: `leave-cal-${v}`,
                            label: l.calendarViews[v],
                            icon:
                                v === 'month'
                                    ? CalendarRange
                                    : v === 'week'
                                      ? Columns3
                                      : v === 'day'
                                        ? Square
                                        : List,
                            iconTone: 'education' as const,
                            pressed: view === v,
                            onSelect: () => setView(v),
                        }),
                    ),
                ],
            },
            {
                id: 'leave-calendar-layers',
                label: l.layers,
                commands: [
                    {
                        id: 'leave-layer-holidays',
                        label: l.layerHolidays,
                        icon: Umbrella,
                        iconTone: 'amber',
                        pressed: filters.layers.includes('holidays'),
                        onSelect: () => toggleLayer('holidays'),
                    },
                    {
                        id: 'leave-layer-staff',
                        label: l.layerStaff,
                        icon: Users,
                        iconTone: 'growth',
                        pressed: filters.layers.includes('staff'),
                        onSelect: () => toggleLayer('staff'),
                    },
                    ...(abilities.student_excuses
                        ? [
                              {
                                  id: 'leave-layer-students',
                                  label: l.layerStudents,
                                  icon: GraduationCap,
                                  iconTone: 'rose' as const,
                                  pressed: filters.layers.includes('students'),
                                  onSelect: () => toggleLayer('students'),
                              },
                          ]
                        : []),
                ],
            },
        ],
        // eslint-disable-next-line react-hooks/exhaustive-deps
        [view, filters, abilities],
    );
    // Navigation (3) and the four views are two groups: one row each.
    const groups = useMemo(
        (): PageRibbonGroup[] => [
            {
                id: 'leave-calendar-nav',
                label: l.calendarNav,
                commands: rawGroups[0].commands.slice(0, 3),
            },
            {
                id: 'leave-calendar-mode',
                label: l.calendarMode,
                commands: rawGroups[0].commands.slice(3),
            },
            rawGroups[1],
        ],
        [rawGroups, l.calendarNav, l.calendarMode],
    );
    useLeaveEditRibbon(groups);
    const yearSpec = useYearFilterSpec(filters.academic_year_id, (year) =>
        visitWith(PATH, { academic_year_id: year }),
    );
    useAttendanceHomeFilters(
        [
            yearSpec,
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
        () =>
            visitWith(PATH, {
                academic_year_id: filters.academic_year_id,
                month: filters.month,
            }),
        [filters, types, yearSpec.options.length],
    );

    const chip = (e: CalEvent) => (
        <button
            key={e.key}
            type="button"
            className="sis-leave-cal__event"
            data-pending={e.pending ? 'yes' : undefined}
            style={{ '--leave-hue': e.hue } as CSSProperties}
            title={`${e.label} (${periodText(e.from, e.to)})`}
            onClick={() => setOpen(e)}
        >
            {e.label}
        </button>
    );
    const dayCell = (day: string, many: boolean) => {
        const list = on(day);
        const weekday = parse(day).getDay() + 1;

        return (
            <div
                key={day}
                className="sis-leave-cal__day"
                role="gridcell"
                aria-label={`${day}: ${list.length}`}
                data-off={
                    calendar.working_days.includes(weekday) ? undefined : 'yes'
                }
                data-out={day.startsWith(filters.month) ? undefined : 'yes'}
                data-today={day === today ? 'yes' : undefined}
            >
                <button
                    type="button"
                    className="sis-leave-cal__num"
                    onClick={() => {
                        setFocus(day);
                        setView('day');
                    }}
                >
                    <span dir="ltr">{parse(day).getDate()}</span>
                </button>
                {(many ? list : list.slice(0, MAX_PER_DAY)).map(chip)}
                {!many && list.length > MAX_PER_DAY ? (
                    <span className="sis-leave-cal__more">
                        +{list.length - MAX_PER_DAY}
                    </span>
                ) : null}
            </div>
        );
    };

    const days: string[] = [];
    for (let d = calendar.from; d <= calendar.to; d = addDays(d, 1))
        days.push(d);
    const weekStart = addDays(focus, -parse(focus).getDay());
    const weekDays = Array.from({ length: 7 }, (_, i) => addDays(weekStart, i));
    const title = parse(monthStart).toLocaleDateString('ar-u-nu-latn', {
        month: 'long',
        year: 'numeric',
    });
    const sorted = [...events].sort(
        (a, b) =>
            a.from.localeCompare(b.from) ||
            a.label.localeCompare(b.label, 'ar'),
    );

    return (
        <LeavePage
            title={l.calendar}
            current={'calendar'}
            abilities={abilities}
        >
            <div className="sis-leave-cal__legend" aria-hidden>
                <span
                    className="sis-leave-cal__event"
                    style={{ '--leave-hue': 140 } as CSSProperties}
                >
                    {l.legendApproved}
                </span>
                <span
                    className="sis-leave-cal__event"
                    data-pending="yes"
                    style={{ '--leave-hue': 140 } as CSSProperties}
                >
                    {l.legendPending}
                </span>
                <span
                    className="sis-leave-cal__event"
                    style={{ '--leave-hue': HOLIDAY_HUE } as CSSProperties}
                >
                    {l.legendHoliday}
                </span>
                {abilities.student_excuses ? (
                    <span
                        className="sis-leave-cal__event"
                        style={{ '--leave-hue': STUDENT_HUE } as CSSProperties}
                    >
                        {l.legendStudent}
                    </span>
                ) : null}
            </div>

            {view === 'month' || view === 'week' ? (
                <div
                    className={`sis-leave-cal${view === 'week' ? ' sis-leave-cal--week' : ''}`}
                    role="grid"
                    aria-label={title}
                >
                    {l.weekdays.map((w) => (
                        <span
                            key={w}
                            className="sis-leave-cal__dow"
                            role="columnheader"
                        >
                            {w}
                        </span>
                    ))}
                    {(view === 'month' ? days : weekDays).map((d) =>
                        dayCell(d, view === 'week'),
                    )}
                </div>
            ) : null}

            {view === 'week' ? (
                <div className="sis-admission-sheet__actions">
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() => setFocus(addDays(focus, -7))}
                    >
                        {l.previous}
                    </Button>
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() => setFocus(addDays(focus, 7))}
                    >
                        {l.next}
                    </Button>
                </div>
            ) : null}

            {view === 'day' || view === 'list' ? (
                <AttendanceTable
                    caption={view === 'day' ? focus : title}
                    storageKey="leave.calendar.list"
                    rows={(view === 'day' ? on(focus) : sorted).map((e, i) => ({
                        ...e,
                        id: i + 1,
                    }))}
                    rowKey={(e) => e.id}
                    fit={view === 'list'}
                    emptyText={l.noEvents}
                    onOpen={(e) => setOpen(e)}
                    columns={[
                        {
                            id: 'period',
                            header: l.period,
                            kind: 'text',
                            cell: (e) => (
                                <span dir="ltr">
                                    {periodText(e.from, e.to)}
                                </span>
                            ),
                        },
                        {
                            id: 'label',
                            header: l.type,
                            kind: 'name',
                            cell: (e) => e.label,
                        },
                        {
                            id: 'state',
                            header: l.status,
                            kind: 'text',
                            cell: (e) =>
                                e.kind === 'holiday'
                                    ? l.holiday
                                    : e.kind === 'student'
                                      ? l.studentStatuses[
                                            e.student?.status ?? 1
                                        ]
                                      : l.statuses[e.leave?.status ?? 2],
                        },
                    ]}
                />
            ) : null}

            {view === 'day' ? (
                <div className="sis-admission-sheet__actions">
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() => setFocus(addDays(focus, -1))}
                    >
                        {l.previous}
                    </Button>
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() => setFocus(addDays(focus, 1))}
                    >
                        {l.next}
                    </Button>
                </div>
            ) : null}

            {open !== null ? (
                <AttendanceWindow
                    fit
                    title={open.label}
                    columns={2}
                    onClose={() => setOpen(null)}
                    footer={
                        open.leave ? (
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() =>
                                    router.visit('/leave/requests', {
                                        data: {
                                            q: String(
                                                open.leave?.request_no ?? '',
                                            ),
                                            academic_year_id:
                                                filters.academic_year_id,
                                        },
                                    })
                                }
                            >
                                {l.requests}
                            </Button>
                        ) : open.student ? (
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() =>
                                    router.visit('/attendance/excuses')
                                }
                            >
                                {l.studentLeave}
                            </Button>
                        ) : null
                    }
                    sections={[
                        {
                            id: 'leave-cal-event',
                            title: periodText(open.from, open.to),
                            content: open.leave ? (
                                <>
                                    <WindowFact
                                        label={l.person}
                                        value={`${open.leave.person_name ?? '—'} — ${l.kinds[open.leave.requester_kind]}`}
                                        wide
                                    />
                                    <WindowFact
                                        label={l.type}
                                        value={typeText(open.leave)}
                                    />
                                    <WindowFact
                                        label={l.status}
                                        value={l.statuses[open.leave.status]}
                                    />
                                    <WindowFact
                                        label={l.quantity}
                                        value={quantityText(
                                            open.leave.quantity,
                                            open.leave.unit,
                                        )}
                                    />
                                    <WindowFact
                                        label={l.requestNo}
                                        value={`#${open.leave.request_no}`}
                                    />
                                    {open.leave.masked ? (
                                        <WindowFact
                                            wide
                                            label={l.reason}
                                            value={l.maskedHint}
                                        />
                                    ) : null}
                                </>
                            ) : open.holiday ? (
                                <>
                                    <WindowFact
                                        label={l.holiday}
                                        value={open.holiday.name}
                                        wide
                                    />
                                    <WindowFact
                                        label={l.reportScope}
                                        value={
                                            open.holiday.global
                                                ? l.reportGlobal
                                                : l.reportSchool
                                        }
                                    />
                                </>
                            ) : (
                                <>
                                    <WindowFact
                                        label={l.student}
                                        value={`${open.student?.full_name ?? ''} (${open.student?.student_code ?? ''})`}
                                        wide
                                    />
                                    <WindowFact
                                        label={l.status}
                                        value={
                                            l.studentStatuses[
                                                open.student?.status ?? 1
                                            ]
                                        }
                                    />
                                </>
                            ),
                        },
                    ]}
                />
            ) : null}
        </LeavePage>
    );
}
