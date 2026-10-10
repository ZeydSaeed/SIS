import { Head, router, usePage } from '@inertiajs/react';
import {
    ArrowLeftRight,
    BookOpen,
    ClipboardCheck,
    CheckCircle2,
    ChevronDown,
    ChevronLeft,
    CircleSlash,
    Eye,
    FileSignature,
    FilterX,
    GraduationCap,
    Layers,
    Palette,
    Pencil,
    PlusCircle,
    Presentation,
    IdCard,
    Trash2,
    UserCheck,
    UserX,
    XCircle,
    type LucideIcon,
} from 'lucide-react';
import { Fragment, useCallback, useEffect, useMemo, useRef, useState, type CSSProperties, type ReactNode } from 'react';
import {
    blankToNull,
    newIdempotencyKey,
    RegistryListField,
    RegistrySheetDialog,
    RegistryTextField,
    useRegistryRequest,
} from '@/components/organization/registry-sheet';
import { SheetSection } from '@/components/sis/admission-sheet';
import { AppearanceDialog } from '@/components/sis/appearance-fields';
import { ConfirmDialog } from '@/components/sis/confirm-dialog';
import { formatAcademicYearOptionLabel, type YearOption } from '@/components/sis/ops-year-filter';
import { usePageError } from '@/components/sis/page-error-context';
import { useRegisterPageRibbon, type PageRibbonCommand, type PageRibbonGroup } from '@/components/sis/page-ribbon-context';
import { useRegisterPageTitlebarSearch } from '@/components/sis/page-titlebar-search-context';
import { SisListSelect } from '@/components/sis/sis-list-select';
import { tableActionIds, toggleTableRowChecked, toggleTableSelectAll } from '@/components/sis/table-row-selection';
import { Button } from '@/components/ui/button';
import { useFitTablePageSize } from '@/hooks/use-fit-table-page-size';
import { hasPageTextSelection } from '@/hooks/use-page-clipboard';
import { useResizableTableColumns } from '@/hooks/use-resizable-table-columns';
import { useSmoothVerticalScroll } from '@/hooks/use-smooth-vertical-scroll';
import { t } from '@/i18n';
import { initialSearchParam } from '@/lib/initial-search';
import { resolveSisSectionCode, resolveSisSectionId, sisSectionSelectOptions, type SisSectionRef } from '@/lib/sis-class-section-options';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

type Assignment = {
    id: number;
    subject_id: number;
    subject_name: string;
    branch_id: number;
    branch_name: string;
    department_id: number | null;
    department_name: string | null;
    class_id: number | null;
    class_name: string | null;
    section_id: number | null;
    section_name: string | null;
};

type Teacher = {
    id: number;
    employee_code: string;
    first_name: string;
    father_name: string | null;
    grandfather_name: string | null;
    last_name: string;
    full_name: string;
    national_id: string | null;
    specialization_field: string | null;
    hire_date: string | null;
    status: number;
    is_primary: boolean;
    employment_type: number | null;
    weekly_lessons_min: number | null;
    weekly_lessons_max: number | null;
    daily_lessons_max: number | null;
    /** «الاختصار واللون» and «اللقب العلمي» (shown on the timetable). */
    abbreviation?: string | null;
    color_hue?: number | null;
    academic_title_id?: number | null;
    subject_ids: number[];
    assignments: Assignment[];
};

type Subject = { id: number; code: string; name: string };
type BranchOption = { id: number; name: string; departments: Array<{ id: number; name: string }> };
type ClassOption = { id: number; name: string; sections: Array<{ id: number; code: string; name: string }> };

type Props = {
    teachers: Teacher[];
    total: number;
    subjects: Subject[];
    branches: BranchOption[];
    classes: ClassOption[];
    curriculumSubjects: { general: number[]; by_department: Record<string, number[]> };
    academicTitles?: Array<{ id: number; code: string; name: string; abbreviation: string | null }>;
    filters: { academic_year_id: number | null };
    authorization: { can_manage: boolean };
};

type TeacherForm = {
    employee_code: string;
    first_name: string;
    father_name: string;
    grandfather_name: string;
    last_name: string;
    specialization_field: string;
    employment_type: string;
    weekly_lessons_min: string;
    weekly_lessons_max: string;
    daily_lessons_max: string;
    status: string;
    national_id: string;
    hire_date: string;
    academic_title_id: string;
    abbreviation: string;
};

/** section_code: shared SSOT code A / B / C (sis-class-section-options), resolved to the class's section on save. */
type TeachingForm = { branch_id: string; department_id: string; subject_id: string; class_id: string; section_code: string };

/** TeacherStatus: 1 نشط · 2 غير نشط. TeacherEmploymentType: 1 ملاك · 2 مكلف · 3 تنسيب · 4 محاضر · 5 عقد. */
const ACTIVE = 1;
const INACTIVE = 2;
const EMPLOYMENT_TYPES = [1, 2, 3, 4, 5] as const;
/** ملاك = staff ID card · مكلف = assigned task · تنسيب = moved in from another post · محاضر = lecture · عقد = signed contract. */
const EMPLOYMENT_ICONS: Record<number, LucideIcon> = {
    1: IdCard,
    2: ClipboardCheck,
    3: ArrowLeftRight,
    4: Presentation,
    5: FileSignature,
};
const TEACHERS_PER_PAGE = 17;
const RELOAD_PROPS = ['teachers', 'total', 'flash'];
/** GetTeacherRosterHandler::ROSTER_LIMIT */
const ROSTER_LIMIT = 500;

type FilterKey = 'all' | 'active' | 'inactive' | `type-${number}`;

/** Home-ribbon filters (client-side over the loaded roster); '' = all. */
/** department = department name (shown without its branch; same-named departments of all branches match together). */
type RosterFilters = { specialization: string; branch_id: string; department: string; subject_id: string };

const EMPTY_ROSTER_FILTERS: RosterFilters = { specialization: '', branch_id: '', department: '', subject_id: '' };

type SubjectTone = { hue: number; light: number };

/**
 * One unique colour per subject on the roster: hues spread evenly around the wheel
 * (the widest possible gap between any two subjects), neighbours alternate lightness.
 */
function subjectTones(subjectIds: number[]): Map<number, SubjectTone> {
    const step = 360 / Math.max(1, subjectIds.length);

    return new Map(subjectIds.map((id, index) => [id, { hue: Math.round(index * step), light: index % 2 === 0 ? 82 : 90 }]));
}

function SubjectChip({ tone, name, query = '' }: { tone: SubjectTone | undefined; name: string; query?: string }) {
    const style = tone === undefined ? undefined : ({ '--subject-hue': tone.hue, '--subject-light': `${tone.light}%` } as CSSProperties);

    return (
        <span className="sis-teachers-subject" style={style}>
            <HighlightedText text={name} query={query} />
        </span>
    );
}

const EMPTY_FORM: TeacherForm = {
    employee_code: '',
    first_name: '',
    father_name: '',
    grandfather_name: '',
    last_name: '',
    specialization_field: '',
    employment_type: '',
    weekly_lessons_min: '',
    weekly_lessons_max: '',
    daily_lessons_max: '',
    status: String(ACTIVE),
    national_id: '',
    hire_date: '',
    academic_title_id: '',
    abbreviation: '',
};

const EMPTY_TEACHING: TeachingForm = { branch_id: '', department_id: '', subject_id: '', class_id: '', section_code: '' };

