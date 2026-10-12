import { router } from '@inertiajs/react';
import {
    CalendarDays,
    CalendarPlus,
    ChevronLeft,
    ChevronRight,
    DoorOpen,
    Link2,
    MessageSquareWarning,
    PlayCircle,
} from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import {
    AttendancePage,
    AttendanceTable,
    AttendanceWindow,
    WindowFact,
    WindowTextArea,
    selectionGroup,
    useAttendanceEditRibbon,
    useAttendanceHomeFilters,
    useAttendanceMessages,
    useAttendanceSelection,
    useRowViewer,
    useYearFilterSpec,
    visitWith,
    type AttendanceColumn,
    type AttendanceMessage,
} from '@/components/attendance/attendance-chrome';
import {
    StatusLabel,
    countsText,
    dateTime,
    newKey,
    sheetLabel,
    sheetStatusMeta,
    type AttendanceAbilities,
    type StatusCounts,
} from '@/components/attendance/attendance-ui';
import { RegistryListField } from '@/components/organization/registry-sheet';
import type { ContextMenuItem } from '@/components/sis/context-menu';
import { usePageError } from '@/components/sis/page-error-context';
import type { PageRibbonGroup } from '@/components/sis/page-ribbon-context';
import { dayOfWeekLabel } from '@/components/sis/status-chip';
import { t } from '@/i18n';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

type LessonSession = {
    session_id: number;
    sheet_status: number;
    status: number;
    version: number;
    counts: StatusCounts;
    submitted_at: string | null;
    review_note: string | null;
};

type Lesson = {
    schedule_id: number;
    date: string;
    period_number: number;
    start_time: string;
    end_time: string;
    subject_name: string;
    class_name: string;
    section_name: string;
    room_name: string | null;
    group_name: string | null;
    lesson_type: number;
    substitute_teacher_id: number | null;
    co_teacher_id: number | null;
    window: string;
    phase: 'upcoming' | 'running' | 'ended';
    minutes_to_start: number;
    minutes_to_end: number;
    session: LessonSession | null;
};

type AdminNotice = {
    session_id: number;
    sheet_status: number;
    review_note: string | null;
    reviewed_at: string | null;
    subject_name: string;
    section_name: string;
    class_name: string;
    session_date: string;
};

type Lookups = {
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
    subjects: Array<{ id: number; name: string }>;
    teachers: Array<{ id: number; full_name: string }>;
};

type Props = {
    day: {
        date: string;
        today: string;
        now: string;
        weekday: number;
        timezone: string;
        holiday: boolean;
        teacher: { id: number; full_name: string } | null;
        lessons: Lesson[];
        notices: AdminNotice[];
    };
    filters: { academic_year_id: number; teacher_id: number | null };
    teachers: Array<{ id: number; full_name: string }>;
    lookups: Lookups | null;
    abilities: AttendanceAbilities;
    linked: boolean;
    account_email: string | null;
};

type Exceptional = {
    section_id: string;
    subject_id: string;
    teacher_id: string;
    period_id: string;
    reason: string;
};

function shiftDate(date: string, days: number): string {
    const d = new Date(`${date}T12:00:00Z`);
    d.setUTCDate(d.getUTCDate() + days);

    return d.toISOString().slice(0, 10);
}

export default function AttendanceMyDay(props: Props) {
    const a = t().attendanceSystem;
    const breadcrumbs: BreadcrumbItem[] = [
        { title: t().attendance.title, href: '/attendance' },
        { title: a.myDay.title, href: '/attendance/my' },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <MyDayPage {...props} />
        </AppLayout>
    );
}

