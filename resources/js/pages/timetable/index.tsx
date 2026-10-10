import { Head, router, usePage } from '@inertiajs/react';
import { BadgeCheck, FileText, Boxes, Building2, ClipboardCheck, ClipboardPaste, Coffee, Copy, DoorClosed, Eye, EyeOff, Lightbulb, Palette, Pencil, Search, Unlock, CalendarClock, CalendarX, ChevronsLeft, ChevronsRight, CircleAlert, Clock, DoorOpen, FileCheck, FileClock, FileWarning, FilterX, Gauge, History, LoaderCircle, Lock, OctagonX, Scale, Settings2, ShieldAlert, ShieldCheck, TriangleAlert, Wand2, WandSparkles, LayoutGrid, LayoutList, Minimize2, Printer, Sparkles, Trash2, UserRound, X } from 'lucide-react';
import { cloneElement, Fragment, useCallback, useEffect, useMemo, useRef, useState, type CSSProperties, type DragEvent, type ReactElement, type ReactNode } from 'react';
import { RegistryListField, RegistrySheetDialog, useRegistryRequest } from '@/components/organization/registry-sheet';
import { AppearanceDialog, hueStyle } from '@/components/sis/appearance-fields';
import { isContextMenuKey, SisContextMenu, useContextMenu, type ContextMenuItem } from '@/components/sis/context-menu';
import { displayRow, formatClock, gridData, gridStyle, ownerHue, resolveDisplay, type DisplayCatalog, type DisplayKind, type DisplaySettings } from '@/components/timetable/display-settings';
import { FormatSheet } from '@/components/timetable/format-sheet';
import { HeadingSheet } from '@/components/timetable/heading-sheet';
import { CellBody, cellLines, type CellContext, type CellLesson } from '@/components/timetable/lesson-card-content';
import { SchoolDaySheet } from '@/components/timetable/school-day-sheet';
import { TestSheet, type TestFix, type TestIssue, type TestReport } from '@/components/timetable/test-sheet';
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
import { PlacesSheet } from '@/components/timetable/engine/places-sheet';
import { MasterTimetable } from '@/components/timetable/master-timetable';
import { loadPrintSettings, pageRule, PrintSettingsPanel, savePrintSettings, TimetablePaper, type PrintSettings } from '@/components/timetable/print-paper';
import { LessonEngineTools } from '@/components/timetable/engine/lesson-engine-tools';
import { TimetableSubWindow } from '@/components/timetable/engine/engine-ui';
import { SettingsSheet } from '@/components/timetable/engine/settings-sheet';
import { VersionsSheet } from '@/components/timetable/engine/versions-sheet';
import { Button } from '@/components/ui/button';
import { t } from '@/i18n';
import { resolveSisSectionCode, sisSectionSelectOptions } from '@/lib/sis-class-section-options';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

type Period = {
    id: number;
    period_number: number;
    start_time: string;
    end_time: string;
    period_type: number;
    name?: string | null;
    abbreviation?: string | null;
    color_hue?: number | null;
    show_in?: number;
    print_in?: number;
};
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
    /** Names, abbreviations, colours (from the owning pages) + «تنسيق الجدول». */
    display?: DisplayCatalog | null;
    /** «اختبار الجدول» (loaded on demand). */
    testReport?: TestReport | null;
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
/** What the right-click menu opened on: a lesson, an empty cell, or a period / break header. */
type MenuTarget = { type: 'lesson'; schedule: Schedule } | { type: 'cell'; sectionId: number; day: number; periodId: number } | { type: 'period'; period: Period };

/** Filters «الفرع / الاختصاص / الصف / الشعبة» ('' = الكل); section = the shared code A / B / C (SSOT). */
type GridFilters = { branch: string; department: string; class: string; section: string };

/** A block of the «عرض الكل» split: branch › department (or the sections without students). */
type GridGroup = { key: string; title: string; sections: Array<{ section: Section; students: number }> };
/** «جدول الدروس الأسبوعي» sheet: one stage (class name), its departments across, each with its sections. */
type StageColumn = { key: string; title: string; members: Array<{ section: Section; students: number }> };
type StageSheet = { key: string; name: string; columns: StageColumn[] };
const STAGE_TEACHERS_KEY = 'sis.timetable.stage-teachers';

