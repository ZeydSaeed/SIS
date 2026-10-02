import { useEffect, useMemo, useRef, useState, type HTMLAttributes, type ReactNode } from 'react';
import { router, usePage } from '@inertiajs/react';
import AppLogo from '@/components/app-logo';
import {
    formatAcademicYearOptionLabel,
    type YearOption,
} from '@/components/sis/ops-year-filter';
import { SheetSection } from '@/components/sis/admission-sheet';
import { SisListSelect } from '@/components/sis/sis-list-select';
import { usePageError } from '@/components/sis/page-error-context';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { WindowControls } from '@/components/window-controls';
import { StudentStatusBadge } from '@/components/students/student-status-badge';
import { useManySheetPageFit } from '@/hooks/use-many-sheet-page-fit';
import { useSmoothDialogDrag } from '@/hooks/use-smooth-dialog-drag';
import { useSheetMaximize } from '@/hooks/use-sheet-maximize';
import {
    resolveSisSectionCode,
    resolveSisSectionId,
    sisSectionSelectOptions,
} from '@/lib/sis-class-section-options';
import { t } from '@/i18n';

export type EnrollmentRecordValues = {
    id: number;
    student_id: number;
    school_id: number;
    academic_year_id: number;
    academic_year_name?: string | null;
    academic_year_code?: string | null;
    class_id: number;
    section_id: number;
    enrollment_number: string;
    status: number;
    effective_from: string;
    effective_to: string | null;
    specialization_id?: number | null;
    branch_id?: number | null;
    department_id?: number | null;
    student_code?: string | null;
    student_full_name?: string | null;
    student_first_name?: string | null;
    student_father_name?: string | null;
    student_grandfather_name?: string | null;
    student_great_grandfather_name?: string | null;
    student_last_name?: string | null;
    student_gender?: number | null;
    student_birth_date?: string | null;
    /** Civil student status from students.status (not enrollment.status). */
    student_status?: number | null;
    class_code?: string | null;
    class_name?: string | null;
    section_code?: string | null;
    section_name?: string | null;
    specialization_code?: string | null;
    specialization_name?: string | null;
    branch_code?: string | null;
    branch_name?: string | null;
    grade_level_code?: string | null;
    grade_level_name?: string | null;
    department_name?: string | null;
    stage_name?: string | null;
};

export type EnrollmentFormFilterOptions = {
    branches: Array<{ id: number; code: string; name: string }>;
    classes: Array<{ id: number; code: string; name: string; grade_level_id?: number }>;
    sections: Array<{ id: number; class_id: number; code: string; name: string }>;
    departments: Array<{ id: number; branch_id: number | null; code: string; name: string }>;
    specializations: Array<{
        id: number;
        department_id: number | null;
        code: string;
        name: string;
    }>;
    grade_levels?: Array<{
        id: number;
        code: string;
        name: string;
        education_stage: number;
    }>;
};

type EnrollmentRecordFormProps = {
    enrollment: EnrollmentRecordValues;
    canUpdate: boolean;
    filterOptions: EnrollmentFormFilterOptions;
    initialEditing?: boolean;
    sheetTitle?: string;
    onClose?: () => void;
    heroDragProps?: HTMLAttributes<HTMLElement>;
    showWindowControls?: boolean;
    /** Hide per-form hero when a shared dialog chrome owns the title bar. */
    hideHero?: boolean;
    maximized?: boolean;
    onMaximize?: () => void;
    onSaved?: (enrollment: EnrollmentRecordValues) => void;
};

type EnrollmentViewDialogProps = {
    enrollments: EnrollmentRecordValues[];
    canUpdate?: boolean;
    filterOptions: EnrollmentFormFilterOptions;
    onClose: () => void;
    onSaved?: (enrollment: EnrollmentRecordValues) => void;
    initialEditing?: boolean;
};

type EnrollmentCreateDialogProps = {
    academicYearId: number | null;
    filterOptions: EnrollmentFormFilterOptions;
    initialStudentId?: number | null;
    student?: EnrollmentCreateStudent | null;
    initialDefaults?: EnrollmentCreateFormProps['initialDefaults'];
    onClose: () => void;
    onCreated?: (studentId: number) => void;
};

export type EnrollmentCreateStudent = {
    id: number;
    student_code: string;
    full_name: string;
    first_name?: string | null;
    father_name?: string | null;
    grandfather_name?: string | null;
    great_grandfather_name?: string | null;
    last_name?: string | null;
    gender?: number | null;
    birth_date?: string | null;
};

type EnrollmentCreateFormProps = {
    academicYearId: number | null;
    filterOptions: EnrollmentFormFilterOptions;
    initialStudentId?: number | null;
    student?: EnrollmentCreateStudent | null;
    initialDefaults?: {
        class_id?: number | null;
        branch_id?: number | null;
        department_id?: number | null;
        effective_from?: string | null;
    };
    onCancel?: () => void;
    onCreated?: (studentId: number) => void;
    showCancel?: boolean;
};

