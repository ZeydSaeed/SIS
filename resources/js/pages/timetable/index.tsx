import { Head, router, usePage } from '@inertiajs/react';
import { BadgeCheck, Boxes, CalendarClock, CalendarX, ChevronsLeft, ChevronsRight, CircleAlert, Clock, DoorOpen, FileCheck, FileClock, FileWarning, FilterX, Gauge, History, LoaderCircle, Lock, OctagonX, Scale, Settings2, ShieldAlert, ShieldCheck, TriangleAlert, Wand2, WandSparkles, LayoutGrid, LayoutList, Maximize2, Minimize2, Printer, Sparkles, Trash2, UserRound, X } from 'lucide-react';
import { Fragment, useCallback, useEffect, useMemo, useRef, useState, type CSSProperties, type DragEvent, type ReactElement, type ReactNode } from 'react';
import { RegistryListField, RegistrySheetDialog, useRegistryRequest } from '@/components/organization/registry-sheet';
import { SheetSection } from '@/components/sis/admission-sheet';
import { ConfirmDialog } from '@/components/sis/confirm-dialog';
import { formatAcademicYearOptionLabel, type YearOption } from '@/components/sis/ops-year-filter';
import { useRegisterPageRibbon, type PageRibbonCommand, type PageRibbonGroup } from '@/components/sis/page-ribbon-context';
import { SisListSelect } from '@/components/sis/sis-list-select';
import { dayOfWeekLabel } from '@/components/sis/status-chip';
import { ActivitiesSheet } from '@/components/timetable/engine/activities-sheet';
import { AvailabilitySheet } from '@/components/timetable/engine/availability-sheet';
import { ConstraintsSheet } from '@/components/timetable/engine/constraints-sheet';
import type { EngineContext } from '@/components/timetable/engine/engine-context';
import type { Comparison, Engine, MoveSuggestions, RunDetail, Substitutes } from '@/components/timetable/engine/engine-types';
import { GenerateSheet } from '@/components/timetable/engine/generate-sheet';
import { LessonEngineTools } from '@/components/timetable/engine/lesson-engine-tools';
import { SettingsSheet } from '@/components/timetable/engine/settings-sheet';
import { VersionsSheet } from '@/components/timetable/engine/versions-sheet';
import { Button } from '@/components/ui/button';
import { t } from '@/i18n';
import { resolveSisSectionCode, sisSectionSelectOptions } from '@/lib/sis-class-section-options';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

type Period = { id: number; period_number: number; start_time: string; end_time: string; period_type: number };
type Section = { id: number; class_id: number; class_name: string; code: string; name: string };
type Branch = { id: number; name: string; departments: Array<{ id: number; name: string }> };
type Placement = { section_id: number; branch_id: number; department_id: number | null; students: number };
type Lesson = {
    teacher_id: number;
    teacher_name: string;
    subject_id: number;
    subject_name: string;
    class_id: number;
    section_id: number | null;
    weekly_hours: number | null;
};
type Schedule = {
    id: number;
    section_id: number;
    day_of_week: number;
    period_id: number;
    subject_id: number;
    teacher_id: number;
    room_id: number | null;
    group_id?: number | null;
    week_no?: number | null;
    co_teacher_id?: number | null;
    joined_to?: number | null;
    locked?: boolean;
    activity_id?: number | null;
};
type Severity = 'error' | 'warning' | 'info';
type Issue = {
    severity: Severity;
    code: string;
    section_id: number | null;
    teacher_id: number | null;
    subject_id: number | null;
    day: number | null;
    period_id: number | null;
    schedule_ids: number[];
    count: number | null;
};
type Teacher = { id: number; full_name: string; short_name: string };
type FindingSeverity = 'blocker' | 'warning' | 'info';
/** «جاهزية الجدول» (Domain `TimetableAdvisor`): can these lessons fit the week at all? */
type Advice = {
    verdict: 'ready' | 'blocked';
    findings: Array<{ severity: FindingSeverity; code: string; section_id: number | null; teacher_id: number | null; subject_id: number | null; count: number | null; limit: number | null }>;
    readiness: { overall: number; school_day: number; weekly_loads: number | null; qualified: number | null; sections: number | null; capacity: number | null };
    totals: { sections: number; teachers: number; requirements: number; weekly_lessons: number; lesson_periods: number; slots_per_week: number };
};
/** «عبء المدرسين» (Domain `TeacherWorkloadAnalyzer`). */
type WorkloadRow = {
    teacher_id: number;
    required: number;
    placed: number;
    practical: number;
    theory: number;
    sections: number;
    by_day: Record<string, number>;
    max_day: number;
    gaps: number;
    max_consecutive: number;
    capacity: number;
    status: 'over' | 'incomplete' | 'ok';
};
/** «جودة الجدول» (Domain `TimetableQualityScorer`). */
type Quality = {
    feasible: boolean;
    grade: 'infeasible' | 'incomplete' | 'complete';
    overall: number | null;
    errors: number;
    warnings: number;
    metrics: Record<string, number | null>;
    counts: { required: number; placed: number; teacher_gaps: number; section_gaps: number };
};
type Props = {
    periods: Period[];
    sections: Section[];
    branches: Branch[];
    placements: Placement[];
    lessons: Lesson[];
    schedules: Schedule[];
    teachers: Teacher[];
    teacherSubjects: Array<{ teacher_id: number; subject_id: number }>;
    practicalSubjectIds: number[];
    issues: Issue[];
    advice: Advice | null;
    workload: WorkloadRow[];
    quality: Quality | null;
    subjects: Array<{ id: number; name: string }>;
    filters: { academic_year_id: number | null };
    engine: Engine | null;
    viewingVersion: { id: number; version_no: number; name: string } | null;
    runDetail?: RunDetail | null;
    comparison?: Comparison | null;
    moveSuggestions?: MoveSuggestions | null;
    substitutes?: Substitutes | null;
    authorization: {
        can_create: boolean;
        can_update: boolean;
        can_cancel: boolean;
        can_manage_periods: boolean;
        can_manage_constraints: boolean;
        can_generate: boolean;
        can_publish: boolean;
        can_approve: boolean;
        can_substitute: boolean;
    };
};

/** A lesson of a section: one subject taught by one teacher, `required` times a week (null = not set in the curriculum). */
type SectionLesson = { key: string; subjectId: number; teacherId: number; subjectName: string; teacherName: string; required: number | null; placed: number };

/** What is being dragged: a tray card (new lesson) or a placed lesson (move / swap). */
type DragPayload = { sectionId: number; subjectId: number; teacherId: number; scheduleId: number | null };
type CellState = 'free' | 'busy' | 'taken' | 'swap' | 'idle';

/** Filters «الفرع / الاختصاص / الصف / الشعبة» ('' = الكل); section = the shared code A / B / C (SSOT). */
type GridFilters = { branch: string; department: string; class: string; section: string };

/** A block of the «عرض الكل» split: branch › department (or the sections without students). */
type GridGroup = { key: string; title: string; sections: Array<{ section: Section; students: number }> };

const LESSON = 1;
/** School week: الأحد → الخميس (`day_of_week` 1–5). */
const DEFAULT_DAYS = [1, 2, 3, 4, 5];
const SCHEDULE_RELOAD = ['schedules', 'issues', 'advice', 'workload', 'quality', 'engine', 'flash'];
const PERIOD_RELOAD = ['periods', 'issues', 'advice', 'workload', 'quality', 'flash'];
const FINDING_SEVERITIES: FindingSeverity[] = ['blocker', 'warning', 'info'];
const QUALITY_METRICS = ['completeness', 'teacher_compactness', 'section_compactness', 'distribution', 'practical_doubles', 'workload_balance'] as const;
const SEVERITIES: Severity[] = ['error', 'warning', 'info'];
const SEVERITY_RANK: Record<Severity, number> = { error: 0, warning: 1, info: 2 };
const NO_FILTERS: GridFilters = { branch: '', department: '', class: '', section: '' };

const cellKey = (day: number, periodId: number) => `${day}:${periodId}`;
const teacherSlotKey = (teacherId: number, day: number, periodId: number) => `${teacherId}:${day}:${periodId}`;
const lessonKey = (subjectId: number, teacherId: number) => `${subjectId}:${teacherId}`;
const minutesOf = (time: string) => {
    const [h, m] = time.split(':').map(Number);

    return (h ?? 0) * 60 + (m ?? 0);
};

/** "13:15" → "1:15 م" — the timetable shows a 12-hour clock (ص / م). */
function clock12(time: string): string {
    const [h = 0, m = 0] = time.split(':').map(Number);
    const suffix = h < 12 ? t().timetable.am : t().timetable.pm;

    return `${h % 12 === 0 ? 12 : h % 12}:${String(m).padStart(2, '0')} ${suffix}`;
}

/** Break tint by length: short (≤ 5), medium (≤ 10), long (main break). */
function breakTone(minutes: number): 'short' | 'medium' | 'long' {
    return minutes <= 5 ? 'short' : minutes <= 10 ? 'medium' : 'long';
}

/** Section boards per A3 sheet (2 columns × 3 rows, landscape). */
const A3_BOARDS_PER_SHEET = 6;

/** Break columns are as wide as they are long: 5 minutes narrow, 10 wider, 15 wider still. */
const BREAK_REM_PER_MINUTE = 0.2;

/** One colour per subject (same wheel as «المعلمون»): hues spread evenly, neighbours alternate lightness. */
function subjectStyle(index: number, count: number): CSSProperties {
    const step = 360 / Math.max(1, count);

    return { '--subject-hue': Math.round(index * step), '--subject-light': `${index % 2 === 0 ? 84 : 91}%` } as CSSProperties;
}

export default function TimetableIndex(props: Props) {
    const breadcrumbs: BreadcrumbItem[] = [{ title: t().timetable.title, href: '/timetable' }];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <TimetablePage {...props} />
        </AppLayout>
    );
}