const LESSON = 1;
/** School week: الأحد → الخميس (`day_of_week` 1–5). */
const DEFAULT_DAYS = [1, 2, 3, 4, 5];
const SCHEDULE_RELOAD = ['schedules', 'periods', 'display', 'issues', 'advice', 'workload', 'quality', 'engine', 'flash'];
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
    display = null,
    testReport = null,
    authorization,
}: Props) {
    const i18n = t();
    const tt = i18n.timetable;
    const et = tt.engine;
    const yearId = filters.academic_year_id;
    const scheduleRequest = useRegistryRequest(SCHEDULE_RELOAD);
    const page = usePage().props as { academicYears?: YearOption[]; schoolContext?: { schoolId: number | null; schools: Array<{ id: number; name: string }> } };
    const years = page.academicYears ?? [];
    /** Working days of the school (settings), Sunday–Thursday by default. */
    const DAYS = engine?.settings.working_days ?? DEFAULT_DAYS;

    // «عرض جدول الغرفة» from «الغرف الدراسية» opens here with ?view=room&room_id=…
    const query = typeof window === 'undefined' ? null : new URLSearchParams(window.location.search);
    const [view, setView] = useState<'section' | 'teacher' | 'room'>(query?.get('view') === 'room' ? 'room' : query?.get('view') === 'teacher' ? 'teacher' : 'section');
    const [roomId, setRoomId] = useState<number | null>(Number(query?.get('room_id')) || (engine?.rooms[0]?.id ?? null));
    const [engineSheet, setEngineSheet] = useState<'generate' | 'activities' | 'constraints' | 'availability' | 'places' | 'versions' | 'settings' | null>(null);
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
    const [printLayout, setPrintLayout] = useState(false);
    const [printPanel, setPrintPanel] = useState(false);
    const [printSettings, setPrintSettings] = useState<PrintSettings>(() => loadPrintSettings());
    const changePrintSettings = useCallback((next: PrintSettings) => {
        setPrintSettings(next);
        savePrintSettings(next);
    }, []);
    const previewRef = useRef<HTMLDivElement | null>(null);
    const previewBodyRef = useRef<HTMLDivElement | null>(null);
    const [saving, setSaving] = useState(false);

    // ── Workbench: «تنسيق الجدول», «اختبار الجدول», the school day, context menu, clipboard, drop alternatives ──
    const [displayOverride, setDisplayOverride] = useState<DisplaySettings | null>(null);
    // Memoised: `settings` feeds the ribbon groups, and a new object each render re-registers the ribbon forever.
    const savedSettings = useMemo(() => resolveDisplay(display?.settings), [display?.settings]);
    const settings = displayOverride ?? savedSettings;
    const [formatFor, setFormatFor] = useState<{ scheduleId: number | null } | null>(null);
    const [testOpen, setTestOpen] = useState(false);
    const [dayOpen, setDayOpen] = useState(false);
    const [headingOpen, setHeadingOpen] = useState(false);
    const [editTab, setEditTab] = useState('content');
    const [appearanceFor, setAppearanceFor] = useState<{ kind: DisplayKind; id: number } | null>(null);
    const [clipboard, setClipboard] = useState<{ sectionId: number; subjectId: number; teacherId: number } | null>(null);
    const [dropReject, setDropReject] = useState<{ payload: DragPayload; reason: 'busy' | 'taken'; day: number; periodId: number } | null>(null);
    /** The cell the user chose (one click): marked on the grid; «تحرير الخلية» acts on it. */
    const [selectedCell, setSelectedCell] = useState<{ sectionId: number; day: number; periodId: number } | null>(null);
    // «الدرس» only, or «الدرس | اسم المدرس» — the sheet columns of the working page and the print.
    const [stageTeachers, setStageTeachersState] = useState<boolean>(() => {
        try {
            return window.localStorage.getItem(STAGE_TEACHERS_KEY) !== '0';
        } catch {
            return true;
        }
    });
    const setStageTeachers = useCallback((next: boolean) => {
        setStageTeachersState(next);
        try {
            window.localStorage.setItem(STAGE_TEACHERS_KEY, next ? '1' : '0');
        } catch {
            // private mode — the choice lasts for this page only
        }
    }, []);
    const cardMenu = useContextMenu<MenuTarget>();
    const openEdit = (scheduleId: number, tab = 'content') => {
        setEditTab(tab);
        setEditId(scheduleId);
    };

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

        // Inside a block: class, then section code (A / B / C), then id — the order of «الجدول الموحّد» in the preview.
        const codeOf = (section: Section) => resolveSisSectionCode(section.id, sections);
        for (const block of blocks.values()) {
            block.sections.sort((a, b) => a.section.class_id - b.section.class_id || codeOf(a.section).localeCompare(codeOf(b.section)) || a.section.id - b.section.id);
        }

        return [...blocks.values()].sort((a, b) => {
            const [ab, ad] = rank(a.key);
            const [bb, bd] = rank(b.key);

            return (ab ?? 0) - (bb ?? 0) || (ad ?? 0) - (bd ?? 0);
        });
    }, [branches, departmentNames, gridFilters, placements, sections, tt]);

    const visibleSectionIds = useMemo(() => [...new Set(groups.flatMap((g) => g.sections.map((s) => s.section.id)))], [groups]);
    const gridFiltered = gridFilters.branch !== '' || gridFilters.department !== '' || gridFilters.class !== '' || gridFilters.section !== '';
    const setFilter = useCallback((patch: Partial<GridFilters>) => setGridFilters((current) => ({ ...current, ...patch })), []);
    // Branch / specialization / class / section options — the ribbon filters and the full-screen preview share them.
    const filterOptions = useMemo(() => {
        const branch = branches.find((b) => String(b.id) === gridFilters.branch) ?? null;

        return {
            branch: [{ value: '', label: tt.allBranches }, ...branches.map((b) => ({ value: String(b.id), label: b.name }))],
            department: [
                { value: '', label: tt.allDepartments },
                ...(branch === null
                    ? [...new Map(branches.flatMap((b) => b.departments.map((d) => [d.id, `${d.name}`] as const))).entries()].map(([id, name]) => ({ value: String(id), label: name }))
                    : branch.departments.map((d) => ({ value: String(d.id), label: d.name }))),
            ],
            class: [{ value: '', label: tt.allClasses }, ...[...new Map(sections.map((s) => [s.class_id, s.class_name] as const)).entries()].map(([id, name]) => ({ value: String(id), label: name }))],
            section: [{ value: '', label: tt.allSections }, ...sisSectionSelectOptions()],
        };
    }, [branches, sections, gridFilters.branch, tt]);
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
        if (state === 'busy' || state === 'taken') {
            setDropReject({ payload, reason: state, day, periodId });

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
        setPrintLayout(false);
        setPrintPanel(false);
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
                setPrintLayout(false);
                setPrintPanel(false);
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
        const yearOptions = years.map((year) => ({ value: String(year.id), label: formatAcademicYearOptionLabel(year.name, year.code) }));
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
                                    {field(gridFilters.branch, tt.branch, filterOptions.branch, (next) => setFilter({ branch: next, department: '' }))}
                                    {field(gridFilters.department, tt.department, filterOptions.department, (next) => setFilter({ department: next }))}
                                    {field(gridFilters.class, tt.class, filterOptions.class, (next) => setFilter({ class: next }))}
                                    {field(gridFilters.section, tt.section, filterOptions.section, (next) => setFilter({ section: next }))}
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
                                    disabled={!gridFiltered}
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
    }, [i18n, years, sections, branches, teachers, view, yearId, gridFilters, gridFiltered, filterOptions, setFilter, activeTeacherId, activeRoomId, engine, issueCounts, canPlace, saving, lessonPeriods.length, visibleSectionIds]);
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
                    {
                        id: 'timetable-test',
                        label: tt.test.open,
                        icon: ClipboardCheck,
                        iconTone: 'rose',
                        title: tt.test.hint,
                        disabled: noData || yearId === null,
                        onSelect: () => setTestOpen(true),
                    },
                ],
            },
            {
                id: 'timetable-format',
                label: tt.editRibbon.format,
                commands: [
                    { id: 'timetable-format-lens', label: tt.format.open, icon: Search, iconTone: 'steel', title: tt.format.lens, disabled: lessonPeriods.length === 0, onSelect: () => setFormatFor({ scheduleId: highlight[0] ?? null }) },
                    {
                        id: 'timetable-format-cell',
                        label: tt.cell.title,
                        icon: Pencil,
                        title: tt.cell.title,
                        disabled: (selectedCell === null && highlight.length === 0) || !authorization.can_update,
                        onSelect: () => {
                            if (selectedCell !== null) {
                                const placed = sectionCells.get(selectedCell.sectionId)?.get(cellKey(selectedCell.day, selectedCell.periodId));
                                if (placed !== undefined) {
                                    openEdit(placed.id);
                                } else if (canPlace) {
                                    setPickCell(selectedCell);
                                }

                                return;
                            }
                            if (highlight[0] !== undefined) {
                                openEdit(highlight[0]);
                            }
                        },
                    },
                    // «المعلم» left the ribbon: the teacher is shown through «الدرس والمدرس» / «تنسيق الجدول».
                    ...(['room', 'section'] as const).map((field) => ({
                        id: `timetable-show-${field}`,
                        label: tt.format.fieldNames[field],
                        icon: settings.fields[field] ? Eye : EyeOff,
                        pressed: settings.fields[field],
                        title: `${tt.editRibbon.show}: ${tt.format.fieldNames[field]}`,
                        onSelect: () => setDisplayOverride({ ...settings, fields: { ...settings.fields, [field]: !settings.fields[field] } }),
                    })),
                    {
                        id: 'timetable-sheet-lesson',
                        label: tt.master.lessonOnly,
                        icon: LayoutGrid,
                        iconTone: 'steel',
                        pressed: !stageTeachers,
                        title: tt.master.lessonOnlyHint,
                        onSelect: () => setStageTeachers(false),
                    },
                    {
                        id: 'timetable-sheet-teacher',
                        label: tt.master.lessonTeacher,
                        icon: UserRound,
                        iconTone: 'steel',
                        pressed: stageTeachers,
                        title: tt.master.lessonTeacherHint,
                        onSelect: () => setStageTeachers(true),
                    },
                    { id: 'timetable-heading', label: tt.heading.open, icon: FileText, iconTone: 'steel', title: tt.heading.hint, disabled: yearId === null, onSelect: () => setHeadingOpen(true) },
                    { id: 'timetable-day', label: tt.schoolDay.open, icon: Coffee, iconTone: 'amber', title: tt.schoolDay.hint, disabled: !authorization.can_manage_periods, onSelect: () => setDayOpen(true) },
                    {
                        id: 'timetable-print-preview',
                        label: tt.editRibbon.printPreview,
                        icon: Printer,
                        iconTone: 'steel',
                        disabled: lessonPeriods.length === 0,
                        onSelect: () => setPreview(true),
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
                        id: 'timetable-engine-places',
                        label: et.places,
                        icon: Building2,
                        count: engine !== null && engine.places.rooms.length + engine.places.workshops.length > 0 ? engine.places.rooms.length + engine.places.workshops.length : undefined,
                        title: et.placesHint,
                        disabled: noData,
                        onSelect: () => setEngineSheet('places'),
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
    }, [tt, canPlace, view, visibleSectionIds, lessonPeriods.length, saving, issueCounts, advice, quality, engine, settings, highlight, yearId, stageTeachers, setStageTeachers, selectedCell, sectionCells]);
    useRegisterPageRibbon('edit', editRibbonGroups);

    const addRibbonGroups = useMemo(
        (): PageRibbonGroup[] =>
            authorization.can_manage_periods
                ? [
                      {
                          id: 'timetable-setup',
                          label: tt.ribbonSetup,
                          commands: [
                              { id: 'timetable-periods', label: tt.periodsSetup, icon: CalendarClock, title: tt.periodsSetupHint, onSelect: () => setPeriodsOpen(true) },
                              { id: 'timetable-breaks', label: tt.schoolDay.open, icon: Coffee, title: tt.schoolDay.hint, onSelect: () => setDayOpen(true) },
                          ],
                      },
                  ]
                : [],
        [authorization.can_manage_periods, tt],
    );
    useRegisterPageRibbon('add', addRibbonGroups);

    // ── Grid ─────────────────────────────────────────────────────────────────
    /** One colour wheel per kind (teacher / section / room colouring) when the owner chose none. */
    const wheelStyle = (id: number): CSSProperties => ({ '--subject-hue': (id * 47) % 360, '--subject-light': `${id % 2 === 0 ? 84 : 91}%` }) as CSSProperties;
    /** The card tint for «لوّن حسب»: the owner's colour, else the timetable's default wheel. */
    const cardStyle = (s: CellLesson): CSSProperties | undefined => {
        if (settings.color_by === 'none') {
            return undefined;
        }
        const own = ownerHue(display, settings.color_by, s);
        if (own !== null) {
            return hueStyle(own);
        }
        if (settings.color_by === 'subject') {
            return subjectStyles.get(s.subject_id);
        }
        const id = settings.color_by === 'teacher' ? s.teacher_id : settings.color_by === 'section' ? s.section_id : s.room_id;

        return id === null ? undefined : wheelStyle(id);
    };
    const placementsBySection = useMemo(() => {
        const map = new Map<number, Placement[]>();
        for (const p of placements) {
            map.set(p.section_id, [...(map.get(p.section_id) ?? []), p]);
        }

        return map;
    }, [placements]);
    const roomName = (id: number | null) => (id === null ? null : (engine?.rooms.find((r) => r.id === id)?.name ?? `#${id}`));
    /** What a cell can say about a lesson (names resolved once; the owners' abbreviations through the catalogue). */
    const cellCtx = (s: Schedule, contextLine: string | null): CellContext => {
        const section = sectionsById.get(s.section_id);
        const served = placementsBySection.get(s.section_id) ?? [];
        const groupCount = s.group_id ? (engine?.groups.find((g) => g.id === s.group_id)?.student_count ?? null) : null;
        const sectionRow = displayRow(display, 'sections', s.section_id);

        return {
            catalog: display,
            subjectName: subjectNames.get(s.subject_id) ?? `#${s.subject_id}`,
            teacherName: teacherNames.get(s.teacher_id) ?? `#${s.teacher_id}`,
            sectionName: sectionRow !== null && settings.abbreviate.section ? sectionRow.short : (section?.name ?? ''),
            className: section?.class_name ?? '',
            roomName: roomName(s.room_id),
            groupName: s.group_id ? (groupNames.get(s.group_id) ?? et.group) : null,
            coTeacherName: s.co_teacher_id ? (teacherNames.get(s.co_teacher_id) ?? '') : null,
            students: groupCount ?? served.reduce((sum, p) => sum + p.students, 0),
            branchName: served[0] ? (branches.find((b) => b.id === served[0].branch_id)?.name ?? null) : null,
            departmentName: served[0]?.department_id ? (departmentNames.get(served[0].department_id) ?? null) : null,
            practical: practical.has(s.subject_id),
            shared: (sharedCells.get(`${s.section_id}:${cellKey(s.day_of_week, s.period_id)}`) ?? 1) - 1,
            labels: { practical: tt.practical, week: et.week, students: tt.cell.students, locked: et.locked },
            contextLine,
        };
    };

    const lessonCard = (s: Schedule, line: string, interactive: boolean, hideTeacher = false) => {
        const cardSettings = hideTeacher ? { ...settings, fields: { ...settings.fields, teacher: false } } : settings;
        const severity = lessonSeverity.get(s.id);
        // A locked lesson opens (to unlock) but is not dragged.
        const draggable = interactive && s.locked !== true;
        // The section view names the teacher; the teacher / room views pass what the view does not tell.
        const ctx = cellCtx(s, line === (teacherNames.get(s.teacher_id) ?? '') ? null : line);
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
                style={cardStyle(s)}
                title={cellLines(s, cardSettings, ctx).title}
                draggable={draggable}
                onDragStart={draggable ? (event) => startDrag(event, { sectionId: s.section_id, subjectId: s.subject_id, teacherId: s.teacher_id, scheduleId: s.id }) : undefined}
                onDragEnd={endDrag}
                onDoubleClick={interactive ? (event) => {
                    event.stopPropagation();
                    openEdit(s.id);
                } : undefined}
                onContextMenu={(event) => cardMenu.open(event, { type: 'lesson', schedule: s })}
            >
                <CellBody
                    lesson={s}
                    settings={cardSettings}
                    ctx={ctx}
                    trailing={
                        <>
                            {interactive ? (
                                <button
                                    type="button"
                                    className="sis-timetable-card__open"
                                    aria-label={`${tt.editLesson}: ${subjectName}`}
                                    onClick={() => openEdit(s.id)}
                                    onKeyDown={(event) => {
                                        if (isContextMenuKey(event)) {
                                            event.preventDefault();
                                            cardMenu.openAt(event.currentTarget, { type: 'lesson', schedule: s });
                                        }
                                    }}
                                />
                            ) : null}
                            {draggable && authorization.can_cancel ? (
                                <button type="button" className="sis-timetable-card__remove" aria-label={tt.removeLesson} title={tt.removeLesson} disabled={saving} onClick={() => void unplace(s.id)}>
                                    <X aria-hidden />
                                </button>
                            ) : null}
                        </>
                    }
                />
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

    const sectionCell = (sectionId: number, day: number, period: Period, hideTeacher = false) => {
        const key = `${sectionId}:${cellKey(day, period.id)}`;
        const placed = sectionCells.get(sectionId)?.get(cellKey(day, period.id));
        const state = cellState(sectionId, day, period.id, drag);
        const chosen = selectedCell !== null && selectedCell.sectionId === sectionId && selectedCell.day === day && selectedCell.periodId === period.id;
        const className = [
            'sis-timetable-cell',
            chosen ? 'sis-timetable-cell--selected' : '',
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
                aria-selected={chosen}
                onClick={() => setSelectedCell({ sectionId, day, periodId: period.id })}
                onDoubleClick={() => {
                    if (placed !== undefined) {
                        if (authorization.can_update && viewingVersion === null) {
                            openEdit(placed.id);
                        }
                    } else if (canPlace) {
                        setPickCell({ sectionId, day, periodId: period.id });
                    }
                }}
                onDragOver={(event) => {
                    if (drag !== null && state !== 'idle') {
                        // Busy / taken cells accept the drop too: it answers with the reason and free alternatives.
                        event.preventDefault();
                        setOverCell(state === 'free' || state === 'swap' ? key : null);
                    }
                }}
                onContextMenu={(event) => {
                    setSelectedCell({ sectionId, day, periodId: period.id });
                    if (placed === undefined) {
                        cardMenu.open(event, { type: 'cell', sectionId, day, periodId: period.id });
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
                    lessonCard(placed, teacherNames.get(placed.teacher_id) ?? '', authorization.can_update && !saving, hideTeacher)
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

    /** Breaks show / print only on the timetables chosen for them (PeriodPresentation bitmask; lessons always). */
    const viewTarget = view === 'teacher' ? 2 : view === 'room' ? 16 : singleGrid ? 4 : 5;
    const dayPeriods = sortedPeriods.filter((p) => p.period_type === LESSON || ((preview ? (p.print_in ?? 31) : (p.show_in ?? 31)) & viewTarget) !== 0);
    const breakText = (period: Period) => `${period.abbreviation ?? period.name ?? tt.breakLabel} · ${minutesOf(period.end_time) - minutesOf(period.start_time)} ${tt.minutesShort}`;
    const headerMenu = (period: Period) => ({
        onContextMenu: (event: React.MouseEvent<HTMLElement>) => cardMenu.open(event, { type: 'period', period }),
        onDoubleClick: authorization.can_manage_periods ? () => setDayOpen(true) : undefined,
    });

    /** Lessons 1 … n with their times; breaks are narrow columns showing their minutes. */
    const gridTable = (cell: (day: number, period: Period) => ReactElement, compact: boolean) => (
        <table className={compact ? 'sis-timetable-table sis-timetable-table--compact' : 'sis-timetable-table'}>
            <colgroup>
                <col className="sis-timetable-table__day-col" />
                {dayPeriods.map((period) =>
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
                    {dayPeriods.map((period) =>
                        period.period_type === LESSON ? (
                            <th key={period.id} {...headerMenu(period)}>
                                <span className="sis-timetable-grid__period">
                                    {tt.periodLabel} {lessonNumber.get(period.id)}
                                </span>
                                {settings.period_header.show_time ? (
                                    <span className="sis-timetable-grid__time">
                                        {formatClock(period.start_time, settings.period_header.clock, tt.am, tt.pm)} – {formatClock(period.end_time, settings.period_header.clock, tt.am, tt.pm)}
                                    </span>
                                ) : null}
                            </th>
                        ) : (
                            <th key={period.id} {...headerMenu(period)} className={`sis-timetable-grid__break sis-timetable-break--${breakTone(minutesOf(period.end_time) - minutesOf(period.start_time))}`} style={hueStyle(period.color_hue)} title={`${period.name ?? tt.breakLabel} ${clock12(period.start_time)} – ${clock12(period.end_time)}`}>
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
                        {dayPeriods.map((period) =>
                            period.period_type !== LESSON ? (
                                // One band per break, down the whole week (read top → bottom).
                                row === 0 ? (
                                    <td key={period.id} {...headerMenu(period)} className={`sis-timetable-cell sis-timetable-cell--break sis-timetable-break--${breakTone(minutesOf(period.end_time) - minutesOf(period.start_time))}`} style={hueStyle(period.color_hue)} rowSpan={DAYS.length}>
                                        <span className="sis-timetable-cell__break-label">{breakText(period)}</span>
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
            <div className="sis-timetable-grid" {...gridData(settings)} style={gridStyle(settings)}>{gridTable((day, period) => (readOnly ? previewCell : sectionCell)(section.id, day, period), compact)}</div>
        </section>
    );

    /**
     * «جدول الدروس الأسبوعي» on the working page — the school's sheet: one table per stage (class), its
     * departments across, each department its sections, each section «الدرس» (+ «اسم المدرس» when shown);
     * the days down the side (written vertically, a heavy line between days) with their periods. The lesson
     * cell is the working cell (drop / pick / menu / edit); choosing a cell or heading focuses that section.
     */
    const stageBoard = (stage: StageSheet) => {
        const codeOf = (section: Section) => resolveSisSectionCode(section.id, sections);
        const label = (section: Section) => (codeOf(section) !== '' ? `${section.class_name} ${codeOf(section)}` : `${section.class_name} — ${section.name}`);
        const perSection = stageTeachers ? 2 : 1;
        const sectionCount = stage.columns.reduce((n, c) => n + c.members.length, 0);
        const teacherText = (s: Schedule) =>
            [s.teacher_id, s.co_teacher_id]
                .filter((id): id is number => id !== null && id !== undefined)
                .map((id) => teacherNames.get(id) ?? `#${id}`)
                .join(' + ');
        const edge = (column: StageColumn, index: number) => (index === column.members.length - 1 ? ' sis-timetable-stage__edge--department' : ' sis-timetable-stage__edge--section');

        return (
            <section key={stage.key} className="sis-timetable-board sis-timetable-stage" aria-busy={saving}>
                <header className="sis-timetable-board__head sis-timetable-stage__head">
                    <span className="sis-timetable-stage__school">{schoolName}</span>
                    <span className="sis-timetable-board__title">
                        {tt.master.title}
                        {yearName !== '' ? ` ${tt.master.forYear} ${sheetYear}` : ''}
                    </span>
                    <span className="sis-timetable-board__title">
                        {tt.master.stage}: {stage.name}
                    </span>
                    <span className="sis-timetable-board__meta">{sheetEffective !== '' ? tt.master.effectiveFrom.replace('{date}', sheetEffective) : ''}</span>
                </header>
                <div className="sis-timetable-grid" {...gridData(settings)} style={gridStyle(settings)}>
                    <table className={`sis-timetable-table sis-timetable-table--compact sis-timetable-stage__table${stageTeachers ? ' sis-timetable-stage__table--teachers' : ''}`}>
                        <colgroup>
                            <col className="sis-timetable-stage__col-day" />
                            <col className="sis-timetable-stage__col-period" />
                            {stage.columns.flatMap((column) =>
                                column.members.flatMap(({ section }) =>
                                    stageTeachers
                                        ? [<col key={`${column.key}:${section.id}:s`} className="sis-timetable-stage__col-lesson" />, <col key={`${column.key}:${section.id}:t`} className="sis-timetable-stage__col-teacher" />]
                                        : [<col key={`${column.key}:${section.id}:s`} className="sis-timetable-stage__col-lesson" />],
                                ),
                            )}
                        </colgroup>
                        <thead>
                            <tr>
                                <th rowSpan={stageTeachers ? 3 : 2} className="sis-timetable-grid__corner">{tt.master.days}</th>
                                <th rowSpan={stageTeachers ? 3 : 2} className="sis-timetable-grid__corner sis-timetable-stage__corner--vertical">
                                    <span>{tt.master.periods}</span>
                                </th>
                                {stage.columns.map((column) => (
                                    <th key={column.key} colSpan={column.members.length * perSection} className="sis-timetable-stage__department sis-timetable-stage__edge--department">
                                        {column.title}
                                    </th>
                                ))}
                            </tr>
                            <tr>
                                {stage.columns.flatMap((column) =>
                                    column.members.map(({ section, students }, index) => (
                                        <th
                                            key={`${column.key}:${section.id}`}
                                            scope="colgroup"
                                            colSpan={perSection}
                                            className={`sis-timetable-stage__section${edge(column, index)}`}
                                            title={`${students > 0 ? `${tt.studentsCount.replace('{n}', String(students))} · ` : ''}${sectionCells.get(section.id)?.size ?? 0} / ${lessonsOf(section).reduce((sum, l) => sum + (l.required ?? 0), 0)}`}
                                            onClick={() => setFocusId(section.id)}
                                        >
                                            {label(section)}
                                        </th>
                                    )),
                                )}
                            </tr>
                            {stageTeachers ? (
                                <tr>
                                    {stage.columns.flatMap((column) =>
                                        column.members.flatMap(({ section }, index) => [
                                            <th key={`${column.key}:${section.id}:s`} className="sis-timetable-stage__sub">{tt.master.lesson}</th>,
                                            <th key={`${column.key}:${section.id}:t`} className={`sis-timetable-stage__sub${edge(column, index)}`}>{tt.master.teacher}</th>,
                                        ]),
                                    )}
                                </tr>
                            ) : null}
                        </thead>
                        {DAYS.map((day, dayIndex) => (
                            <Fragment key={day}>
                            {dayIndex > 0 ? (
                                <tbody className="sis-timetable-stage__day-gap" aria-hidden="true">
                                    <tr>
                                        <td colSpan={2 + sectionCount * perSection} />
                                    </tr>
                                </tbody>
                            ) : null}
                            <tbody className="sis-timetable-stage__day-block">
                                {dayPeriods.map((period, index) => (
                                    <tr key={period.id} className={period.period_type === LESSON ? undefined : 'sis-timetable-stage__break-row'}>
                                        {index === 0 ? (
                                            <th scope="rowgroup" rowSpan={dayPeriods.length} className="sis-timetable-grid__day sis-timetable-stage__day">
                                                <span>{dayOfWeekLabel(day)}</span>
                                            </th>
                                        ) : null}
                                        {period.period_type === LESSON ? (
                                            <>
                                                <th scope="row" className="sis-timetable-stage__period" {...headerMenu(period)}>
                                                    {lessonNumber.get(period.id)}
                                                </th>
                                                {stage.columns.flatMap((column) =>
                                                    column.members.flatMap(({ section }, memberIndex) => {
                                                        const cell = sectionCell(section.id, day, period, stageTeachers);
                                                        const lessonCell = cloneElement(cell, {
                                                            key: `${column.key}:${section.id}:${day}:${period.id}:s`,
                                                            // lesson only: the lesson cell closes the section (its separator line)
                                                            className: `${(cell.props as { className?: string }).className ?? ''}${stageTeachers ? '' : edge(column, memberIndex)}`,
                                                            onClickCapture: () => setFocusId(section.id),
                                                        });
                                                        if (!stageTeachers) {
                                                            return [lessonCell];
                                                        }
                                                        const placed = sectionCells.get(section.id)?.get(cellKey(day, period.id));

                                                        return [
                                                            lessonCell,
                                                            <td
                                                                key={`${column.key}:${section.id}:${day}:${period.id}:t`}
                                                                className={`sis-timetable-cell sis-timetable-stage__teacher${edge(column, memberIndex)}`}
                                                                style={placed === undefined ? undefined : cardStyle(placed)}
                                                                onClick={() => setFocusId(section.id)}
                                                            >
                                                                {placed === undefined ? null : teacherText(placed)}
                                                            </td>,
                                                        ];
                                                    }),
                                                )}
                                            </>
                                        ) : (
                                            <>
                                                <th scope="row" className="sis-timetable-stage__period sis-timetable-stage__period--break" {...headerMenu(period)} style={hueStyle(period.color_hue)} />
                                                {stage.columns.map((column) => (
                                                    <td
                                                        key={`${column.key}:break:${period.id}`}
                                                        colSpan={column.members.length * perSection}
                                                        {...headerMenu(period)}
                                                        className={`sis-timetable-cell sis-timetable-cell--break sis-timetable-break--${breakTone(minutesOf(period.end_time) - minutesOf(period.start_time))} sis-timetable-stage__edge--department`}
                                                        style={hueStyle(period.color_hue)}
                                                    >
                                                        <span className="sis-timetable-cell__break-label">{breakText(period)}</span>
                                                    </td>
                                                ))}
                                            </>
                                        )}
                                    </tr>
                                ))}
                            </tbody>
                            </Fragment>
                        ))}
                    </table>
                </div>
            </section>
        );
    };

    /**
     * The sheets of the page: one per stage (class name — «الأول», «الثاني» …), in class order; inside, the
     * departments in the order of `groups` (organization structure), each with its sections of that stage.
     */
    const stageSheets = useMemo((): StageSheet[] => {
        const sheets = new Map<string, StageSheet & { order: number }>();
        for (const group of groups) {
            const title = group.title.split(' › ').pop() ?? group.title;
            for (const member of group.sections) {
                const name = member.section.class_name;
                const sheet = sheets.get(name) ?? { key: `stage:${name}`, name, columns: [], order: member.section.class_id };
                sheet.order = Math.min(sheet.order, member.section.class_id);
                let column = sheet.columns.find((c) => c.key === group.key);
                if (column === undefined) {
                    column = { key: group.key, title, members: [] };
                    sheet.columns.push(column);
                }
                column.members.push(member);
                sheets.set(name, sheet);
            }
        }

        return [...sheets.values()].sort((a, b) => a.order - b.order).map(({ order: _order, ...sheet }) => sheet);
    }, [groups]);

    const yearName = years.find((y) => y.id === yearId)?.name ?? '';

    const effectiveVersion = engine?.versions.find((v) => v.id === engine.status.effective_version_id) ?? null;
    /** Heading of the sheets: «2026 - 2027», and «4 / 10 / 2026» from «ترويسة الجدول» (else the effective version's date). */
    // The stored name reads «السنة الدراسية 2026-2027»; the header already says «للسنة الدراسية» → the number only.
    const sheetYear = yearName.replace(/^\s*السنة\s+الدراسية\s*/, '').replace(/\s*[-–]\s*/, ' - ').trim();
    const sheetEffective = (() => {
        const iso = settings.heading.effective_from ?? effectiveVersion?.effective_from ?? null;
        const m = iso === null ? null : /^(\d{4})-(\d{2})-(\d{2})/.exec(iso);

        return m === null ? '' : `${Number(m[3])} / ${Number(m[2])} / ${m[1]}`;
    })();
    const schoolName = page.schoolContext?.schools.find((x) => x.id === page.schoolContext?.schoolId)?.name ?? '';

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
                    {singleGrid
                        ? groups.map((group) => <Fragment key={group.key}>{group.sections.map(({ section, students }) => sectionBoard(section, students, false))}</Fragment>)
                        : stageSheets.map((stage) => stageBoard(stage))}
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
                    <div className="sis-timetable-grid" {...gridData(settings)} style={gridStyle(settings)}>{gridTable(teacherCell, false)}</div>
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
                    <div className="sis-timetable-grid" {...gridData(settings)} style={gridStyle(settings)}>
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

    // What the full-screen preview shows — and, in «تخطيط الطباعة», what lies on the sheet and prints.
    const previewContent =
        view === 'teacher' ? (
                        <section className="sis-timetable-board">

                            <header className="sis-timetable-board__head">

                                <span className="sis-timetable-board__title">{teacherNames.get(activeTeacherId ?? 0) ?? ''}</span>

                            </header>

                            <div className="sis-timetable-grid" {...gridData(settings)} style={gridStyle(settings)}>

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
        ) : view === 'room' ? (
            roomView
        ) : (
                        // One sheet per stage, like the school's printed pages (a page break between stages).
                        <div className="sis-timetable-master-sheets">
                            {stageSheets.map((stage) => (
                                <MasterTimetable
                                    key={stage.key}
                                    columns={stage.columns.map((column) => ({
                                        key: column.key,
                                        title: column.title,
                                        sections: column.members.map(({ section }) => ({
                                            id: section.id,
                                            label: resolveSisSectionCode(section.id, sections) !== '' ? `${section.class_name} ${resolveSisSectionCode(section.id, sections)}` : `${section.class_name} — ${section.name}`,
                                        })),
                                    }))}
                                    days={DAYS}
                                    dayLabel={dayOfWeekLabel}
                                    periods={lessonPeriods.map((p) => ({ id: p.id, number: lessonNumber.get(p.id) ?? 0 }))}
                                    lessons={schedules}
                                    subjectName={(id) => subjectNames.get(id) ?? `#${id}`}
                                    teacherName={(id) => teacherNames.get(id) ?? `#${id}`}
                                    groupName={(id) => groupNames.get(id) ?? ''}
                                    subjectStyle={(id) => subjectStyles.get(id)}
                                    heading={{ title: tt.master.title, school: schoolName, year: sheetYear, stage: stage.name, effectiveFrom: sheetEffective === '' ? null : sheetEffective }}
                                    showTeacher={stageTeachers}
                                />
                            ))}
                        </div>
        );
    /** «تحرير الخلية» panes beside «المحتوى»: each entity's facts with its owner's «الاختصار واللون» and page. */
    const cellPanes = (s: Schedule): Record<string, ReactNode> => {
        const c = tt.cell;
        const ctx = cellCtx(s, null);
        const period = sortedPeriods.find((p) => p.id === s.period_id);
        const row = (kind: DisplayKind, id: number | null) => (id === null ? null : displayRow(display, kind, id));
        const facts = (items: Array<[string, string | number | null | undefined]>) => (
            <ul className="sis-timetable-audit__items sis-timetable-facts sis-branches-field--wide">
                {items.filter(([, v]) => v !== null && v !== undefined && v !== '').map(([label, value]) => (
                    <li key={label} className="sis-timetable-audit__item">
                        <span className="sis-timetable-audit__text">{label}</span>
                        <span>{value}</span>
                    </li>
                ))}
            </ul>
        );
        const entityPane = (id: string, title: string, kind: DisplayKind, entityId: number | null, items: Array<[string, string | number | null | undefined]>, page: string) => (
            <SheetSection id={id} title={title}>
                {facts(items)}
                <div className="sis-admission-sheet__actions">
                    {entityId !== null ? (
                        <Button type="button" variant="outline" onClick={() => setAppearanceFor({ kind, id: entityId })}>
                            <Palette aria-hidden />
                            {i18n.appearance.edit}
                        </Button>
                    ) : null}
                    <Button type="button" variant="outline" onClick={() => router.get(page)}>
                        {c.openPage}
                    </Button>
                </div>
            </SheetSection>
        );
        const teacher = row('teachers', s.teacher_id);
        const subject = row('subjects', s.subject_id);
        const room = row('rooms', s.room_id);
        const section = row('sections', s.section_id);
        const printMask = period?.print_in ?? 31;

        return {
            format: (
                <SheetSection id="timetable-cell-format" title={tt.format.lens}>
                    <p className="sis-timetable-sheet__hint sis-branches-field--wide">{c.formatHint}</p>
                    <div className="sis-admission-sheet__actions">
                        <Button type="button" onClick={() => setFormatFor({ scheduleId: s.id })}>
                            <Search aria-hidden />
                            {tt.format.open}
                        </Button>
                    </div>
                </SheetSection>
            ),
            teacher: entityPane('timetable-cell-teacher', ctx.teacherName, 'teachers', s.teacher_id, [
                [i18n.appearance.abbreviation, teacher?.short],
                [tt.format.teacherTitle, teacher?.title ?? null],
                [tt.teacherLoad, schedules.filter((x) => x.teacher_id === s.teacher_id).length],
            ], '/teachers'),
            subject: entityPane('timetable-cell-subject', ctx.subjectName, 'subjects', s.subject_id, [
                [i18n.appearance.abbreviation, subject?.short],
                [tt.format.fieldNames.subject_type, ctx.practical ? tt.practical : null],
            ], '/curriculum'),
            room: entityPane('timetable-cell-room', ctx.roomName ?? c.noRoom, 'rooms', s.room_id, [
                [i18n.appearance.abbreviation, room?.short ?? null],
                [c.capacity, room?.capacity ?? null],
                [c.practicalRoom, room?.supports_practical ? tt.practical : null],
                [c.students, ctx.students],
            ], '/organization/rooms'),
            section: entityPane('timetable-cell-section', sectionLabel(s.section_id), 'sections', s.section_id, [
                [i18n.appearance.abbreviation, section?.short ?? null],
                [c.students, ctx.students],
                [c.capacity, section?.capacity ?? null],
                [tt.format.fieldNames.branch, ctx.branchName],
                [tt.format.fieldNames.department, ctx.departmentName],
                [tt.format.fieldNames.group, ctx.groupName],
            ], '/organization/classes-sections'),
            view: (
                <SheetSection id="timetable-cell-view" title={tt.format.fields}>
                    <p className="sis-timetable-sheet__hint sis-branches-field--wide">{c.viewHint}</p>
                    <span className="sis-timetable-audit__bar">
                        {(Object.keys(settings.fields) as Array<keyof DisplaySettings['fields']>).map((field) => (
                            <label key={field} className="sis-timetable-audit__filter">
                                <input type="checkbox" checked={settings.fields[field]} onChange={(e) => setDisplayOverride({ ...settings, fields: { ...settings.fields, [field]: e.target.checked } })} />
                                {tt.format.fieldNames[field]}
                            </label>
                        ))}
                    </span>
                </SheetSection>
            ),
            print: (
                <SheetSection id="timetable-cell-print" title={tt.schoolDay.printIn}>
                    <p className="sis-timetable-sheet__hint sis-branches-field--wide">{c.printHint}</p>
                    <span className="sis-timetable-audit__bar">
                        {([['general', 1], ['teachers', 2], ['sections', 4], ['students', 8], ['rooms', 16]] as const).map(([key, bit]) => (
                            <label key={key} className="sis-timetable-audit__filter">
                                <input
                                    type="checkbox"
                                    checked={(printMask & bit) === bit}
                                    disabled={!authorization.can_manage_periods || period === undefined || saving}
                                    onChange={(e) =>
                                        period !== undefined &&
                                        void scheduleRequest('post', '/timetable/periods/reshape', {
                                            operation: 'retime',
                                            period_id: period.id,
                                            start_time: period.start_time.slice(0, 5),
                                            end_time: period.end_time.slice(0, 5),
                                            cascade: false,
                                            name: period.name ?? null,
                                            abbreviation: period.abbreviation ?? null,
                                            color_hue: period.color_hue ?? null,
                                            show_in: period.show_in ?? 31,
                                            print_in: e.target.checked ? printMask | bit : printMask & ~bit,
                                        })
                                    }
                                />
                                {tt.schoolDay.targets[key]}
                            </label>
                        ))}
                    </span>
                </SheetSection>
            ),
        };
    };

    // ── Context menu (right click / Shift+F10) ──────────────────────────────────
    const appearanceTarget = (kind: DisplayKind, id: number): { url: string; payload: Record<string, string | number> } => {
        switch (kind) {
            case 'subjects':
                return { url: `/curriculum/subjects/${id}`, payload: {} };
            case 'teachers':
                return { url: `/teachers/${id}/appearance`, payload: {} };
            case 'sections':
            case 'classes':
                return { url: '/organization/classes-sections/appearance', payload: { target: kind === 'sections' ? 'section' : 'class', id } };
            default:
                return { url: '/organization/appearance', payload: { target: kind === 'rooms' ? 'room' : kind === 'room_types' ? 'room_type' : kind === 'branches' ? 'branch' : 'department', id } };
        }
    };
    const lockLesson = (s: Schedule) => void run('post', '/timetable/schedules/lock', { academic_year_id: yearId, schedule_ids: [s.id], lock: s.locked ? 0 : 1 });
    const toggleField = (field: 'teacher' | 'room') => setDisplayOverride({ ...settings, fields: { ...settings.fields, [field]: !settings.fields[field] } });
    const menuItems = (target: MenuTarget): ContextMenuItem[] => {
        const m = tt.menu;
        if (target.type === 'period') {
            return [
                { id: 'period-edit', label: target.period.period_type === LESSON ? m.editPeriod : m.editBreak, icon: target.period.period_type === LESSON ? CalendarClock : Coffee, disabled: !authorization.can_manage_periods, onSelect: () => setDayOpen(true) },
                { id: 'period-format', label: m.format, icon: Search, onSelect: () => setFormatFor({ scheduleId: null }) },
            ];
        }
        if (target.type === 'cell') {
            const targetSection = sectionsById.get(target.sectionId) ?? null;
            const canPaste =
                clipboard !== null &&
                canPlace &&
                (clipboard.sectionId === target.sectionId || lessonsOf(targetSection).some((l) => l.subjectId === clipboard.subjectId && l.teacherId === clipboard.teacherId));
            return [
                { id: 'cell-add', label: m.add, icon: Sparkles, disabled: !canPlace, onSelect: () => setPickCell({ sectionId: target.sectionId, day: target.day, periodId: target.periodId }) },
                {
                    id: 'cell-paste',
                    label: m.paste,
                    icon: ClipboardPaste,
                    disabled: !canPaste,
                    onSelect: () => clipboard !== null && place({ sectionId: target.sectionId, subjectId: clipboard.subjectId, teacherId: clipboard.teacherId, scheduleId: null }, target.sectionId, target.day, target.periodId),
                },
                { id: 'cell-format', label: m.format, icon: Search, separator: true, onSelect: () => setFormatFor({ scheduleId: null }) },
            ];
        }
        const s = target.schedule;
        const editable = authorization.can_update && viewingVersion === null;
        return [
            { id: 'lesson-edit', label: m.edit, icon: Pencil, disabled: !editable, hint: '2×', onSelect: () => openEdit(s.id) },
            { id: 'lesson-format', label: m.format, icon: Search, onSelect: () => setFormatFor({ scheduleId: s.id }) },
            { id: 'lesson-details', label: m.details, icon: Eye, onSelect: () => openEdit(s.id, 'section') },
            { id: 'lesson-copy', label: m.copy, icon: Copy, separator: true, onSelect: () => setClipboard({ sectionId: s.section_id, subjectId: s.subject_id, teacherId: s.teacher_id }) },
            { id: 'lesson-lock', label: s.locked ? m.unlock : m.lock, icon: s.locked ? Unlock : Lock, disabled: !editable, onSelect: () => lockLesson(s) },
            { id: 'lesson-suggest', label: m.suggest, icon: Lightbulb, disabled: !editable || engineCtx === null, onSelect: () => openEdit(s.id) },
            { id: 'lesson-subject-look', label: m.subjectAppearance, icon: Palette, separator: true, onSelect: () => setAppearanceFor({ kind: 'subjects', id: s.subject_id }) },
            { id: 'lesson-teacher-look', label: m.teacherAppearance, icon: Palette, onSelect: () => setAppearanceFor({ kind: 'teachers', id: s.teacher_id }) },
            { id: 'lesson-section-look', label: m.sectionAppearance, icon: Palette, onSelect: () => setAppearanceFor({ kind: 'sections', id: s.section_id }) },
            ...(s.room_id !== null ? [{ id: 'lesson-room-look', label: m.roomAppearance, icon: Palette, onSelect: () => setAppearanceFor({ kind: 'rooms', id: s.room_id as number }) }] : []),
            { id: 'lesson-open-teacher', label: m.openTeacher, icon: UserRound, separator: true, onSelect: () => router.get('/teachers') },
            { id: 'lesson-open-subject', label: m.openSubject, icon: Boxes, onSelect: () => router.get('/curriculum') },
            ...(s.room_id !== null ? [{ id: 'lesson-open-room', label: m.openRoom, icon: DoorClosed, onSelect: () => router.get('/organization/rooms') }] : []),
            singleGrid || view !== 'section'
                ? { id: 'lesson-teacher-toggle', label: settings.fields.teacher ? m.hideTeacher : m.showTeacher, icon: settings.fields.teacher ? EyeOff : Eye, separator: true, onSelect: () => toggleField('teacher') }
                : { id: 'lesson-teacher-toggle', label: stageTeachers ? m.hideTeacher : m.showTeacher, icon: stageTeachers ? EyeOff : Eye, separator: true, onSelect: () => setStageTeachers(!stageTeachers) },
            { id: 'lesson-room-toggle', label: settings.fields.room ? m.hideRoom : m.showRoom, icon: settings.fields.room ? EyeOff : Eye, onSelect: () => toggleField('room') },
            { id: 'lesson-remove', label: m.remove, icon: Trash2, danger: true, separator: true, disabled: !editable || !authorization.can_cancel || s.locked === true, onSelect: () => void unplace(s.id) },
        ];
    };

    // ── «اختبار الجدول»: remedies run through the normal endpoints (validated again on the server) ───────
    const testNames = {
        section: (id: number | null) => sectionLabel(id),
        teacher: (id: number | null) => (id === null ? '' : (teacherNames.get(id) ?? `#${id}`)),
        subject: (id: number | null) => (id === null ? '' : (subjectNames.get(id) ?? `#${id}`)),
        room: (id: number | null) => roomName(id) ?? '',
        day: (day: number | null) => (day === null ? '' : dayOfWeekLabel(day)),
        period: (id: number | null) => periodLabel(id),
    };
    const applyFix = async (fix: TestFix): Promise<boolean> => {
        const p = fix.params as Record<string, unknown>;
        switch (fix.action) {
            case 'patch_schedule': {
                const lesson = schedules.find((s) => s.id === Number(p.schedule_id));
                if (lesson === undefined) {
                    return false;
                }
                return run('patch', `/timetable/schedules/${lesson.id}`, lessonBody({
                    ...lesson,
                    day_of_week: p.day_of_week === undefined ? lesson.day_of_week : Number(p.day_of_week),
                    period_id: p.period_id === undefined ? lesson.period_id : Number(p.period_id),
                    room_id: 'room_id' in p ? (p.room_id === null ? null : Number(p.room_id)) : lesson.room_id,
                }));
            }
            case 'cancel_schedule':
                return unplace(Number(p.schedule_id));
            case 'auto_place':
                return run('post', '/timetable/schedules/auto-place', { academic_year_id: yearId, section_ids: (p.section_ids as number[]) ?? [] });
            case 'reshape_day': {
                const first = lessonPeriods[0];
                const body: Record<string, string | number | null> = { ...(p as Record<string, string | number | null>) };
                if (body.operation === 'fixed_pattern' && first !== undefined) {
                    body.start_time = (sortedPeriods[0]?.start_time ?? first.start_time).slice(0, 5);
                    body.minutes = minutesOf(first.end_time) - minutesOf(first.start_time);
                }
                return scheduleRequest('post', '/timetable/periods/reshape', body);
            }
            case 'update_settings':
                if (engine === null) {
                    return false;
                }
                return scheduleRequest('post', '/timetable/settings', { academic_year_id: yearId, ...engine.settings, ...(p as Record<string, number>) });
            case 'apply_abbreviations': {
                const target = String(p.target);
                const kind: DisplayKind = target === 'teacher' ? 'teachers' : target === 'subject' ? 'subjects' : 'rooms';
                let ok = true;
                for (const item of (p.items as Array<{ id: number; abbreviation: string }>) ?? []) {
                    const at = appearanceTarget(kind, item.id);
                    ok = (await scheduleRequest('patch', at.url, { ...at.payload, abbreviation: item.abbreviation, color_hue: displayRow(display, kind, item.id)?.color_hue ?? null })) && ok;
                }
                return ok;
            }
            case 'appearance': {
                const ids = (p.ids as number[]) ?? [];
                const target = String(p.target);
                if (ids[0] !== undefined) {
                    setAppearanceFor({ kind: target === 'teacher' ? 'teachers' : target === 'subject' ? 'subjects' : 'rooms', id: ids[0] });
                }
                return true;
            }
            case 'open':
                router.get(p.page === 'teachers' ? '/teachers' : p.page === 'curriculum' ? '/curriculum' : p.page === 'rooms' ? '/organization/rooms' : '/organization/classes-sections');
                return true;
            case 'open_periods':
                setTestOpen(false);
                setDayOpen(true);
                return true;
            case 'open_engine':
                setTestOpen(false);
                setEngineSheet(p.sheet === 'places' ? 'places' : 'activities');
                return true;
            case 'generate':
                setTestOpen(false);
                setEngineSheet('generate');
                return true;
            default:
                return false;
        }
    };
    const goToTestIssue = (issue: TestIssue) => {
        goToIssue({ severity: 'error', code: issue.code, section_id: issue.section_id, teacher_id: issue.teacher_id, subject_id: issue.subject_id, day: issue.day, period_id: issue.period_id, schedule_ids: issue.schedule_ids, count: issue.count });
        setTestOpen(false);
    };

    /** «اقتراح أماكن بديلة» after a refused drop: the section's free cells for that teacher, lighter days first. */
    const dropAlternatives = (payload: DragPayload) => {
        const free: Array<{ day: number; periodId: number; load: number }> = [];
        for (const day of DAYS) {
            const load = [...(sectionCells.get(payload.sectionId)?.values() ?? [])].filter((s) => s.day_of_week === day).length;
            for (const p of lessonPeriods) {
                if (cellState(payload.sectionId, day, p.id, payload) === 'free') {
                    free.push({ day, periodId: p.id, load });
                }
            }
        }

        return free.sort((a, b) => a.load - b.load || a.day - b.day).slice(0, 8);
    };
    const formatSamples = (() => {
        const focus = formatFor?.scheduleId ? schedules.find((s) => s.id === formatFor.scheduleId) : undefined;
        const picks = [...(focus ? [focus] : []), ...schedules.filter((s) => s.id !== focus?.id)].slice(0, 2);

        return picks.map((s) => ({ lesson: s as CellLesson, ctx: cellCtx(s, null) }));
    })();

    const printDate = new Date().toLocaleDateString('ar', { year: 'numeric', month: 'long', day: 'numeric' });
    /** Prints the sheet shown in the preview. */
    const printNow = () => {
        setPrintPanel(false);
        window.print();
    };

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
                    {/* The printed sheet: the paper, orientation and margins of «إعدادات الطباعة». */}
                    <style>{pageRule(printSettings)}</style>
                    <header className="sis-timetable-preview__head sis-timetable-preview__head--one-line">
                        <div className="sis-timetable-preview__line">
                        <span className="sis-timetable-preview__filter">
                            <span className="sis-timetable-preview__filter-label">{tt.zoom.kind}</span>
                            <SisListSelect
                                value={view}
                                options={[
                                    { value: 'section', label: tt.bySection },
                                    { value: 'teacher', label: tt.byTeacher },
                                    ...((engine?.rooms.length ?? 0) > 0 ? [{ value: 'room', label: et.byRoom }] : []),
                                ]}
                                onChange={(next) => setView(next as 'section' | 'teacher' | 'room')}
                                className="sis-timetable-preview__select"
                                triggerClassName="sis-timetable-preview__control"
                                dir="rtl"
                                ariaLabel={tt.zoom.kind}
                            />
                        </span>
                        {view === 'teacher' ? (
                            <span className="sis-timetable-preview__filter">
                                <span className="sis-timetable-preview__filter-label">{tt.teacher}</span>
                                <SisListSelect
                                    value={activeTeacherId === null ? '' : String(activeTeacherId)}
                                    options={teachers.map((x) => ({ value: String(x.id), label: x.full_name }))}
                                    onChange={(next) => setTeacherId(Number(next))}
                                    className="sis-timetable-preview__select"
                                    triggerClassName="sis-timetable-preview__control"
                                    dir="rtl"
                                    ariaLabel={tt.teacher}
                                />
                            </span>
                        ) : null}
                        {view === 'room' ? (
                            <span className="sis-timetable-preview__filter">
                                <span className="sis-timetable-preview__filter-label">{et.room}</span>
                                <SisListSelect
                                    value={activeRoomId === null ? '' : String(activeRoomId)}
                                    options={(engine?.rooms ?? []).map((r) => ({ value: String(r.id), label: `${r.code} — ${r.name}` }))}
                                    onChange={(next) => setRoomId(Number(next))}
                                    className="sis-timetable-preview__select"
                                    triggerClassName="sis-timetable-preview__control"
                                    dir="rtl"
                                    ariaLabel={et.room}
                                />
                            </span>
                        ) : null}
                        {view === 'section' ? (
                            <span className="sis-timetable-preview__filters" role="group" aria-label={tt.ribbonFilters}>
                                {(
                                    [
                                        ['branch', tt.branch, (next: string) => setFilter({ branch: next, department: '' })],
                                        ['department', tt.department, (next: string) => setFilter({ department: next })],
                                        ['class', tt.class, (next: string) => setFilter({ class: next })],
                                        ['section', tt.section, (next: string) => setFilter({ section: next })],
                                    ] as const
                                ).map(([key, label, onChange]) => (
                                    <span key={key} className="sis-timetable-preview__filter">
                                        <span className="sis-timetable-preview__filter-label">{label}</span>
                                        <SisListSelect
                                            value={gridFilters[key]}
                                            options={filterOptions[key]}
                                            onChange={onChange}
                                            className="sis-timetable-preview__select"
                                            triggerClassName="sis-timetable-preview__control"
                                            dir="rtl"
                                            ariaLabel={label}
                                        />
                                    </span>
                                ))}
                                <Button type="button" variant="outline" className="sis-timetable-preview__close" aria-pressed={!gridFiltered} title={tt.showAllHint} onClick={() => setGridFilters(NO_FILTERS)}>
                                    <FilterX aria-hidden />
                                    {tt.showAll}
                                </Button>
                            </span>
                        ) : null}
                        <span className="sis-timetable-preview__actions" role="group" aria-label={tt.zoom.kind}>
                            <Button type="button" variant="outline" className="sis-timetable-preview__close" aria-pressed={printPanel} aria-expanded={printPanel} onClick={() => setPrintPanel((current) => !current)}>
                                <Settings2 aria-hidden />
                                {tt.printSetup.title}
                            </Button>
                            <Button type="button" variant="outline" className="sis-timetable-preview__close" onClick={printNow}>
                                <Printer aria-hidden />
                                {tt.print}
                            </Button>
                            <Button type="button" variant="outline" className="sis-timetable-preview__close" onClick={closePreview}>
                                <Minimize2 aria-hidden />
                                {tt.previewClose}
                            </Button>
                        </span>
                        </div>
                    </header>
                    {printPanel ? <PrintSettingsPanel settings={printSettings} onChange={changePrintSettings} onClose={() => setPrintPanel(false)} /> : null}
                    {/* «معاينة الطباعة» only: always the printed sheet (paper, orientation, margins of «إعدادات الطباعة»). */}
                    <div ref={previewBodyRef} className="sis-timetable-preview__body sis-timetable-preview__body--paper">
                        <TimetablePaper settings={printSettings} footer={`${tt.printSetup.printedOn} ${printDate}`}>
                            {previewContent}
                        </TimetablePaper>
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
                    onSave={async (subjectId, teacher, room) => {
                        if (await run('patch', `/timetable/schedules/${editing.id}`, lessonBody({ ...editing, subject_id: subjectId, teacher_id: teacher, room_id: room }))) {
                            setEditId(null);
                        }
                    }}
                    tab={editTab}
                    onTab={setEditTab}
                    rooms={(engine?.rooms ?? []).map((r) => ({ id: r.id, label: `${r.code} — ${r.name}`, capacity: r.capacity, practical: r.room_type === 2 }))}
                    roomBusy={(room) => schedules.some((x) => x.id !== editing.id && x.room_id === room && x.day_of_week === editing.day_of_week && x.period_id === editing.period_id && (x.joined_to ?? null) === null)}
                    students={cellCtx(editing, null).students ?? 0}
                    panes={cellPanes(editing)}
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
            {engineCtx !== null && engineSheet === 'places' ? <PlacesSheet ctx={engineCtx} onClose={() => setEngineSheet(null)} /> : null}
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

            <SisContextMenu controller={cardMenu} items={menuItems} label={tt.menu.label} />

            {formatFor !== null ? (
                <FormatSheet
                    settings={settings}
                    samples={formatSamples}
                    sampleStyle={cardStyle}
                    yearId={yearId}
                    canSave={authorization.can_manage_constraints}
                    focusLabel={formatFor.scheduleId === null ? null : (() => {
                        const s = schedules.find((x) => x.id === formatFor.scheduleId);

                        return s === undefined ? null : `${dayOfWeekLabel(s.day_of_week)} · ${periodLabel(s.period_id)} · ${sectionLabel(s.section_id)}`;
                    })()}
                    onApply={setDisplayOverride}
                    onClose={() => setFormatFor(null)}
                />
            ) : null}

            {testOpen && yearId !== null ? (
                <TestSheet
                    report={testReport}
                    yearId={yearId}
                    names={testNames}
                    canManage={authorization.can_manage_constraints}
                    canGenerate={authorization.can_generate}
                    onFix={applyFix}
                    onGo={goToTestIssue}
                    onGenerate={() => {
                        setTestOpen(false);
                        setEngineSheet('generate');
                    }}
                    onClose={() => setTestOpen(false)}
                />
            ) : null}

            {headingOpen && yearId !== null ? (
                <HeadingSheet
                    settings={settings}
                    yearId={yearId}
                    school={schoolName}
                    year={sheetYear}
                    stages={stageSheets.map((s) => s.name)}
                    canSave={authorization.can_manage_constraints}
                    onSaved={setDisplayOverride}
                    onClose={() => setHeadingOpen(false)}
                />
            ) : null}
            {dayOpen ? <SchoolDaySheet periods={sortedPeriods} lessonNumber={lessonNumber} clock={(time) => formatClock(time, settings.period_header.clock, tt.am, tt.pm)} onClose={() => setDayOpen(false)} /> : null}

            {appearanceFor !== null ? (() => {
                const row = displayRow(display, appearanceFor.kind, appearanceFor.id);
                const target = appearanceTarget(appearanceFor.kind, appearanceFor.id);

                return (
                    <AppearanceDialog
                        title={tt.cell.appearanceOf.replace('{name}', row?.name ?? '')}
                        entityName={row?.name ?? `#${appearanceFor.id}`}
                        initial={{ abbreviation: row?.abbreviation ?? '', color_hue: row?.color_hue ?? null }}
                        suggested={row?.suggested ?? null}
                        url={target.url}
                        payload={target.payload}
                        reloadProps={['display', 'schedules', 'engine', 'flash']}
                        canEdit
                        onClose={() => setAppearanceFor(null)}
                    />
                );
            })() : null}

            {dropReject !== null ? (
                <RegistrySheetDialog title={tt.drop.title} className="sis-branches-sheet sis-timetable-sheet sis-timetable-pick-sheet" onClose={() => setDropReject(null)}>
                    <SheetSection id="timetable-drop" title={`${subjectNames.get(dropReject.payload.subjectId) ?? ''} · ${sectionLabel(dropReject.payload.sectionId)} — ${dayOfWeekLabel(dropReject.day)} · ${periodLabel(dropReject.periodId)}`}>
                        <p className="sis-timetable-sheet__hint sis-branches-field--wide">
                            {dropReject.reason === 'busy' ? tt.drop.teacher.replace('{teacher}', teacherNames.get(dropReject.payload.teacherId) ?? '') : tt.drop.taken}
                        </p>
                        <p className="sis-timetable-sheet__hint sis-branches-field--wide">{tt.drop.alternatives}:</p>
                        <div className="sis-timetable-pick sis-branches-field--wide">
                            {dropAlternatives(dropReject.payload).length === 0 ? (
                                <p className="sis-branches-empty">{tt.drop.none}</p>
                            ) : (
                                dropAlternatives(dropReject.payload).map((alt) => (
                                    <button
                                        key={`${alt.day}:${alt.periodId}`}
                                        type="button"
                                        className="sis-timetable-card sis-timetable-card--pick"
                                        style={subjectStyles.get(dropReject.payload.subjectId)}
                                        disabled={saving}
                                        onClick={() => {
                                            const payload = dropReject.payload;
                                            setDropReject(null);
                                            place(payload, payload.sectionId, alt.day, alt.periodId);
                                        }}
                                    >
                                        <span className="sis-timetable-card__subject">{dayOfWeekLabel(alt.day)}</span>
                                        <span className="sis-timetable-card__line">{periodLabel(alt.periodId)} · {tt.drop.place}</span>
                                    </button>
                                ))
                            )}
                        </div>
                    </SheetSection>
                    <div className="sis-admission-sheet__actions">
                        <Button type="button" variant="outline" onClick={() => setDropReject(null)}>
                            {tt.close}
                        </Button>
                    </div>
                </RegistrySheetDialog>
            ) : null}

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
    tab,
    onTab,
    panes,
    rooms,
    roomBusy,
    students,
}: {
    lesson: Schedule;
    when: string;
    subjectOptions: Array<[number, string]>;
    teachers: Teacher[];
    teacherSubjectKeys: Set<string>;
    isBusy: (teacherId: number) => boolean;
    saving: boolean;
    canCancel: boolean;
    onSave: (subjectId: number, teacherId: number, roomId: number | null) => void;
    onShift: (direction: 1 | -1) => void;
    onDelete: () => void;
    onClose: () => void;
    /** Engine tools (lock, move suggestions, substitutes) under the lesson fields. */
    extra?: ReactNode;
    /** «تحرير الخلية» tabs: content (here) + the panes the page passes (format, teacher, subject, room, section, view, print). */
    tab: string;
    onTab: (tab: string) => void;
    panes: Record<string, ReactNode>;
    rooms: Array<{ id: number; label: string; capacity: number | null; practical: boolean }>;
    roomBusy: (roomId: number) => boolean;
    students: number;
}) {
    const tt = t().timetable;
    const [subjectId, setSubjectId] = useState(String(lesson.subject_id));
    const [teacherId, setTeacherId] = useState(String(lesson.teacher_id));
    const [roomId, setRoomId] = useState(lesson.room_id === null ? '' : String(lesson.room_id));
    const c = t().timetable.cell;

    // Teachers who may teach the chosen subject; busy ones are listed but marked.
    const teacherOptions = teachers
        .filter((x) => teacherSubjectKeys.has(lessonKey(Number(subjectId), x.id)) || x.id === lesson.teacher_id)
        .map((x) => ({ value: String(x.id), label: isBusy(x.id) ? `${x.short_name} — ${tt.teacherBusyShort}` : x.short_name }));
    const teacherValid = teacherOptions.some((o) => o.value === teacherId) && !isBusy(Number(teacherId));
    const changed = subjectId !== String(lesson.subject_id) || teacherId !== String(lesson.teacher_id) || roomId !== (lesson.room_id === null ? '' : String(lesson.room_id));
    const roomOptions = [
        { value: '', label: c.noRoom },
        ...rooms.map((r) => ({
            value: String(r.id),
            label: `${r.label}${r.capacity !== null ? ` (${r.capacity})` : ''}${r.id !== lesson.room_id && roomBusy(r.id) ? ` — ${c.roomBusy}` : ''}${r.capacity !== null && r.capacity < students ? ` — ${c.roomSmall}` : ''}`,
        })),
    ];
    const roomValid = roomId === '' || Number(roomId) === lesson.room_id || !roomBusy(Number(roomId));
    const subjectSelect = subjectOptions.map(([id, name]) => ({ value: String(id), label: name }));

    return (
        <RegistrySheetDialog title={c.title} className="sis-branches-sheet sis-timetable-sheet sis-timetable-lesson-sheet" onClose={onClose}>
            <div className="sis-curriculum-view-tabs sis-timetable-cell-tabs" role="tablist" aria-label={c.title}>
                {['content', ...Object.keys(panes)].map((key) => (
                    <button key={key} type="button" role="tab" aria-selected={tab === key} className={`sis-curriculum-view-tabs__btn${tab === key ? ' is-active' : ''}`} onClick={() => onTab(key)}>
                        {(c.tabs as Record<string, string>)[key] ?? key}
                    </button>
                ))}
            </div>
            {tab !== 'content' ? panes[tab] ?? null : null}
            {tab === 'content' ? (
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
                    />
                    <RegistryListField
                        label={tt.lessonTeacher}
                        editing
                        value={teacherId}
                        display={teacherOptions.find((o) => o.value === teacherId)?.label ?? ''}
                        options={teacherOptions.length === 0 ? [{ value: '', label: tt.noOtherTeacher }] : teacherOptions}
                        onChange={setTeacherId}
                    />
                    <RegistryListField
                        label={c.room}
                        editing
                        value={roomId}
                        display={roomOptions.find((o) => o.value === roomId)?.label ?? ''}
                        options={roomOptions}
                        onChange={setRoomId}
                    />
                    <p className="sis-timetable-sheet__hint sis-branches-field--wide">{tt.shiftHint}</p>
                </div>
            </SheetSection>
            ) : null}
            {tab === 'content' ? extra : null}
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
                <Button type="button" disabled={saving || !changed || !teacherValid || !roomValid} onClick={() => onSave(Number(subjectId), Number(teacherId), roomId === '' ? null : Number(roomId))}>
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
    const [confirmArrange, setConfirmArrange] = useState(false);
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

    // «تعديل الحصة» / «إضافة حصة»: its own window above this one (not a row editor inside the list).
    const periodWindow =
        editingId === null ? null : (
            <TimetableSubWindow title={editingId === 'new' ? tt.addPeriod : tt.editPeriod} onClose={() => setEditingId(null)}>
                <SheetSection id="timetable-period-form" title={editingId === 'new' ? tt.addPeriod : tt.editPeriod}>
                    <div className="sis-admission-sheet__row sis-admission-sheet__row--full sis-branches-sheet__row">
                        <label className="sis-admission-sheet__field">
                            <span className="sis-admission-sheet__label">{tt.periodOrder}</span>
                            <input className="sis-admission-sheet__control" type="number" min={1} max={20} value={form.period_number} onChange={(e) => set('period_number')(e.target.value)} />
                        </label>
                        <label className="sis-admission-sheet__field">
                            <span className="sis-admission-sheet__label">{tt.startTime}</span>
                            <input className="sis-admission-sheet__control" type="time" value={form.start_time} onChange={(e) => set('start_time')(e.target.value)} />
                        </label>
                        <label className="sis-admission-sheet__field">
                            <span className="sis-admission-sheet__label">{tt.endTime}</span>
                            <input className="sis-admission-sheet__control" type="time" value={form.end_time} onChange={(e) => set('end_time')(e.target.value)} />
                        </label>
                        <div className="sis-admission-sheet__field">
                            <span className="sis-admission-sheet__label">{tt.periodType}</span>
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
                        </div>
                    </div>
                </SheetSection>
                <div className="sis-admission-sheet__actions">
                    <Button type="button" variant="outline" disabled={saving} onClick={() => setEditingId(null)}>
                        {tt.cancelEdit}
                    </Button>
                    <Button type="button" disabled={saving || form.start_time === '' || form.end_time === '' || form.period_number === ''} onClick={() => void save()}>
                        {saving ? i18n.common.saving : tt.savePeriod}
                    </Button>
                </div>
            </TimetableSubWindow>
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
                        <Button type="button" className="sis-timetable-arrange__run" disabled={saving || editingId !== null || dayStart === '' || lessonMinutes === ''} onClick={() => setConfirmArrange(true)} title={tt.arrangeHint}>
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
                                {periods.map((period) => (
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
                                ))}
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
            {periodWindow}
            <ConfirmDialog
                open={confirmArrange}
                title={i18n.timetable.engine.confirmTitle}
                description={i18n.timetable.engine.confirmArrange}
                confirmLabel={tt.arrangeDay}
                tone="danger"
                onConfirm={() => {
                    setConfirmArrange(false);
                    void arrange();
                }}
                onOpenChange={(open) => {
                    if (!open) {
                        setConfirmArrange(false);
                    }
                }}
            />
        </RegistrySheetDialog>
    );
}