/** Editable placement fields for the enrollment sheet (view/edit dialog). Status is display-only. */
type PlacementDraft = {
    effective_from: string;
    effective_to: string;
    branch_id: string;
    department_id: string;
    class_id: string;
    /** Shared SIS section code: A | B | C */
    section_code: string;
};

type CreateDraft = {
    student_id: string;
    academic_year_id: string;
    class_id: string;
    section_code: string;
    branch_id: string;
    department_id: string;
    effective_from: string;
};

function displayValue(value: string | number | null | undefined): string {
    if (value === null || value === undefined || value === '') {
        return '—';
    }

    return String(value);
}

function isoDate(value: string | null | undefined): string {
    if (value === null || value === undefined || value === '') {
        return '';
    }

    const match = /^(\d{4}-\d{2}-\d{2})/.exec(value);

    return match ? match[1] : value;
}

function formatCivilDate(value: string | null | undefined): string {
    if (value === null || value === undefined || value === '') {
        return '—';
    }

    const match = /^(\d{4})-(\d{2})-(\d{2})/.exec(value);

    if (!match) {
        return value;
    }

    return `${match[2]}/${match[3]}/${match[1]}`;
}

function studentQuadName(enrollment: EnrollmentRecordValues): string {
    const parts = [
        enrollment.student_first_name,
        enrollment.student_father_name,
        enrollment.student_grandfather_name,
        enrollment.student_great_grandfather_name,
        enrollment.student_last_name,
    ]
        .map((part) => part?.trim() ?? '')
        .filter((part) => part !== '');

    if (parts.length > 0) {
        return parts.join(' ');
    }

    return enrollment.student_full_name?.trim() || '—';
}

function studentIdentifier(enrollment: EnrollmentRecordValues): string {
    const code = enrollment.student_code?.trim();

    if (code !== undefined && code !== '') {
        return code;
    }

    return enrollment.student_id > 0 ? String(enrollment.student_id) : '—';
}

function isFilled(value: string | number | null | undefined): boolean {
    if (value === null || value === undefined) {
        return false;
    }

    const text = String(value).trim();

    return text !== '' && text !== '—';
}

function todayIso(): string {
    const now = new Date();
    const year = now.getFullYear();
    const month = String(now.getMonth() + 1).padStart(2, '0');
    const day = String(now.getDate()).padStart(2, '0');

    return `${year}-${month}-${day}`;
}

function filledControlClass(filled: boolean, editing: boolean): string {
    return `sis-admission-sheet__control${filled ? ' sis-admission-draft-field--filled' : ''}${editing ? '' : ' sis-admission-draft-readonly'}`;
}

/** Read-only sheet field — quad name / gender / academic year / student id. */
function SheetDisplayField({
    label,
    display,
    dir = 'rtl',
}: {
    label: string;
    display: string;
    dir?: 'rtl' | 'ltr';
}) {
    return (
        <div className="sis-admission-sheet__field">
            <span className="sis-admission-sheet__label">{label}</span>
            <div className={filledControlClass(isFilled(display), false)} dir={dir} aria-readonly="true">
                {display}
            </div>
        </div>
    );
}

/** Editable sheet list field (branch, department, class, section). */
function SheetListField({
    label,
    editing,
    value,
    display,
    options,
    onChange,
    disabled = false,
    includeBlank = true,
    fieldClassName = '',
}: {
    label: string;
    editing: boolean;
    value: string;
    display: string;
    options: Array<{ value: string; label: string }>;
    onChange: (value: string) => void;
    disabled?: boolean;
    includeBlank?: boolean;
    fieldClassName?: string;
}) {
    return (
        <label className={`sis-admission-sheet__field ${fieldClassName}`.trim()}>
            <span className="sis-admission-sheet__label">{label}</span>
            {editing ? (
                <SisListSelect
                    value={value}
                    options={options}
                    onChange={onChange}
                    ariaLabel={label}
                    includeBlank={includeBlank}
                    disabled={disabled}
                    className={`sis-admission-sheet-list-select${isFilled(value) ? ' sis-admission-draft-field--filled' : ''}`}
                    triggerClassName={`sis-admission-sheet__control sis-admission-draft-select${isFilled(value) ? ' sis-admission-draft-field--filled' : ''}`}
                    menuClassName="sis-admission-sheet-list-select__menu"
                />
            ) : (
                <div className={filledControlClass(isFilled(display), false)}>{display}</div>
            )}
        </label>
    );
}

/** Editable sheet date field (effective_from / effective_to). */
function SheetDateField({
    label,
    editing,
    value,
    display,
    onChange,
}: {
    label: string;
    editing: boolean;
    value: string;
    display: string;
    onChange: (value: string) => void;
}) {
    return (
        <label className="sis-admission-sheet__field">
            <span className="sis-admission-sheet__label">{label}</span>
            {editing ? (
                <input
                    type="date"
                    dir="ltr"
                    className={filledControlClass(isFilled(value), true)}
                    value={value}
                    placeholder=" "
                    aria-label={label}
                    onChange={(event) => onChange(event.target.value)}
                />
            ) : (
                <div className={filledControlClass(isFilled(display), false)} dir="ltr">
                    {display}
                </div>
            )}
        </label>
    );
}