function TimetablePage({
    periods,
    sections,
    branches,
    placements,
    lessons,
    schedules,
    teachers,
    teacherSubjects,
    practicalSubjectIds,
    issues,
    advice,
    workload,
    quality,
    subjects,
    filters,
    engine,
    viewingVersion,
    runDetail = null,
    comparison = null,
    moveSuggestions = null,
    substitutes = null,
    authorization,
}: Props) {
    const i18n = t();
    const tt = i18n.timetable;
    const et = tt.engine;
    const yearId = filters.academic_year_id;
    const scheduleRequest = useRegistryRequest(SCHEDULE_RELOAD);
    const page = usePage().props as { academicYears?: YearOption[] };
    const years = page.academicYears ?? [];
    /** Working days of the school (settings), Sunday–Thursday by default. */
    const DAYS = engine?.settings.working_days ?? DEFAULT_DAYS;

    const [view, setView] = useState<'section' | 'teacher' | 'room'>('section');
    const [roomId, setRoomId] = useState<number | null>(engine?.rooms[0]?.id ?? null);
    const [engineSheet, setEngineSheet] = useState<'generate' | 'activities' | 'constraints' | 'availability' | 'versions' | 'settings' | null>(null);
    const [gridFilters, setGridFilters] = useState<GridFilters>(NO_FILTERS);
    const [focusId, setFocusId] = useState<number | null>(null);
    const [teacherId, setTeacherId] = useState<number | null>(teachers[0]?.id ?? null);
    const [drag, setDrag] = useState<DragPayload | null>(null);
    const [overCell, setOverCell] = useState<string | null>(null);
    const [pickCell, setPickCell] = useState<{ sectionId: number; day: number; periodId: number } | null>(null);
    const [editId, setEditId] = useState<number | null>(null);
    const [swapPair, setSwapPair] = useState<{ from: number; to: number } | null>(null);
    const [periodsOpen, setPeriodsOpen] = useState(false);
    const [auditOpen, setAuditOpen] = useState(false);
    const [readinessOpen, setReadinessOpen] = useState(false);
    const [highlight, setHighlight] = useState<number[]>([]);
    const [preview, setPreview] = useState(false);
    const [previewA3, setPreviewA3] = useState(false);
    const previewRef = useRef<HTMLDivElement | null>(null);
    const [saving, setSaving] = useState(false);

    // A version on the grid is history: read-only.
    const canPlace = authorization.can_create && yearId !== null && viewingVersion === null;
    const activeTeacherId = teachers.some((x) => x.id === teacherId) ? teacherId : (teachers[0]?.id ?? null);
    const sortedPeriods = useMemo(() => [...periods].sort((a, b) => a.period_number - b.period_number), [periods]);
    const lessonPeriods = sortedPeriods.filter((p) => p.period_type === LESSON);
    /** Lessons are numbered 1, 2, 3 … in the day; breaks take no number. */
    const lessonNumber = useMemo(() => new Map(lessonPeriods.map((p, index) => [p.id, index + 1])), [lessonPeriods]);
    const subjectNames = useMemo(() => new Map(subjects.map((s) => [s.id, s.name])), [subjects]);
    // «الاسم واسم الأب»; lessons also name teachers who left the active list (their lessons show in the audit).
    const teacherNames = useMemo(
        () => new Map([...lessons.map((l) => [l.teacher_id, l.teacher_name] as const), ...teachers.map((x) => [x.id, x.short_name] as const)]),
        [lessons, teachers],
    );
    const sectionsById = useMemo(() => new Map(sections.map((s) => [s.id, s])), [sections]);
    const practical = useMemo(() => new Set(practicalSubjectIds), [practicalSubjectIds]);
    const teacherSubjectKeys = useMemo(() => new Set(teacherSubjects.map((x) => lessonKey(x.subject_id, x.teacher_id))), [teacherSubjects]);

    useEffect(() => {
        if (highlight.length === 0) {
            return;
        }
        const timer = window.setTimeout(() => setHighlight([]), 4000);

        return () => window.clearTimeout(timer);
    }, [highlight]);

    // ── «عرض الكل» split: branch › department › class › section (placement = SSOT) ─────────
    const departmentNames = useMemo(() => new Map(branches.flatMap((b) => b.departments.map((d) => [d.id, d.name] as const))), [branches]);
    const groups = useMemo((): GridGroup[] => {
        const bySection = new Map<number, Placement[]>();
        for (const p of placements) {
            bySection.set(p.section_id, [...(bySection.get(p.section_id) ?? []), p]);
        }
        const blocks = new Map<string, GridGroup>();
        const add = (key: string, title: string, section: Section, students: number) => {
            const block = blocks.get(key) ?? { key, title, sections: [] };
            block.sections.push({ section, students });
            blocks.set(key, block);
        };
        const branchFilter = gridFilters.branch === '' ? null : Number(gridFilters.branch);
        const departmentFilter = gridFilters.department === '' ? null : Number(gridFilters.department);
        for (const section of sections) {
            if ((gridFilters.class !== '' && String(section.class_id) !== gridFilters.class) || (gridFilters.section !== '' && resolveSisSectionCode(section.id, sections) !== gridFilters.section)) {
                continue;
            }
            const served = (bySection.get(section.id) ?? []).filter(
                (p) => (branchFilter === null || p.branch_id === branchFilter) && (departmentFilter === null || p.department_id === departmentFilter),
            );
            if (served.length === 0) {
                if (branchFilter === null && departmentFilter === null) {
                    add('unlinked', tt.unlinkedGroup, section, 0);
                }
                continue;
            }
            for (const p of served) {
                const branchName = branches.find((b) => b.id === p.branch_id)?.name ?? `#${p.branch_id}`;
                const departmentName = p.department_id === null ? tt.noDepartment : (departmentNames.get(p.department_id) ?? `#${p.department_id}`);
                add(`${p.branch_id}:${p.department_id ?? 0}`, `${branchName} › ${departmentName}`, section, p.students);
            }
        }
        // Branch order of the organization structure, then department order; unlinked last.
        const branchRank = new Map(branches.map((b, index) => [b.id, index]));
        const departmentRank = new Map(branches.flatMap((b) => b.departments.map((d, index) => [d.id, index] as const)));
        const rank = (key: string) => {
            if (key === 'unlinked') {
                return [Number.MAX_SAFE_INTEGER, 0];
            }
            const [branchId, departmentId] = key.split(':').map(Number);

            return [branchRank.get(branchId ?? 0) ?? 0, departmentRank.get(departmentId ?? 0) ?? -1];
        };

        return [...blocks.values()].sort((a, b) => {
            const [ab, ad] = rank(a.key);
            const [bb, bd] = rank(b.key);

            return (ab ?? 0) - (bb ?? 0) || (ad ?? 0) - (bd ?? 0);
        });
    }, [branches, departmentNames, gridFilters, placements, sections, tt]);

    const visibleSectionIds = useMemo(() => [...new Set(groups.flatMap((g) => g.sections.map((s) => s.section.id)))], [groups]);
    const focusSectionId = focusId !== null && visibleSectionIds.includes(focusId) ? focusId : (visibleSectionIds[0] ?? null);
    const focusSection = focusSectionId === null ? null : (sectionsById.get(focusSectionId) ?? null);
    const singleGrid = visibleSectionIds.length === 1 && groups.length === 1;

    // Subject colours: stable order by name over every subject on the page.
    const subjectStyles = useMemo(() => {
        const ids = [...new Set([...lessons.map((l) => l.subject_id), ...schedules.map((s) => s.subject_id)])].sort((a, b) =>
            (subjectNames.get(a) ?? '').localeCompare(subjectNames.get(b) ?? '', 'ar'),
        );

        return new Map(ids.map((id, index) => [id, subjectStyle(index, ids.length)]));
    }, [lessons, schedules, subjectNames]);

    /** teacher × day × period → the schedule holding the teacher (any section). */
    const teacherBusy = useMemo(() => new Map(schedules.map((s) => [teacherSlotKey(s.teacher_id, s.day_of_week, s.period_id), s])), [schedules]);
    /** section → its cells. */
    const sectionCells = useMemo(() => {
        const bySection = new Map<number, Map<string, Schedule>>();
        for (const s of schedules) {
            const cells = bySection.get(s.section_id) ?? new Map<string, Schedule>();
            cells.set(cellKey(s.day_of_week, s.period_id), s);
            bySection.set(s.section_id, cells);
        }

        return bySection;
    }, [schedules]);
    /** The teacher's week: lead or co-teacher; a joined lesson once (its lead row). */
    const teacherCells = useMemo(
        () => new Map(schedules.filter((s) => (s.teacher_id === activeTeacherId || s.co_teacher_id === activeTeacherId) && (s.joined_to ?? null) === null).map((s) => [cellKey(s.day_of_week, s.period_id), s])),
        [schedules, activeTeacherId],
    );
    /** «حسب القاعة»: the room's week (lead rows hold the room). */
    const activeRoomId = engine?.rooms.some((r) => r.id === roomId) ? roomId : (engine?.rooms[0]?.id ?? null);
    const roomCells = useMemo(
        () => new Map(schedules.filter((s) => s.room_id !== null && s.room_id === activeRoomId && (s.joined_to ?? null) === null).map((s) => [cellKey(s.day_of_week, s.period_id), s])),
        [schedules, activeRoomId],
    );
    /** Lessons sharing a section cell (parallel groups of one division): the cell shows the first and «+n». */
    const sharedCells = useMemo(() => {
        const count = new Map<string, number>();
        for (const s of schedules) {
            const key = `${s.section_id}:${cellKey(s.day_of_week, s.period_id)}`;
            count.set(key, (count.get(key) ?? 0) + 1);
        }

        return count;
    }, [schedules]);
    const groupNames = useMemo(() => new Map((engine?.groups ?? []).map((g) => [g.id, g.name])), [engine]);

    const lessonSeverity = useMemo(() => {
        const worst = new Map<number, Severity>();
        for (const issue of issues) {
            for (const id of issue.schedule_ids) {
                const current = worst.get(id);
                if (current === undefined || SEVERITY_RANK[issue.severity] < SEVERITY_RANK[current]) {
                    worst.set(id, issue.severity);
                }
            }
        }

        return worst;
    }, [issues]);
    const issueCounts = useMemo(() => {
        const counts: Record<Severity, number> = { error: 0, warning: 0, info: 0 };
        for (const issue of issues) {
            counts[issue.severity] += 1;
        }

        return counts;
    }, [issues]);

    /** Lessons of a section: assignments naming the section, or its class with no section. */
    const lessonsOf = (section: Section | null): SectionLesson[] => {
        if (section === null) {
            return [];
        }
        const byKey = new Map<string, SectionLesson>();
        for (const l of lessons) {
            if (l.class_id !== section.class_id || (l.section_id !== null && l.section_id !== section.id)) {
                continue;
            }
            const key = lessonKey(l.subject_id, l.teacher_id);
            if (!byKey.has(key)) {
                byKey.set(key, { key, subjectId: l.subject_id, teacherId: l.teacher_id, subjectName: l.subject_name, teacherName: l.teacher_name, required: l.weekly_hours, placed: 0 });
            }
        }
        for (const s of sectionCells.get(section.id)?.values() ?? []) {
            const entry = byKey.get(lessonKey(s.subject_id, s.teacher_id));
            if (entry !== undefined) {
                entry.placed += 1;
            }
        }

        return [...byKey.values()].sort((a, b) => a.subjectName.localeCompare(b.subjectName, 'ar') || a.teacherName.localeCompare(b.teacherName, 'ar'));
    };
    const focusLessons = useMemo(() => lessonsOf(focusSection), [focusSection, lessons, sectionCells]); // eslint-disable-line react-hooks/exhaustive-deps
    const trayLessons = focusLessons.filter((l) => l.required === null || l.placed < l.required);
    const requiredTotal = focusLessons.reduce((sum, l) => sum + (l.required ?? 0), 0);
    const placedTotal = focusSectionId === null ? 0 : (sectionCells.get(focusSectionId)?.size ?? 0);
    const progress = requiredTotal === 0 ? 0 : Math.min(100, Math.round((Math.min(placedTotal, requiredTotal) / requiredTotal) * 100));

    /** Drop target state of a section cell for the lesson being dragged / picked. */
    const cellState = (sectionId: number, day: number, periodId: number, lesson: Pick<DragPayload, 'sectionId' | 'teacherId' | 'scheduleId'> | null): CellState => {
        if (lesson === null) {
            return 'idle';
        }
        if (lesson.sectionId !== sectionId) {
            return 'idle';
        }
        const own = sectionCells.get(sectionId)?.get(cellKey(day, periodId));
        if (own !== undefined && own.id !== lesson.scheduleId) {
            return lesson.scheduleId !== null && authorization.can_update ? 'swap' : 'taken';
        }
        const holder = teacherBusy.get(teacherSlotKey(lesson.teacherId, day, periodId));

        return holder !== undefined && holder.id !== lesson.scheduleId ? 'busy' : 'free';
    };

    const run = async (method: 'post' | 'patch', url: string, data: Record<string, number | number[] | null> = {}) => {
        setSaving(true);
        const ok = await scheduleRequest(method, url, data);
        setSaving(false);

        return ok;
    };

    const lessonBody = (s: Pick<Schedule, 'section_id' | 'day_of_week' | 'period_id' | 'subject_id' | 'teacher_id' | 'room_id'>) => ({
        academic_year_id: yearId,
        section_id: s.section_id,
        day_of_week: s.day_of_week,
        period_id: s.period_id,
        subject_id: s.subject_id,
        teacher_id: s.teacher_id,
        room_id: s.room_id,
    });

    const place = (payload: DragPayload, sectionId: number, day: number, periodId: number) => {
        if (yearId === null) {
            return;
        }
        const state = cellState(sectionId, day, periodId, payload);
        if (state === 'swap' && payload.scheduleId !== null) {
            const target = sectionCells.get(sectionId)?.get(cellKey(day, periodId));
            if (target !== undefined) {
                setSwapPair({ from: payload.scheduleId, to: target.id });
            }

            return;
        }
        if (state !== 'free') {
            return;
        }
        if (payload.scheduleId === null) {
            void run('post', '/timetable/schedules', lessonBody({ section_id: sectionId, day_of_week: day, period_id: periodId, subject_id: payload.subjectId, teacher_id: payload.teacherId, room_id: null }));

            return;
        }
        const current = schedules.find((s) => s.id === payload.scheduleId);
        if (current === undefined || (current.day_of_week === day && current.period_id === periodId)) {
            return;
        }
        void run('patch', `/timetable/schedules/${current.id}`, lessonBody({ ...current, day_of_week: day, period_id: periodId }));
    };

    const unplace = async (scheduleId: number) => (authorization.can_cancel ? run('post', `/timetable/schedules/${scheduleId}/cancel`) : false);

    const startDrag = (event: DragEvent<HTMLElement>, payload: DragPayload) => {
        event.dataTransfer.effectAllowed = 'move';
        event.dataTransfer.setData('text/plain', String(payload.scheduleId ?? payload.subjectId));
        setFocusId(payload.sectionId);
        setDrag(payload);
    };
    const endDrag = () => {
        setDrag(null);
        setOverCell(null);
    };

    const changeYear = (next: string) => {
        if (next !== '' && Number(next) !== yearId) {
            setGridFilters(NO_FILTERS);
            router.get('/timetable', { academic_year_id: Number(next) }, { preserveScroll: true });
        }
    };

    const autoPlace = () => {
        if (yearId !== null && visibleSectionIds.length > 0) {
            void run('post', '/timetable/schedules/auto-place', { academic_year_id: yearId, section_ids: visibleSectionIds });
        }
    };

    // ── «معاينة ملء الشاشة» ──────────────────────────────────────────────────
    const closePreview = useCallback(() => {
        setPreview(false);
        setPreviewA3(false);
        if (typeof document !== 'undefined' && document.fullscreenElement !== null) {
            void document.exitFullscreen().catch(() => undefined);
        }
    }, []);
    useEffect(() => {
        if (!preview) {
            return;
        }
        const node = previewRef.current;
        if (node !== null && typeof node.requestFullscreen === 'function' && document.fullscreenElement === null) {
            void node.requestFullscreen().catch(() => undefined);
        }
        const onKey = (event: KeyboardEvent) => {
            if (event.key === 'Escape') {
                closePreview();
            }
        };
        // Leaving browser full screen (Esc / F11) also ends the preview.
        const onFullscreen = () => {
            if (document.fullscreenElement === null) {
                setPreview(false);
                setPreviewA3(false);
            }
        };
        window.addEventListener('keydown', onKey);
        document.addEventListener('fullscreenchange', onFullscreen);

        return () => {
            window.removeEventListener('keydown', onKey);
            document.removeEventListener('fullscreenchange', onFullscreen);
        };
    }, [preview, closePreview]);

    // ── Labels ───────────────────────────────────────────────────────────────
    const sectionLabel = (id: number | null) => {
        const s = id === null ? undefined : sectionsById.get(id);

        return s === undefined ? '' : `${s.class_name} — ${s.name}`;
    };
    const periodLabel = (id: number | null) => {
        const p = sortedPeriods.find((x) => x.id === id);

        return p === undefined ? '' : p.period_type === LESSON ? `${tt.periodLabel} ${lessonNumber.get(p.id) ?? ''}` : tt.breakLabel;
    };
    /** What the engine sheets read (names resolved once; permissions from the server). */
    const engineCtx: EngineContext | null =
        engine === null || yearId === null
            ? null
            : {
                  engine,
                  yearId,
                  lessonPeriods: lessonPeriods.map((p) => ({ id: p.id, number: lessonNumber.get(p.id) ?? 0, label: periodLabel(p.id) })),
                  days: DAYS,
                  dayLabel: dayOfWeekLabel,
                  sections: sections.map((s) => ({ id: s.id, label: `${s.class_name} — ${s.name}`, classId: s.class_id })),
                  teachers: teachers.map((x) => ({ id: x.id, name: x.short_name })),
                  subjects,
                  branches,
                  sectionLabel,
                  teacherName: (id) => (id === null ? '' : (teacherNames.get(id) ?? `#${id}`)),
                  subjectName: (id) => (id === null ? '' : (subjectNames.get(id) ?? `#${id}`)),
                  can: {
                      manage: authorization.can_manage_constraints,
                      generate: authorization.can_generate,
                      publish: authorization.can_publish,
                      approve: authorization.can_approve,
                  },
              };
    const issueText = (issue: Issue) =>
        ((tt.issues as Record<string, string>)[issue.code] ?? issue.code)
            .replace('{section}', sectionLabel(issue.section_id))
            .replace('{teacher}', issue.teacher_id === null ? '' : (teacherNames.get(issue.teacher_id) ?? `#${issue.teacher_id}`))
            .replace('{subject}', issue.subject_id === null ? '' : (subjectNames.get(issue.subject_id) ?? `#${issue.subject_id}`))
            .replace('{day}', issue.day === null ? '' : dayOfWeekLabel(issue.day))
            .replace('{period}', periodLabel(issue.period_id))
            .replace('{count}', String(issue.count ?? ''));

    const goToIssue = (issue: Issue) => {
        if (issue.section_id !== null) {
            setGridFilters({ ...NO_FILTERS, class: String(sectionsById.get(issue.section_id)?.class_id ?? ''), section: resolveSisSectionCode(issue.section_id, sections) });
            setFocusId(issue.section_id);
            setView('section');
        } else if (issue.teacher_id !== null) {
            setTeacherId(issue.teacher_id);
            setView('teacher');
        }
        setHighlight(issue.schedule_ids);
        setAuditOpen(false);
    };

    // ── Ribbon (الصفحة الرئيسية) ─────────────────────────────────────────────
    const homeRibbonGroups = useMemo((): PageRibbonGroup[] => {
        const field = (value: string, label: string, options: Array<{ value: string; label: string }>, onChange: (next: string) => void) => (
            <div className="sis-ribbon__filter-field" dir="rtl">
                <span className="sis-admission-select-fit">
                    <span className="sis-admission-select-fit__mirror" aria-hidden="true">
                        {options.find((o) => o.value === value)?.label ?? options[0]?.label ?? ''}
                    </span>
                    <SisListSelect
                        value={value}
                        options={options}
                        onChange={onChange}
                        triggerClassName="sis-ops-hub__link px-2 py-1 min-h-0 min-w-0 sis-admission-year-control"
                        dir="rtl"
                        ariaLabel={label}
                    />
                </span>
            </div>
        );
        const setFilter = (patch: Partial<GridFilters>) => setGridFilters((current) => ({ ...current, ...patch }));
        const yearOptions = years.map((year) => ({ value: String(year.id), label: formatAcademicYearOptionLabel(year.name, year.code) }));
        const branchOptions = [{ value: '', label: tt.allBranches }, ...branches.map((b) => ({ value: String(b.id), label: b.name }))];
        const branch = branches.find((b) => String(b.id) === gridFilters.branch) ?? null;
        const departmentOptions = [
            { value: '', label: tt.allDepartments },
            ...(branch === null
                ? [...new Map(branches.flatMap((b) => b.departments.map((d) => [d.id, `${d.name}`] as const))).entries()].map(([id, name]) => ({ value: String(id), label: name }))
                : branch.departments.map((d) => ({ value: String(d.id), label: d.name }))),
        ];
        const classOptions = [{ value: '', label: tt.allClasses }, ...[...new Map(sections.map((s) => [s.class_id, s.class_name] as const)).entries()].map(([id, name]) => ({ value: String(id), label: name }))];
        const sectionOptions = [{ value: '', label: tt.allSections }, ...sisSectionSelectOptions()];
        const filtered = gridFilters.branch !== '' || gridFilters.department !== '' || gridFilters.class !== '' || gridFilters.section !== '';
        const teacherOptions = teachers.map((x) => ({ value: String(x.id), label: x.short_name }));

        return [
            {
                id: 'timetable-view',
                label: tt.viewBy,
                commands: [
                    { id: 'timetable-view-section', label: tt.bySection, icon: LayoutGrid, pressed: view === 'section', onSelect: () => setView('section') },
                    { id: 'timetable-view-teacher', label: tt.byTeacher, icon: UserRound, pressed: view === 'teacher', onSelect: () => setView('teacher') },
                    { id: 'timetable-view-room', label: et.byRoom, icon: DoorOpen, pressed: view === 'room', disabled: (engine?.rooms.length ?? 0) === 0, onSelect: () => setView('room') },
                ],
            },
            {
                id: 'timetable-filters',
                label: tt.ribbonFilters,
                commands: [],
                custom: (
                    <div className="sis-ribbon__filters" dir="rtl">
                        <div className="sis-ribbon__filters-stack sis-ribbon__filters-stack--curriculum sis-ribbon__filters-stack--timetable">
                            {field(yearId === null ? '' : String(yearId), tt.academicYear, yearOptions, changeYear)}
                            {view === 'section' ? (
                                <>
                                    {field(gridFilters.branch, tt.branch, branchOptions, (next) => setFilter({ branch: next, department: '' }))}
                                    {field(gridFilters.department, tt.department, departmentOptions, (next) => setFilter({ department: next }))}
                                    {field(gridFilters.class, tt.class, classOptions, (next) => setFilter({ class: next }))}
                                    {field(gridFilters.section, tt.section, sectionOptions, (next) => setFilter({ section: next }))}
                                </>
                            ) : view === 'room' ? (
                                field(activeRoomId === null ? '' : String(activeRoomId), et.room, (engine?.rooms ?? []).map((r) => ({ value: String(r.id), label: `${r.code} — ${r.name}` })), (next) => setRoomId(Number(next)))
                            ) : (
                                field(activeTeacherId === null ? '' : String(activeTeacherId), tt.teacher, teacherOptions, (next) => setTeacherId(Number(next)))
                            )}
                        </div>
                        {view === 'section' ? (
                            <>
                                <button
                                    type="button"
                                    className="sis-ribbon__item sis-ribbon__item--filter-clear"
                                    data-item-id="timetable-filters-clear"
                                    aria-label={tt.clearFilters}
                                    title={tt.clearFilters}
                                    disabled={!filtered}
                                    onClick={() => setGridFilters(NO_FILTERS)}
                                >
                                    <FilterX className="sis-ribbon__icon" aria-hidden />
                                    <span className="sis-ribbon__label">{tt.clearFilters}</span>
                                </button>
                                <button
                                    type="button"
                                    className="sis-ribbon__item sis-ribbon__item--filter-clear"
                                    data-item-id="timetable-show-all"
                                    aria-label={tt.showAll}
                                    title={tt.showAllHint}
                                    onClick={() => {
                                        setGridFilters(NO_FILTERS);
                                        setFocusId(null);
                                    }}
                                >
                                    <LayoutList className="sis-ribbon__icon" aria-hidden />
                                    <span className="sis-ribbon__label">{tt.showAll}</span>
                                </button>
                            </>
                        ) : null}
                    </div>
                ),
            },
        ];
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [i18n, years, sections, branches, teachers, view, yearId, gridFilters, activeTeacherId, activeRoomId, engine, issueCounts, canPlace, saving, lessonPeriods.length, visibleSectionIds]);
    useRegisterPageRibbon('home', homeRibbonGroups);

    // «تحرير»: one line of commands — building tools · checks · engine. Each icon is its own command and its
    // shape / colour tells the state (errors, blocked, a run working, a version waiting for approval …).
    const activeRun = engine?.runs.find((r) => r.status === 1 || r.status === 2) ?? null;
    const lastRun = engine?.runs[0] ?? null;
    const reviewVersions = engine?.versions.filter((v) => v.status === 2).length ?? 0;
    const blockers = advice?.findings.filter((f) => f.severity === 'blocker').length ?? 0;
    const editRibbonGroups = useMemo((): PageRibbonGroup[] => {
        const noData = engine === null;
        const audit: Pick<PageRibbonCommand, 'icon' | 'iconTone' | 'count'> =
            issueCounts.error > 0
                ? { icon: ShieldAlert, iconTone: 'danger', count: issueCounts.error }
                : issueCounts.warning > 0
                  ? { icon: TriangleAlert, iconTone: 'warning', count: issueCounts.warning }
                  : { icon: ShieldCheck, iconTone: 'ok' };
        const readiness: Pick<PageRibbonCommand, 'icon' | 'iconTone' | 'count'> =
            advice === null
                ? { icon: Gauge }
                : advice.verdict === 'blocked'
                  ? { icon: OctagonX, iconTone: 'danger', count: blockers }
                  : quality?.grade === 'complete'
                    ? { icon: BadgeCheck, iconTone: 'ok' }
                    : quality?.grade === 'infeasible'
                      ? { icon: CircleAlert, iconTone: 'danger' }
                      : { icon: Gauge, iconTone: 'warning' };
        const generate: Pick<PageRibbonCommand, 'icon' | 'iconTone' | 'count'> =
            activeRun !== null
                ? {
                      icon: LoaderCircle,
                      iconTone: 'active',
                      count: activeRun.progress && activeRun.progress.total > 0 ? Math.round((100 * activeRun.progress.placed) / activeRun.progress.total) : undefined,
                  }
                : lastRun?.status === 3
                  ? { icon: WandSparkles, iconTone: 'warning' }
                  : lastRun?.status === 4
                    ? { icon: Wand2, iconTone: 'danger' }
                    : { icon: Wand2 };
        const versions: Pick<PageRibbonCommand, 'icon' | 'iconTone' | 'count'> =
            engine?.status.stale
                ? { icon: FileWarning, iconTone: 'warning' }
                : reviewVersions > 0
                  ? { icon: FileClock, iconTone: 'warning', count: reviewVersions }
                  : engine?.status.effective_version_id
                    ? { icon: FileCheck, iconTone: 'ok' }
                    : { icon: History };

        // Every icon has a coloured outline: its state colour, else the system chrome colour.
        // Each command has its own colour; a state colour (ok / warning / danger / working) replaces it.
        const COLOURS: Record<string, PageRibbonCommand['iconTone']> = {
            'timetable-auto-place': 'amber',
            'timetable-preview': 'steel',
            'timetable-audit': 'forest',
            'timetable-readiness': 'sky',
            'timetable-engine-generate': 'forest',
            'timetable-engine-activities': 'authority',
            'timetable-engine-constraints': 'rose',
            'timetable-engine-availability': 'sky',
            'timetable-engine-versions': 'growth',
            'timetable-engine-settings': 'steel',
        };
        const outlined = (groups: PageRibbonGroup[]): PageRibbonGroup[] =>
            groups.map((group) => ({ ...group, commands: group.commands.map((command) => ({ ...command, iconTone: command.iconTone ?? COLOURS[command.id] ?? 'authority' })) }));

        return outlined([
            {
                id: 'timetable-tools',
                label: tt.ribbonTools,
                commands: [
                    {
                        id: 'timetable-auto-place',
                        label: tt.autoPlace,
                        icon: Sparkles,
                        title: tt.autoPlaceAllHint,
                        disabled: !canPlace || view !== 'section' || visibleSectionIds.length === 0 || lessonPeriods.length === 0 || saving,
                        onSelect: autoPlace,
                    },
                    {
                        id: 'timetable-preview',
                        label: tt.preview,
                        icon: Maximize2,
                        title: tt.previewHint,
                        disabled: lessonPeriods.length === 0 || (view === 'section' && visibleSectionIds.length === 0),
                        onSelect: () => setPreview(true),
                    },
                ],
            },
            {
                id: 'timetable-checks',
                label: et.ribbonChecks,
                commands: [
                    {
                        id: 'timetable-audit',
                        label: tt.audit,
                        ...audit,
                        title: `${tt.auditHint} — ${tt.auditErrors} ${issueCounts.error} · ${tt.auditWarnings} ${issueCounts.warning} · ${tt.auditInfo} ${issueCounts.info}`,
                        onSelect: () => setAuditOpen(true),
                    },
                    {
                        id: 'timetable-readiness',
                        label: tt.readiness,
                        ...readiness,
                        title: advice === null ? tt.readinessHint : `${advice.verdict === 'ready' ? tt.readinessReady : tt.readinessBlocked} — ${tt.readinessHint}`,
                        disabled: advice === null,
                        onSelect: () => setReadinessOpen(true),
                    },
                ],
            },
            {
                id: 'timetable-engine',
                label: et.ribbonEngine,
                commands: [
                    {
                        id: 'timetable-engine-generate',
                        label: et.generate,
                        ...generate,
                        title: activeRun !== null ? et.runStatus[activeRun.status] : lastRun?.status === 3 ? et.runAwaitingReview : et.generateHint,
                        disabled: noData || lessonPeriods.length === 0,
                        onSelect: () => setEngineSheet('generate'),
                    },
                    {
                        id: 'timetable-engine-activities',
                        label: et.activities,
                        icon: Boxes,
                        count: engine !== null && engine.activities.length > 0 ? engine.activities.length : undefined,
                        title: et.activitiesHint,
                        disabled: noData,
                        onSelect: () => setEngineSheet('activities'),
                    },
                    {
                        id: 'timetable-engine-constraints',
                        label: et.constraints,
                        icon: Scale,
                        count: engine !== null && engine.rules.length > 0 ? engine.rules.length : undefined,
                        title: et.constraintsHint,
                        disabled: noData,
                        onSelect: () => setEngineSheet('constraints'),
                    },
                    {
                        id: 'timetable-engine-availability',
                        label: et.availability,
                        icon: CalendarX,
                        title: et.availabilityHint,
                        disabled: noData || lessonPeriods.length === 0,
                        onSelect: () => setEngineSheet('availability'),
                    },
                    {
                        id: 'timetable-engine-versions',
                        label: et.versions,
                        ...versions,
                        title: engine?.status.stale ? et.staleBanner : reviewVersions > 0 ? et.versionsAwaiting : et.versionsHint,
                        disabled: noData,
                        onSelect: () => setEngineSheet('versions'),
                    },
                    {
                        id: 'timetable-engine-settings',
                        label: et.settings,
                        icon: Settings2,
                        title: et.settingsHint,
                        disabled: noData || !authorization.can_manage_constraints,
                        onSelect: () => setEngineSheet('settings'),
                    },
                ],
            },
        ]);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [tt, canPlace, view, visibleSectionIds, lessonPeriods.length, saving, issueCounts, advice, quality, engine]);
    useRegisterPageRibbon('edit', editRibbonGroups);

    const addRibbonGroups = useMemo(
        (): PageRibbonGroup[] =>
            authorization.can_manage_periods
                ? [
                      {
                          id: 'timetable-setup',
                          label: tt.ribbonSetup,
                          commands: [{ id: 'timetable-periods', label: tt.periodsSetup, icon: CalendarClock, title: tt.periodsSetupHint, onSelect: () => setPeriodsOpen(true) }],
                      },
                  ]
                : [],
        [authorization.can_manage_periods, tt],
    );
    useRegisterPageRibbon('add', addRibbonGroups);

    // ── Grid ─────────────────────────────────────────────────────────────────
    const lessonCard = (s: Schedule, line: string, interactive: boolean) => {
        const severity = lessonSeverity.get(s.id);
        // A locked lesson opens (to unlock) but is not dragged.
        const draggable = interactive && s.locked !== true;
        const shared = (sharedCells.get(`${s.section_id}:${cellKey(s.day_of_week, s.period_id)}`) ?? 1) - 1;
        const markers = [
            s.group_id ? groupNames.get(s.group_id) ?? et.group : null,
            s.co_teacher_id ? `+ ${teacherNames.get(s.co_teacher_id) ?? ''}` : null,
            s.week_no ? `${et.week} ${s.week_no}` : null,
            shared > 0 ? `+${shared}` : null,
        ].filter((m): m is string => m !== null);
        const className = [
            'sis-timetable-card',
            draggable ? 'sis-timetable-card--draggable' : '',
            severity !== undefined ? `sis-timetable-card--${severity}` : '',
            highlight.includes(s.id) ? 'sis-timetable-card--highlight' : '',
        ]
            .filter(Boolean)
            .join(' ');
        const subjectName = subjectNames.get(s.subject_id) ?? `#${s.subject_id}`;

        return (
            <div
                className={className}
                style={subjectStyles.get(s.subject_id)}
                title={`${subjectName} — ${line}${markers.length > 0 ? ` — ${markers.join(' · ')}` : ''}${s.locked ? ` — ${et.locked}` : ''}`}
                draggable={draggable}
                onDragStart={draggable ? (event) => startDrag(event, { sectionId: s.section_id, subjectId: s.subject_id, teacherId: s.teacher_id, scheduleId: s.id }) : undefined}
                onDragEnd={endDrag}
            >
                <span className="sis-timetable-card__subject">
                    {s.locked ? <Lock aria-label={et.locked} width={11} height={11} /> : null}
                    {subjectName}
                    {practical.has(s.subject_id) ? <span className="sis-timetable-card__badge">{tt.practical}</span> : null}
                </span>
                <span className="sis-timetable-card__line">{markers.length > 0 ? `${line} · ${markers.join(' · ')}` : line}</span>
                {interactive ? (
                    <button type="button" className="sis-timetable-card__open" aria-label={`${tt.editLesson}: ${subjectName}`} onClick={() => setEditId(s.id)} />
                ) : null}
                {draggable && authorization.can_cancel ? (
                    <button type="button" className="sis-timetable-card__remove" aria-label={tt.removeLesson} title={tt.removeLesson} disabled={saving} onClick={() => void unplace(s.id)}>
                        <X aria-hidden />
                    </button>
                ) : null}
            </div>
        );
    };

    const previewCell = (sectionId: number, day: number, period: Period) => {
        const placed = sectionCells.get(sectionId)?.get(cellKey(day, period.id));

        return (
            <td key={`${sectionId}:${cellKey(day, period.id)}`} className="sis-timetable-cell">
                {placed !== undefined ? lessonCard(placed, teacherNames.get(placed.teacher_id) ?? '', false) : null}
            </td>
        );
    };

    const sectionCell = (sectionId: number, day: number, period: Period) => {
        const key = `${sectionId}:${cellKey(day, period.id)}`;
        const placed = sectionCells.get(sectionId)?.get(cellKey(day, period.id));
        const state = cellState(sectionId, day, period.id, drag);
        const className = [
            'sis-timetable-cell',
            state !== 'idle' ? `sis-timetable-cell--${state}` : '',
            overCell === key && (state === 'free' || state === 'swap') ? 'sis-timetable-cell--over' : '',
        ]
            .filter(Boolean)
            .join(' ');

        return (
            <td
                key={key}
                className={className}
                title={state === 'busy' ? tt.teacherBusy : state === 'swap' ? tt.swapHint : undefined}
                onDragOver={(event) => {
                    if (drag !== null && (state === 'free' || state === 'swap')) {
                        event.preventDefault();
                        setOverCell(key);
                    }
                }}
                onDragLeave={() => setOverCell((current) => (current === key ? null : current))}
                onDrop={(event) => {
                    event.preventDefault();
                    const payload = drag;
                    endDrag();
                    if (payload !== null) {
                        place(payload, sectionId, day, period.id);
                    }
                }}
            >
                {placed !== undefined ? (
                    lessonCard(placed, teacherNames.get(placed.teacher_id) ?? '', authorization.can_update && !saving)
                ) : canPlace ? (
                    <button
                        type="button"
                        className="sis-timetable-cell__add"
                        aria-label={`${tt.chooseLesson}: ${sectionLabel(sectionId)} — ${dayOfWeekLabel(day)} — ${periodLabel(period.id)}`}
                        onClick={() => {
                            setFocusId(sectionId);
                            setPickCell({ sectionId, day, periodId: period.id });
                        }}
                    >
                        +
                    </button>
                ) : null}
            </td>
        );
    };

    const teacherCell = (day: number, period: Period) => {
        const placed = teacherCells.get(cellKey(day, period.id));

        return (
            <td key={cellKey(day, period.id)} className="sis-timetable-cell">
                {placed !== undefined ? (
                    <button
                        type="button"
                        className="sis-timetable-cell__open"
                        onClick={() => {
                            setGridFilters({ ...NO_FILTERS, class: String(sectionsById.get(placed.section_id)?.class_id ?? ''), section: resolveSisSectionCode(placed.section_id, sections) });
                            setFocusId(placed.section_id);
                            setView('section');
                            setHighlight([placed.id]);
                        }}
                    >
                        {lessonCard(placed, sectionLabel(placed.section_id), false)}
                    </button>
                ) : null}
            </td>
        );
    };

    /** Lessons 1 … n with their times; breaks are narrow columns showing their minutes. */
    const gridTable = (cell: (day: number, period: Period) => ReactElement, compact: boolean) => (
        <table className={compact ? 'sis-timetable-table sis-timetable-table--compact' : 'sis-timetable-table'}>
            <colgroup>
                <col className="sis-timetable-table__day-col" />
                {sortedPeriods.map((period) =>
                    period.period_type === LESSON ? (
                        <col key={period.id} />
                    ) : (
                        <col
                            key={period.id}
                            className="sis-timetable-table__break-col"
                            style={{ width: `${(minutesOf(period.end_time) - minutesOf(period.start_time)) * BREAK_REM_PER_MINUTE}rem` }}
                        />
                    ),
                )}
            </colgroup>
            <thead>
                <tr>
                    <th className="sis-timetable-grid__corner">{tt.day}</th>
                    {sortedPeriods.map((period) =>
                        period.period_type === LESSON ? (
                            <th key={period.id}>
                                <span className="sis-timetable-grid__period">
                                    {tt.periodLabel} {lessonNumber.get(period.id)}
                                </span>
                                <span className="sis-timetable-grid__time">
                                    {clock12(period.start_time)} – {clock12(period.end_time)}
                                </span>
                            </th>
                        ) : (
                            <th key={period.id} className={`sis-timetable-grid__break sis-timetable-break--${breakTone(minutesOf(period.end_time) - minutesOf(period.start_time))}`} title={`${tt.breakLabel} ${clock12(period.start_time)} – ${clock12(period.end_time)}`}>
                                <span className="sis-timetable-grid__break-minutes">
                                    {minutesOf(period.end_time) - minutesOf(period.start_time)}
                                    {tt.minutesShort}
                                </span>
                            </th>
                        ),
                    )}
                </tr>
            </thead>
            <tbody>
                {DAYS.map((day, row) => (
                    <tr key={day}>
                        <th scope="row" className="sis-timetable-grid__day">
                            {dayOfWeekLabel(day)}
                        </th>
                        {sortedPeriods.map((period) =>
                            period.period_type !== LESSON ? (
                                // One band per break, down the whole week (read top → bottom).
                                row === 0 ? (
                                    <td key={period.id} className={`sis-timetable-cell sis-timetable-cell--break sis-timetable-break--${breakTone(minutesOf(period.end_time) - minutesOf(period.start_time))}`} rowSpan={DAYS.length}>
                                        <span className="sis-timetable-cell__break-label">
                                            {tt.breakLabel} · {minutesOf(period.end_time) - minutesOf(period.start_time)} {tt.minutesShort}
                                        </span>
                                    </td>
                                ) : null
                            ) : (
                                cell(day, period)
                            ),
                        )}
                    </tr>
                ))}
            </tbody>
        </table>
    );

    const sectionBoard = (section: Section, students: number, compact: boolean, readOnly = false) => (
        <section
            key={section.id}
            className={`sis-timetable-board${section.id === focusSectionId && !singleGrid && !readOnly ? ' sis-timetable-board--focus' : ''}`}
            aria-busy={saving}
            onClick={readOnly ? undefined : () => setFocusId(section.id)}
        >
            <header className="sis-timetable-board__head">
                <span className="sis-timetable-board__title">{sectionLabel(section.id)}</span>
                <span className="sis-timetable-board__meta">
                    {students > 0 ? tt.studentsCount.replace('{n}', String(students)) : null}
                    {students > 0 ? ' · ' : null}
                    <bdi dir="ltr">
                        {sectionCells.get(section.id)?.size ?? 0} / {lessonsOf(section).reduce((sum, l) => sum + (l.required ?? 0), 0)}
                    </bdi>
                </span>
            </header>
            <div className="sis-timetable-grid">{gridTable((day, period) => (readOnly ? previewCell : sectionCell)(section.id, day, period), compact)}</div>
        </section>
    );

    const a3Sheets = useMemo(() => {
        const ordered = [...sections].sort(
            (a, b) => a.class_id - b.class_id || resolveSisSectionCode(a.id, sections).localeCompare(resolveSisSectionCode(b.id, sections)) || a.id - b.id,
        );
        const sheets: Section[][] = [];
        for (let i = 0; i < ordered.length; i += A3_BOARDS_PER_SHEET) {
            sheets.push(ordered.slice(i, i + A3_BOARDS_PER_SHEET));
        }

        return sheets;
    }, [sections]);
    const yearName = years.find((y) => y.id === yearId)?.name ?? '';

    const tray = (
        <aside
            className="sis-timetable-tray"
            aria-label={tt.tray}
            onDragOver={(event) => {
                if (drag?.scheduleId != null && authorization.can_cancel) {
                    event.preventDefault();
                }
            }}
            onDrop={(event) => {
                event.preventDefault();
                const payload = drag;
                endDrag();
                if (payload?.scheduleId != null) {
                    void unplace(payload.scheduleId);
                }
            }}
        >
            <header className="sis-timetable-tray__head">
                <span className="sis-timetable-tray__title">{tt.tray}</span>
                <span className="sis-timetable-tray__count" dir="ltr">
                    {trayLessons.length}
                </span>
            </header>
            <p className="sis-timetable-tray__section">{sectionLabel(focusSectionId)}</p>
            <div className="sis-timetable-progress" aria-label={tt.progress}>
                <span className="sis-timetable-progress__label">
                    {tt.progress}:{' '}
                    <bdi dir="ltr">
                        {placedTotal} / {requiredTotal}
                    </bdi>
                </span>
                <span className="sis-timetable-progress__bar">
                    <span style={{ width: `${progress}%` }} />
                </span>
            </div>
            {canPlace ? <p className="sis-timetable-tray__hint">{singleGrid ? tt.trayHint : tt.focusHint}</p> : null}
            <div className="sis-timetable-tray__list">
                {focusLessons.length === 0 ? (
                    <p className="sis-timetable-tray__empty">{tt.trayNoLessons}</p>
                ) : trayLessons.length === 0 ? (
                    <p className="sis-timetable-tray__empty">{tt.trayEmpty}</p>
                ) : (
                    trayLessons.map((lesson) => (
                        <div
                            key={lesson.key}
                            className={`sis-timetable-card sis-timetable-card--tray${canPlace ? ' sis-timetable-card--draggable' : ''}`}
                            style={subjectStyles.get(lesson.subjectId)}
                            draggable={canPlace && !saving && focusSectionId !== null}
                            onDragStart={(event) =>
                                focusSectionId !== null && startDrag(event, { sectionId: focusSectionId, subjectId: lesson.subjectId, teacherId: lesson.teacherId, scheduleId: null })
                            }
                            onDragEnd={endDrag}
                        >
                            <span className="sis-timetable-card__subject">
                                {lesson.subjectName}
                                {practical.has(lesson.subjectId) ? <span className="sis-timetable-card__badge">{tt.practical}</span> : null}
                            </span>
                            <span className="sis-timetable-card__line">{lesson.teacherName}</span>
                            <span className="sis-timetable-card__count" title={lesson.required === null ? tt.weeklyUnknown : `${lesson.required} ${tt.weeklyHours}`}>
                                {lesson.required === null ? `${lesson.placed} / ؟` : `${tt.remaining} ${lesson.required - lesson.placed}`}
                            </span>
                        </div>
                    ))
                )}
            </div>
        </aside>
    );

    const pickSection = pickCell === null ? null : (sectionsById.get(pickCell.sectionId) ?? null);
    const picking =
        pickCell === null
            ? []
            : lessonsOf(pickSection)
                  .filter((l) => l.required === null || l.placed < l.required)
                  .map((lesson) => ({ lesson, state: cellState(pickCell.sectionId, pickCell.day, pickCell.periodId, { sectionId: pickCell.sectionId, teacherId: lesson.teacherId, scheduleId: null }) }));
    const editing = editId === null ? null : (schedules.find((s) => s.id === editId) ?? null);
    const swapFrom = swapPair === null ? null : (schedules.find((s) => s.id === swapPair.from) ?? null);
    const swapTo = swapPair === null ? null : (schedules.find((s) => s.id === swapPair.to) ?? null);

    const notice = (title: string, hint: string) => (
        <div className="sis-timetable-empty">
            <Clock aria-hidden />
            <p className="sis-timetable-empty__title">{title}</p>
            <p className="sis-timetable-empty__hint">{hint}</p>
            {title === tt.noPeriodsTitle && authorization.can_manage_periods ? (
                <Button type="button" onClick={() => setPeriodsOpen(true)}>
                    {tt.periodsSetup}
                </Button>
            ) : null}
        </div>
    );

    const sectionView =
        sections.length === 0 ? (
            notice(tt.noSectionsTitle, tt.noSectionsHint)
        ) : visibleSectionIds.length === 0 ? (
            notice(tt.noMatch, '')
        ) : (
            <div className={`sis-timetable-layout${singleGrid ? '' : ' sis-timetable-layout--groups'}`}>
                <div className={`sis-timetable-boards${singleGrid ? ' sis-timetable-boards--single' : ''}`}>
                    {groups.map((group) =>
                        singleGrid ? (
                            <Fragment key={group.key}>{group.sections.map(({ section, students }) => sectionBoard(section, students, false))}</Fragment>
                        ) : (
                            <div key={group.key} className="sis-timetable-group">
                                <h2 className="sis-timetable-group__title">
                                    {group.title}
                                    <span className="sis-timetable-group__count">{tt.sectionsCount.replace('{n}', String(group.sections.length))}</span>
                                </h2>
                                <div className="sis-timetable-group__boards">{group.sections.map(({ section, students }) => sectionBoard(section, students, true))}</div>
                            </div>
                        ),
                    )}
                </div>
                {tray}
            </div>
        );

    const teacherView = (
        <div className="sis-timetable-layout sis-timetable-layout--wide">
            <div className="sis-timetable-boards sis-timetable-boards--single">
                <section className="sis-timetable-board" aria-busy={saving}>
                    <header className="sis-timetable-board__head">
                        <span className="sis-timetable-board__title">{teacherNames.get(activeTeacherId ?? 0) ?? tt.pickTeacher}</span>
                        <span className="sis-timetable-board__meta">
                            {tt.teacherLoad}: <bdi dir="ltr">{teacherCells.size}</bdi> {tt.lessonsUnit}
                        </span>
                    </header>
                    <div className="sis-timetable-grid">{gridTable(teacherCell, false)}</div>
                </section>
            </div>
        </div>
    );

    const activeRoom = engine?.rooms.find((r) => r.id === activeRoomId) ?? null;
    const roomView = (
        <div className="sis-timetable-layout sis-timetable-layout--wide">
            <div className="sis-timetable-boards sis-timetable-boards--single">
                <section className="sis-timetable-board" aria-busy={saving}>
                    <header className="sis-timetable-board__head">
                        <span className="sis-timetable-board__title">{activeRoom === null ? et.noRooms : `${activeRoom.code} — ${activeRoom.name}`}</span>
                        <span className="sis-timetable-board__meta">
                            <bdi dir="ltr">{roomCells.size}</bdi> {tt.lessonsUnit}
                        </span>
                    </header>
                    <div className="sis-timetable-grid">
                        {gridTable((day, period) => {
                            const placed = roomCells.get(cellKey(day, period.id));

                            return (
                                <td key={cellKey(day, period.id)} className="sis-timetable-cell">
                                    {placed !== undefined ? lessonCard(placed, `${sectionLabel(placed.section_id)} · ${teacherNames.get(placed.teacher_id) ?? ''}`, false) : null}
                                </td>
                            );
                        }, false)}
                    </div>
                </section>
            </div>
        </div>
    );

    return (
        <>
            <Head title={tt.title} />
            <div className="sis-ops-hub sis-admission-page sis-timetable-page flex h-full min-h-0 flex-col overflow-hidden pb-4" dir="rtl" lang="ar">
                {authorization.can_create ? null : <p className="sis-branches-page__notice">{tt.readOnly}</p>}
                {yearId === null ? <p className="sis-branches-page__notice">{tt.noYear}</p> : null}
                {viewingVersion !== null ? (
                    <p className="sis-branches-page__notice">
                        {et.viewingVersion.replace('{no}', String(viewingVersion.version_no)).replace('{name}', viewingVersion.name)}{' '}
                        <button type="button" className="sis-ops-hub__link" onClick={() => router.get('/timetable', { academic_year_id: yearId }, { preserveScroll: true })}>
                            {et.backToWorking}
                        </button>
                    </p>
                ) : null}
                {engine?.status.stale ? <p className="sis-branches-page__notice">{et.staleBanner}</p> : null}

                <div className="sis-admission-page-body sis-timetable-body">
                    {yearId === null ? null : lessonPeriods.length === 0 ? notice(tt.noPeriodsTitle, tt.noPeriodsHint) : (
                        <>
                            <div className="sis-timetable-toolbar">
                                {drag !== null ? (
                                    <span className="sis-timetable-legend">
                                        <span className="sis-timetable-legend__item sis-timetable-legend__item--free">{tt.legendFree}</span>
                                        <span className="sis-timetable-legend__item sis-timetable-legend__item--busy">{tt.legendBusy}</span>
                                    </span>
                                ) : saving ? (
                                    <span className="sis-timetable-toolbar__meta">{tt.saving}</span>
                                ) : null}
                            </div>
                            {view === 'section' ? sectionView : view === 'room' ? roomView : teacherView}
                        </>
                    )}
                </div>
            </div>

            {preview ? (
                <div ref={previewRef} className="sis-timetable-preview" role="dialog" aria-modal="true" aria-label={tt.preview} dir="rtl" lang="ar">
                    <header className="sis-timetable-preview__head">
                        <span className="sis-timetable-preview__title">
                            {tt.title}
                            {view === 'teacher' ? ` — ${teacherNames.get(activeTeacherId ?? 0) ?? ''}` : ''}
                        </span>
                        <span className="sis-timetable-preview__actions">
                            <Button
                                type="button"
                                variant="outline"
                                className="sis-timetable-preview__close"
                                aria-pressed={previewA3}
                                title={tt.a3LayoutHint}
                                onClick={() => setPreviewA3((current) => !current)}
                            >
                                <LayoutList aria-hidden />
                                {previewA3 ? tt.a3LayoutBack : tt.a3Layout}
                            </Button>
                            {previewA3 ? (
                                <Button type="button" variant="outline" className="sis-timetable-preview__close" onClick={() => window.print()}>
                                    <Printer aria-hidden />
                                    {tt.print}
                                </Button>
                            ) : null}
                            <Button type="button" variant="outline" className="sis-timetable-preview__close" onClick={closePreview}>
                                <Minimize2 aria-hidden />
                                {tt.previewClose}
                            </Button>
                        </span>
                    </header>
                    <div className={`sis-timetable-preview__body${previewA3 ? ' sis-timetable-preview__body--a3' : ''}`}>
                        {previewA3 ? (
                            <div className="sis-timetable-a3">
                                {a3Sheets.map((sheet, index) => (
                                    <article key={index} className="sis-timetable-a3__sheet" aria-label={`${tt.a3Sheet} ${index + 1}`}>
                                        <header className="sis-timetable-a3__head">
                                            <span className="sis-timetable-a3__title">{tt.title}</span>
                                            <span>{yearName}</span>
                                            <span>
                                                {tt.a3Sheet} {index + 1} / {a3Sheets.length}
                                            </span>
                                        </header>
                                        <div className="sis-timetable-a3__boards">{sheet.map((section) => sectionBoard(section, 0, true, true))}</div>
                                    </article>
                                ))}
                            </div>
                        ) : view === 'teacher' ? (
                            <section className="sis-timetable-board">
                                <header className="sis-timetable-board__head">
                                    <span className="sis-timetable-board__title">{teacherNames.get(activeTeacherId ?? 0) ?? ''}</span>
                                </header>
                                <div className="sis-timetable-grid">
                                    {gridTable((day, period) => {
                                        const placed = teacherCells.get(cellKey(day, period.id));

                                        return (
                                            <td key={cellKey(day, period.id)} className="sis-timetable-cell">
                                                {placed !== undefined ? lessonCard(placed, sectionLabel(placed.section_id), false) : null}
                                            </td>
                                        );
                                    }, false)}
                                </div>
                            </section>
                        ) : (
                            groups.map((group) => (
                                <div key={group.key} className="sis-timetable-group">
                                    {singleGrid ? null : <h2 className="sis-timetable-group__title">{group.title}</h2>}
                                    <div className={singleGrid ? 'sis-timetable-preview__single' : 'sis-timetable-group__boards'}>
                                        {group.sections.map(({ section, students }) => sectionBoard(section, students, !singleGrid, true))}
                                    </div>
                                </div>
                            ))
                        )}
                    </div>
                </div>
            ) : null}

            {pickCell !== null && pickSection !== null ? (
                <RegistrySheetDialog
                    title={`${tt.chooseLesson} — ${dayOfWeekLabel(pickCell.day)} · ${periodLabel(pickCell.periodId)}`}
                    className="sis-branches-sheet sis-timetable-sheet sis-timetable-pick-sheet"
                    onClose={() => setPickCell(null)}
                >
                    <SheetSection id="timetable-pick" title={sectionLabel(pickSection.id)}>
                        <div className="sis-admission-sheet__row sis-admission-sheet__row--full sis-branches-sheet__row">
                            <div className="sis-timetable-pick sis-branches-field--wide">
                                {picking.length === 0 ? (
                                    <p className="sis-branches-empty">{tt.chooseLessonEmpty}</p>
                                ) : (
                                    picking.map(({ lesson, state }) => (
                                        <button
                                            key={lesson.key}
                                            type="button"
                                            className="sis-timetable-card sis-timetable-card--pick"
                                            style={subjectStyles.get(lesson.subjectId)}
                                            disabled={state !== 'free' || saving}
                                            title={state === 'busy' ? tt.teacherBusy : undefined}
                                            onClick={() => {
                                                place({ sectionId: pickCell.sectionId, subjectId: lesson.subjectId, teacherId: lesson.teacherId, scheduleId: null }, pickCell.sectionId, pickCell.day, pickCell.periodId);
                                                setPickCell(null);
                                            }}
                                        >
                                            <span className="sis-timetable-card__subject">
                                                {lesson.subjectName}
                                                {practical.has(lesson.subjectId) ? <span className="sis-timetable-card__badge">{tt.practical}</span> : null}
                                            </span>
                                            <span className="sis-timetable-card__line">{state === 'busy' ? `${lesson.teacherName} · ${tt.teacherBusy}` : lesson.teacherName}</span>
                                        </button>
                                    ))
                                )}
                            </div>
                        </div>
                    </SheetSection>
                    <div className="sis-admission-sheet__actions">
                        <Button type="button" variant="outline" onClick={() => setPickCell(null)}>
                            {tt.close}
                        </Button>
                    </div>
                </RegistrySheetDialog>
            ) : null}

            {editing !== null && yearId !== null ? (
                <LessonSheet
                    key={editing.id}
                    lesson={editing}
                    when={`${dayOfWeekLabel(editing.day_of_week)} · ${periodLabel(editing.period_id)} · ${sectionLabel(editing.section_id)}`}
                    subjectOptions={[
                        ...new Map([
                            ...lessonsOf(sectionsById.get(editing.section_id) ?? null).map((l) => [l.subjectId, l.subjectName] as const),
                            [editing.subject_id, subjectNames.get(editing.subject_id) ?? `#${editing.subject_id}`] as const,
                        ]).entries(),
                    ]}
                    teachers={teachers}
                    teacherSubjectKeys={teacherSubjectKeys}
                    isBusy={(teacher) => {
                        const holder = teacherBusy.get(teacherSlotKey(teacher, editing.day_of_week, editing.period_id));

                        return holder !== undefined && holder.id !== editing.id;
                    }}
                    saving={saving}
                    canCancel={authorization.can_cancel}
                    onSave={async (subjectId, teacher) => {
                        if (await run('patch', `/timetable/schedules/${editing.id}`, lessonBody({ ...editing, subject_id: subjectId, teacher_id: teacher }))) {
                            setEditId(null);
                        }
                    }}
                    onShift={(direction) => void run('post', `/timetable/schedules/${editing.id}/shift`, { direction })}
                    onDelete={async () => {
                        if (await unplace(editing.id)) {
                            setEditId(null);
                        }
                    }}
                    onClose={() => setEditId(null)}
                    extra={
                        engineCtx !== null ? (
                            <LessonEngineTools
                                ctx={engineCtx}
                                lesson={editing}
                                suggestions={moveSuggestions}
                                substitutes={substitutes}
                                periodLabel={periodLabel}
                                canLock={authorization.can_update}
                                canSubstitute={authorization.can_substitute}
                                onMove={async (day, periodId) => {
                                    if (await run('patch', `/timetable/schedules/${editing.id}`, lessonBody({ ...editing, day_of_week: day, period_id: periodId }))) {
                                        setEditId(null);
                                    }
                                }}
                                onSwap={async (withId) => {
                                    if (await run('post', `/timetable/schedules/${editing.id}/swap`, { with_schedule_id: withId })) {
                                        setEditId(null);
                                    }
                                }}
                            />
                        ) : null
                    }
                />
            ) : null}

            {engineCtx !== null && engineSheet === 'generate' ? (
                <GenerateSheet
                    ctx={engineCtx}
                    visibleSectionIds={visibleSectionIds}
                    focusSectionId={view === 'section' ? focusSectionId : null}
                    focusTeacherId={view === 'teacher' ? activeTeacherId : null}
                    preview={{
                        sections: advice?.totals.sections ?? sections.length,
                        teachers: advice?.totals.teachers ?? teachers.length,
                        lessons: advice?.totals.weekly_lessons ?? 0,
                        locked: schedules.filter((s) => s.locked === true).length,
                    }}
                    runDetail={runDetail}
                    onClose={() => setEngineSheet(null)}
                />
            ) : null}
            {engineCtx !== null && engineSheet === 'activities' ? (
                <ActivitiesSheet ctx={engineCtx} sectionId={view === 'section' ? focusSectionId : null} onClose={() => setEngineSheet(null)} />
            ) : null}
            {engineCtx !== null && engineSheet === 'constraints' ? <ConstraintsSheet ctx={engineCtx} onClose={() => setEngineSheet(null)} /> : null}
            {engineCtx !== null && engineSheet === 'availability' ? <AvailabilitySheet ctx={engineCtx} onClose={() => setEngineSheet(null)} /> : null}
            {engineCtx !== null && engineSheet === 'versions' ? <VersionsSheet ctx={engineCtx} comparison={comparison} onClose={() => setEngineSheet(null)} /> : null}
            {engineCtx !== null && engineSheet === 'settings' ? <SettingsSheet ctx={engineCtx} onClose={() => setEngineSheet(null)} /> : null}

            {auditOpen ? (
                <AuditSheet issues={issues} sectionId={view === 'section' ? focusSectionId : null} text={issueText} onGo={goToIssue} onClose={() => setAuditOpen(false)} />
            ) : null}

            {readinessOpen && advice !== null && quality !== null ? (
                <ReadinessSheet
                    advice={advice}
                    quality={quality}
                    workload={workload}
                    sectionLabel={sectionLabel}
                    teacherName={(id) => teacherNames.get(id) ?? `#${id}`}
                    subjectName={(id) => subjectNames.get(id) ?? `#${id}`}
                    onClose={() => setReadinessOpen(false)}
                />
            ) : null}

            <ConfirmDialog
                open={swapFrom !== null && swapTo !== null}
                title={tt.swapTitle}
                description={
                    swapFrom === null || swapTo === null
                        ? ''
                        : tt.swapConfirm
                              .replace('{a}', `${subjectNames.get(swapFrom.subject_id) ?? ''} (${dayOfWeekLabel(swapFrom.day_of_week)} · ${periodLabel(swapFrom.period_id)})`)
                              .replace('{b}', `${subjectNames.get(swapTo.subject_id) ?? ''} (${dayOfWeekLabel(swapTo.day_of_week)} · ${periodLabel(swapTo.period_id)})`)
                }
                confirmLabel={tt.swapAction}
                onConfirm={() => {
                    if (swapPair !== null) {
                        void run('post', `/timetable/schedules/${swapPair.from}/swap`, { with_schedule_id: swapPair.to });
                    }
                    setSwapPair(null);
                }}
                onOpenChange={(open) => {
                    if (!open) {
                        setSwapPair(null);
                    }
                }}
            />

            {periodsOpen ? <PeriodsSheet periods={sortedPeriods} lessonNumber={lessonNumber} onClose={() => setPeriodsOpen(false)} /> : null}
        </>
    );
}

/** «تعديل الحصة»: change subject / teacher, slide the lesson (زحف), or take it off the grid. */
function LessonSheet({
    lesson,
    when,
    subjectOptions,
    teachers,
    teacherSubjectKeys,
    isBusy,
    saving,
    canCancel,
    onSave,
    onShift,
    onDelete,
    onClose,
    extra = null,
}: {
    lesson: Schedule;
    when: string;
    subjectOptions: Array<[number, string]>;
    teachers: Teacher[];
    teacherSubjectKeys: Set<string>;
    isBusy: (teacherId: number) => boolean;
    saving: boolean;
    canCancel: boolean;
    onSave: (subjectId: number, teacherId: number) => void;
    onShift: (direction: 1 | -1) => void;
    onDelete: () => void;
    onClose: () => void;
    /** Engine tools (lock, move suggestions, substitutes) under the lesson fields. */
    extra?: ReactNode;
}) {
    const tt = t().timetable;
    const [subjectId, setSubjectId] = useState(String(lesson.subject_id));
    const [teacherId, setTeacherId] = useState(String(lesson.teacher_id));

    // Teachers who may teach the chosen subject; busy ones are listed but marked.
    const teacherOptions = teachers
        .filter((x) => teacherSubjectKeys.has(lessonKey(Number(subjectId), x.id)) || x.id === lesson.teacher_id)
        .map((x) => ({ value: String(x.id), label: isBusy(x.id) ? `${x.short_name} — ${tt.teacherBusyShort}` : x.short_name }));
    const teacherValid = teacherOptions.some((o) => o.value === teacherId) && !isBusy(Number(teacherId));
    const changed = subjectId !== String(lesson.subject_id) || teacherId !== String(lesson.teacher_id);
    const subjectSelect = subjectOptions.map(([id, name]) => ({ value: String(id), label: name }));

    return (
        <RegistrySheetDialog title={tt.editLesson} className="sis-branches-sheet sis-timetable-sheet sis-timetable-lesson-sheet" onClose={onClose}>
            <SheetSection id="timetable-lesson" title={when}>
                <div className="sis-admission-sheet__row sis-admission-sheet__row--full sis-branches-sheet__row">
                    <RegistryListField
                        label={tt.lessonSubject}
                        editing
                        value={subjectId}
                        display={subjectSelect.find((o) => o.value === subjectId)?.label ?? ''}
                        options={subjectSelect}
                        onChange={(next) => {
                            setSubjectId(next);
                            if (!teacherSubjectKeys.has(lessonKey(Number(next), Number(teacherId)))) {
                                const first = teachers.find((x) => teacherSubjectKeys.has(lessonKey(Number(next), x.id)) && !isBusy(x.id));
                                setTeacherId(first === undefined ? '' : String(first.id));
                            }
                        }}
                        fieldClassName="sis-branches-field--wide"
                    />
                    <RegistryListField
                        label={tt.lessonTeacher}
                        editing
                        value={teacherId}
                        display={teacherOptions.find((o) => o.value === teacherId)?.label ?? ''}
                        options={teacherOptions.length === 0 ? [{ value: '', label: tt.noOtherTeacher }] : teacherOptions}
                        onChange={setTeacherId}
                        fieldClassName="sis-branches-field--wide"
                    />
                    <p className="sis-timetable-sheet__hint sis-branches-field--wide">{tt.shiftHint}</p>
                </div>
            </SheetSection>
            {extra}
            <div className="sis-admission-sheet__actions">
                <Button type="button" variant="outline" onClick={onClose}>
                    {tt.close}
                </Button>
                <Button type="button" disabled={saving} onClick={() => onShift(-1)} title={tt.shiftHint}>
                    <ChevronsRight aria-hidden />
                    {tt.shiftEarlier}
                </Button>
                <Button type="button" disabled={saving} onClick={() => onShift(1)} title={tt.shiftHint}>
                    {tt.shiftLater}
                    <ChevronsLeft aria-hidden />
                </Button>
                {canCancel ? (
                    <Button type="button" disabled={saving} onClick={onDelete}>
                        <Trash2 aria-hidden />
                        {tt.deleteLesson}
                    </Button>
                ) : null}
                <Button type="button" disabled={saving || !changed || !teacherValid} onClick={() => onSave(Number(subjectId), Number(teacherId))}>
                    {saving ? tt.saving : tt.saveLesson}
                </Button>
            </div>
        </RegistrySheetDialog>
    );
}

/** «تدقيق الجدول»: every issue of the timetable, worst first; «انتقال» opens its place on the grid. */
function AuditSheet({
    issues,
    sectionId,
    text,
    onGo,
    onClose,
}: {
    issues: Issue[];
    sectionId: number | null;
    text: (issue: Issue) => string;
    onGo: (issue: Issue) => void;
    onClose: () => void;
}) {
    const tt = t().timetable;
    const [onlySection, setOnlySection] = useState(false);
    const shown = onlySection && sectionId !== null ? issues.filter((i) => i.section_id === sectionId) : issues;
    const titles: Record<Severity, string> = { error: tt.auditErrors, warning: tt.auditWarnings, info: tt.auditInfo };

    return (
        <RegistrySheetDialog title={tt.audit} className="sis-branches-sheet sis-timetable-sheet sis-timetable-audit-sheet" onClose={onClose}>
            <div className="sis-timetable-audit__bar">
                {SEVERITIES.map((severity) => (
                    <span key={severity} className={`sis-timetable-badge sis-timetable-badge--${severity}`}>
                        {titles[severity]}: <bdi dir="ltr">{shown.filter((i) => i.severity === severity).length}</bdi>
                    </span>
                ))}
                {sectionId !== null ? (
                    <label className="sis-timetable-audit__filter">
                        <input type="checkbox" checked={onlySection} onChange={(e) => setOnlySection(e.target.checked)} />
                        {tt.auditFilterSection}
                    </label>
                ) : null}
            </div>
            <div className="sis-timetable-audit__list">
                {shown.length === 0 ? (
                    <p className="sis-timetable-audit__clean">{tt.auditClean}</p>
                ) : (
                    SEVERITIES.map((severity) => {
                        const group = shown.filter((i) => i.severity === severity);

                        return group.length === 0 ? null : (
                            <SheetSection key={severity} id={`timetable-audit-${severity}`} title={`${titles[severity]} (${group.length})`}>
                                <ul className="sis-timetable-audit__items sis-branches-field--wide">
                                    {group.map((issue, index) => (
                                        <li key={`${issue.code}-${index}`} className={`sis-timetable-audit__item sis-timetable-audit__item--${severity}`}>
                                            <span className="sis-timetable-audit__text">{text(issue)}</span>
                                            {issue.section_id !== null || issue.teacher_id !== null ? (
                                                <Button type="button" size="sm" variant="outline" onClick={() => onGo(issue)}>
                                                    {tt.auditGoTo}
                                                </Button>
                                            ) : null}
                                        </li>
                                    ))}
                                </ul>
                            </SheetSection>
                        );
                    })
                )}
            </div>
            <div className="sis-admission-sheet__actions">
                <Button type="button" variant="outline" onClick={onClose}>
                    {tt.close}
                </Button>
            </div>
        </RegistrySheetDialog>
    );
}

/**
 * «الجاهزية والجودة»: before placing — can the lessons fit the week (ready / blocked and why); after —
 * how good the grid is (valid ≠ complete ≠ good) and each teacher's load. Computed on the server.
 */
function ReadinessSheet({
    advice,
    quality,
    workload,
    sectionLabel,
    teacherName,
    subjectName,
    onClose,
}: {
    advice: Advice;
    quality: Quality;
    workload: WorkloadRow[];
    sectionLabel: (id: number | null) => string;
    teacherName: (id: number) => string;
    subjectName: (id: number) => string;
    onClose: () => void;
}) {
    const tt = t().timetable;
    const titles: Record<FindingSeverity, string> = { blocker: tt.readinessBlockers, warning: tt.auditWarnings, info: tt.auditInfo };
    const percent = (value: number | null) => (value === null ? '—' : `${value}%`);
    const findingText = (f: Advice['findings'][number]) =>
        ((tt.findings as Record<string, string>)[f.code] ?? f.code)
            .replace('{section}', sectionLabel(f.section_id))
            .replace('{teacher}', f.teacher_id === null ? '' : teacherName(f.teacher_id))
            .replace('{subject}', f.subject_id === null ? '' : subjectName(f.subject_id))
            .replace('{count}', String(f.count ?? ''))
            .replace('{limit}', String(f.limit ?? ''));
    const readinessRows: Array<[string, number | null]> = [
        [tt.readinessSchoolDay, advice.readiness.school_day],
        [tt.readinessWeeklyLoads, advice.readiness.weekly_loads],
        [tt.readinessQualified, advice.readiness.qualified],
        [tt.readinessSections, advice.readiness.sections],
        [tt.readinessCapacity, advice.readiness.capacity],
    ];

    return (
        <RegistrySheetDialog title={tt.readiness} className="sis-branches-sheet sis-timetable-sheet sis-timetable-audit-sheet sis-timetable-engine-sheet sis-timetable-engine-sheet--readiness" onClose={onClose}>
            <div className="sis-timetable-audit__bar">
                <span className={`sis-timetable-badge sis-timetable-badge--${advice.verdict === 'ready' ? 'info' : 'error'}`}>
                    {advice.verdict === 'ready' ? tt.readinessReady : tt.readinessBlocked}
                </span>
                <span className="sis-timetable-badge sis-timetable-badge--info">
                    {tt.readinessOverall}: <bdi dir="ltr">{percent(advice.readiness.overall)}</bdi>
                </span>
                <span className={`sis-timetable-badge sis-timetable-badge--${quality.feasible ? 'info' : 'error'}`}>
                    {(tt.qualityGrades as Record<string, string>)[quality.grade]} · {tt.qualityOverall}: <bdi dir="ltr">{percent(quality.overall)}</bdi>
                </span>
            </div>
            <div className="sis-timetable-audit__list">
                <SheetSection id="timetable-readiness-summary" title={tt.readinessSummary}>
                    <ul className="sis-timetable-audit__items sis-branches-field--wide">
                        {readinessRows.map(([label, value]) => (
                            <li key={label} className="sis-timetable-audit__item">
                                <span className="sis-timetable-audit__text">{label}</span>
                                <bdi dir="ltr">{percent(value)}</bdi>
                            </li>
                        ))}
                        <li className="sis-timetable-audit__item">
                            <span className="sis-timetable-audit__text">
                                {tt.readinessTotals
                                    .replace('{sections}', String(advice.totals.sections))
                                    .replace('{teachers}', String(advice.totals.teachers))
                                    .replace('{lessons}', String(advice.totals.weekly_lessons))
                                    .replace('{slots}', String(advice.totals.slots_per_week))}
                            </span>
                        </li>
                    </ul>
                </SheetSection>

                {FINDING_SEVERITIES.map((severity) => {
                    const group = advice.findings.filter((f) => f.severity === severity);

                    return group.length === 0 ? null : (
                        <SheetSection key={severity} id={`timetable-readiness-${severity}`} title={`${titles[severity]} (${group.length})`}>
                            <ul className="sis-timetable-audit__items sis-branches-field--wide">
                                {group.map((f, index) => (
                                    <li key={`${f.code}-${index}`} className={`sis-timetable-audit__item sis-timetable-audit__item--${severity === 'blocker' ? 'error' : severity}`}>
                                        <span className="sis-timetable-audit__text">{findingText(f)}</span>
                                    </li>
                                ))}
                            </ul>
                        </SheetSection>
                    );
                })}

                <SheetSection id="timetable-quality" title={tt.quality}>
                    <ul className="sis-timetable-audit__items sis-branches-field--wide">
                        {QUALITY_METRICS.map((metric) => (
                            <li key={metric} className="sis-timetable-audit__item">
                                <span className="sis-timetable-audit__text">{(tt.qualityMetrics as Record<string, string>)[metric]}</span>
                                <bdi dir="ltr">{percent(quality.metrics[metric] ?? null)}</bdi>
                            </li>
                        ))}
                    </ul>
                </SheetSection>

                <SheetSection id="timetable-workload" title={tt.workload}>
                    {workload.length === 0 ? (
                        <p className="sis-timetable-audit__clean">{tt.workloadEmpty}</p>
                    ) : (
                        <div className="sis-admission-periods-table sis-admission-drafts-table sis-branches-field--wide">
                            <div className="sis-admission-drafts-table__scroller" data-allow-x-scroll>
                                <table>
                                    <thead>
                                        <tr>
                                            <th>{tt.teacher}</th>
                                            <th className="sis-admission-drafts-table__num">{tt.workloadRequired}</th>
                                            <th className="sis-admission-drafts-table__num">{tt.workloadPlaced}</th>
                                            <th className="sis-admission-drafts-table__num">{tt.practical}</th>
                                            <th className="sis-admission-drafts-table__num">{tt.workloadMaxDay}</th>
                                            <th className="sis-admission-drafts-table__num">{tt.workloadGaps}</th>
                                            <th className="sis-admission-drafts-table__num">{tt.workloadConsecutive}</th>
                                            <th>{tt.workloadStatus}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {workload.map((row) => (
                                            <tr key={row.teacher_id}>
                                                <td>{teacherName(row.teacher_id)}</td>
                                                <td className="sis-admission-drafts-table__num">
                                                    <bdi dir="ltr">
                                                        {row.required}/{row.capacity}
                                                    </bdi>
                                                </td>
                                                <td className="sis-admission-drafts-table__num">{row.placed}</td>
                                                <td className="sis-admission-drafts-table__num">{row.practical}</td>
                                                <td className="sis-admission-drafts-table__num">{row.max_day}</td>
                                                <td className="sis-admission-drafts-table__num">{row.gaps}</td>
                                                <td className="sis-admission-drafts-table__num">{row.max_consecutive}</td>
                                                <td>
                                                    <span className={`sis-timetable-badge sis-timetable-badge--${row.status === 'over' ? 'error' : row.status === 'incomplete' ? 'warning' : 'info'}`}>
                                                        {(tt.workloadStatuses as Record<string, string>)[row.status]}
                                                    </span>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    )}
                </SheetSection>
            </div>
            <div className="sis-admission-sheet__actions">
                <Button type="button" variant="outline" onClick={onClose}>
                    {tt.close}
                </Button>
            </div>
        </RegistrySheetDialog>
    );
}

type PeriodForm = { period_number: string; start_time: string; end_time: string; period_type: string };

/** «توقيت الحصص»: the school day — add / re-time a period, or lay the breaks out automatically. */
function PeriodsSheet({ periods, lessonNumber, onClose }: { periods: Period[]; lessonNumber: Map<number, number>; onClose: () => void }) {
    const i18n = t();
    const tt = i18n.timetable;
    const request = useRegistryRequest(PERIOD_RELOAD);
    const [editingId, setEditingId] = useState<number | 'new' | null>(null);
    const [form, setForm] = useState<PeriodForm>({ period_number: '', start_time: '', end_time: '', period_type: String(LESSON) });
    const firstLesson = periods.find((p) => p.period_type === LESSON);
    const [dayStart, setDayStart] = useState(periods[0]?.start_time ?? '08:00');
    const [lessonMinutes, setLessonMinutes] = useState(String(firstLesson === undefined ? 45 : minutesOf(firstLesson.end_time) - minutesOf(firstLesson.start_time)));
    const [saving, setSaving] = useState(false);

    const startNew = () => {
        const last = periods[periods.length - 1];
        setEditingId('new');
        setForm({ period_number: String((last?.period_number ?? 0) + 1), start_time: last?.end_time ?? '08:00', end_time: '', period_type: String(LESSON) });
    };
    const startEdit = (period: Period) => {
        setEditingId(period.id);
        setForm({ period_number: String(period.period_number), start_time: period.start_time, end_time: period.end_time, period_type: String(period.period_type) });
    };
    const save = async () => {
        if (editingId === null) {
            return;
        }
        setSaving(true);
        const data = { period_number: Number(form.period_number), start_time: form.start_time, end_time: form.end_time, period_type: Number(form.period_type) };
        const ok = editingId === 'new' ? await request('post', '/timetable/periods', data) : await request('patch', `/timetable/periods/${editingId}`, data);
        setSaving(false);
        if (ok) {
            setEditingId(null);
        }
    };
    const arrange = async () => {
        setSaving(true);
        await request('post', '/timetable/periods/arrange', { start_time: dayStart, lesson_minutes: Number(lessonMinutes) });
        setSaving(false);
    };
    const set = (field: keyof PeriodForm) => (value: string) => setForm((current) => ({ ...current, [field]: value }));
    const typeOptions = [
        { value: String(LESSON), label: tt.periodLesson },
        { value: '2', label: tt.periodBreak },
    ];
    const typeLabel = (period: Period) =>
        period.period_type === LESSON
            ? `${tt.periodLabel} ${lessonNumber.get(period.id) ?? ''}`
            : `${tt.periodBreak} ${minutesOf(period.end_time) - minutesOf(period.start_time)} ${tt.minutesShort}`;

    const editRow = (key: string) => (
        <tr key={key} className="sis-timetable-periods__editing">
            <td>
                <input className="sis-admission-sheet__control" type="number" min={1} max={20} dir="ltr" value={form.period_number} aria-label={tt.periodOrder} onChange={(e) => set('period_number')(e.target.value)} />
            </td>
            <td>
                <input className="sis-admission-sheet__control" type="time" dir="ltr" value={form.start_time} aria-label={tt.startTime} onChange={(e) => set('start_time')(e.target.value)} />
            </td>
            <td>
                <input className="sis-admission-sheet__control" type="time" dir="ltr" value={form.end_time} aria-label={tt.endTime} onChange={(e) => set('end_time')(e.target.value)} />
            </td>
            <td>
                <SisListSelect
                    value={form.period_type}
                    options={typeOptions}
                    onChange={set('period_type')}
                    ariaLabel={tt.periodType}
                    includeBlank={false}
                    className="sis-admission-sheet-list-select"
                    triggerClassName="sis-admission-sheet__control sis-admission-draft-select"
                    menuClassName="sis-admission-sheet-list-select__menu"
                />
            </td>
            <td className="sis-timetable-periods__actions">
                <Button type="button" size="sm" disabled={saving || form.start_time === '' || form.end_time === '' || form.period_number === ''} onClick={() => void save()}>
                    {saving ? i18n.common.saving : tt.savePeriod}
                </Button>
                <Button type="button" size="sm" variant="outline" disabled={saving} onClick={() => setEditingId(null)}>
                    {tt.cancelEdit}
                </Button>
            </td>
        </tr>
    );

    return (
        <RegistrySheetDialog title={tt.periodsSetup} className="sis-branches-sheet sis-timetable-sheet sis-timetable-periods-sheet" onClose={onClose}>
            <SheetSection id="timetable-arrange" title={tt.arrangeDay}>
                <div className="sis-admission-sheet__row sis-admission-sheet__row--full sis-branches-sheet__row">
                    <div className="sis-timetable-arrange sis-branches-field--wide">
                        <label className="sis-admission-sheet__field">
                            <span className="sis-admission-sheet__label">{tt.dayStart}</span>
                            <input className="sis-admission-sheet__control" type="time" dir="ltr" value={dayStart} onChange={(e) => setDayStart(e.target.value)} />
                        </label>
                        <label className="sis-admission-sheet__field">
                            <span className="sis-admission-sheet__label">{tt.lessonMinutes}</span>
                            <input className="sis-admission-sheet__control" type="number" min={20} max={90} dir="ltr" value={lessonMinutes} onChange={(e) => setLessonMinutes(e.target.value)} />
                        </label>
                        <Button type="button" className="sis-timetable-arrange__run" disabled={saving || editingId !== null || dayStart === '' || lessonMinutes === ''} onClick={() => void arrange()} title={tt.arrangeHint}>
                            {tt.arrangeDay}
                        </Button>
                        <p className="sis-timetable-sheet__hint">{tt.arrangeHint}</p>
                    </div>
                </div>
            </SheetSection>
            <SheetSection id="timetable-periods" title={tt.periodsSetupHint}>
                <div className="sis-admission-sheet__row sis-admission-sheet__row--full sis-branches-sheet__row">
                    <div className="sis-timetable-periods sis-branches-field--wide">
                        <table>
                            <colgroup>
                                <col className="sis-timetable-periods__col-number" />
                                <col className="sis-timetable-periods__col-time" />
                                <col className="sis-timetable-periods__col-time" />
                                <col className="sis-timetable-periods__col-type" />
                                <col />
                            </colgroup>
                            <thead>
                                <tr>
                                    <th>{tt.periodOrder}</th>
                                    <th>{tt.startTime}</th>
                                    <th>{tt.endTime}</th>
                                    <th>{tt.periodType}</th>
                                    <th aria-hidden="true" />
                                </tr>
                            </thead>
                            <tbody>
                                {periods.map((period) =>
                                    editingId === period.id ? (
                                        editRow(String(period.id))
                                    ) : (
                                        <tr key={period.id} className={period.period_type === LESSON ? '' : 'sis-timetable-periods__break'}>
                                            <td dir="ltr">{period.period_number}</td>
                                            <td>{clock12(period.start_time)}</td>
                                            <td>{clock12(period.end_time)}</td>
                                            <td>{typeLabel(period)}</td>
                                            <td className="sis-timetable-periods__actions">
                                                <Button type="button" size="sm" variant="outline" disabled={editingId !== null || saving} onClick={() => startEdit(period)}>
                                                    {tt.editPeriod}
                                                </Button>
                                            </td>
                                        </tr>
                                    ),
                                )}
                                {editingId === 'new' ? editRow('new') : null}
                            </tbody>
                        </table>
                    </div>
                </div>
            </SheetSection>
            <div className="sis-admission-sheet__actions">
                <Button type="button" variant="outline" onClick={onClose}>
                    {tt.close}
                </Button>
                <Button type="button" disabled={editingId !== null || saving} onClick={startNew}>
                    {tt.addPeriod}
                </Button>
            </div>
        </RegistrySheetDialog>
    );
}
