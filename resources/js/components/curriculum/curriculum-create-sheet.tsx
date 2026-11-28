import { router, usePage } from '@inertiajs/react';
import { ChevronDown, ChevronLeft, ChevronRight } from 'lucide-react';
import { Fragment, useEffect, useMemo, useState, type CSSProperties } from 'react';
import AppLogo from '@/components/app-logo';
import {
    formatAcademicYearOptionLabel,
    type YearOption,
} from '@/components/sis/ops-year-filter';
import { usePageError } from '@/components/sis/page-error-context';
import { resolveErrorMessage } from '@/lib/format-inertia-errors';
import { SisListSelect } from '@/components/sis/sis-list-select';
import { SisMultiSelect } from '@/components/sis/sis-multi-select';
import { ariaSort, SortButton, useTableSort, type TableSort } from '@/components/sis/table-sort';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogTitle } from '@/components/ui/dialog';
import { WindowControls } from '@/components/window-controls';
import { TypeHourTotalsBar } from '@/components/curriculum/type-hour-totals';
import { typeHourTotals } from '@/lib/subject-type-order';
import {
    orderSubjectTypes,
    subjectTypeLabel,
    type CurriculumFilterOptions,
    type CurriculumRow,
    type LinkedSubjectRow,
    type SubjectRow,
    type TeacherOption,
} from '@/components/curriculum/curriculum-workspace';
import { useDragScroll } from '@/hooks/use-drag-scroll';
import { useSheetMaximize } from '@/hooks/use-sheet-maximize';
import { useSmoothDialogDrag } from '@/hooks/use-smooth-dialog-drag';
import { t } from '@/i18n';
import {
    resolveSisClassId,
    resolveSisClassKey,
    sisClassSelectOptions,
} from '@/lib/sis-class-section-options';
import { cn } from '@/lib/utils';

type Props = {
    canManage: boolean;
    filterOptions: CurriculumFilterOptions;
    defaultAcademicYearId: number | null;
    subjects: SubjectRow[];
    /** The year's teachers — each subject's «المدرسون». */
    teacherOptions?: TeacherOption[];
    /** «تعديل منهج»: the same window, filled with the curriculum, its subjects and their teachers; every field editable. */
    editing?: { curriculum: CurriculumRow; lines: LinkedSubjectRow[] };
    onClose: () => void;
};

/** A curriculum's current teachers as the window's assignments: one per (part, section) of each subject. */
function groupsFromLines(lines: LinkedSubjectRow[]): SubjectTeachers {
    const out: SubjectTeachers = {};
    for (const line of lines) {
        const groups = new Map<string, AssignmentGroup>();
        for (const teacher of line.teachers ?? []) {
            const part = teacher.subject_type ? String(teacher.subject_type) : '0';
            const section = teacher.section_id ? String(teacher.section_id) : '';
            const key = `${part}|${section}`;
            const group = groups.get(key) ?? { part, sections: section === '' ? [] : [section], mains: [], assistants: [] };
            (teacher.role === 1 ? group.mains : group.assistants).push(String(teacher.teacher_id));
            groups.set(key, group);
        }
        if (groups.size > 0) {
            out[line.subject_id] = [...groups.values()];
        }
    }

    return out;
}

/**
 * One «إسناد مدرس للمادة»: a part of the subject (its type; '0' = the whole subject), the sections taught (none = all
 * sections), the main teachers and the assistants.
 */
type AssignmentGroup = { part: string; sections: string[]; mains: string[]; assistants: string[] };

/** subject id → its assignments. */
type SubjectTeachers = Record<number, AssignmentGroup[]>;

/**
 * Each subject's own colour in «المنهج الدراسي» (its row and its open «اسناد مدرس للمادة»), so subjects are not
 * mixed up: the subject's «الاختصار واللون» hue, else one derived from its id (the timetable's rule).
 */
function subjectTint(subject: SubjectRow): { row: CSSProperties; panel: CSSProperties } {
    const hue = subject.color_hue ?? (subject.id * 47) % 360;
    const edge = `4px solid hsl(${hue} 60% 42%)`;

    return {
        row: { background: `hsl(${hue} 70% 86%)`, boxShadow: `inset -4px 0 0 hsl(${hue} 60% 42%)` },
        panel: { background: `hsl(${hue} 70% 93%)`, borderInlineStart: edge },
    };
}

/** Distinct main teachers and assistants over a subject's assignments («أساسي: 2 · مساعد: 1»). */
function teacherCounts(groups: AssignmentGroup[]): { mains: number; assistants: number } {
    return {
        mains: new Set(groups.flatMap((group) => group.mains)).size,
        assistants: new Set(groups.flatMap((group) => group.assistants)).size,
    };
}

/** The parts a subject's teachers are set for: each of its types when it has several, else the whole subject. */
function partsOf(subject: SubjectRow): string[] {
    const types = subject.subject_types && subject.subject_types.length > 0 ? subject.subject_types : [subject.subject_type];

    return types.length > 1 ? orderSubjectTypes(types).map(String) : ['0'];
}

/** Rows that fill the shuttle pane without scrolling. */
const CREATE_SHEET_PAGE_SIZE = 12;

function newIdempotencyKey(prefix: string): string {
    if (typeof crypto !== 'undefined' && 'randomUUID' in crypto) {
        return `${prefix}-${crypto.randomUUID()}`;
    }

    return `${prefix}-${Date.now()}-${Math.random().toString(16).slice(2)}`;
}