function draftFromEnrollment(
    enrollment: EnrollmentRecordValues,
    sections: EnrollmentFormFilterOptions['sections'],
): PlacementDraft {
    return {
        effective_from: isoDate(enrollment.effective_from),
        effective_to: isoDate(enrollment.effective_to),
        branch_id: enrollment.branch_id ? String(enrollment.branch_id) : '',
        department_id: enrollment.department_id ? String(enrollment.department_id) : '',
        class_id: String(enrollment.class_id),
        section_code: resolveSisSectionCode(enrollment.section_id, sections),
    };
}

/** Enrollment view/edit sheet — mirrors StudentRecordForm's sheet chrome and layout. */
export function EnrollmentRecordForm({
    enrollment,
    canUpdate,
    filterOptions,
    initialEditing = false,
    sheetTitle,
    onClose,
    heroDragProps,
    showWindowControls = true,
    hideHero = false,
    maximized = false,
    onMaximize,
    onSaved,
}: EnrollmentRecordFormProps) {
    const i18n = t();
    const { showError, showInertiaErrors } = usePageError();
    const { academicYears } = usePage().props as { academicYears?: YearOption[] };
    const years = academicYears ?? [];
    const [editing, setEditing] = useState(initialEditing && canUpdate);
    const [saving, setSaving] = useState(false);
    const [draft, setDraft] = useState<PlacementDraft>(() =>
        draftFromEnrollment(enrollment, filterOptions.sections),
    );

    useEffect(() => {
        if (editing) {
            return;
        }

        setDraft(draftFromEnrollment(enrollment, filterOptions.sections));
    }, [editing, enrollment, filterOptions.sections]);

    useEffect(() => {
        if (initialEditing && canUpdate) {
            setEditing(true);
        }
    }, [canUpdate, initialEditing]);

    const setField = <K extends keyof PlacementDraft>(key: K, value: PlacementDraft[K]) => {
        setDraft((current) => ({ ...current, [key]: value }));
    };

    const sectionOptions = useMemo(() => sisSectionSelectOptions(), []);

    const filteredDepartments = useMemo(() => {
        if (draft.branch_id === '') {
            return filterOptions.departments;
        }

        return filterOptions.departments.filter(
            (department) =>
                department.branch_id === null || String(department.branch_id) === draft.branch_id,
        );
    }, [draft.branch_id, filterOptions.departments]);

    const name = studentQuadName(enrollment);
    const genderDisplay =
        enrollment.student_gender === 1
            ? i18n.students.male
            : enrollment.student_gender === 2
              ? i18n.students.female
              : '—';
    const yearDisplay =
        formatAcademicYearOptionLabel(
            enrollment.academic_year_name
                ?? years.find((year) => year.id === enrollment.academic_year_id)?.name
                ?? '',
            enrollment.academic_year_code
                ?? years.find((year) => year.id === enrollment.academic_year_id)?.code
                ?? '',
        ) || displayValue(enrollment.academic_year_id);
    const classDisplay = displayValue(enrollment.class_name ?? enrollment.class_code);
    const sectionDisplay =
        resolveSisSectionCode(enrollment.section_id, filterOptions.sections)
        || displayValue(enrollment.section_code ?? enrollment.section_name);
    const branchDisplay = displayValue(enrollment.branch_name ?? enrollment.branch_code);
    const departmentDisplay = displayValue(enrollment.department_name);
    const birthDateDisplay = formatCivilDate(enrollment.student_birth_date);
    const fieldsEditable = editing;

    const finishSave = (next: EnrollmentRecordValues) => {
        setEditing(false);
        setSaving(false);
        onSaved?.(next);
    };

    const savePlacement = (statusValue: number) => {
        const resolvedClassId = draft.class_id !== '' ? Number(draft.class_id) : enrollment.class_id;
        const resolvedSectionId = resolveSisSectionId(
            draft.section_code,
            resolvedClassId,
            filterOptions.sections,
        );

        if (!resolvedClassId || resolvedSectionId === null) {
            setSaving(false);
            showError(
                resolvedSectionId === null && draft.section_code !== ''
                    ? i18n.students.enrollSectionNotFound
                    : i18n.errors.missingClassSection,
            );

            return;
        }

        const resolvedBranchId = draft.branch_id === '' ? null : Number(draft.branch_id);
        const resolvedDepartmentId = draft.department_id === '' ? null : Number(draft.department_id);
        const sectionLabel =
            sectionOptions.find((item) => item.value === draft.section_code)?.label
            ?? draft.section_code;

        const nextBase: EnrollmentRecordValues = {
            ...enrollment,
            class_id: resolvedClassId,
            section_id: resolvedSectionId,
            branch_id: resolvedBranchId,
            department_id: resolvedDepartmentId,
            status: statusValue,
            effective_from: draft.effective_from || enrollment.effective_from,
            effective_to: draft.effective_to === '' ? null : draft.effective_to,
            class_name:
                filterOptions.classes.find((item) => item.id === resolvedClassId)?.name
                ?? enrollment.class_name,
            section_name: sectionLabel,
            section_code: draft.section_code,
            branch_name:
                filterOptions.branches.find((item) => item.id === resolvedBranchId)?.name ?? null,
            department_name:
                filterOptions.departments.find((item) => item.id === resolvedDepartmentId)?.name
                ?? null,
        };

        router.put(
            `/enrollments/${enrollment.id}`,
            {
                class_id: nextBase.class_id,
                section_id: nextBase.section_id,
                branch_id: nextBase.branch_id,
                department_id: nextBase.department_id,
                effective_from: draft.effective_from || undefined,
                academic_year_id: enrollment.academic_year_id || undefined,
                ...(draft.effective_to === ''
                    ? { clear_effective_to: true }
                    : { effective_to: draft.effective_to }),
            },
            {
                preserveScroll: true,
                preserveState: true,
                async: true,
                only: ['enrollments', 'filters', 'filterOptions', 'authorization'],
                onSuccess: () => {
                    finishSave(nextBase);
                },
                onError: (errors) => {
                    setSaving(false);
                    showInertiaErrors(errors, i18n.errors.placementFailed);
                },
            },
        );
    };

    const save = () => {
        if (saving || !canUpdate) {
            return;
        }

        if (draft.class_id === '' || draft.section_code === '') {
            showError(i18n.errors.missingClassSection);
            return;
        }

        setSaving(true);
        savePlacement(enrollment.status);
    };

    const resolvedSheetTitle = sheetTitle ?? i18n.enrollments.viewTitle;

    return (
        <article
            className="sis-admission-draft-form sis-admission-sheet sis-student-record-form"
            dir="rtl"
            lang="ar"
        >
            {!hideHero && (onClose || sheetTitle) ? (
                <header className="sis-admission-sheet__hero" {...heroDragProps}>
                    {onClose && showWindowControls ? (
                        <WindowControls
                            className="sis-admission-sheet__window-controls"
                            label={i18n.window.controls}
                            minimizeLabel={i18n.window.minimize}
                            maximizeLabel={i18n.window.maximize}
                            restoreLabel={i18n.window.restore}
                            closeLabel={i18n.window.close}
                            minimizable={false}
                            maximizable={Boolean(onMaximize)}
                            maximized={maximized}
                            onMaximize={onMaximize}
                            onClose={onClose}
                        />
                    ) : (
                        <span className="sis-admission-sheet__window-controls" aria-hidden="true" />
                    )}
                    <div className="sis-admission-sheet__hero-copy">
                        <p className="sis-admission-sheet__hero-title">{resolvedSheetTitle}</p>
                    </div>
                    <div className="sis-admission-sheet__hero-logo">
                        <AppLogo tone="on-dark" className="sis-admission-sheet__logo" />
                    </div>
                </header>
            ) : null}

            <SheetSection
                id={`enrollment-student-${enrollment.id}`}
                title={i18n.enrollments.student}
            >
                <div className="sis-student-record-form__name-line">
                    <SheetDisplayField label={i18n.enrollments.quadName} display={name} />
                    <div className="sis-admission-sheet__field sis-student-record-form__status-field">
                        <span className="sis-admission-sheet__label">{i18n.common.status}</span>
                        <div
                            className="sis-student-record-form__status-value"
                            aria-readonly="true"
                        >
                            <StudentStatusBadge status={enrollment.student_status ?? 1} />
                        </div>
                    </div>
                </div>
                <div className="sis-admission-sheet__row sis-admission-sheet__row--track5">
                    <SheetDisplayField
                        label={i18n.enrollments.studentId}
                        display={studentIdentifier(enrollment)}
                        dir="ltr"
                    />
                    <SheetDisplayField label={i18n.enrollments.academicYear} display={yearDisplay} />
                    <SheetDisplayField label={i18n.enrollments.gender} display={genderDisplay} />
                    <SheetDisplayField
                        label={i18n.students.birthDate}
                        display={birthDateDisplay}
                        dir="ltr"
                    />
                    <SheetDateField
                        label={i18n.enrollments.effectiveFrom}
                        editing={fieldsEditable}
                        value={draft.effective_from}
                        display={formatCivilDate(enrollment.effective_from)}
                        onChange={(value) => setField('effective_from', value)}
                    />
                    <SheetDateField
                        label={i18n.enrollments.effectiveTo}
                        editing={fieldsEditable}
                        value={draft.effective_to}
                        display={formatCivilDate(enrollment.effective_to)}
                        onChange={(value) => setField('effective_to', value)}
                    />
                </div>
            </SheetSection>

            <SheetSection
                id={`enrollment-placement-${enrollment.id}`}
                title={i18n.enrollments.editPlacement}
            >
                <div className="sis-admission-sheet__row sis-admission-sheet__row--track5 sis-enrollment-record-sheet__placement-row">
                    <SheetListField
                        label={i18n.enrollments.branchName}
                        editing={fieldsEditable}
                        value={draft.branch_id}
                        display={branchDisplay}
                        fieldClassName="sis-enrollment-record-sheet__field--wide"
                        options={filterOptions.branches.map((item) => ({
                            value: String(item.id),
                            label: item.name,
                        }))}
                        onChange={(next) => {
                            setDraft((current) => ({
                                ...current,
                                branch_id: next,
                                department_id: '',
                            }));
                        }}
                    />
                    <SheetListField
                        label={i18n.enrollments.departmentName}
                        editing={fieldsEditable}
                        value={draft.department_id}
                        display={departmentDisplay}
                        disabled={draft.branch_id === ''}
                        fieldClassName="sis-enrollment-record-sheet__field--wide"
                        options={filteredDepartments.map((item) => ({
                            value: String(item.id),
                            label: item.name,
                        }))}
                        onChange={(next) => setField('department_id', next)}
                    />
                    <SheetListField
                        label={i18n.enrollments.className}
                        editing={fieldsEditable}
                        value={draft.class_id}
                        display={classDisplay}
                        includeBlank={false}
                        fieldClassName="sis-enrollment-record-sheet__field--narrow"
                        options={filterOptions.classes.map((item) => ({
                            value: String(item.id),
                            label: item.name,
                        }))}
                        onChange={(next) => {
                            setDraft((current) => ({
                                ...current,
                                class_id: next,
                                section_code: '',
                            }));
                        }}
                    />
                    <SheetListField
                        label={i18n.enrollments.sectionName}
                        editing={fieldsEditable}
                        value={draft.section_code}
                        display={sectionDisplay}
                        includeBlank={false}
                        fieldClassName="sis-enrollment-record-sheet__field--narrow"
                        options={sectionOptions}
                        onChange={(next) => setField('section_code', next)}
                    />
                </div>
            </SheetSection>

            <div className="sis-admission-sheet__actions">
                {onClose ? (
                    <Button type="button" variant="outline" onClick={onClose}>
                        {i18n.dialog.cancel}
                    </Button>
                ) : null}
                {canUpdate ? (
                    <>
                        <Button
                            type="button"
                            variant="outline"
                            disabled={editing}
                            onClick={() => setEditing(true)}
                        >
                            {i18n.common.edit}
                        </Button>
                        <Button type="button" disabled={saving || !editing} onClick={save}>
                            {saving ? i18n.common.saving : i18n.common.save}
                        </Button>
                    </>
                ) : null}
            </div>
        </article>
    );
}