function formOf(teacher: Teacher): TeacherForm {
    return {
        employee_code: teacher.employee_code,
        first_name: teacher.first_name,
        father_name: teacher.father_name ?? '',
        grandfather_name: teacher.grandfather_name ?? '',
        last_name: teacher.last_name,
        specialization_field: teacher.specialization_field ?? '',
        employment_type: teacher.employment_type === null ? '' : String(teacher.employment_type),
        weekly_lessons_min: teacher.weekly_lessons_min === null ? '' : String(teacher.weekly_lessons_min),
        weekly_lessons_max: teacher.weekly_lessons_max === null ? '' : String(teacher.weekly_lessons_max),
        daily_lessons_max: teacher.daily_lessons_max === null ? '' : String(teacher.daily_lessons_max),
        status: String(teacher.status === ACTIVE ? ACTIVE : INACTIVE),
        national_id: teacher.national_id ?? '',
        hire_date: teacher.hire_date ?? '',
        academic_title_id: teacher.academic_title_id == null ? '' : String(teacher.academic_title_id),
        abbreviation: teacher.abbreviation ?? '',
    };
}

function matchesFilter(teacher: Teacher, key: FilterKey): boolean {
    if (key === 'all') return true;
    if (key === 'active') return teacher.status === ACTIVE;
    if (key === 'inactive') return teacher.status !== ACTIVE;

    return teacher.employment_type === Number(key.slice(5));
}

/** Branch / department / subject must meet on one teaching; subject alone also matches an untaught linked subject. */
function matchesRosterFilters(teacher: Teacher, f: RosterFilters): boolean {
    if (f.specialization !== '' && (teacher.specialization_field ?? '').trim() !== f.specialization) {
        return false;
    }
    if (f.branch_id === '' && f.department === '') {
        return f.subject_id === '' || teacher.subject_ids.includes(Number(f.subject_id)) || teacher.assignments.some((a) => a.subject_id === Number(f.subject_id));
    }

    return teacher.assignments.some(
        (a) =>
            (f.branch_id === '' || a.branch_id === Number(f.branch_id))
            && (f.department === '' || a.department_name === f.department)
            && (f.subject_id === '' || a.subject_id === Number(f.subject_id)),
    );
}

function visiblePages(current: number, totalPages: number): number[] {
    const start = Math.max(1, Math.min(current - 2, totalPages - 4));
    const end = Math.min(totalPages, start + 4);

    return Array.from({ length: end - start + 1 }, (_, index) => start + index);
}

/** «شبكات الحاسوب — الحاسوب وتقنية المعلومات › شبكات الحاسوب · الأول/أ» */
function assignmentPlace(assignment: Assignment): string {
    const tc = t().teachers;
    const where = [assignment.branch_name, assignment.department_name ?? tc.allDepartments].join(' › ');
    const room = assignment.class_name === null ? null : [assignment.class_name, assignment.section_name].filter(Boolean).join(' / ');

    return room === null ? where : `${where} · ${room}`;
}

function CellScroll({ children }: { children: ReactNode }) {
    return <div className="sis-students-table__cell-scroll">{children}</div>;
}

/** Search words (any order): a teacher matches when every word is found in one of its fields. */
function searchTokens(query: string): string[] {
    return query
        .trim()
        .split(/\s+/)
        .filter((token) => token !== '')
        .map((token) => token.toLocaleLowerCase('ar'));
}

function matchesSearch(teacher: Teacher, tokens: string[]): boolean {
    const fields = [
        teacher.full_name,
        teacher.employee_code,
        teacher.specialization_field ?? '',
        ...teacher.assignments.flatMap((a) => [a.subject_name, a.branch_name, a.department_name ?? '']),
    ].map((value) => value.toLocaleLowerCase('ar'));

    return tokens.every((token) => fields.some((value) => value.includes(token)));
}

/** Same yellow hit marks as the other rosters («الطلاب», «التسجيل»). */
function HighlightedText({ text, query }: { text: string; query: string }) {
    const tokens = searchTokens(query);
    if (text === '' || tokens.length === 0) {
        return <>{text}</>;
    }

    const matcher = new RegExp(`(${tokens.map((token) => token.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')).join('|')})`, 'giu');

    return (
        <>
            {text
                .split(matcher)
                .filter((part) => part !== '')
                .map((part, index) =>
                    tokens.includes(part.toLocaleLowerCase('ar')) ? (
                        <mark key={`hit-${index}`} className="sis-admission-search-hit">
                            {part}
                        </mark>
                    ) : (
                        <span key={`plain-${index}`}>{part}</span>
                    ),
                )}
        </>
    );
}

function StatusCell({ teacher }: { teacher: Teacher }) {
    const tc = t().teachers;
    const active = teacher.status === ACTIVE;

    return (
        <span className="sis-teachers-status">
            <span className={`sis-student-status-badge sis-student-status-badge--${active ? 1 : 0}`}>
                {active ? tc.statusActive : tc.statusInactive}
            </span>{' '}
            <span className="sis-org-tag">
                {teacher.employment_type === null ? tc.noEmploymentType : tc.employmentTypes[teacher.employment_type]}
            </span>
        </span>
    );
}

export default function TeachersIndex(props: Props) {
    const breadcrumbs: BreadcrumbItem[] = [{ title: t().teachers.title, href: '/teachers' }];

    // Inner component: page error / flash contexts live inside AppLayout.
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <TeachersPage {...props} />
        </AppLayout>
    );
}