function suggestCurriculumName(
    filterOptions: CurriculumFilterOptions,
    branchId: string,
    departmentId: string,
    gradeLevelId: string,
    sectionId = '',
): string {
    const branch = filterOptions.branches.find((item) => String(item.id) === branchId);
    const spec = filterOptions.departments.find((item) => String(item.id) === departmentId);
    const gradeClass = filterOptions.classes.find(
        (item) => String(item.grade_level_id) === gradeLevelId,
    );
    const section = (filterOptions.sections ?? []).find((item) => String(item.id) === sectionId);

    return [branch?.name, spec?.name, gradeClass?.name, section?.name].filter(Boolean).join(' — ');
}

function pageSlice<T>(rows: T[], page: number, pageSize: number): T[] {
    const start = (page - 1) * pageSize;

    return rows.slice(start, start + pageSize);
}

function lastPageFor(total: number, pageSize: number): number {
    return Math.max(1, Math.ceil(total / pageSize));
}

function visiblePages(current: number, totalPages: number): number[] {
    const windowSize = 5;
    if (totalPages <= windowSize) {
        return Array.from({ length: totalPages }, (_, index) => index + 1);
    }

    const half = Math.floor(windowSize / 2);
    let start = Math.max(1, current - half);
    let end = start + windowSize - 1;
    if (end > totalPages) {
        end = totalPages;
        start = Math.max(1, end - windowSize + 1);
    }

    return Array.from({ length: end - start + 1 }, (_, index) => start + index);
}

function SubjectShuttleTable({
    rows,
    selectedIds,
    pageSize,
    onToggle,
    subjectNameLabel,
    sort,
}: {
    rows: SubjectRow[];
    selectedIds: number[];
    pageSize: number;
    onToggle: (id: number) => void;
    subjectNameLabel: string;
    sort: TableSort;
}) {
    const padded = [...rows];
    while (padded.length < pageSize) {
        padded.push({
            id: -1 - padded.length,
            code: '',
            name: '',
            subject_type: 1,
            credit_hours: null,
            max_grade: 0,
            pass_grade: 0,
            status: 1,
        });
    }

    return (
        <table className="sis-curriculum-create-sheet__table">
            <thead>
                <tr>
                    <th className="sis-curriculum-create-sheet__check" scope="col">
                        <span className="sr-only">{subjectNameLabel}</span>
                    </th>
                    <th className="sis-curriculum-create-sheet__name-head" scope="col" aria-sort={ariaSort(sort, 'name')}>
                        <SortButton sort={sort} column="name">
                            {subjectNameLabel}
                        </SortButton>
                    </th>
                </tr>
            </thead>
            <tbody>
                {padded.map((row, index) => {
                    if (row.id < 0) {
                        return (
                            <tr key={`pad-${index}`} className="sis-curriculum-create-sheet__row--pad">
                                <td className="sis-curriculum-create-sheet__check">
                                    <span className="sis-curriculum-create-sheet__cell-inner" />
                                </td>
                                <td className="sis-curriculum-create-sheet__name">
                                    <div className="sis-curriculum-create-sheet__cell-inner" />
                                </td>
                            </tr>
                        );
                    }

                    const checked = selectedIds.includes(row.id);

                    return (
                        <tr
                            key={row.id}
                            className={
                                checked ? 'sis-curriculum-create-sheet__row--selected' : undefined
                            }
                            onClick={() => onToggle(row.id)}
                        >
                            <td className="sis-curriculum-create-sheet__check">
                                <span className="sis-curriculum-create-sheet__cell-inner sis-curriculum-create-sheet__cell-inner--check">
                                    <input
                                        type="checkbox"
                                        checked={checked}
                                        onChange={() => onToggle(row.id)}
                                        onClick={(event) => event.stopPropagation()}
                                        aria-label={row.name}
                                    />
                                </span>
                            </td>
                            <td className="sis-curriculum-create-sheet__name">
                                <div className="sis-curriculum-create-sheet__cell-inner sis-curriculum-create-sheet__cell-inner--name">
                                    {row.name}
                                </div>
                            </td>
                        </tr>
                    );
                })}
            </tbody>
        </table>
    );
}

/**
 * «المنهج الدراسي» pane: each subject has a collapse arrow; opened, it shows «إسناد مدرس للمادة» — one or more
 * assignments, each for a part of the subject (نظري، عملي … or the whole subject), one or more sections (none chosen =
 * all sections), one or more main teachers and one or more assistants.
 */