export function EnrollmentViewDialog({
    enrollments,
    canUpdate = false,
    filterOptions,
    onClose,
    onSaved,
    initialEditing = false,
}: EnrollmentViewDialogProps) {
    const i18n = t();
    const count = enrollments.length;
    const viewTitle = i18n.enrollments.viewTitle;
    const dialogTitle =
        count > 1 ? `${i18n.enrollments.viewManyTitle} (${count})` : viewTitle;
    const { contentRef, heroDragProps, bringToFront, resizeHandles } = useSmoothDialogDrag(true, {
        resizable: true,
        minSize: { width: 520, height: 360 },
    });
    const { maximized, toggleMaximize, maximizeClassName } = useSheetMaximize(contentRef);
    const scrollerRef = useRef<HTMLDivElement | null>(null);
    const enrollmentIdsKey = enrollments.map((enrollment) => enrollment.id).join(',');

    useManySheetPageFit({
        contentRef,
        scrollerRef,
        count,
        maximized,
        mode: 'hug',
        resetKey: enrollmentIdsKey,
    });

    return (
        <Dialog
            open
            modal={false}
            onOpenChange={(open) => {
                if (!open) {
                    onClose();
                }
            }}
        >
            <DialogContent
                ref={contentRef}
                className={`sis-admission-draft-dialog sis-admission-sheet-dialog sis-student-sheet-dialog sis-enrollment-record-sheet${maximizeClassName}${count > 1 ? ' sis-student-sheet-dialog--many' : ''}`}
                overlayClassName="sis-admission-sheet-dialog__overlay"
                dir="rtl"
                lang="ar"
                onOpenAutoFocus={(event) => event.preventDefault()}
                onCloseAutoFocus={(event) => event.preventDefault()}
                onInteractOutside={(event) => event.preventDefault()}
                onPointerDownOutside={(event) => event.preventDefault()}
                onPointerDownCapture={bringToFront}
            >
                <DialogTitle className="sr-only">{dialogTitle}</DialogTitle>
                {maximized ? null : resizeHandles}
                {count > 1 ? (
                    <>
                        <header
                            className="sis-admission-sheet__hero sis-student-sheet-dialog__shared-hero"
                            {...(maximized ? {} : heroDragProps)}
                        >
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
                                <p className="sis-admission-sheet__hero-title">{viewTitle}</p>
                            </div>
                            <div className="sis-admission-sheet__hero-logo">
                                <AppLogo tone="on-dark" className="sis-admission-sheet__logo" />
                            </div>
                        </header>
                        <div
                            ref={scrollerRef}
                            className="sis-student-sheet-dialog__many-scroller"
                            dir="rtl"
                        >
                            {enrollments.map((enrollment, index) => (
                                <div
                                    key={enrollment.id}
                                    className="sis-student-sheet-dialog__many-page"
                                    data-enrollment-page={index}
                                >
                                    <EnrollmentRecordForm
                                        enrollment={enrollment}
                                        canUpdate={canUpdate}
                                        filterOptions={filterOptions}
                                        initialEditing={initialEditing}
                                        hideHero
                                        onClose={onClose}
                                        onSaved={onSaved}
                                    />
                                </div>
                            ))}
                        </div>
                    </>
                ) : (
                    enrollments.map((enrollment) => (
                        <EnrollmentRecordForm
                            key={enrollment.id}
                            enrollment={enrollment}
                            canUpdate={canUpdate}
                            filterOptions={filterOptions}
                            initialEditing={initialEditing}
                            sheetTitle={viewTitle}
                            onClose={onClose}
                            heroDragProps={maximized ? undefined : heroDragProps}
                            showWindowControls
                            maximized={maximized}
                            onMaximize={toggleMaximize}
                            onSaved={onSaved}
                        />
                    ))
                )}
            </DialogContent>
        </Dialog>
    );
}

