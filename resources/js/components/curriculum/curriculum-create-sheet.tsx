import { router, usePage } from '@inertiajs/react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import AppLogo from '@/components/app-logo';
import {
    formatAcademicYearOptionLabel,
    type YearOption,
} from '@/components/sis/ops-year-filter';
import { usePageError } from '@/components/sis/page-error-context';
import { SisListSelect } from '@/components/sis/sis-list-select';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogTitle } from '@/components/ui/dialog';
import { WindowControls } from '@/components/window-controls';
import type {
    CurriculumFilterOptions,
    SubjectRow,
} from '@/components/curriculum/curriculum-workspace';
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
    onClose: () => void;
};

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
    specializationId: string,
    gradeLevelId: string,
): string {
    const branch = filterOptions.branches.find((item) => String(item.id) === branchId);
    const spec = filterOptions.specializations.find(
        (item) => String(item.id) === specializationId,
    );
    const gradeClass = filterOptions.classes.find(
        (item) => String(item.grade_level_id) === gradeLevelId,
    );

    return [branch?.name, spec?.name, gradeClass?.name].filter(Boolean).join(' — ');
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
}: {
    rows: SubjectRow[];
    selectedIds: number[];
    pageSize: number;
    onToggle: (id: number) => void;
    subjectNameLabel: string;
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
                    <th className="sis-curriculum-create-sheet__name-head" scope="col">
                        {subjectNameLabel}
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
    onClose,
}: Props) {
    const i18n = t();
    const c = i18n.curriculum;
    const { showInertiaErrors, showSuccess, showError } = usePageError();
    const { academicYears } = usePage().props as { academicYears?: YearOption[] };
    const years = academicYears ?? [];
    const { contentRef, bringToFront } = useSmoothDialogDrag(true, {
        disabled: true,
        resizable: false,
    });
    const { maximized, toggleMaximize, maximizeClassName } = useSheetMaximize(contentRef);

    const [academicYearId, setAcademicYearId] = useState(
        defaultAcademicYearId ? String(defaultAcademicYearId) : '',
    );
    const [branchId, setBranchId] = useState('');
    const [specializationId, setSpecializationId] = useState('');
    const [gradeLevelId, setGradeLevelId] = useState(
        filterOptions.classes[0] ? String(filterOptions.classes[0].grade_level_id) : '',
    );
    const [plannedIds, setPlannedIds] = useState<number[]>([]);
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

    const pagedAvailable = useMemo(
        () => pageSlice(availableSubjects, availablePage, CREATE_SHEET_PAGE_SIZE),
        [availablePage, availableSubjects],
    );
    const pagedPlanned = useMemo(
        () => pageSlice(plannedSubjects, plannedPage, CREATE_SHEET_PAGE_SIZE),
        [plannedPage, plannedSubjects],
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

    const branchDepartments = useMemo(() => {
        if (branchId === '') {
            return [];
        }

        const id = Number(branchId);

        return filterOptions.departments.filter((department) => department.branch_id === id);
    }, [branchId, filterOptions.departments]);

    const specializationOptions = useMemo(() => {
        const deptIds = new Set(branchDepartments.map((department) => department.id));
        const list =
            deptIds.size === 0
                ? filterOptions.specializations
                : filterOptions.specializations.filter(
                      (item) =>
                          item.department_id !== null && deptIds.has(item.department_id),
                  );

        return list.map((item) => ({ value: String(item.id), label: item.name }));
    }, [branchDepartments, filterOptions.specializations]);

    const canSave =
        canManage &&
        !saving &&
        academicYearId !== '' &&
        gradeLevelId !== '' &&
        plannedIds.length > 0;

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

        setPlannedIds((current) => {
            const next = [...current];
            for (const id of availableSelected) {
                if (!next.includes(id)) {
                    next.push(id);
                }
            }

            return next;
        });
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

    const save = (): void => {
        if (!canSave) {
            if (plannedIds.length === 0) {
                showError(c.createCurriculumHint);
            }

            return;
        }

        const planName = suggestCurriculumName(
            filterOptions,
            branchId,
            specializationId,
            gradeLevelId,
        );
        if (planName === '') {
            showError(i18n.errors.requiredFields);

            return;
        }

        setSaving(true);
        router.post(
            '/curriculum/curricula',
            {
                academic_year_id: Number(academicYearId),
                grade_level_id: Number(gradeLevelId),
                name: planName,
                specialization_id:
                    specializationId === '' ? null : Number(specializationId),
                subject_ids: plannedIds,
            },
            {
                preserveScroll: true,
                preserveState: true,
                only: ['curricula', 'filters', 'filterOptions', 'authorization', 'flash'],
                headers: { 'X-Idempotency-Key': newIdempotencyKey('curriculum') },
                onError: (errors) => {
                    showInertiaErrors(errors, i18n.errors.createFailed);
                    setSaving(false);
                },
                onSuccess: () => {
                    setSaving(false);
                    showSuccess({ description: c.createCurriculumTitle });
                    onClose();
                },
                onFinish: () => setSaving(false),
            },
        );
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
                    'sis-curriculum-create-sheet sis-curriculum-create-sheet--fixed',
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
                <DialogTitle className="sr-only">{c.createCurriculumTitle}</DialogTitle>

                <article
                    className="sis-admission-draft-form sis-admission-sheet sis-student-record-form sis-curriculum-subject-sheet sis-curriculum-create-sheet__form"
                    dir="rtl"
                    lang="ar"
                >
                    <header className="sis-admission-sheet__hero">
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
                            <p className="sis-admission-sheet__hero-title">{c.createCurriculumTitle}</p>
                        </div>
                        <div className="sis-admission-sheet__hero-logo">
                            <AppLogo tone="on-dark" className="sis-admission-sheet__logo" />
                        </div>
                    </header>

                    <div className="sis-curriculum-create-sheet__filters" dir="rtl">
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
                            <span className="sis-admission-sheet__label">{c.branch}</span>
                            <SisListSelect
                                value={branchId}
                                options={filterOptions.branches.map((item) => ({
                                    value: String(item.id),
                                    label: item.name,
                                }))}
                                onChange={(next) => {
                                    setBranchId(next);
                                    setSpecializationId('');
                                }}
                                ariaLabel={c.branch}
                                includeBlank
                                className="sis-admission-sheet-list-select"
                                triggerClassName="sis-admission-sheet__control sis-admission-draft-select"
                                menuClassName="sis-admission-sheet-list-select__menu"
                            />
                        </label>
                        <label className="sis-curriculum-create-sheet__filter">
                            <span className="sis-admission-sheet__label">{c.specialization}</span>
                            <SisListSelect
                                value={specializationId}
                                options={specializationOptions}
                                onChange={setSpecializationId}
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
                                }}
                                ariaLabel={c.gradeLevel}
                                includeBlank={false}
                                className="sis-admission-sheet-list-select"
                                triggerClassName="sis-admission-sheet__control sis-admission-draft-select"
                                menuClassName="sis-admission-sheet-list-select__menu"
                            />
                        </label>
                    </div>

                    <div className="sis-curriculum-create-sheet__shuttle" dir="rtl">
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
                            <div className="sis-curriculum-create-sheet__pane-body">
                                <SubjectShuttleTable
                                    rows={pagedPlanned}
                                    selectedIds={plannedSelected}
                                    pageSize={CREATE_SHEET_PAGE_SIZE}
                                    onToggle={(id) =>
                                        toggleId(id, plannedSelected, setPlannedSelected)
                                    }
                                    subjectNameLabel={c.subjectName}
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