function PlannedSubjectsTable({
    rows,
    selectedIds,
    pageSize,
    onToggle,
    sort,
    teachers,
    teacherOptions,
    sectionOptions,
    onTeachers,
}: {
    rows: SubjectRow[];
    selectedIds: number[];
    pageSize: number;
    onToggle: (id: number) => void;
    sort: TableSort;
    teachers: SubjectTeachers;
    teacherOptions: TeacherOption[];
    sectionOptions: Array<{ value: string; label: string }>;
    onTeachers: (subjectId: number, next: AssignmentGroup[]) => void;
}) {
    const c = t().curriculum;
    const [open, setOpen] = useState<number[]>([]);
    const options = teacherOptions.map((teacher) => ({ value: String(teacher.id), label: teacher.name }));
    const padCount = Math.max(0, pageSize - rows.length);
    const selectClasses = {
        className: 'sis-admission-sheet-list-select',
        triggerClassName: 'sis-admission-sheet__control sis-admission-draft-select',
        menuClassName: 'sis-admission-sheet-list-select__menu',
    };

    return (
        <table className="sis-curriculum-create-sheet__table">
            <thead>
                <tr>
                    <th className="sis-curriculum-create-sheet__check" scope="col">
                        <span className="sr-only">{c.subjectName}</span>
                    </th>
                    <th className="sis-curriculum-create-sheet__name-head" scope="col" aria-sort={ariaSort(sort, 'name')}>
                        <SortButton sort={sort} column="name">
                            {c.subjectName}
                        </SortButton>
                    </th>
                    <th className="sis-curriculum-create-sheet__name-head" scope="col" style={{ width: '9rem', textAlign: 'center' }}>
                        {c.assignSubjectTeacher}
                    </th>
                </tr>
            </thead>
            <tbody>
                {rows.map((row) => {
                    const checked = selectedIds.includes(row.id);
                    const expanded = open.includes(row.id);
                    const parts = partsOf(row);
                    // Until something is chosen: one empty assignment per part (all sections) — shown, not saved.
                    const groups = teachers[row.id] ?? parts.map((part) => ({ part, sections: [], mains: [], assistants: [] }));
                    const { mains, assistants } = teacherCounts(groups);
                    const tint = subjectTint(row);
                    const setGroup = (index: number, next: AssignmentGroup): void =>
                        onTeachers(row.id, groups.map((group, i) => (i === index ? next : group)));

                    return (
                        <Fragment key={row.id}>
                            <tr
                                className={checked ? 'sis-curriculum-create-sheet__row--selected' : undefined}
                                style={expanded ? tint.row : undefined}
                            >
                                <td className="sis-curriculum-create-sheet__check">
                                    <span className="sis-curriculum-create-sheet__cell-inner sis-curriculum-create-sheet__cell-inner--check">
                                        <input type="checkbox" checked={checked} onChange={() => onToggle(row.id)} aria-label={row.name} />
                                    </span>
                                </td>
                                <td className="sis-curriculum-create-sheet__name">
                                    <div className="sis-curriculum-create-sheet__cell-inner sis-curriculum-create-sheet__cell-inner--name">
                                        {row.name}
                                        {mains + assistants > 0
                                            ? ` — ${c.mainCount.replace(':n', String(mains))} · ${c.assistantCount.replace(':n', String(assistants))}`
                                            : ''}
                                    </div>
                                </td>
                                <td className="sis-curriculum-create-sheet__check">
                                    <span className="sis-curriculum-create-sheet__cell-inner sis-curriculum-create-sheet__cell-inner--check">
                                        <button
                                            type="button"
                                            aria-expanded={expanded}
                                            aria-label={`${c.assignSubjectTeacher} — ${row.name}`}
                                            title={c.assignSubjectTeacher}
                                            onClick={() => {
                                                setOpen((current) =>
                                                    current.includes(row.id) ? current.filter((id) => id !== row.id) : [...current, row.id],
                                                );
                                            }}
                                        >
                                            <ChevronDown className={`size-4 transition-transform${expanded ? ' rotate-180' : ''}`} aria-hidden />
                                        </button>
                                    </span>
                                </td>
                            </tr>
                            {expanded ? (
                                <tr>
                                    <td
                                        colSpan={3}
                                        style={{ height: 'auto', maxHeight: 'none', whiteSpace: 'normal', overflow: 'visible', padding: '0.35rem 0.5rem', ...tint.panel }}
                                    >
                                        <p className="sis-admission-sheet__label">
                                            {c.assignSubjectTeacher}
                                            {checked && selectedIds.length > 1
                                                ? ` — ${c.appliesToSelected.replace(':n', String(selectedIds.length))}`
                                                : ''}
                                        </p>
                                        {options.length === 0 ? <p className="text-sm">{c.noTeacherOptions}</p> : null}
                                        <div className="flex flex-col gap-2">
                                            {groups.map((group, index) => (
                                                // Two rows: the part / section / «حذف», then the main teachers and the assistants,
                                                // each half the width so a full name fits on its line.
                                                <div
                                                    key={index}
                                                    className="flex flex-col gap-2 border-b border-[color:var(--sis-powder-blue)] pb-2 last:border-b-0"
                                                >
                                                <div
                                                    className={`grid items-end gap-2 ${parts.length > 1 ? 'grid-cols-[minmax(0,1fr)_minmax(0,1fr)_auto]' : 'grid-cols-[minmax(0,1fr)_auto]'}`}
                                                >
                                                    {parts.length > 1 ? (
                                                        <label className="flex min-w-0 flex-col gap-1">
                                                            <span className="sis-admission-sheet__label">{c.teachingPart}</span>
                                                            <SisListSelect
                                                                value={group.part}
                                                                options={['0', ...parts].map((part) => ({
                                                                    value: part,
                                                                    label: part === '0' ? c.wholeSubject : subjectTypeLabel(Number(part), c),
                                                                }))}
                                                                includeBlank={false}
                                                                ariaLabel={`${c.teachingPart} — ${row.name}`}
                                                                onChange={(value) => setGroup(index, { ...group, part: value })}
                                                                {...selectClasses}
                                                            />
                                                        </label>
                                                    ) : null}
                                                    <label className="flex min-w-0 flex-col gap-1">
                                                        <span className="sis-admission-sheet__label">{c.section}</span>
                                                        <SisMultiSelect
                                                            values={group.sections}
                                                            options={sectionOptions}
                                                            allLabel={c.allSections}
                                                            ariaLabel={`${c.section} — ${row.name}`}
                                                            wrapChosen
                                                            onChange={(values) => setGroup(index, { ...group, sections: values })}
                                                            {...selectClasses}
                                                        />
                                                    </label>
                                                    <Button
                                                        type="button"
                                                        variant="outline"
                                                        size="sm"
                                                        aria-label={`${c.removeAssignment} — ${row.name}`}
                                                        onClick={() => onTeachers(row.id, groups.filter((_, i) => i !== index))}
                                                    >
                                                        {c.removeAssignment}
                                                    </Button>
                                                </div>
                                                <div className="grid grid-cols-2 items-start gap-2">
                                                    <label className="flex min-w-0 flex-col gap-1">
                                                        <span className="sis-admission-sheet__label">{c.mainTeachers}</span>
                                                        <SisMultiSelect
                                                            values={group.mains}
                                                            options={options}
                                                            allLabel={c.noTeacher}
                                                            ariaLabel={`${c.mainTeachers} — ${row.name}`}
                                                            wrapChosen
                                                            scrollableMenu
                                                            onChange={(values) =>
                                                                setGroup(index, {
                                                                    ...group,
                                                                    mains: values,
                                                                    // A teacher is main or assistant, not both.
                                                                    assistants: group.assistants.filter((id) => !values.includes(id)),
                                                                })
                                                            }
                                                            {...selectClasses}
                                                        />
                                                    </label>
                                                    <label className="flex min-w-0 flex-col gap-1">
                                                        <span className="sis-admission-sheet__label">{c.assistantTeachers}</span>
                                                        <SisMultiSelect
                                                            values={group.assistants}
                                                            options={options}
                                                            allLabel={c.noAssistant}
                                                            ariaLabel={`${c.assistantTeachers} — ${row.name}`}
                                                            wrapChosen
                                                            scrollableMenu
                                                            onChange={(values) =>
                                                                setGroup(index, {
                                                                    ...group,
                                                                    assistants: values,
                                                                    mains: group.mains.filter((id) => !values.includes(id)),
                                                                })
                                                            }
                                                            {...selectClasses}
                                                        />
                                                    </label>
                                                </div>
                                                </div>
                                            ))}
                                            <div>
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    size="sm"
                                                    onClick={() =>
                                                        onTeachers(row.id, [...groups, { part: parts[0] ?? '0', sections: [], mains: [], assistants: [] }])
                                                    }
                                                >
                                                    {c.addAssignment}
                                                </Button>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            ) : null}
                        </Fragment>
                    );
                })}
                {Array.from({ length: padCount }, (_, index) => (
                    <tr key={`pad-${index}`} className="sis-curriculum-create-sheet__row--pad">
                        <td className="sis-curriculum-create-sheet__check">
                            <span className="sis-curriculum-create-sheet__cell-inner" />
                        </td>
                        <td className="sis-curriculum-create-sheet__name">
                            <div className="sis-curriculum-create-sheet__cell-inner" />
                        </td>
                        <td className="sis-curriculum-create-sheet__check">
                            <span className="sis-curriculum-create-sheet__cell-inner" />
                        </td>
                    </tr>
                ))}
            </tbody>
        </table>
    );
}