function controlClass(filled: boolean): string {
    return `sis-ops-hub__link sis-admission-draft-control${filled ? ' sis-admission-draft-field--filled' : ''}`;
}

function StatusLikeButton({
    children,
    tone,
    disabled = false,
    onClick,
}: {
    children: string;
    tone: 'edit' | 'close';
    disabled?: boolean;
    onClick: () => void;
}) {
    return (
        <button
            type="button"
            className={
                tone === 'close'
                    ? 'sis-student-record-form__action sis-student-record-form__action--close'
                    : 'sis-student-record-form__action sis-student-record-form__action--edit'
            }
            disabled={disabled}
            onMouseDown={(event) => event.preventDefault()}
            onClick={() => {
                onClick();
                requestAnimationFrame(() => {
                    if (document.activeElement instanceof HTMLElement) {
                        document.activeElement.blur();
                    }
                });
            }}
        >
            {children}
        </button>
    );
}

function FormSection({
    id,
    title,
    children,
}: {
    id: string;
    title: string;
    children: ReactNode;
}) {
    return (
        <section className="sis-student-record-form__section" aria-labelledby={id}>
            <h4 id={id} className="sis-student-record-form__section-title">
                {title}
            </h4>
            {children}
        </section>
    );
}

function DraftField({
    label,
    editing,
    value,
    display,
    type = 'text',
    dir = 'rtl',
    className,
    onChange,
}: {
    label: string;
    editing: boolean;
    value: string;
    display: string;
    type?: 'text' | 'date' | 'number';
    dir?: 'ltr' | 'rtl';
    className?: string;
    onChange: (value: string) => void;
}) {
    return (
        <label className={`flex flex-col gap-1 text-sm${className ? ` ${className}` : ''}`}>
            <span>{label}</span>
            <div
                className={`${controlClass(isFilled(editing ? value : display))}${editing ? '' : ' sis-admission-draft-readonly'}`}
                dir={dir}
            >
                {editing ? (
                    <input
                        type={type}
                        dir={dir}
                        className="sis-student-record-form__value"
                        value={value}
                        placeholder=" "
                        aria-label={label}
                        onChange={(event) => onChange(event.target.value)}
                    />
                ) : (
                    display
                )}
            </div>
        </label>
    );
}