function TeachersPage({ teachers, total, subjects, branches, classes, curriculumSubjects, academicTitles = [], filters, authorization }: Props) {
    const [appearanceTeacher, setAppearanceTeacher] = useState<Teacher | null>(null);
    const i18n = t();
    const tc = i18n.teachers;
    const canManage = authorization.can_manage;
    const yearId = filters.academic_year_id;
    const request = useRegistryRequest(RELOAD_PROPS);
    const { showInertiaErrors, showWarning } = usePageError();
    const page = usePage().props as { academicYears?: YearOption[] };
    const years = page.academicYears ?? [];
    const subjectsById = useMemo(() => new Map(subjects.map((subject) => [subject.id, subject])), [subjects]);
    /** Sections of the school's classes, read through the shared A / B / C source of truth. */
    const sectionRefs = useMemo(
        (): SisSectionRef[] => classes.flatMap((item) => item.sections.map((section) => ({ id: section.id, class_id: item.id, code: section.code, name: section.name }))),
        [classes],
    );
    const placeOf = (assignment: Assignment) =>
        assignmentPlace({
            ...assignment,
            section_name:
                assignment.section_id === null ? null : resolveSisSectionCode(assignment.section_id, sectionRefs) || assignment.section_name,
        });
    /** Unique colour per subject used on the roster (catalogue order, stable across rows). */
    const subjectTone = useMemo(() => {
        const used = new Set<number>();
        for (const teacher of teachers) {
            teacher.subject_ids.forEach((id) => used.add(id));
            teacher.assignments.forEach((a) => used.add(a.subject_id));
        }
        const ordered = [...subjects.map((subject) => subject.id).filter((id) => used.has(id)), ...[...used].filter((id) => !subjectsById.has(id))];

        return subjectTones(ordered);
    }, [subjects, subjectsById, teachers]);

    const scrollerRef = useRef<HTMLDivElement | null>(null);
    const tableRef = useRef<HTMLTableElement | null>(null);
    const selectAllRef = useRef<HTMLInputElement | null>(null);

    const [selectedId, setSelectedId] = useState<number | null>(null);
    const [checkedIds, setCheckedIds] = useState<number[]>([]);
    const [filterKey, setFilterKey] = useState<FilterKey>('all');
    const [rosterFilters, setRosterFilters] = useState<RosterFilters>(EMPTY_ROSTER_FILTERS);
    const [expandedIds, setExpandedIds] = useState<number[]>([]);
    const [query, setQuery] = useState(() => initialSearchParam('search'));
    const [pageNumber, setPageNumber] = useState(1);
    const [saving, setSaving] = useState(false);
    /** Teacher sheet: add (id null), view (viewOnly) or edit; queue = selected teachers walked with «السابق / التالي». */
    const [sheet, setSheet] = useState<{ id: number | null; form: TeacherForm; viewOnly: boolean; queue: number[] } | null>(null);
    const [teachingFor, setTeachingFor] = useState<number | null>(null);
    const [teachingForm, setTeachingForm] = useState<TeachingForm>(EMPTY_TEACHING);
    const [confirmDeactivate, setConfirmDeactivate] = useState(false);
    const [endTarget, setEndTarget] = useState<Assignment | null>(null);

    const filteredRows = useMemo(() => {
        const tokens = searchTokens(query);

        return teachers.filter(
            (teacher) =>
                matchesFilter(teacher, filterKey) && matchesRosterFilters(teacher, rosterFilters) && (tokens.length === 0 || matchesSearch(teacher, tokens)),
        );
    }, [teachers, filterKey, rosterFilters, query]);

    const fitPageSize = useFitTablePageSize(scrollerRef, { fallbackRows: TEACHERS_PER_PAGE, enabled: true });
    const lastPage = Math.max(1, Math.ceil(filteredRows.length / Math.max(1, fitPageSize)));
    const currentPage = Math.min(pageNumber, lastPage);
    const rowOffset = (currentPage - 1) * fitPageSize;
    const displayRows = useMemo(() => filteredRows.slice(rowOffset, rowOffset + fitPageSize), [filteredRows, rowOffset, fitPageSize]);

    useEffect(() => setPageNumber(1), [filterKey, rosterFilters, query]);

    useResizableTableColumns(tableRef, {
        storageKey: 'teachers.list',
        columnSignature: `${canManage ? 'select' : 'readonly'}:v2`,
        enabled: displayRows.length > 0,
    });
    useSmoothVerticalScroll(scrollerRef, displayRows.length > 0);

    const rowIds = useMemo(() => displayRows.map((row) => row.id), [displayRows]);
    const visibleCheckedIds = useMemo(() => checkedIds.filter((id) => teachers.some((row) => row.id === id)), [checkedIds, teachers]);
    const actionIds = useMemo(() => tableActionIds(visibleCheckedIds, selectedId), [selectedId, visibleCheckedIds]);
    const pageChecked = rowIds.filter((id) => checkedIds.includes(id));
    const allChecked = rowIds.length > 0 && pageChecked.length === rowIds.length;
    const someChecked = pageChecked.length > 0 && !allChecked;
    const single = actionIds.length === 1 ? (teachers.find((row) => row.id === actionIds[0]) ?? null) : null;

    useEffect(() => {
        if (selectAllRef.current) {
            selectAllRef.current.indeterminate = someChecked;
        }
    }, [someChecked]);

    // Drop selections of rows that disappeared after a reload.
    useEffect(() => {
        setCheckedIds((current) => current.filter((id) => teachers.some((row) => row.id === id)));
        setSelectedId((current) => (current !== null && teachers.some((row) => row.id === current) ? current : null));
    }, [teachers]);

    const selectRow = useCallback((teacherId: number) => {
        setSelectedId(teacherId);
        setCheckedIds([teacherId]);
    }, []);
    const toggleChecked = useCallback(
        (teacherId: number) => {
            const next = toggleTableRowChecked(checkedIds, teacherId);
            setCheckedIds(next.checkedIds);
            setSelectedId(next.selectedId);
        },
        [checkedIds],
    );
    const toggleAll = useCallback(() => {
        const next = toggleTableSelectAll(checkedIds, rowIds, selectedId);
        setCheckedIds(next.checkedIds);
        setSelectedId(next.selectedId);
    }, [checkedIds, rowIds, selectedId]);
    const clearSelection = useCallback(() => {
        setCheckedIds([]);
        setSelectedId(null);
    }, []);

    const run = async (action: () => Promise<boolean>, after?: () => void): Promise<void> => {
        if (saving) {
            return;
        }
        setSaving(true);
        try {
            if (await action()) {
                after?.();
            }
        } finally {
            setSaving(false);
        }
    };

    const bulk = (url: string, payload: Record<string, unknown>, after?: () => void) =>
        run(() => request('post', url, { teacher_ids: actionIds, ...payload }), after);

    const saveSheet = () => {
        if (sheet === null) {
            return;
        }
        const f = sheet.form;
        const payload = {
            first_name: f.first_name.trim(),
            father_name: blankToNull(f.father_name),
            grandfather_name: blankToNull(f.grandfather_name),
            last_name: f.last_name.trim(),
            specialization_field: blankToNull(f.specialization_field),
            // Not shown on this page — the stored value is sent back unchanged so it is never wiped.
            national_id: blankToNull(f.national_id),
            hire_date: blankToNull(f.hire_date),
            employment_type: f.employment_type === '' ? null : Number(f.employment_type),
            academic_year_id: yearId,
            academic_title_id: f.academic_title_id === '' ? null : Number(f.academic_title_id),
            abbreviation: blankToNull(f.abbreviation),
        };
        const teacherId = sheet.id;
        const original = teacherId === null ? null : (teachers.find((teacher) => teacher.id === teacherId) ?? null);
        const statusChanged = original !== null && f.status !== formOf(original).status;
        void run(
            async () => {
                if (teacherId === null) {
                    return request('post', '/teachers', { ...payload, employee_code: f.employee_code.trim() });
                }
                const workload = {
                    weekly_lessons_min: f.weekly_lessons_min === '' ? null : Number(f.weekly_lessons_min),
                    weekly_lessons_max: f.weekly_lessons_max === '' ? null : Number(f.weekly_lessons_max),
                    daily_lessons_max: f.daily_lessons_max === '' ? null : Number(f.daily_lessons_max),
                };
                if (!(await request('patch', `/teachers/${teacherId}`, { ...payload, ...workload }))) {
                    return false;
                }

                // الحالة has its own server command (same as the ribbon «تفعيل / إيقاف»).
                return !statusChanged || request('post', '/teachers/bulk-status', { teacher_ids: [teacherId], teacher_status: Number(f.status) });
            },
            // Editing a selection: continue with the next selected teacher, close after the last.
            () => {
                const next = teacherId === null ? null : nextInQueue(teacherId, 1);
                if (next === null) {
                    setSheet(null);
                } else {
                    showSheetTeacher(next, false);
                }
            },
        );
    };

    const openSheet = (teacher: Teacher | null, viewOnly: boolean, queue: number[] = teacher === null ? [] : [teacher.id]) =>
        setSheet({ id: teacher?.id ?? null, form: teacher === null ? EMPTY_FORM : formOf(teacher), viewOnly, queue });

    /** View / edit every selected teacher (select-all included), starting with the first. */
    const openSelection = (viewOnly: boolean) => {
        const queue = actionIds.filter((id) => teachers.some((teacher) => teacher.id === id));
        const first = teachers.find((teacher) => teacher.id === queue[0]) ?? null;
        if (first !== null) {
            openSheet(first, viewOnly, queue);
        }
    };

    const nextInQueue = (teacherId: number, step: 1 | -1): Teacher | null => {
        if (sheet === null) {
            return null;
        }
        const index = sheet.queue.indexOf(teacherId) + step;

        return index < 0 || index >= sheet.queue.length ? null : (teachers.find((teacher) => teacher.id === sheet.queue[index]) ?? null);
    };

    const showSheetTeacher = (teacher: Teacher, viewOnly: boolean) =>
        setSheet((current) => (current === null ? current : { ...current, id: teacher.id, form: formOf(teacher), viewOnly }));

    const openTeaching = (teacher: Teacher) => {
        setTeachingFor(teacher.id);
        setTeachingForm(EMPTY_TEACHING);
    };

    const addTeaching = () => {
        if (teachingFor === null) {
            return;
        }
        const f = teachingForm;
        let sectionId: number | null = null;
        if (f.section_code !== '') {
            if (f.class_id === '') {
                showWarning(tc.sectionNeedsClass);

                return;
            }
            sectionId = resolveSisSectionId(f.section_code, Number(f.class_id), sectionRefs);
            if (sectionId === null) {
                showWarning(tc.sectionNotInClass.replace('{section}', f.section_code));

                return;
            }
        }
        void run(
            () =>
                request('post', `/teachers/${teachingFor}/assignments`, {
                    academic_year_id: yearId,
                    subject_id: Number(f.subject_id),
                    branch_id: Number(f.branch_id),
                    department_id: f.department_id === '' ? null : Number(f.department_id),
                    class_id: f.class_id === '' ? null : Number(f.class_id),
                    section_id: sectionId,
                }),
            () => setTeachingForm((current) => ({ ...current, subject_id: '' })),
        );
    };

    const endTeaching = () => {
        if (endTarget === null || teachingFor === null) {
            return;
        }
        // No idempotency requirement on this route; the header is harmless.
        router.post(`/teachers/${teachingFor}/assignments/${endTarget.id}/end`, {}, {
            preserveScroll: true,
            preserveState: true,
            only: RELOAD_PROPS,
            headers: { 'X-Idempotency-Key': newIdempotencyKey('teaching-end') },
            onError: (errors) => showInertiaErrors(errors, i18n.errors.saveFailed),
            onFinish: () => setEndTarget(null),
        });
    };

    const changeYear = (next: string) => {
        if (next === '' || Number(next) === yearId) {
            return;
        }
        clearSelection();
        setRosterFilters(EMPTY_ROSTER_FILTERS);
        router.get('/teachers', { academic_year_id: Number(next) }, { preserveScroll: true });
    };

    const toggleExpanded = (teacherId: number) =>
        setExpandedIds((current) => (current.includes(teacherId) ? current.filter((id) => id !== teacherId) : [...current, teacherId]));

    const specializationOptions = useMemo(
        () =>
            [...new Set(teachers.map((teacher) => (teacher.specialization_field ?? '').trim()).filter((value) => value !== ''))].sort((a, b) =>
                a.localeCompare(b, 'ar'),
            ),
        [teachers],
    );
    const filterBranch = branches.find((branch) => String(branch.id) === rosterFilters.branch_id) ?? null;
    const departmentOptions = useMemo(() => {
        const names = (filterBranch !== null ? [filterBranch] : branches).flatMap((branch) => branch.departments.map((d) => d.name));

        return [...new Set(names)].sort((a, b) => a.localeCompare(b, 'ar')).map((name) => ({ value: name, label: name }));
    }, [branches, filterBranch]);
    const rosterFiltered =
        rosterFilters.specialization !== '' || rosterFilters.branch_id !== '' || rosterFilters.department !== '' || rosterFilters.subject_id !== '';

    // Subject picker: curriculum of the chosen department (or of every department of the branch) + general curricula.
    const teachingBranch = branches.find((branch) => String(branch.id) === teachingForm.branch_id) ?? null;
    const teachingClass = classes.find((item) => String(item.id) === teachingForm.class_id) ?? null;
    const pickableSubjectIds = useMemo(() => {
        if (teachingBranch === null) {
            return [];
        }
        const ids = new Set<number>(curriculumSubjects.general);
        const departmentIds =
            teachingForm.department_id === '' ? teachingBranch.departments.map((d) => d.id) : [Number(teachingForm.department_id)];
        for (const departmentId of departmentIds) {
            for (const subjectId of curriculumSubjects.by_department[String(departmentId)] ?? []) {
                ids.add(subjectId);
            }
        }

        return [...ids].filter((id) => subjectsById.has(id)).sort((a, b) => (subjectsById.get(a)?.name ?? '').localeCompare(subjectsById.get(b)?.name ?? '', 'ar'));
    }, [curriculumSubjects, subjectsById, teachingBranch, teachingForm.department_id]);

    const counts = useMemo(() => {
        const byType: Record<number, number> = {};
        for (const type of EMPLOYMENT_TYPES) {
            byType[type] = teachers.filter((teacher) => teacher.employment_type === type).length;
        }

        return { active: teachers.filter((teacher) => teacher.status === ACTIVE).length, byType };
    }, [teachers]);
    const withTeaching = teachers.filter((teacher) => teacher.assignments.length > 0).length;
    const teachingPercent = teachers.length === 0 ? 0 : Math.round((withTeaching / teachers.length) * 100);
    const hasSelection = actionIds.length > 0;
    const selectionTitle = (label: string) => (hasSelection ? label : tc.needsSelection);

    const editRibbonGroups = useMemo((): PageRibbonGroup[] => {
        const recordCommands: PageRibbonCommand[] = [
            {
                id: 'teachers-view',
                label: i18n.common.view,
                icon: Eye,
                title: selectionTitle(i18n.common.view),
                disabled: !hasSelection,
                onSelect: () => openSelection(true),
            },
        ];
        if (canManage) {
            recordCommands.push(
                {
                    id: 'teachers-edit',
                    label: i18n.common.edit,
                    icon: Pencil,
                    tone: 'edit',
                    title: selectionTitle(i18n.common.edit),
                    disabled: !hasSelection || saving,
                    onSelect: () => openSelection(false),
                },
                {
                    id: 'teachers-cancel',
                    label: i18n.common.cancel,
                    icon: XCircle,
                    disabled: !hasSelection || saving,
                    onSelect: clearSelection,
                },
                {
                    id: 'teachers-delete',
                    label: i18n.common.delete,
                    icon: Trash2,
                    tone: 'delete',
                    title: selectionTitle(tc.setInactive),
                    disabled: !hasSelection || saving,
                    onSelect: () => setConfirmDeactivate(true),
                },
            );
        }

        const filterTabs: Array<{ key: FilterKey; label: string; icon: LucideIcon; count: number }> = [
            { key: 'all', label: tc.all, icon: Layers, count: teachers.length },
            { key: 'active', label: tc.statusActive, icon: CheckCircle2, count: counts.active },
            { key: 'inactive', label: tc.statusInactive, icon: CircleSlash, count: teachers.length - counts.active },
            ...EMPLOYMENT_TYPES.map((type) => ({
                key: `type-${type}` as FilterKey,
                label: tc.employmentTypes[type],
                icon: EMPLOYMENT_ICONS[type],
                count: counts.byType[type] ?? 0,
            })),
        ];

        const groups: PageRibbonGroup[] = [
            { id: 'teachers-actions', label: i18n.common.actions, commands: recordCommands },
            {
                id: 'teachers-filters',
                label: tc.statusColumn,
                commands: filterTabs.map((tab) => ({
                    id: `teachers-filter-${tab.key}`,
                    label: tab.label,
                    title: `${tab.label} (${tab.count})`,
                    icon: tab.icon,
                    count: tab.count,
                    pressed: filterKey === tab.key,
                    onSelect: () => setFilterKey(filterKey === tab.key && tab.key !== 'all' ? 'all' : tab.key),
                })),
            },
        ];

        if (canManage) {
            groups.push({
                id: 'teachers-appearance',
                label: i18n.appearance.title,
                commands: [
                    {
                        id: 'teachers-appearance-edit',
                        label: i18n.appearance.title,
                        icon: Palette,
                        title: single === null ? i18n.orgRibbon.needsSelection : i18n.appearance.edit,
                        disabled: single === null,
                        onSelect: () => setAppearanceTeacher(single),
                    },
                ],
            });
            groups.push({
                id: 'teachers-employment',
                label: tc.employmentGroup,
                commands: [
                    {
                        id: 'teachers-set-active',
                        label: tc.setActive,
                        icon: UserCheck,
                        title: selectionTitle(tc.setActive),
                        disabled: !hasSelection || saving,
                        onSelect: () => void bulk('/teachers/bulk-status', { teacher_status: ACTIVE }),
                    },
                    {
                        id: 'teachers-set-inactive',
                        label: tc.setInactive,
                        icon: UserX,
                        title: selectionTitle(tc.setInactive),
                        disabled: !hasSelection || saving,
                        onSelect: () => setConfirmDeactivate(true),
                    },
                    ...EMPLOYMENT_TYPES.map((type) => ({
                        id: `teachers-set-type-${type}`,
                        label: tc.employmentTypes[type],
                        icon: EMPLOYMENT_ICONS[type],
                        title: selectionTitle(`${tc.employmentType}: ${tc.employmentTypes[type]}`),
                        disabled: !hasSelection || saving || yearId === null,
                        onSelect: () => void bulk('/teachers/bulk-employment-type', { employment_type: type, academic_year_id: yearId }),
                    })),
                ],
            });
        }

        groups.push({
            id: 'teachers-progress',
            label: i18n.orgRibbon.progress,
            commands: [],
            custom: (
                <div
                    className="sis-ribbon__progress-track"
                    role="progressbar"
                    aria-label={tc.progressLabel}
                    aria-valuemin={0}
                    aria-valuemax={100}
                    aria-valuenow={teachingPercent}
                    data-contrast={teachingPercent >= 45 ? 'light' : 'dark'}
                    dir="rtl"
                    title={`${tc.progressLabel}: ${teachingPercent}%`}
                >
                    <span className="sis-ribbon__progress-fill" style={{ width: `${teachingPercent}%` }} />
                    <span className="sis-ribbon__progress-value" dir="ltr">
                        {teachingPercent}%
                    </span>
                </div>
            ),
        });

        return groups;
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [actionIds, canManage, counts, filterKey, hasSelection, i18n, saving, single, teachers.length, teachingPercent, yearId]);
    useRegisterPageRibbon('edit', editRibbonGroups);

    const addRibbonGroups = useMemo((): PageRibbonGroup[] => {
        if (!canManage) {
            return [];
        }

        return [
            {
                id: 'teachers-teaching',
                label: tc.ribbonTeacher,
                commands: [
                    { id: 'teachers-add', label: tc.addTeacher, icon: PlusCircle, disabled: yearId === null, onSelect: () => openSheet(null, false) },
                    {
                        id: 'teachers-teaching-open',
                        label: tc.teachingTitle,
                        icon: BookOpen,
                        title: single === null ? tc.needsSingle : tc.teachingTitle,
                        disabled: single === null || yearId === null,
                        onSelect: () => single && openTeaching(single),
                    },
                ],
            },
        ];
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [canManage, i18n, single, yearId]);
    useRegisterPageRibbon('add', addRibbonGroups);

    const homeRibbonGroups = useMemo((): PageRibbonGroup[] => {
        const setFilter = (patch: Partial<RosterFilters>) => {
            clearSelection();
            setRosterFilters((current) => ({ ...current, ...patch }));
        };
        const field = (value: string, mirror: string, label: string, options: Array<{ value: string; label: string }>, onChange: (next: string) => void) => (
            <div className="sis-ribbon__filter-field" dir="rtl">
                <span className="sis-admission-select-fit">
                    <span className="sis-admission-select-fit__mirror" aria-hidden="true">
                        {mirror}
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
        const specializationSelect = [{ value: '', label: tc.allSpecializations }, ...specializationOptions.map((value) => ({ value, label: value }))];
        const branchSelect = [{ value: '', label: tc.allBranches }, ...branches.map((branch) => ({ value: String(branch.id), label: branch.name }))];
        const departmentSelect = [{ value: '', label: tc.allBranchDepartments }, ...departmentOptions];
        const subjectSelect = [{ value: '', label: tc.allSubjects }, ...subjects.map((subject) => ({ value: String(subject.id), label: subject.name }))];
        const labelOf = (options: Array<{ value: string; label: string }>, value: string) => options.find((o) => o.value === value)?.label ?? options[0]?.label ?? '';

        return [
            {
                id: 'teachers-roster-filters',
                label: tc.ribbonFilters,
                commands: [],
                custom: (
                    <div className="sis-ribbon__filters" aria-label={tc.filtersTitle} dir="rtl">
                        <div className="sis-ribbon__filters-stack sis-ribbon__filters-stack--curriculum sis-ribbon__filters-stack--teachers">
                            {field(yearId === null ? '' : String(yearId), labelOf(yearOptions, String(yearId ?? '')), tc.academicYear, yearOptions, changeYear)}
                            {field(rosterFilters.specialization, labelOf(specializationSelect, rosterFilters.specialization), tc.certificateSpecialization, specializationSelect, (specialization) =>
                                setFilter({ specialization }),
                            )}
                            {field(rosterFilters.branch_id, labelOf(branchSelect, rosterFilters.branch_id), tc.branch, branchSelect, (branch_id) =>
                                setFilter({ branch_id, department: '' }),
                            )}
                            {field(rosterFilters.department, labelOf(departmentSelect, rosterFilters.department), tc.department, departmentSelect, (department) =>
                                setFilter({ department }),
                            )}
                            {field(rosterFilters.subject_id, labelOf(subjectSelect, rosterFilters.subject_id), tc.subject, subjectSelect, (subject_id) =>
                                setFilter({ subject_id }),
                            )}
                        </div>
                        <button
                            type="button"
                            className="sis-ribbon__item sis-ribbon__item--filter-clear"
                            data-item-id="teachers-filters-clear"
                            aria-label={tc.clearFiltersAria}
                            title={tc.clearFiltersAria}
                            disabled={!rosterFiltered}
                            onClick={() => setFilter(EMPTY_ROSTER_FILTERS)}
                        >
                            <FilterX className="sis-ribbon__icon" aria-hidden />
                            <span className="sis-ribbon__label">{tc.clearFilters}</span>
                        </button>
                    </div>
                ),
            },
        ];
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [branches, departmentOptions, i18n, rosterFilters, rosterFiltered, specializationOptions, subjects, years, yearId]);
    useRegisterPageRibbon('home', homeRibbonGroups);

    const titlebarSearch = useMemo(
        () => ({ committedQuery: query, label: tc.search, placeholder: tc.search, onDraftChange: setQuery, onCommit: setQuery }),
        [query, tc.search],
    );
    useRegisterPageTitlebarSearch(titlebarSearch);

    const teachingTeacher = teachers.find((teacher) => teacher.id === teachingFor) ?? null;
    const sheetEditing = sheet !== null && !sheet.viewOnly;
    const setSheetField = (field: keyof TeacherForm) => (value: string) =>
        setSheet((current) => (current === null ? current : { ...current, form: { ...current.form, [field]: value } }));
    const prevTeacher = sheet === null || sheet.id === null ? null : nextInQueue(sheet.id, -1);
    const nextTeacher = sheet === null || sheet.id === null ? null : nextInQueue(sheet.id, 1);
    const statusOptions = [
        { value: String(ACTIVE), label: tc.statusActive },
        { value: String(INACTIVE), label: tc.statusInactive },
    ];
    const sheetTeacher = sheet === null || sheet.id === null ? null : (teachers.find((teacher) => teacher.id === sheet.id) ?? null);
    const sheetSubjectIds =
        sheetTeacher === null ? [] : [...new Set([...sheetTeacher.assignments.map((a) => a.subject_id), ...sheetTeacher.subject_ids])];
    const employmentOptions = [
        { value: '', label: tc.noEmploymentType },
        ...EMPLOYMENT_TYPES.map((type) => ({ value: String(type), label: tc.employmentTypes[type] })),
    ];

    return (
        <>
            <Head title={tc.title} />
            <div
                className="sis-ops-hub sis-admission-page sis-students-page sis-enrollments-page sis-teachers-page flex h-full min-h-0 flex-col overflow-hidden pb-4"
                dir="rtl"
                lang="ar"
            >
                {canManage ? null : <p className="sis-branches-page__notice">{tc.readOnly}</p>}
                {yearId === null ? <p className="sis-branches-page__notice">{tc.noYear}</p> : null}
                {total > teachers.length ? (
                    <p className="sis-branches-page__notice">
                        {tc.truncated.replace('{limit}', String(ROSTER_LIMIT)).replace('{total}', String(total))}
                    </p>
                ) : null}

                <div className="sis-admission-page-body">
                    <section aria-label={tc.tableCaption} className="flex min-h-0 flex-1 flex-col">
                        {filteredRows.length === 0 ? (
                            <p className="text-sm">{teachers.length === 0 ? tc.noTeachers : tc.noSearchResult}</p>
                        ) : (
                            <>
                                <div className="sis-admission-periods-table sis-admission-drafts-table">
                                    <div className="sis-admission-drafts-table__scroller" ref={scrollerRef}>
                                        <table ref={tableRef}>
                                            <thead>
                                                <tr>
                                                    {canManage ? (
                                                        <th className="sis-admission-drafts-table__select">
                                                            <input
                                                                ref={selectAllRef}
                                                                type="checkbox"
                                                                checked={allChecked}
                                                                disabled={saving}
                                                                aria-label={tc.selectAll}
                                                                onChange={toggleAll}
                                                            />
                                                        </th>
                                                    ) : null}
                                                    <th className="sis-admission-drafts-table__num">{tc.seq}</th>
                                                    <th className="sis-admission-drafts-table__name-head">{tc.tripleName}</th>
                                                    <th>{tc.employeeCode}</th>
                                                    <th title={tc.certificateSpecializationHint}>{tc.certificateSpecialization}</th>
                                                    <th>{tc.subjectsCountColumn}</th>
                                                    <th className="sis-teachers-table__subjects-head">{tc.subjectsColumn}</th>
                                                    <th>{tc.statusColumn}</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                {displayRows.map((row, index) => {
                                                    const checked = checkedIds.includes(row.id);
                                                    const selected = selectedId === row.id;
                                                    const taught = new Set(row.assignments.map((a) => a.subject_id));
                                                    const untaught = row.subject_ids.filter((id) => !taught.has(id));
                                                    const rowSubjectIds = [...new Set([...row.assignments.map((a) => a.subject_id), ...untaught])];
                                                    const expanded = expandedIds.includes(row.id);
                                                    const subjectName = (subjectId: number) =>
                                                        subjectsById.get(subjectId)?.name
                                                        ?? row.assignments.find((a) => a.subject_id === subjectId)?.subject_name
                                                        ?? `#${subjectId}`;
                                                    const subjectChip = (subjectId: number) => <SubjectChip tone={subjectTone.get(subjectId)} name={subjectName(subjectId)} query={query} />;

                                                    return (
                                                        <tr
                                                            key={row.id}
                                                            className={selected || checked ? 'sis-admission-periods-table__row--selected' : undefined}
                                                            aria-selected={selected || checked}
                                                            onClick={() => {
                                                                if (!hasPageTextSelection()) {
                                                                    selectRow(row.id);
                                                                }
                                                            }}
                                                            onDoubleClick={() => openSheet(row, true)}
                                                        >
                                                            {canManage ? (
                                                                <td className="sis-admission-drafts-table__select">
                                                                    <input
                                                                        type="checkbox"
                                                                        checked={checked}
                                                                        disabled={saving}
                                                                        aria-label={`${tc.selectTeacher}: ${row.full_name}`}
                                                                        onClick={(event) => event.stopPropagation()}
                                                                        onChange={() => toggleChecked(row.id)}
                                                                    />
                                                                </td>
                                                            ) : null}
                                                            <td className="sis-admission-drafts-table__num">
                                                                <span dir="ltr">{rowOffset + index + 1}</span>
                                                            </td>
                                                            <td className="sis-admission-drafts-table__name">
                                                                <CellScroll>
                                                                    <HighlightedText text={row.full_name} query={query} />
                                                                </CellScroll>
                                                            </td>
                                                            <td>
                                                                <span dir="ltr">
                                                                    <HighlightedText text={row.employee_code} query={query} />
                                                                </span>
                                                            </td>
                                                            <td>
                                                                <CellScroll>
                                                                    <HighlightedText text={row.specialization_field ?? '—'} query={query} />
                                                                </CellScroll>
                                                            </td>
                                                            <td>
                                                                <span dir="ltr">{row.subject_ids.length}</span>
                                                            </td>
                                                            <td className="sis-teachers-table__subjects">
                                                                {rowSubjectIds.length === 0 ? (
                                                                    '—'
                                                                ) : (
                                                                    <div className={expanded ? 'sis-teachers-subjects sis-teachers-subjects--expanded' : 'sis-teachers-subjects'}>
                                                                        <button
                                                                            type="button"
                                                                            className="sis-teachers-subjects__toggle"
                                                                            aria-expanded={expanded}
                                                                            aria-label={`${expanded ? tc.collapseSubjects : tc.expandSubjects}: ${row.full_name}`}
                                                                            title={expanded ? tc.collapseSubjects : tc.expandSubjects}
                                                                            onClick={(event) => {
                                                                                event.stopPropagation();
                                                                                toggleExpanded(row.id);
                                                                            }}
                                                                            onDoubleClick={(event) => event.stopPropagation()}
                                                                        >
                                                                            {expanded ? <ChevronDown aria-hidden /> : <ChevronLeft aria-hidden />}
                                                                        </button>
                                                                        {expanded ? (
                                                                            <div className="sis-teachers-subjects__list">
                                                                                {row.assignments.map((assignment) => (
                                                                                    <span key={assignment.id} className="sis-teachers-table__teaching">
                                                                                        {subjectChip(assignment.subject_id)} {placeOf(assignment)}
                                                                                    </span>
                                                                                ))}
                                                                                {untaught.map((subjectId) => (
                                                                                    <span key={`s-${subjectId}`} className="sis-teachers-table__teaching">
                                                                                        {subjectChip(subjectId)}
                                                                                    </span>
                                                                                ))}
                                                                            </div>
                                                                        ) : (
                                                                            <span className="sis-teachers-subjects__line">
                                                                                {rowSubjectIds.map((subjectId) => (
                                                                                    <Fragment key={subjectId}>{subjectChip(subjectId)}</Fragment>
                                                                                ))}
                                                                            </span>
                                                                        )}
                                                                    </div>
                                                                )}
                                                            </td>
                                                            <td>
                                                                <StatusCell teacher={row} />
                                                            </td>
                                                        </tr>
                                                    );
                                                })}
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                                <nav className="sis-admission-drafts-pagination" aria-label={i18n.common.page}>
                                    <ul className="sis-admission-pagination" dir="ltr">
                                        <li className="sis-admission-pagination__item">
                                            <button
                                                type="button"
                                                className="sis-admission-pagination__link"
                                                aria-label={i18n.common.previous}
                                                disabled={currentPage <= 1}
                                                onClick={() => setPageNumber(currentPage - 1)}
                                            >
                                                <span aria-hidden="true">&laquo;</span>
                                            </button>
                                        </li>
                                        {visiblePages(currentPage, lastPage).map((pageNum) => (
                                            <li key={pageNum} className="sis-admission-pagination__item">
                                                <button
                                                    type="button"
                                                    className={
                                                        pageNum === currentPage
                                                            ? 'sis-admission-pagination__link sis-admission-pagination__link--active'
                                                            : 'sis-admission-pagination__link'
                                                    }
                                                    aria-label={`${i18n.common.page} ${pageNum}`}
                                                    aria-current={pageNum === currentPage ? 'page' : undefined}
                                                    onClick={() => setPageNumber(pageNum)}
                                                >
                                                    {pageNum}
                                                </button>
                                            </li>
                                        ))}
                                        <li className="sis-admission-pagination__item">
                                            <button
                                                type="button"
                                                className="sis-admission-pagination__link"
                                                aria-label={i18n.common.next}
                                                disabled={currentPage >= lastPage}
                                                onClick={() => setPageNumber(currentPage + 1)}
                                            >
                                                <span aria-hidden="true">&raquo;</span>
                                            </button>
                                        </li>
                                    </ul>
                                </nav>
                            </>
                        )}
                    </section>
                </div>
            </div>

            {/* Teacher sheet: add / view / edit (school + teaching details only — contact data belongs to HR). */}
            {sheet !== null ? (
                <RegistrySheetDialog
                    title={sheet.id === null ? tc.addTeacher : sheet.viewOnly ? tc.viewTeacher : tc.editTeacher}
                    className="sis-branches-sheet sis-teachers-sheet"
                    onClose={() => setSheet(null)}
                >
                    <SheetSection id="teacher-identity" title={tc.tripleName}>
                        <div className="sis-admission-sheet__row sis-admission-sheet__row--full sis-branches-sheet__row">
                            <RegistryTextField label={tc.firstName} editing={sheetEditing} required value={sheet.form.first_name} onChange={setSheetField('first_name')} />
                            <RegistryTextField label={tc.fatherName} editing={sheetEditing} value={sheet.form.father_name} onChange={setSheetField('father_name')} />
                            <RegistryTextField label={tc.grandfatherName} editing={sheetEditing} value={sheet.form.grandfather_name} onChange={setSheetField('grandfather_name')} />
                            <RegistryTextField label={tc.familyName} editing={sheetEditing} required value={sheet.form.last_name} onChange={setSheetField('last_name')} />
                        </div>
                    </SheetSection>
                    <SheetSection id="teacher-school" title={tc.employmentGroup}>
                        <div className="sis-admission-sheet__row sis-admission-sheet__row--full sis-branches-sheet__row">
                            <RegistryTextField
                                label={tc.employeeCode}
                                editing={sheet.id === null}
                                required
                                dir="ltr"
                                value={sheet.form.employee_code}
                                onChange={setSheetField('employee_code')}
                            />
                            <RegistryTextField
                                label={`${tc.certificateSpecialization} (${tc.certificateSpecializationHint})`}
                                editing={sheetEditing}
                                value={sheet.form.specialization_field}
                                onChange={setSheetField('specialization_field')}
                            />
                            <RegistryListField
                                label={tc.academicTitle}
                                editing={sheetEditing}
                                value={sheet.form.academic_title_id}
                                display={academicTitles.find((x) => String(x.id) === sheet.form.academic_title_id)?.name ?? tc.noAcademicTitle}
                                options={[{ value: '', label: tc.noAcademicTitle }, ...academicTitles.map((x) => ({ value: String(x.id), label: x.abbreviation ? `${x.name} (${x.abbreviation})` : x.name }))]}
                                onChange={setSheetField('academic_title_id')}
                            />
                            <RegistryTextField label={tc.abbreviation} editing={sheetEditing} value={sheet.form.abbreviation} onChange={setSheetField('abbreviation')} />
                            <RegistryListField
                                label={tc.employmentType}
                                editing={sheetEditing}
                                value={sheet.form.employment_type}
                                display={sheet.form.employment_type === '' ? tc.noEmploymentType : tc.employmentTypes[Number(sheet.form.employment_type)]}
                                options={employmentOptions}
                                onChange={setSheetField('employment_type')}
                            />
                            <RegistryListField
                                label={tc.statusColumn}
                                editing={sheetEditing && sheet.id !== null}
                                value={sheet.form.status}
                                display={sheet.form.status === String(ACTIVE) ? tc.statusActive : tc.statusInactive}
                                options={statusOptions}
                                onChange={setSheetField('status')}
                            />
                            <RegistryTextField label={tc.hireDate} editing={sheetEditing} type="date" dir="ltr" value={sheet.form.hire_date} onChange={setSheetField('hire_date')} />
                        </div>
                    </SheetSection>
                    {sheet.id !== null ? (
                        <SheetSection id="teacher-workload" title={tc.workloadGroup}>
                            <div className="sis-admission-sheet__row sis-admission-sheet__row--full sis-branches-sheet__row">
                                <RegistryTextField label={tc.weeklyMax} editing={sheetEditing} type="number" dir="ltr" value={sheet.form.weekly_lessons_max} onChange={setSheetField('weekly_lessons_max')} />
                                <RegistryTextField label={tc.weeklyMin} editing={sheetEditing} type="number" dir="ltr" value={sheet.form.weekly_lessons_min} onChange={setSheetField('weekly_lessons_min')} />
                                <RegistryTextField label={tc.dailyMax} editing={sheetEditing} type="number" dir="ltr" value={sheet.form.daily_lessons_max} onChange={setSheetField('daily_lessons_max')} />
                            </div>
                            <p className="sis-admission-sheet__hint text-muted-foreground mt-2 text-xs">{tc.workloadHint}</p>
                        </SheetSection>
                    ) : null}
                    {sheetTeacher !== null ? (
                        <SheetSection id="teacher-subjects" title={tc.subjectsColumn}>
                            <div className="sis-admission-sheet__row sis-admission-sheet__row--full sis-branches-sheet__row">
                                <div className="sis-admission-sheet__field sis-branches-field--wide">
                                    <span className="sis-admission-sheet__label">{`${tc.subjectsColumn} (${sheetSubjectIds.length})`}</span>
                                    <div className="sis-teachers-sheet-subjects">
                                        <div className="sis-admission-sheet__control sis-admission-draft-readonly sis-teachers-sheet-subjects__card">
                                            <span className="sis-teachers-sheet-subjects__chips">
                                                {sheetSubjectIds.length === 0
                                                    ? tc.noSubjects
                                                    : sheetSubjectIds.map((subjectId) => (
                                                          <SubjectChip
                                                              key={subjectId}
                                                              tone={subjectTone.get(subjectId)}
                                                              name={
                                                                  subjectsById.get(subjectId)?.name
                                                                  ?? sheetTeacher.assignments.find((a) => a.subject_id === subjectId)?.subject_name
                                                                  ?? `#${subjectId}`
                                                              }
                                                          />
                                                      ))}
                                                </span>
                                        </div>
                                        <Button
                                            type="button"
                                            className="sis-teachers-sheet-subjects__open"
                                            disabled={yearId === null}
                                            onClick={() => openTeaching(sheetTeacher)}
                                        >
                                            <BookOpen aria-hidden />
                                            {tc.teachingTitle}
                                        </Button>
                                    </div>
                                </div>
                            </div>
                        </SheetSection>
                    ) : null}
                    <div className="sis-admission-sheet__actions">
                        {sheet.queue.length > 1 && sheet.id !== null ? (
                            <span className="sis-teachers-sheet__nav">
                                <Button
                                    type="button"
                                    variant="outline"
                                    disabled={saving || prevTeacher === null}
                                    onClick={() => prevTeacher && showSheetTeacher(prevTeacher, sheet.viewOnly)}
                                >
                                    {tc.previousTeacher}
                                </Button>
                                <span className="sis-teachers-sheet__counter" dir="ltr">
                                    {sheet.queue.indexOf(sheet.id) + 1} / {sheet.queue.length}
                                </span>
                                <Button
                                    type="button"
                                    variant="outline"
                                    disabled={saving || nextTeacher === null}
                                    onClick={() => nextTeacher && showSheetTeacher(nextTeacher, sheet.viewOnly)}
                                >
                                    {tc.nextTeacher}
                                </Button>
                            </span>
                        ) : null}
                        <Button type="button" variant="outline" disabled={saving} onClick={() => setSheet(null)}>
                            {tc.cancel}
                        </Button>
                        {sheet.viewOnly ? (
                            canManage ? (
                                <Button type="button" onClick={() => setSheet((current) => (current === null ? current : { ...current, viewOnly: false }))}>
                                    {i18n.common.edit}
                                </Button>
                            ) : null
                        ) : (
                            <Button
                                type="button"
                                disabled={
                                    saving
                                    || sheet.form.first_name.trim() === ''
                                    || sheet.form.last_name.trim() === ''
                                    || (sheet.id === null && sheet.form.employee_code.trim() === '')
                                }
                                onClick={saveSheet}
                            >
                                {saving ? i18n.common.saving : sheet.id === null ? tc.add : tc.saveChanges}
                            </Button>
                        )}
                    </div>
                </RegistrySheetDialog>
            ) : null}

            {/* Teaching sheet: where the teacher teaches each subject. */}
            {teachingTeacher !== null ? (
                <RegistrySheetDialog title={`${tc.teachingTitle}: ${teachingTeacher.full_name}`} className="sis-branches-sheet sis-teachers-sheet" onClose={() => setTeachingFor(null)}>
                    <SheetSection id="teacher-teaching-list" title={`${tc.teaching} (${teachingTeacher.assignments.length})`}>
                        <div className="sis-admission-sheet__row sis-admission-sheet__row--full sis-branches-sheet__row">
                            {teachingTeacher.assignments.length === 0 ? (
                                <p className="sis-branches-empty sis-branches-field--wide">{tc.noTeaching}</p>
                            ) : (
                                <ul className="sis-branches-checklist sis-branches-field--wide">
                                    {teachingTeacher.assignments.map((assignment) => (
                                        <li key={assignment.id}>
                                            <span className="sis-branches-checklist__item">
                                                <GraduationCap aria-hidden className="sis-branches-table__icon" />
                                                <span className="sis-branches-checklist__text">
                                                    <span className="sis-branches-checklist__name">{assignment.subject_name}</span>
                                                    <span className="sis-branches-checklist__desc">{placeOf(assignment)}</span>
                                                </span>
                                                {canManage ? (
                                                    <button
                                                        type="button"
                                                        className="sis-branches-icon-btn sis-branches-icon-btn--plain"
                                                        title={tc.endTeaching}
                                                        aria-label={`${tc.endTeaching}: ${assignment.subject_name}`}
                                                        onClick={() => setEndTarget(assignment)}
                                                    >
                                                        <Trash2 aria-hidden />
                                                    </button>
                                                ) : null}
                                            </span>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </div>
                    </SheetSection>
                    {canManage ? (
                        <SheetSection id="teacher-teaching-add" title={tc.addTeaching}>
                            <div className="sis-admission-sheet__row sis-admission-sheet__row--full sis-branches-sheet__row">
                                <p className="sis-branches-checklist__desc sis-branches-field--wide">{tc.teachingHint}</p>
                                <RegistryListField
                                    label={tc.branch}
                                    editing
                                    required
                                    value={teachingForm.branch_id}
                                    display={teachingBranch?.name ?? '—'}
                                    options={branches.map((branch) => ({ value: String(branch.id), label: branch.name }))}
                                    onChange={(branch_id) => setTeachingForm({ ...EMPTY_TEACHING, branch_id, class_id: teachingForm.class_id })}
                                />
                                <RegistryListField
                                    label={tc.department}
                                    editing
                                    value={teachingForm.department_id}
                                    display={teachingBranch?.departments.find((d) => String(d.id) === teachingForm.department_id)?.name ?? tc.allDepartments}
                                    options={[
                                        { value: '', label: tc.allDepartments },
                                        ...(teachingBranch?.departments ?? []).map((d) => ({ value: String(d.id), label: d.name })),
                                    ]}
                                    onChange={(department_id) => setTeachingForm((current) => ({ ...current, department_id, subject_id: '' }))}
                                />
                                <RegistryListField
                                    label={tc.subject}
                                    editing
                                    required
                                    value={teachingForm.subject_id}
                                    display={subjectsById.get(Number(teachingForm.subject_id))?.name ?? '—'}
                                    options={pickableSubjectIds.map((id) => ({ value: String(id), label: subjectsById.get(id)?.name ?? `#${id}` }))}
                                    onChange={(subject_id) => setTeachingForm((current) => ({ ...current, subject_id }))}
                                />
                                <RegistryListField
                                    label={tc.class}
                                    editing
                                    value={teachingForm.class_id}
                                    display={teachingClass?.name ?? tc.allClasses}
                                    options={[{ value: '', label: tc.allClasses }, ...classes.map((item) => ({ value: String(item.id), label: item.name }))]}
                                    onChange={(class_id) => setTeachingForm((current) => ({ ...current, class_id }))}
                                />
                                <RegistryListField
                                    label={tc.section}
                                    editing
                                    value={teachingForm.section_code}
                                    display={teachingForm.section_code === '' ? tc.allSections : teachingForm.section_code}
                                    options={[{ value: '', label: tc.allSections }, ...sisSectionSelectOptions()]}
                                    onChange={(section_code) => setTeachingForm((current) => ({ ...current, section_code }))}
                                />
                            </div>
                        </SheetSection>
                    ) : null}
                    <div className="sis-admission-sheet__actions">
                        <Button type="button" variant="outline" disabled={saving} onClick={() => setTeachingFor(null)}>
                            {i18n.branchStructure.close}
                        </Button>
                        {canManage ? (
                            <Button type="button" disabled={saving || teachingForm.branch_id === '' || teachingForm.subject_id === ''} onClick={addTeaching}>
                                {saving ? i18n.common.saving : tc.addTeaching}
                            </Button>
                        ) : null}
                    </div>
                </RegistrySheetDialog>
            ) : null}

            {appearanceTeacher !== null ? (
                <AppearanceDialog
                    title={i18n.appearance.edit}
                    entityName={appearanceTeacher.full_name}
                    initial={{ abbreviation: appearanceTeacher.abbreviation ?? '', color_hue: appearanceTeacher.color_hue ?? null }}
                    suggested={appearanceTeacher.first_name}
                    url={`/teachers/${appearanceTeacher.id}/appearance`}
                    reloadProps={RELOAD_PROPS}
                    canEdit={canManage}
                    onClose={() => setAppearanceTeacher(null)}
                />
            ) : null}
            <ConfirmDialog
                open={confirmDeactivate}
                title={tc.deactivate}
                description={`${tc.deactivateConfirm} (${tc.selectedCount}: ${actionIds.length})`}
                confirmLabel={tc.setInactive}
                tone="danger"
                confirmPending={saving}
                onConfirm={() => void bulk('/teachers/bulk-status', { teacher_status: INACTIVE }, () => setConfirmDeactivate(false))}
                onOpenChange={(open) => {
                    if (!open && !saving) {
                        setConfirmDeactivate(false);
                    }
                }}
            />
            <ConfirmDialog
                open={endTarget !== null}
                title={tc.endTeaching}
                description={endTarget === null ? '' : `${endTarget.subject_name} — ${placeOf(endTarget)}. ${tc.endTeachingConfirm}`}
                confirmLabel={tc.endTeaching}
                tone="danger"
                onConfirm={endTeaching}
                onOpenChange={(open) => {
                    if (!open) {
                        setEndTarget(null);
                    }
                }}
            />
        </>
    );
}