function PanePagination({
    page,
    lastPage,
    total,
    onPage,
    pageLabel,
    prevLabel,
    nextLabel,
}: {
    page: number;
    lastPage: number;
    total: number;
    onPage: (page: number) => void;
    pageLabel: string;
    prevLabel: string;
    nextLabel: string;
}) {
    if (total === 0) {
        return <div className="sis-curriculum-create-sheet__pager" aria-hidden />;
    }

    return (
        <nav className="sis-curriculum-create-sheet__pager" aria-label={pageLabel}>
            <div className="sis-admission-drafts-pagination">
                <ul className="sis-admission-pagination" dir="ltr">
                    <li className="sis-admission-pagination__item">
                        <button
                            type="button"
                            className="sis-admission-pagination__link"
                            aria-label={prevLabel}
                            disabled={page <= 1}
                            onClick={() => onPage(page - 1)}
                        >
                            <span aria-hidden="true">&laquo;</span>
                        </button>
                    </li>
                    {visiblePages(page, lastPage).map((pageNum) => (
                        <li key={pageNum} className="sis-admission-pagination__item">
                            <button
                                type="button"
                                className={
                                    pageNum === page
                                        ? 'sis-admission-pagination__link sis-admission-pagination__link--active'
                                        : 'sis-admission-pagination__link'
                                }
                                aria-label={`${pageLabel} ${pageNum}`}
                                aria-current={pageNum === page ? 'page' : undefined}
                                onClick={() => onPage(pageNum)}
                            >
                                {pageNum}
                            </button>
                        </li>
                    ))}
                    <li className="sis-admission-pagination__item">
                        <button
                            type="button"
                            className="sis-admission-pagination__link"
                            aria-label={nextLabel}
                            disabled={page >= lastPage}
                            onClick={() => onPage(page + 1)}
                        >
                            <span aria-hidden="true">&raquo;</span>
                        </button>
                    </li>
                </ul>
            </div>
        </nav>
    );
}

/**
 * Create curriculum shuttle sheet — dual lists (subjects ↔ curriculum) with SIS sheet chrome.
 */