function DraftOptionalSelect({
    label,
    editing,
    value,
    display,
    onChange,
    options,
}: {
    label: string;
    editing: boolean;
    value: string;
    display: string;
    onChange: (value: string) => void;
    options: Array<{ value: string; label: string }>;
}) {
    return (
        <label className="flex flex-col gap-1 text-sm">
            <span>{label}</span>
            <div
                className={`${controlClass(isFilled(editing ? value : display))}${editing ? '' : ' sis-admission-draft-readonly'}`}
            >
                {editing ? (
                    <SisListSelect
                        value={value}
                        options={options}
                        onChange={onChange}
                        triggerClassName="sis-student-record-form__value"
                        dir="rtl"
                        ariaLabel={label}
                    />
                ) : (
                    display
                )}
            </div>
        </label>
    );
}

export function EnrollmentCreateForm({
    academicYearId,
    filterOptions,
    initialStudentId = null,
    student = null,
    initialDefaults,
    onCancel,
    onCreated,
    showCancel = true,
}: EnrollmentCreateFormProps) {
    const i18n = t();
    const { showError, showInertiaErrors } = usePageError();
    const { academicYears } = usePage().props as { academicYears?: YearOption[] };
    const years = academicYears ?? [];
    const [saving, setSaving] = useState(false);
    const lockedStudentId =
        student?.id && student.id > 0
            ? student.id
            : initialStudentId && initialStudentId > 0
              ? initialStudentId
              : null;
    const [draft, setDraft] = useState<CreateDraft>(() => ({
        student_id: lockedStudentId ? String(lockedStudentId) : '',
        academic_year_id: academicYearId ? String(academicYearId) : '',
        class_id: initialDefaults?.class_id ? String(initialDefaults.class_id) : '',
        section_code: '',
        branch_id: initialDefaults?.branch_id ? String(initialDefaults.branch_id) : '',
        department_id: initialDefaults?.department_id ? String(initialDefaults.department_id) : '',
        effective_from: initialDefaults?.effective_from?.trim() || todayIso(),
    }));

    const sectionOptions = useMemo(() => sisSectionSelectOptions(), []);

    const filteredDepartments = useMemo(() => {
        if (draft.branch_id === '') {
            return filterOptions.departments;
        }

        return filterOptions.departments.filter(
            (department) =>
                department.branch_id === null || String(department.branch_id) === draft.branch_id,
        );
    }, [draft.branch_id, filterOptions.departments]);

    const yearOptions = years.map((year) => ({
        value: String(year.id),
        label: formatAcademicYearOptionLabel(year.name, year.code),
    }));

    const studentName =
        student?.full_name?.trim()
        || [
            student?.first_name,
            student?.father_name,
            student?.grandfather_name,
            student?.great_grandfather_name,
            student?.last_name,
        ]
            .map((part) => part?.trim() ?? '')
            .filter((part) => part !== '')
            .join(' ')
        || '';

    const genderLabel =
        student?.gender === 1
            ? i18n.students.male
            : student?.gender === 2
              ? i18n.students.female
              : '—';

    const submit = () => {
        if (saving) {
            return;
        }

        if (
            draft.student_id === ''
            || draft.academic_year_id === ''
            || draft.class_id === ''
            || draft.section_code === ''
            || draft.effective_from === ''
        ) {
            showError(i18n.errors.requiredFields);
            return;
        }

        const classId = Number(draft.class_id);
        const sectionId = resolveSisSectionId(draft.section_code, classId, filterOptions.sections);
        if (sectionId === null) {
            showError(i18n.students.enrollSectionNotFound);
            return;
        }

        const studentId = Number(draft.student_id);
        setSaving(true);
        const idempotencyKey =
            typeof crypto !== 'undefined' && 'randomUUID' in crypto
                ? crypto.randomUUID()
                : `enroll-${studentId}-${Date.now()}`;

        router.post(
            '/enrollments',
            {
                student_id: studentId,
                academic_year_id: Number(draft.academic_year_id),
                class_id: classId,
                section_id: sectionId,
                effective_from: draft.effective_from,
                ...(draft.branch_id === '' ? {} : { branch_id: Number(draft.branch_id) }),
                ...(draft.department_id === ''
                    ? {}
                    : { department_id: Number(draft.department_id) }),
            },
            {
                headers: { 'X-Idempotency-Key': idempotencyKey },
                preserveScroll: true,
                onSuccess: () => onCreated?.(studentId),
                onError: (errors) => showInertiaErrors(errors, i18n.errors.createFailed),
                onFinish: () => setSaving(false),
            },
        );
    };

    return (
        <article className="sis-admission-draft-form sis-student-record-form" dir="rtl" lang="ar">
            <header className="sis-student-record-form__head">
                <h3 className="sis-student-record-form__title">
                    {studentName !== '' ? studentName : i18n.enrollments.createTitle}
                </h3>
                <div className="sis-student-record-form__head-meta">
                    <StatusLikeButton tone="edit" disabled={saving} onClick={submit}>
                        {saving ? i18n.common.saving : i18n.enrollments.createSubmit}
                    </StatusLikeButton>
                    {showCancel && onCancel ? (
                        <StatusLikeButton tone="close" disabled={saving} onClick={onCancel}>
                            {i18n.window.close}
                        </StatusLikeButton>
                    ) : null}
                </div>
            </header>

            <div className="sis-student-record-form__sections">
                <FormSection id="enr-create-student" title={i18n.enrollments.studentId}>
                    <div className="sis-admission-draft-rows">
                        <div className="sis-admission-draft-row">
                            <DraftField
                                label={i18n.enrollments.studentId}
                                editing={lockedStudentId === null}
                                value={draft.student_id}
                                display={draft.student_id || '—'}
                                type="number"
                                dir="ltr"
                                onChange={(value) =>
                                    setDraft((current) => ({
                                        ...current,
                                        student_id: value.replace(/\D/g, ''),
                                    }))
                                }
                            />
                            <DraftField
                                label={i18n.students.gender}
                                editing={false}
                                value={genderLabel}
                                display={genderLabel}
                                onChange={() => undefined}
                            />
                            <DraftField
                                label={i18n.students.birthDate}
                                editing={false}
                                value={student?.birth_date ?? ''}
                                display={formatCivilDate(student?.birth_date)}
                                dir="ltr"
                                onChange={() => undefined}
                            />
                        </div>
                    </div>
                </FormSection>

                <FormSection id="enr-create-placement" title={i18n.enrollments.createTitle}>
                    <div className="sis-admission-draft-rows">
                        <div className="sis-admission-draft-row">
                            <DraftOptionalSelect
                                label={i18n.enrollments.academicYear}
                                editing
                                value={draft.academic_year_id}
                                display={draft.academic_year_id}
                                options={[
                                    { value: '', label: i18n.enrollments.academicYear },
                                    ...yearOptions,
                                ]}
                                onChange={(next) =>
                                    setDraft((current) => ({
                                        ...current,
                                        academic_year_id: next,
                                    }))
                                }
                            />
                            <DraftField
                                label={i18n.enrollments.effectiveFrom}
                                editing
                                value={draft.effective_from}
                                display={draft.effective_from}
                                type="date"
                                dir="ltr"
                                onChange={(value) =>
                                    setDraft((current) => ({
                                        ...current,
                                        effective_from: value,
                                    }))
                                }
                            />
                            <DraftOptionalSelect
                                label={i18n.enrollments.branchName}
                                editing
                                value={draft.branch_id}
                                display={draft.branch_id}
                                options={[
                                    { value: '', label: i18n.enrollments.allBranches },
                                    ...filterOptions.branches.map((item) => ({
                                        value: String(item.id),
                                        label: item.name,
                                    })),
                                ]}
                                onChange={(next) =>
                                    setDraft((current) => ({
                                        ...current,
                                        branch_id: next,
                                        department_id: '',
                                    }))
                                }
                            />
                            <DraftOptionalSelect
                                label={i18n.enrollments.departmentName}
                                editing
                                value={draft.department_id}
                                display={draft.department_id}
                                options={[
                                    { value: '', label: i18n.enrollments.allDepartments },
                                    ...filteredDepartments.map((item) => ({
                                        value: String(item.id),
                                        label: item.name,
                                    })),
                                ]}
                                onChange={(next) =>
                                    setDraft((current) => ({
                                        ...current,
                                        department_id: next,
                                    }))
                                }
                            />
                        </div>
                        <div className="sis-admission-draft-row">
                            <DraftOptionalSelect
                                label={i18n.enrollments.className}
                                editing
                                value={draft.class_id}
                                display={draft.class_id}
                                options={[
                                    { value: '', label: i18n.enrollments.allClasses },
                                    ...filterOptions.classes.map((item) => ({
                                        value: String(item.id),
                                        label: item.name,
                                    })),
                                ]}
                                onChange={(next) =>
                                    setDraft((current) => ({
                                        ...current,
                                        class_id: next,
                                        section_code: '',
                                    }))
                                }
                            />
                            <DraftOptionalSelect
                                label={i18n.enrollments.sectionName}
                                editing
                                value={draft.section_code}
                                display={draft.section_code}
                                options={[
                                    { value: '', label: i18n.enrollments.allSections },
                                    ...sectionOptions,
                                ]}
                                onChange={(next) =>
                                    setDraft((current) => ({
                                        ...current,
                                        section_code: next,
                                    }))
                                }
                            />
                        </div>
                    </div>
                </FormSection>
            </div>
        </article>
    );
}