function MyDayPage({
    day,
    filters,
    teachers,
    lookups,
    abilities,
    linked,
    account_email,
}: Props) {
    const a = t().attendanceSystem;
    const m = a.myDay;
    const c = a.chrome;
    const { showInertiaErrors, showWarning } = usePageError();
    const [busy, setBusy] = useState(false);
    const [exceptional, setExceptional] = useState<Exceptional | null>(null);
    const [notesOpen, setNotesOpen] = useState(false);
    const [linkTeacher, setLinkTeacher] = useState<string | null>(null);
    const canWork =
        abilities.submit_sheet &&
        (linked || abilities.review_sheet) &&
        !day.holiday;
    const selection = useAttendanceSelection(day.lessons, (l) => l.schedule_id);
    const single = selection.targets.length === 1 ? selection.targets[0] : null;
    const canLink =
        !linked &&
        abilities.link_account === true &&
        account_email !== null &&
        teachers.length > 0;

    const go = (params: Record<string, unknown>) =>
        visitWith('/attendance/my', {
            date: day.date,
            teacher_id: filters.teacher_id,
            academic_year_id: filters.academic_year_id,
            ...params,
        });

    const blocked = (l: Lesson) =>
        l.session === null &&
        (l.window === 'not_yet_open' || l.window === 'closed') &&
        !abilities.review_sheet;
    const openLesson = (l: Lesson) => {
        if (l.session !== null) {
            router.visit(`/attendance/sheets/${l.session.session_id}`);

            return;
        }
        if (!canWork || blocked(l)) {
            return;
        }
        setBusy(true);
        router.post(
            '/attendance/lessons/open',
            { schedule_id: l.schedule_id, date: l.date },
            {
                headers: { 'X-Idempotency-Key': newKey('open') },
                onError: (errors) => showInertiaErrors(errors),
                onFinish: () => setBusy(false),
            },
        );
    };

    const saveExceptional = () => {
        if (exceptional === null) return;
        setBusy(true);
        router.post(
            '/attendance/lessons/exceptional',
            {
                date: day.date,
                section_id: Number(exceptional.section_id),
                subject_id: Number(exceptional.subject_id),
                teacher_id: Number(exceptional.teacher_id),
                period_id: Number(exceptional.period_id),
                reason: exceptional.reason.trim(),
            },
            {
                headers: { 'X-Idempotency-Key': newKey('exceptional') },
                onError: (errors) => showInertiaErrors(errors),
                onFinish: () => setBusy(false),
            },
        );
    };

    /** «ربط حسابي»: this account (its e-mail) becomes the user account of the chosen teacher record. */
    const linkMyAccount = () => {
        if (
            linkTeacher === null ||
            linkTeacher === '' ||
            account_email === null
        )
            return;
        setBusy(true);
        router.post(
            `/attendance/staff/teachers/${linkTeacher}/account`,
            { email: account_email },
            {
                headers: { 'X-Idempotency-Key': newKey('link-self') },
                onSuccess: () => setLinkTeacher(null),
                onError: (errors) => showInertiaErrors(errors),
                onFinish: () => setBusy(false),
            },
        );
    };

    // An account without a teacher record: a warning dialog whose action opens «ربط حسابي».
    const warnedRef = useRef(false);
    useEffect(() => {
        if (linked || filters.teacher_id !== null || warnedRef.current) {
            return;
        }
        warnedRef.current = true;
        showWarning({
            description: m.notLinked,
            ...(canLink
                ? {
                      action: {
                          label: m.linkMine,
                          onSelect: () => setLinkTeacher(''),
                      },
                  }
                : {}),
        });
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [linked, filters.teacher_id]);

    const messages: AttendanceMessage[] = day.holiday
        ? [{ key: 'holiday', tone: 'warning' as const, text: m.holiday }]
        : [];
    useAttendanceMessages(
        linked || filters.teacher_id !== null ? messages : [],
        m.title,
    );

    const timing = (l: Lesson): string => {
        if (l.phase === 'upcoming' && day.date === day.today)
            return m.startsIn.replace(':n', String(l.minutes_to_start));
        if (l.phase === 'running')
            return `${a.phase.running} · ${m.endsIn.replace(':n', String(l.minutes_to_end))}`;

        return a.phase[l.phase] ?? '';
    };

    const columns: AttendanceColumn<Lesson>[] = [
        {
            id: 'period',
            header: m.period,
            kind: 'num',
            cell: (l) => <span dir="ltr">{l.period_number}</span>,
        },
        {
            id: 'time',
            header: m.time,
            kind: 'text',
            cell: (l) => (
                <span dir="ltr">{`${l.start_time}–${l.end_time}`}</span>
            ),
        },
        {
            id: 'subject',
            header: m.subject,
            kind: 'name',
            cell: (l) =>
                `${l.subject_name}${l.substitute_teacher_id !== null ? ` · ${m.substitute}` : ''}${l.co_teacher_id !== null ? ` · ${m.coTeacher}` : ''}`,
        },
        {
            id: 'type',
            header: m.type,
            kind: 'text',
            cell: (l) => a.lessonType[l.lesson_type],
        },
        {
            id: 'class',
            header: m.classSection,
            kind: 'text',
            cell: (l) =>
                `${l.class_name} / ${l.section_name}${l.group_name ? ` · ${l.group_name}` : ''}`,
        },
        {
            id: 'room',
            header: m.room,
            kind: 'text',
            cell: (l) => l.room_name ?? '—',
        },
        {
            id: 'phase',
            header: m.lessonStatus,
            kind: 'text',
            cell: (l) => timing(l),
        },
        {
            id: 'sheet',
            header: m.sheetStatus,
            kind: 'status',
            tone: (l) => sheetStatusMeta(l.session?.sheet_status)?.tone ?? 0,
            cell: (l) => (
                <StatusLabel
                    meta={sheetStatusMeta(l.session?.sheet_status)}
                    label={
                        l.session === null
                            ? a.window[l.window]
                            : sheetLabel(l.session.sheet_status)
                    }
                />
            ),
        },
        {
            id: 'counts',
            header: a.sheet.recorded,
            kind: 'text',
            cell: (l) => countsText(l.session?.counts),
        },
    ];
    const viewer = useRowViewer(
        columns,
        (l) => `${l.subject_name} — ${l.class_name} / ${l.section_name}`,
        (l) =>
            l.session?.review_note ? (
                <WindowFact
                    wide
                    label={a.sheet.reviewNote}
                    value={l.session.review_note}
                />
            ) : null,
    );

    const editGroups = useMemo(
        (): PageRibbonGroup[] => [
            selectionGroup(
                'attendance-day-selection',
                selection.targets,
                viewer.open,
                (rows) => openLesson(rows[0]),
                {
                    editLabel: single?.session ? m.resume : m.open,
                    editDisabled:
                        single === null ||
                        busy ||
                        (single.session === null &&
                            (!canWork || blocked(single))),
                    editTitle:
                        single === null
                            ? c.needsOne
                            : single.session === null && blocked(single)
                              ? a.window[single.window]
                              : undefined,
                },
            ),
            {
                id: 'attendance-day-actions',
                label: c.actions,
                commands: [
                    {
                        id: 'attendance-day-open',
                        label: single?.session ? m.resume : m.open,
                        icon: single?.session ? PlayCircle : DoorOpen,
                        iconTone: 'ok',
                        title:
                            single === null
                                ? c.needsOne
                                : single.session === null && blocked(single)
                                  ? a.window[single.window]
                                  : undefined,
                        disabled:
                            single === null ||
                            busy ||
                            (single.session === null &&
                                (!canWork || blocked(single))),
                        onSelect: () => single && openLesson(single),
                    },
                    {
                        id: 'attendance-day-notes',
                        label: m.notices,
                        icon: MessageSquareWarning,
                        iconTone: 'warning',
                        count: day.notices.length,
                        disabled: day.notices.length === 0,
                        onSelect: () => setNotesOpen(true),
                    },
                    ...(abilities.review_sheet && lookups !== null
                        ? [
                              {
                                  id: 'attendance-day-exceptional',
                                  label: m.exceptional,
                                  icon: CalendarPlus,
                                  iconTone: 'rose' as const,
                                  disabled: day.holiday,
                                  onSelect: () =>
                                      setExceptional({
                                          section_id: '',
                                          subject_id: '',
                                          teacher_id: String(
                                              filters.teacher_id ?? '',
                                          ),
                                          period_id: '',
                                          reason: '',
                                      }),
                              },
                          ]
                        : []),
                    ...(canLink
                        ? [
                              {
                                  id: 'attendance-day-link',
                                  label: m.linkMine,
                                  icon: Link2,
                                  iconTone: 'authority' as const,
                                  onSelect: () => setLinkTeacher(''),
                              },
                          ]
                        : []),
                ],
            },
            {
                id: 'attendance-day-nav',
                label: `${dayOfWeekLabel(day.weekday)} ${day.date}`,
                commands: [
                    {
                        id: 'attendance-day-prev',
                        label: m.prevDay,
                        icon: ChevronRight,
                        iconTone: 'steel',
                        onSelect: () => go({ date: shiftDate(day.date, -1) }),
                    },
                    {
                        id: 'attendance-day-today',
                        label: m.today,
                        icon: CalendarDays,
                        iconTone: 'education',
                        pressed: day.date === day.today,
                        title: m.timezoneNote.replace(':tz', day.timezone),
                        onSelect: () => go({ date: day.today }),
                    },
                    {
                        id: 'attendance-day-next',
                        label: m.nextDay,
                        icon: ChevronLeft,
                        iconTone: 'steel',
                        onSelect: () => go({ date: shiftDate(day.date, 1) }),
                    },
                ],
            },
        ],
        // eslint-disable-next-line react-hooks/exhaustive-deps
        [
            selection.targets,
            busy,
            canWork,
            day,
            abilities,
            lookups,
            filters.teacher_id,
            canLink,
        ],
    );
    useAttendanceEditRibbon('my', abilities, editGroups);

    const yearSpec = useYearFilterSpec(filters.academic_year_id, (year) =>
        go({ academic_year_id: year }),
    );
    useAttendanceHomeFilters(
        [
            yearSpec,
            {
                key: 'date',
                date: true,
                label: m.date,
                value: day.date,
                onChange: (date: string) => go({ date }),
            },
            ...(abilities.review_sheet && teachers.length > 0
                ? [
                      {
                          key: 'teacher',
                          type: 'single' as const,
                          label: m.chooseTeacher,
                          value:
                              filters.teacher_id === null
                                  ? ''
                                  : String(filters.teacher_id),
                          options: [
                              { value: '', label: m.myself },
                              ...teachers.map((x) => ({
                                  value: String(x.id),
                                  label: x.full_name,
                              })),
                          ],
                          onChange: (value: string) =>
                              go({
                                  teacher_id:
                                      value === '' ? null : Number(value),
                              }),
                      },
                  ]
                : []),
        ],
        day.date !== day.today || filters.teacher_id !== null
            ? () => go({ date: day.today, teacher_id: null })
            : null,
        [day.date, day.today, filters, teachers, yearSpec.options.length],
    );

    const menuItems = (l: Lesson): ContextMenuItem[] => [
        {
            id: 'open',
            label: l.session ? m.resume : m.open,
            icon: l.session ? PlayCircle : DoorOpen,
            disabled: l.session === null && (!canWork || blocked(l)),
            onSelect: () => openLesson(l),
        },
        {
            id: 'view',
            label: c.view,
            icon: CalendarDays,
            onSelect: () => viewer.open([l]),
        },
    ];

    const noticeColumns: AttendanceColumn<AdminNotice>[] = [
        {
            id: 'date',
            header: m.date,
            kind: 'text',
            cell: (n) => <span dir="ltr">{n.session_date}</span>,
        },
        {
            id: 'lesson',
            header: m.subject,
            kind: 'name',
            cell: (n) =>
                `${n.subject_name} · ${n.class_name} / ${n.section_name}`,
        },
        {
            id: 'status',
            header: m.sheetStatus,
            kind: 'status',
            tone: (n) => sheetStatusMeta(n.sheet_status)?.tone ?? 0,
            cell: (n) => (
                <StatusLabel
                    meta={sheetStatusMeta(n.sheet_status)}
                    label={sheetLabel(n.sheet_status)}
                />
            ),
        },
        {
            id: 'note',
            header: a.sheet.reviewNote,
            kind: 'text',
            cell: (n) => n.review_note ?? '—',
        },
        {
            id: 'at',
            header: a.sheet.reviewedAt,
            kind: 'text',
            cell: (n) => dateTime(n.reviewed_at),
        },
    ];

    const sectionOptions =
        lookups?.classes.flatMap((cls) =>
            cls.sections.map((s) => ({
                value: String(s.id),
                label: `${cls.name} / ${s.name}`,
            })),
        ) ?? [];
    const subjectOptions =
        lookups?.subjects.map((s) => ({
            value: String(s.id),
            label: s.name,
        })) ?? [];
    const teacherOptions = (lookups?.teachers ?? teachers).map((s) => ({
        value: String(s.id),
        label: s.full_name,
    }));
    const periodOptions =
        lookups?.periods.map((p) => ({
            value: String(p.id),
            label: `${p.period_number} (${p.start_time}–${p.end_time})`,
        })) ?? [];
    const pick = (
        list: Array<{ value: string; label: string }>,
        value: string,
    ) => list.find((o) => o.value === value)?.label ?? '—';

    return (
        <AttendancePage
            title={
                day.teacher ? `${m.title} — ${day.teacher.full_name}` : m.title
            }
        >
            <AttendanceTable
                caption={m.title}
                storageKey="my-day.lessons"
                rows={day.lessons}
                rowKey={(l) => l.schedule_id}
                columns={columns}
                emptyText={day.holiday ? m.holiday : m.noLessons}
                emptyAsDialog={
                    !day.holiday && (linked || filters.teacher_id !== null)
                }
                selection={selection}
                onOpen={openLesson}
                menuItems={menuItems}
                menuLabel={m.title}
            />

            {viewer.element}

            {notesOpen ? (
                <AttendanceWindow
                    wide
                    title={m.notices}
                    onClose={() => setNotesOpen(false)}
                    sections={[
                        {
                            id: 'att-day-notes',
                            title: `${m.notices} (${day.notices.length})`,
                            plain: true,
                            content: (
                                <div className="sis-branches-field--wide">
                                    <AttendanceTable
                                        caption={m.notices}
                                        storageKey="my-day.notices"
                                        rows={day.notices}
                                        rowKey={(n) => n.session_id}
                                        columns={noticeColumns}
                                        emptyText={m.noNotices}
                                        fit={false}
                                        emptyAsDialog={false}
                                        onOpen={(n) =>
                                            router.visit(
                                                `/attendance/sheets/${n.session_id}`,
                                            )
                                        }
                                    />
                                </div>
                            ),
                        },
                    ]}
                />
            ) : null}

            {linkTeacher !== null ? (
                <AttendanceWindow
                    title={m.linkMine}
                    columns={2}
                    pending={busy}
                    confirmLabel={m.linkMine}
                    confirmDisabled={linkTeacher === ''}
                    onConfirm={linkMyAccount}
                    onClose={() => setLinkTeacher(null)}
                    sections={[
                        {
                            id: 'att-link-self',
                            title: a.staff.linkAccount,
                            content: (
                                <>
                                    <WindowFact
                                        label={a.staff.email}
                                        value={
                                            <span dir="ltr">
                                                {account_email ?? '—'}
                                            </span>
                                        }
                                    />
                                    <RegistryListField
                                        label={c.teacher}
                                        editing
                                        required
                                        value={linkTeacher}
                                        display={pick(
                                            teacherOptions,
                                            linkTeacher,
                                        )}
                                        options={teacherOptions}
                                        onChange={(value) =>
                                            setLinkTeacher(value)
                                        }
                                    />
                                    <WindowFact
                                        wide
                                        label={a.staff.linkAccount}
                                        value={m.linkMineHint}
                                    />
                                </>
                            ),
                        },
                    ]}
                />
            ) : null}

            {exceptional !== null ? (
                <AttendanceWindow
                    title={m.exceptionalTitle}
                    columns={2}
                    pending={busy}
                    dirty={exceptional.reason !== ''}
                    confirmLabel={m.open}
                    confirmDisabled={
                        exceptional.section_id === '' ||
                        exceptional.subject_id === '' ||
                        exceptional.teacher_id === '' ||
                        exceptional.period_id === '' ||
                        exceptional.reason.trim().length < 3
                    }
                    onConfirm={saveExceptional}
                    onClose={() => setExceptional(null)}
                    sections={[
                        {
                            id: 'att-exceptional-lesson',
                            title: m.exceptionalLesson,
                            content: (
                                <>
                                    <WindowFact
                                        label={m.date}
                                        value={
                                            <span dir="ltr">{day.date}</span>
                                        }
                                    />
                                    <RegistryListField
                                        label={m.period}
                                        editing
                                        required
                                        value={exceptional.period_id}
                                        display={pick(
                                            periodOptions,
                                            exceptional.period_id,
                                        )}
                                        options={periodOptions}
                                        onChange={(period_id) =>
                                            setExceptional({
                                                ...exceptional,
                                                period_id,
                                            })
                                        }
                                    />
                                    <RegistryListField
                                        label={c.section}
                                        editing
                                        required
                                        value={exceptional.section_id}
                                        display={pick(
                                            sectionOptions,
                                            exceptional.section_id,
                                        )}
                                        options={sectionOptions}
                                        onChange={(section_id) =>
                                            setExceptional({
                                                ...exceptional,
                                                section_id,
                                            })
                                        }
                                    />
                                    <RegistryListField
                                        label={c.subject}
                                        editing
                                        required
                                        value={exceptional.subject_id}
                                        display={pick(
                                            subjectOptions,
                                            exceptional.subject_id,
                                        )}
                                        options={subjectOptions}
                                        onChange={(subject_id) =>
                                            setExceptional({
                                                ...exceptional,
                                                subject_id,
                                            })
                                        }
                                    />
                                    <RegistryListField
                                        label={c.teacher}
                                        editing
                                        required
                                        value={exceptional.teacher_id}
                                        display={pick(
                                            teacherOptions,
                                            exceptional.teacher_id,
                                        )}
                                        options={teacherOptions}
                                        onChange={(teacher_id) =>
                                            setExceptional({
                                                ...exceptional,
                                                teacher_id,
                                            })
                                        }
                                    />
                                    <WindowTextArea
                                        label={m.exceptionalReason}
                                        required
                                        value={exceptional.reason}
                                        onChange={(reason) =>
                                            setExceptional({
                                                ...exceptional,
                                                reason,
                                            })
                                        }
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