export function CurriculumCreateSheetDialog({
    canManage,
    filterOptions,
    defaultAcademicYearId,
    subjects,
    teacherOptions = [],
    editing,
    onClose,
}: Props) {
    const editedCurriculum = editing?.curriculum ?? null;
    const editedLines = useMemo(
        () => (editing?.lines ?? []).filter((line) => line.status === 1).sort((a, b) => a.subject_order - b.subject_order),
        [editing?.lines],
    );
    const i18n = t();
    const c = i18n.curriculum;
    const { showInertiaErrors, showSuccess, showError } = usePageError();
    const { academicYears } = usePage().props as { academicYears?: YearOption[] };
    const years = academicYears ?? [];
    const { contentRef, bringToFront, heroDragProps, resizeHandles } = useSmoothDialogDrag(true, {
        resizable: true,
        minSize: { width: 640, height: 420 },
    });
    const { maximized, toggleMaximize, maximizeClassName } = useSheetMaximize(contentRef);
    // The form scrolls up / down (and sideways when the window is narrower than it), by its scrollbars or by
    // dragging its content — smoothly. The title bar keeps moving the window.
    const formRef = useDragScroll<HTMLElement>(true, 'input, textarea, select, button, a, [role="combobox"], [role="option"], .sis-admission-sheet__hero, [data-drag-scroll-ignore]');
    // «المنهج الدراسي» grows with each subject's «اسناد مدرس للمادة»: its list scrolls up / down on its own.
    const plannedBodyRef = useDragScroll<HTMLDivElement>(true, 'input, textarea, select, button, a, [role="combobox"], [role="option"], .sis-admission-sheet__hero, [data-drag-scroll-ignore]');

    // In «تعديل منهج» every field starts from the curriculum.
    const [academicYearId, setAcademicYearId] = useState(
        editedCurriculum ? String(editedCurriculum.academic_year_id) : defaultAcademicYearId ? String(defaultAcademicYearId) : '',
    );
    const [branchId, setBranchId] = useState(editedCurriculum?.branch_id ? String(editedCurriculum.branch_id) : '');
    /** الاختصاص — department of the selected branch. */
    const [departmentId, setDepartmentId] = useState(editedCurriculum?.department_id ? String(editedCurriculum.department_id) : '');
    const [gradeLevelId, setGradeLevelId] = useState(
        editedCurriculum
            ? String(editedCurriculum.grade_level_id)
            : filterOptions.classes[0]
              ? String(filterOptions.classes[0].grade_level_id)
              : '',
    );
    const [subjectTeachers, setSubjectTeachers] = useState<SubjectTeachers>(() => groupsFromLines(editedLines));
    /** «عدد المواد» the curriculum should have; fewer is accepted but «غير مكتمل». */
    const [plannedCount, setPlannedCount] = useState(
        editedCurriculum ? String(editedCurriculum.planned_subject_count ?? editedLines.length) : '',
    );
    const [plannedIds, setPlannedIds] = useState<number[]>(() => editedLines.map((line) => line.subject_id));
    const [availableSelected, setAvailableSelected] = useState<number[]>([]);
    const [plannedSelected, setPlannedSelected] = useState<number[]>([]);
    const [availablePage, setAvailablePage] = useState(1);
    const [plannedPage, setPlannedPage] = useState(1);
    const [saving, setSaving] = useState(false);

    const activeSubjects = useMemo(
        () => subjects.filter((row) => Number(row.status) === 1),
        [subjects],
    );

    const plannedSet = useMemo(() => new Set(plannedIds), [plannedIds]);

    const availableSubjects = useMemo(
        () => activeSubjects.filter((row) => !plannedSet.has(row.id)),
        [activeSubjects, plannedSet],
    );

    const plannedSubjects = useMemo(
        () =>
            plannedIds
                .map((id) => activeSubjects.find((row) => row.id === id))
                .filter((row): row is SubjectRow => row !== undefined),
        [activeSubjects, plannedIds],
    );

    const availableLastPage = lastPageFor(availableSubjects.length, CREATE_SHEET_PAGE_SIZE);
    const plannedLastPage = lastPageFor(plannedSubjects.length, CREATE_SHEET_PAGE_SIZE);

    useEffect(() => {
        setAvailablePage((page) => Math.min(page, availableLastPage));
    }, [availableLastPage]);

    useEffect(() => {
        setPlannedPage((page) => Math.min(page, plannedLastPage));
    }, [plannedLastPage]);

    // Each pane sorts its whole list by name (header click), then pages it.
    const { rows: sortedAvailable, sort: availableSort } = useTableSort(availableSubjects, (row) => row.name);
    const { rows: sortedPlanned, sort: plannedSort } = useTableSort(plannedSubjects, (row) => row.name);
    const pagedAvailable = useMemo(
        () => pageSlice(sortedAvailable, availablePage, CREATE_SHEET_PAGE_SIZE),
        [availablePage, sortedAvailable],
    );
    const pagedPlanned = useMemo(
        () => pageSlice(sortedPlanned, plannedPage, CREATE_SHEET_PAGE_SIZE),
        [plannedPage, sortedPlanned],
    );

    const yearOptions = useMemo(
        () =>
            years.map((year) => ({
                value: String(year.id),
                label: formatAcademicYearOptionLabel(year.name, year.code),
            })),
        [years],
    );

    const classOptions = useMemo(() => sisClassSelectOptions(), []);

    const selectedClassKey = useMemo(
        () =>
            resolveSisClassKey(
                filterOptions.classes.find(
                    (item) => String(item.grade_level_id) === gradeLevelId,
                )?.id ?? null,
                filterOptions.classes,
            ),
        [filterOptions.classes, gradeLevelId],
    );

    // The year's class for the chosen level, and its sections.
    const classId = useMemo(
        () => filterOptions.classes.find((item) => String(item.grade_level_id) === gradeLevelId)?.id ?? null,
        [filterOptions.classes, gradeLevelId],
    );
    const sectionOptions = useMemo(
        () =>
            (filterOptions.sections ?? [])
                .filter((item) => item.class_id === classId)
                .map((item) => ({ value: String(item.id), label: item.name })),
        [classId, filterOptions.sections],
    );

    const branchDepartments = useMemo(() => {
        if (branchId === '') {
            return [];
        }

        const id = Number(branchId);

        return filterOptions.departments.filter((department) => department.branch_id === id);
    }, [branchId, filterOptions.departments]);

    const departmentOptions = useMemo(
        () => branchDepartments.map((item) => ({ value: String(item.id), label: item.name })),
        [branchDepartments],
    );

    const canSave =
        canManage &&
        !saving &&
        academicYearId !== '' &&
        gradeLevelId !== '' &&
        // A curriculum names its full place: الفرع، الاختصاص، الصف، الشعبة.
        branchId !== '' &&
        departmentId !== '' &&
        classId !== null &&
        Number(plannedCount) >= 1 &&
        plannedIds.length > 0 &&
        // Never more subjects than «عدد المواد» (raise the count to add more).
        plannedIds.length <= Number(plannedCount);

    const toggleId = (
        id: number,
        selected: number[],
        setSelected: (next: number[]) => void,
    ): void => {
        setSelected(
            selected.includes(id)
                ? selected.filter((item) => item !== id)
                : [...selected, id],
        );
    };

    const moveToCurriculum = (): void => {
        if (availableSelected.length === 0) {
            return;
        }

        // «عدد المواد» is the ceiling: only as many as still fit are added.
        const limit = Number(plannedCount) >= 1 ? Number(plannedCount) : Number.POSITIVE_INFINITY;
        const adding = availableSelected.filter((id) => !plannedIds.includes(id));
        const room = Math.max(0, limit - plannedIds.length);
        if (adding.length > room) {
            showError(resolveErrorMessage('curriculum.subjects_exceed_planned'));
        }
        setPlannedIds([...plannedIds, ...adding.slice(0, room)]);
        setAvailableSelected([]);
        setPlannedPage(1);
    };

    const moveToAvailable = (): void => {
        if (plannedSelected.length === 0) {
            return;
        }

        const remove = new Set(plannedSelected);
        setPlannedIds((current) => current.filter((id) => !remove.has(id)));
        setPlannedSelected([]);
        setAvailablePage(1);
    };

    type TeacherRow = { subject_id: number; subject_type: number | null; section_id: number | null; teacher_id: number; teacher_role: number };

    /** Each planned subject's assignments as rows: a row per section (null = all) and teacher, main (1) or assistant (2). */
    const teacherRows = (): TeacherRow[] => {
        const rows: TeacherRow[] = [];
        for (const subjectId of plannedIds) {
            for (const group of subjectTeachers[subjectId] ?? []) {
                const subjectType = group.part === '0' ? null : Number(group.part);
                const sections = group.sections.length > 0 ? group.sections.map(Number) : [null];
                for (const section of sections) {
                    for (const [ids, role] of [[group.mains, 1], [group.assistants, 2]] as const) {
                        for (const id of ids) {
                            rows.push({ subject_id: subjectId, subject_type: subjectType, section_id: section, teacher_id: Number(id), teacher_role: role });
                        }
                    }
                }
            }
        }

        return rows;
    };

    const save = (): void => {
        if (!canSave) {
            if (plannedIds.length === 0) {
                showError(c.createCurriculumHint);
            }

            return;
        }

        // The name follows the place; an edited curriculum in the same place keeps its own name.
        const samePlace =
            editedCurriculum !== null &&
            String(editedCurriculum.branch_id ?? '') === branchId &&
            String(editedCurriculum.department_id ?? '') === departmentId &&
            String(editedCurriculum.grade_level_id) === gradeLevelId;
        const planName = samePlace
            ? editedCurriculum.name
            : suggestCurriculumName(filterOptions, branchId, departmentId, gradeLevelId);
        if (planName === '') {
            showError(i18n.errors.requiredFields);

            return;
        }

        setSaving(true);
        const payload = {
                academic_year_id: Number(academicYearId),
                grade_level_id: Number(gradeLevelId),
                name: planName,
                branch_id: Number(branchId),
                department_id: Number(departmentId),
                class_id: classId,
                planned_subject_count: Number(plannedCount),
                subject_ids: plannedIds,
                teachers: teacherRows(),
        };
        const options = {
            preserveScroll: true,
            headers: { 'X-Idempotency-Key': newIdempotencyKey('curriculum') },
            onError: (errors: Record<string, string>) => {
                showInertiaErrors(errors, editedCurriculum ? i18n.errors.saveFailed : i18n.errors.createFailed);
                setSaving(false);
            },
            onSuccess: () => {
                setSaving(false);
                showSuccess({ description: editedCurriculum ? c.editCurriculumTitle : c.createCurriculumTitle });
                onClose();
            },
            onFinish: () => setSaving(false),
        };
        if (editedCurriculum) {
            // «تعديل منهج»: the whole curriculum — place, subjects, teachers — in one step.
            router.put(`/curriculum/curricula/${editedCurriculum.id}/full`, payload, options);

            return;
        }
        // The new curriculum opens on its own page (the server redirects there).
        router.post('/curriculum/curricula', payload, options);
    };

    return (
        <Dialog
            open
            modal
            onOpenChange={(open) => {
                if (!open) {
                    onClose();
                }
            }}
        >
            <DialogContent
                ref={contentRef}
                className={cn(
                    'sis-admission-draft-dialog sis-admission-sheet-dialog sis-student-sheet-dialog',
                    'sis-enrollment-record-sheet sis-curriculum-subject-sheet',
                    'sis-curriculum-create-sheet sis-curriculum-create-sheet--fixed sis-window--movable',
                    maximizeClassName,
                )}
                overlayClassName="sis-admission-sheet-dialog__overlay"
                dir="rtl"
                lang="ar"
                onPointerDownCapture={bringToFront}
                onOpenAutoFocus={(event) => event.preventDefault()}
                onCloseAutoFocus={(event) => event.preventDefault()}
                onInteractOutside={(event) => event.preventDefault()}
                onPointerDownOutside={(event) => event.preventDefault()}
                onEscapeKeyDown={(event) => event.preventDefault()}
            >
                {maximized ? null : resizeHandles}
                <DialogTitle className="sr-only">{editedCurriculum ? c.editCurriculumTitle : c.createCurriculumTitle}</DialogTitle>

                <article
                    className="sis-admission-draft-form sis-admission-sheet sis-student-record-form sis-curriculum-subject-sheet sis-curriculum-create-sheet__form"
                    dir="rtl"
                    lang="ar"
                    ref={formRef}
                    style={{ overflow: 'auto', minHeight: 0, scrollbarWidth: 'thin' }}
                >
                    <header className="sis-admission-sheet__hero" {...(maximized ? {} : heroDragProps)}>
                        <WindowControls
                            className="sis-admission-sheet__window-controls"
                            label={i18n.window.controls}
                            minimizeLabel={i18n.window.minimize}
                            maximizeLabel={i18n.window.maximize}
                            restoreLabel={i18n.window.restore}
                            closeLabel={i18n.window.close}
                            minimizable={false}
                            maximizable
                            maximized={maximized}
                            onMaximize={toggleMaximize}
                            onClose={onClose}
                        />
                        <div className="sis-admission-sheet__hero-copy">
                            <p className="sis-admission-sheet__hero-title">
                                {editedCurriculum ? c.editCurriculumTitle : c.createCurriculumTitle}
                            </p>
                        </div>
                        <div className="sis-admission-sheet__hero-logo">
                            <AppLogo tone="on-dark" className="sis-admission-sheet__logo" />
                        </div>
                    </header>

                    <div
                        className="sis-curriculum-create-sheet__filters"
                        dir="rtl"
                        style={{ gridTemplateColumns: 'minmax(0, 0.8fr) minmax(0, 1.5fr) minmax(0, 1.5fr) minmax(0, 0.6fr) minmax(0, 0.5fr)', minWidth: '62rem' }}
                    >
                        <label className="sis-curriculum-create-sheet__filter">
                            <span className="sis-admission-sheet__label">{c.academicYear} *</span>
                            <SisListSelect
                                value={academicYearId}
                                options={yearOptions}
                                onChange={setAcademicYearId}
                                ariaLabel={c.academicYear}
                                includeBlank={false}
                                className="sis-admission-sheet-list-select"
                                triggerClassName="sis-admission-sheet__control sis-admission-draft-select"
                                menuClassName="sis-admission-sheet-list-select__menu"
                            />
                        </label>
                        <label className="sis-curriculum-create-sheet__filter">
                            <span className="sis-admission-sheet__label">{c.branch} *</span>
                            <SisListSelect
                                value={branchId}
                                options={filterOptions.branches.map((item) => ({
                                    value: String(item.id),
                                    label: item.name,
                                }))}
                                onChange={(next) => {
                                    setBranchId(next);
                                    setDepartmentId('');
                                }}
                                ariaLabel={c.branch}
                                includeBlank
                                className="sis-admission-sheet-list-select"
                                triggerClassName="sis-admission-sheet__control sis-admission-draft-select"
                                menuClassName="sis-admission-sheet-list-select__menu"
                            />
                        </label>
                        <label className="sis-curriculum-create-sheet__filter">
                            <span className="sis-admission-sheet__label">{c.specialization} *</span>
                            <SisListSelect
                                value={departmentId}
                                options={departmentOptions}
                                onChange={setDepartmentId}
                                ariaLabel={c.specialization}
                                includeBlank
                                className="sis-admission-sheet-list-select"
                                triggerClassName="sis-admission-sheet__control sis-admission-draft-select"
                                menuClassName="sis-admission-sheet-list-select__menu"
                            />
                        </label>
                        <label className="sis-curriculum-create-sheet__filter">
                            <span className="sis-admission-sheet__label">{c.gradeLevel} *</span>
                            <SisListSelect
                                value={selectedClassKey}
                                options={classOptions}
                                onChange={(next) => {
                                    const classId = resolveSisClassId(next, filterOptions.classes);
                                    const selected = filterOptions.classes.find(
                                        (item) => item.id === classId,
                                    );
                                    setGradeLevelId(
                                        selected ? String(selected.grade_level_id) : '',
                                    );
                                    // Sections chosen for another class no longer apply.
                                    setSubjectTeachers((current) =>
                                        Object.fromEntries(
                                            Object.entries(current).map(([id, groups]) => [id, groups.map((group) => ({ ...group, sections: [] }))]),
                                        ),
                                    );
                                }}
                                ariaLabel={c.gradeLevel}
                                includeBlank={false}
                                className="sis-admission-sheet-list-select"
                                triggerClassName="sis-admission-sheet__control sis-admission-draft-select"
                                menuClassName="sis-admission-sheet-list-select__menu"
                            />
                        </label>
                        <label className="sis-curriculum-create-sheet__filter">
                            <span className="sis-admission-sheet__label">{c.plannedSubjectCount} *</span>
                            <input
                                className="sis-admission-sheet__control"
                                type="number"
                                min={1}
                                max={200}
                                dir="ltr"
                                value={plannedCount}
                                onChange={(event) => setPlannedCount(event.target.value)}
                            />
                        </label>

                    </div>

                    <div className="sis-curriculum-create-sheet__shuttle" dir="rtl" style={{ minWidth: '62rem' }}>
                        <section
                            className="sis-curriculum-create-sheet__pane"
                            aria-label={c.availableSubjectsPane}
                        >
                            <header className="sis-curriculum-create-sheet__pane-head">
                                {c.availableSubjectsPane}
                            </header>
                            <div className="sis-curriculum-create-sheet__pane-body">
                                <SubjectShuttleTable
                                    rows={pagedAvailable}
                                    sort={availableSort}
                                    selectedIds={availableSelected}
                                    pageSize={CREATE_SHEET_PAGE_SIZE}
                                    onToggle={(id) =>
                                        toggleId(id, availableSelected, setAvailableSelected)
                                    }
                                    subjectNameLabel={c.subjectName}
                                />
                            </div>
                            <PanePagination
                                page={availablePage}
                                lastPage={availableLastPage}
                                total={availableSubjects.length}
                                onPage={setAvailablePage}
                                pageLabel={i18n.common.page}
                                prevLabel={i18n.common.previous}
                                nextLabel={i18n.common.next}
                            />
                        </section>

                        <div className="sis-curriculum-create-sheet__arrows">
                            <button
                                type="button"
                                className="sis-curriculum-create-sheet__arrow"
                                aria-label={c.addSelectedSubjects}
                                title={c.addSelectedSubjects}
                                disabled={!canManage || availableSelected.length === 0}
                                onClick={moveToCurriculum}
                            >
                                <ChevronLeft aria-hidden />
                            </button>
                            <button
                                type="button"
                                className="sis-curriculum-create-sheet__arrow"
                                aria-label={c.removeSelectedSubjects}
                                title={c.removeSelectedSubjects}
                                disabled={!canManage || plannedSelected.length === 0}
                                onClick={moveToAvailable}
                            >
                                <ChevronRight aria-hidden />
                            </button>
                        </div>

                        <section
                            className="sis-curriculum-create-sheet__pane"
                            aria-label={c.curriculumSubjectsPane}
                        >
                            <header className="sis-curriculum-create-sheet__pane-head">
                                {c.curriculumSubjectsPane}
                            </header>
                            <div
                                ref={plannedBodyRef}
                                className="sis-curriculum-create-sheet__pane-body"
                                style={{ flex: '1 1 auto', minHeight: 0, overflowY: 'auto', overflowX: 'hidden', scrollbarWidth: 'thin' }}
                            >
                                <PlannedSubjectsTable
                                    rows={pagedPlanned}
                                    sort={plannedSort}
                                    selectedIds={plannedSelected}
                                    pageSize={CREATE_SHEET_PAGE_SIZE}
                                    onToggle={(id) =>
                                        toggleId(id, plannedSelected, setPlannedSelected)
                                    }
                                    teachers={subjectTeachers}
                                    teacherOptions={teacherOptions}
                                    sectionOptions={sectionOptions}
                                    onTeachers={(subjectId, next) =>
                                        setSubjectTeachers((current) => {
                                            const targets =
                                                plannedSelected.includes(subjectId) && plannedSelected.length > 1
                                                    ? plannedSelected
                                                    : [subjectId];
                                            const updated = { ...current };
                                            for (const id of targets) {
                                                const subject = activeSubjects.find((row) => row.id === id);
                                                const parts = subject ? partsOf(subject) : ['0'];
                                                // A part the subject does not have becomes «the whole subject».
                                                updated[id] = next.map((group) => ({
                                                    ...group,
                                                    part: parts.includes(group.part) ? group.part : '0',
                                                }));
                                            }

                                            return updated;
                                        })
                                    }
                                />
                            </div>
                            <PanePagination
                                page={plannedPage}
                                lastPage={plannedLastPage}
                                total={plannedSubjects.length}
                                onPage={setPlannedPage}
                                pageLabel={i18n.common.page}
                                prevLabel={i18n.common.previous}
                                nextLabel={i18n.common.next}
                            />
                            {Number(plannedCount) >= 1 ? (
                                // «المواد: 2 من 8 (25%)» — fewer is accepted, as «غير مكتمل».
                                <p className="mx-2 mt-2 text-sm">
                                    {c.subjectsProgress
                                        .replace(':n', String(plannedIds.length))
                                        .replace(':planned', plannedCount)
                                        .replace(':percent', String(Math.min(100, Math.floor((plannedIds.length * 100) / Number(plannedCount)))))}
                                    {plannedIds.length < Number(plannedCount) ? ` — ${c.planIncomplete}` : ''}
                                </p>
                            ) : null}
                            {/* «مجموع الساعات» of the curriculum being made: أساسي، اختصاص، نظري، عملي، الكلي. */}
                            <TypeHourTotalsBar totals={typeHourTotals(plannedSubjects)} className="m-2" />
                        </section>
                    </div>

                    <div className="sis-admission-sheet__actions">
                        <Button type="button" variant="outline" onClick={onClose}>
                            {i18n.dialog.cancel}
                        </Button>
                        <Button type="button" disabled={!canSave} onClick={save}>
                            {saving ? i18n.common.saving : c.saveCurriculum}
                        </Button>
                    </div>
                </article>
            </DialogContent>
        </Dialog>
    );
}