export function EnrollmentCreateDialog({
    academicYearId,
    filterOptions,
    initialStudentId = null,
    student = null,
    initialDefaults,
    onClose,
    onCreated,
}: EnrollmentCreateDialogProps) {
    const i18n = t();

    return (
        <Dialog
            open
            onOpenChange={(open) => {
                if (!open) {
                    onClose();
                }
            }}
        >
            <DialogContent
                className="sis-admission-draft-dialog sis-student-view-dialog gap-1.5 p-3 sm:max-w-[min(96vw,72rem)]"
                dir="rtl"
                lang="ar"
                data-sis-align-exempt=""
                aria-describedby="enrollment-create-dialog-desc"
                onOpenAutoFocus={(event) => event.preventDefault()}
                onCloseAutoFocus={(event) => event.preventDefault()}
                onPointerDownOutside={(event) => event.preventDefault()}
                onInteractOutside={(event) => event.preventDefault()}
                onFocusOutside={(event) => {
                    const target = event.target as HTMLElement | null;
                    if (target?.closest('[data-sis-list-select]')) {
                        event.preventDefault();
                    }
                }}
            >
                <DialogHeader className="sr-only">
                    <DialogTitle>{i18n.enrollments.createTitle}</DialogTitle>
                    <DialogDescription id="enrollment-create-dialog-desc">
                        {i18n.enrollments.createTitle}
                    </DialogDescription>
                </DialogHeader>
                <div className="sis-student-view-dialog__body">
                    <EnrollmentCreateForm
                        academicYearId={academicYearId}
                        filterOptions={filterOptions}
                        initialStudentId={initialStudentId}
                        student={student}
                        initialDefaults={initialDefaults}
                        showCancel
                        onCancel={onClose}
                        onCreated={(studentId) => {
                            onCreated?.(studentId);
                        }}
                    />
                </div>
            </DialogContent>
        </Dialog>
    );
}
