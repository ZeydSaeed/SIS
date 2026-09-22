import { useEffect, useMemo, useState, type ReactNode } from 'react';
import { router, usePage } from '@inertiajs/react';
import {
    formatAcademicYearOptionLabel,
    type YearOption,
} from '@/components/sis/ops-year-filter';
import { SisListSelect } from '@/components/sis/sis-list-select';
import { usePageError } from '@/components/sis/page-error-context';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
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
    onSaved?: (enrollment: EnrollmentRecordValues) => void;
    initialEditing?: boolean;
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
    onCreated?: () => void;
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
        specialization_id?: number | null;
        effective_from?: string | null;
    };
    onCancel?: () => void;
    onCreated?: (studentId: number) => void;
    showCancel?: boolean;
};

type PlacementDraft = {
    quad_name: string;
    gender: string;
    class_id: string;
    section_id: string;
    branch_id: string;
    department_id: string;
    specialization_id: string;
    grade_level_id: string;
    stage_name: string;
    academic_year_id: string;
    status: string;
    effective_from: string;
    effective_to: string;
};

type CreateDraft = {
    student_id: string;
    academic_year_id: string;
    class_id: string;
    section_id: string;
    branch_id: string;
    department_id: string;
    specialization_id: string;
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

function parseQuadName(full: string): {
    first_name: string;
    father_name: string | null;
    grandfather_name: string | null;
    great_grandfather_name: string | null;
    last_name: string;
} {
    const parts = full
        .trim()
        .split(/\s+/)
        .map((part) => part.trim())
        .filter((part) => part !== '');

    if (parts.length === 0) {
        return {
            first_name: '',
            father_name: null,
            grandfather_name: null,
            great_grandfather_name: null,
            last_name: '',
        };
    }

    if (parts.length === 1) {
        return {
            first_name: parts[0],
            father_name: null,
            grandfather_name: null,
            great_grandfather_name: null,
            last_name: parts[0],
        };
    }

    if (parts.length === 2) {
        return {
            first_name: parts[0],
            father_name: null,
            grandfather_name: null,
            great_grandfather_name: null,
            last_name: parts[1],
        };
    }

    if (parts.length === 3) {
        return {
            first_name: parts[0],
            father_name: parts[1],
            grandfather_name: null,
            great_grandfather_name: null,
            last_name: parts[2],
        };
    }

    if (parts.length === 4) {
        return {
            first_name: parts[0],
            father_name: parts[1],
            grandfather_name: parts[2],
            great_grandfather_name: null,
            last_name: parts[3],
        };
    }

    return {
        first_name: parts[0],
        father_name: parts[1],
        grandfather_name: parts[2],
        great_grandfather_name: parts[3],
        last_name: parts.slice(4).join(' '),
    };
}

function statusLabel(status: number, i18n: ReturnType<typeof t>): string {
    const labels: Record<number, string> = {
        0: i18n.status.inactive,
        1: i18n.status.active,
        2: i18n.status.cancelled,
        3: i18n.status.transferred,
    };

    return labels[status] ?? String(status);
}

function isFilled(value: string | number | null | undefined): boolean {
    if (value === null || value === undefined) {
        return false;
    }

    const text = String(value).trim();

    return text !== '' && text !== '—';
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

function resolveGradeLevelId(
    enrollment: EnrollmentRecordValues,
    filterOptions: EnrollmentFormFilterOptions,
): string {
    const fromClass = filterOptions.classes.find((item) => item.id === enrollment.class_id);
    if (fromClass?.grade_level_id) {
        return String(fromClass.grade_level_id);
    }

    const levels = filterOptions.grade_levels ?? [];
    const byCode = levels.find((item) => item.code === enrollment.grade_level_code);
    if (byCode) {
        return String(byCode.id);
    }

    const byName = levels.find((item) => item.name === enrollment.grade_level_name);
    if (byName) {
        return String(byName.id);
    }

    return '';
}

function draftFromEnrollment(enrollment: EnrollmentRecordValues, filterOptions: EnrollmentFormFilterOptions): PlacementDraft {
    const name = studentQuadName(enrollment);

    return {
        quad_name: name === '—' ? '' : name,
        gender:
            enrollment.student_gender === 1 || enrollment.student_gender === 2
                ? String(enrollment.student_gender)
                : '',
        class_id: String(enrollment.class_id),
        section_id: String(enrollment.section_id),
        branch_id: enrollment.branch_id ? String(enrollment.branch_id) : '',
        department_id: enrollment.department_id ? String(enrollment.department_id) : '',
        specialization_id: enrollment.specialization_id ? String(enrollment.specialization_id) : '',
        grade_level_id: resolveGradeLevelId(enrollment, filterOptions),
        stage_name: enrollment.stage_name?.trim() ?? '',
        academic_year_id: String(enrollment.academic_year_id),
        status: String(enrollment.status),
        effective_from: isoDate(enrollment.effective_from),
        effective_to: isoDate(enrollment.effective_to),
    };
}

function todayIso(): string {
    const now = new Date();
    const year = now.getFullYear();
    const month = String(now.getMonth() + 1).padStart(2, '0');
    const day = String(now.getDate()).padStart(2, '0');

    return `${year}-${month}-${day}`;
}

export function EnrollmentRecordForm({
    enrollment,
    canUpdate,
    filterOptions,
    onSaved,
    initialEditing = false,
}: EnrollmentRecordFormProps) {
    const i18n = t();
    const { showError, showInertiaErrors } = usePageError();
    const { academicYears } = usePage().props as { academicYears?: YearOption[] };
    const years = academicYears ?? [];
    const [editing, setEditing] = useState(initialEditing && canUpdate);
    const [saving, setSaving] = useState(false);
    const [draft, setDraft] = useState<PlacementDraft>(() =>
        draftFromEnrollment(enrollment, filterOptions),
    );

    useEffect(() => {
        if (editing) {
            return;
        }

        setDraft(draftFromEnrollment(enrollment, filterOptions));
    }, [editing, enrollment, filterOptions]);

    useEffect(() => {
        if (initialEditing && canUpdate) {
            setEditing(true);
        }
    }, [canUpdate, initialEditing]);

    const setField = <K extends keyof PlacementDraft>(key: K, value: PlacementDraft[K]) => {
        setDraft((current) => ({ ...current, [key]: value }));
    };

    const filteredClasses = useMemo(() => {
        if (draft.grade_level_id === '') {
            return filterOptions.classes;
        }

        return filterOptions.classes.filter(
            (item) =>
                item.grade_level_id === undefined
                || String(item.grade_level_id) === draft.grade_level_id,
        );
    }, [draft.grade_level_id, filterOptions.classes]);

    const filteredSections = useMemo(() => {
        if (draft.class_id === '') {
            return filterOptions.sections;
        }

        return filterOptions.sections.filter((section) => String(section.class_id) === draft.class_id);
    }, [draft.class_id, filterOptions.sections]);

    const filteredDepartments = useMemo(() => {
        if (draft.branch_id === '') {
            return filterOptions.departments;
        }

        return filterOptions.departments.filter(
            (department) =>
                department.branch_id === null || String(department.branch_id) === draft.branch_id,
        );
    }, [draft.branch_id, filterOptions.departments]);

    const filteredSpecializations = useMemo(() => {
        if (draft.department_id === '') {
            return filterOptions.specializations;
        }

        return filterOptions.specializations.filter(
            (item) =>
                item.department_id === null || String(item.department_id) === draft.department_id,
        );
    }, [draft.department_id, filterOptions.specializations]);

    const yearOptions = useMemo(() => {
        const options = years.map((year) => ({
            value: String(year.id),
            label: formatAcademicYearOptionLabel(year.name, year.code),
        }));
        const currentId = String(enrollment.academic_year_id);
        if (currentId !== '' && !options.some((option) => option.value === currentId)) {
            options.unshift({
                value: currentId,
                label:
                    formatAcademicYearOptionLabel(
                        enrollment.academic_year_name ?? '',
                        enrollment.academic_year_code ?? '',
                    ) || currentId,
            });
        }

        return options;
    }, [
        enrollment.academic_year_code,
        enrollment.academic_year_id,
        enrollment.academic_year_name,
        years,
    ]);

    const yearDisplay =
        formatAcademicYearOptionLabel(
            enrollment.academic_year_name
                ?? years.find((year) => year.id === enrollment.academic_year_id)?.name
                ?? '',
            enrollment.academic_year_code
                ?? years.find((year) => year.id === enrollment.academic_year_id)?.code
                ?? '',
        ) || displayValue(enrollment.academic_year_id);

    const genderDisplay =
        enrollment.student_gender === 1
            ? i18n.students.male
            : enrollment.student_gender === 2
              ? i18n.students.female
              : '—';
    const classDisplay = displayValue(enrollment.class_name ?? enrollment.class_code);
    const sectionDisplay = displayValue(enrollment.section_name ?? enrollment.section_code);
    const branchDisplay = displayValue(enrollment.branch_name ?? enrollment.branch_code);
    const departmentDisplay = displayValue(enrollment.department_name);
    const specializationDisplay = displayValue(enrollment.specialization_name);
    const gradeLevels = filterOptions.grade_levels ?? [];
    const gradeDisplay = displayValue(enrollment.grade_level_name ?? enrollment.grade_level_code);
    const stageDisplay = displayValue(enrollment.stage_name);
    const name = studentQuadName(enrollment);

    const finishSave = (next: EnrollmentRecordValues) => {
        setEditing(false);
        setSaving(false);
        onSaved?.(next);
    };

    const saveEnrollmentPlacement = (statusValue: number, nextBase: EnrollmentRecordValues) => {
        router.put(
            `/enrollments/${enrollment.id}`,
            {
                class_id: nextBase.class_id,
                section_id: nextBase.section_id,
                specialization_id: nextBase.specialization_id,
                branch_id: nextBase.branch_id,
                department_id: nextBase.department_id,
                effective_from: nextBase.effective_from || undefined,
                academic_year_id: nextBase.academic_year_id || undefined,
                stage_name: nextBase.stage_name,
                ...(nextBase.student_gender === 1 || nextBase.student_gender === 2
                    ? { gender: nextBase.student_gender }
                    : {}),
                ...(nextBase.effective_to === null || nextBase.effective_to === ''
                    ? { clear_effective_to: true }
                    : { effective_to: nextBase.effective_to }),
            },
            {
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => finishSave(nextBase),
                onError: (errors) => {
                    setSaving(false);
                    showInertiaErrors(errors, i18n.errors.placementFailed);
                },
            },
        );
    };

    const savePlacementAndMeta = (statusValue: number) => {
        const resolvedClassId =
            draft.class_id !== ''
                ? draft.class_id
                : enrollment.class_id > 0
                  ? String(enrollment.class_id)
                  : '';
        let resolvedSectionId =
            draft.section_id !== ''
                ? draft.section_id
                : enrollment.section_id > 0
                  ? String(enrollment.section_id)
                  : '';

        if (resolvedClassId !== '' && resolvedSectionId === '') {
            const firstSection = filterOptions.sections.find(
                (section) => String(section.class_id) === resolvedClassId,
            );
            resolvedSectionId = firstSection ? String(firstSection.id) : '';
        }

        if (resolvedClassId === '' || resolvedSectionId === '') {
            setSaving(false);
            showError(i18n.errors.missingClassSection);

            return;
        }

        const parsedName = parseQuadName(draft.quad_name);
        const hasParsedName = parsedName.first_name !== '' && parsedName.last_name !== '';
        const nameParts = hasParsedName
            ? parsedName
            : {
                  first_name: enrollment.student_first_name?.trim() || '',
                  father_name: enrollment.student_father_name,
                  grandfather_name: enrollment.student_grandfather_name,
                  great_grandfather_name: enrollment.student_great_grandfather_name,
                  last_name: enrollment.student_last_name?.trim() || '',
              };

        const birthDate = isoDate(enrollment.student_birth_date);
        const selectedGrade = gradeLevels.find((item) => String(item.id) === draft.grade_level_id);
        const departmentName =
            filterOptions.departments.find((item) => String(item.id) === draft.department_id)
                ?.name ?? null;
        const specializationName =
            filterOptions.specializations.find((item) => String(item.id) === draft.specialization_id)
                ?.name ?? null;
        const sectionName =
            filterOptions.sections.find((item) => String(item.id) === resolvedSectionId)?.name
            ?? null;
        const stageName = draft.stage_name.trim() === '' ? null : draft.stage_name.trim();

        const nextBase: EnrollmentRecordValues = {
            ...enrollment,
            academic_year_id: Number(draft.academic_year_id) || enrollment.academic_year_id,
            class_id: Number(resolvedClassId),
            section_id: Number(resolvedSectionId),
            branch_id: draft.branch_id === '' ? null : Number(draft.branch_id),
            department_id: draft.department_id === '' ? null : Number(draft.department_id),
            specialization_id:
                draft.specialization_id === '' ? null : Number(draft.specialization_id),
            student_first_name: nameParts.first_name || enrollment.student_first_name,
            student_father_name: nameParts.father_name,
            student_grandfather_name: nameParts.grandfather_name,
            student_great_grandfather_name: nameParts.great_grandfather_name,
            student_last_name: nameParts.last_name || enrollment.student_last_name,
            student_full_name: [
                nameParts.first_name || enrollment.student_first_name,
                nameParts.father_name,
                nameParts.grandfather_name,
                nameParts.great_grandfather_name,
                nameParts.last_name || enrollment.student_last_name,
            ]
                .filter((part): part is string => Boolean(part && part.trim()))
                .join(' '),
            student_gender:
                draft.gender === '1' || draft.gender === '2'
                    ? Number(draft.gender)
                    : enrollment.student_gender,
            status: statusValue,
            effective_from: draft.effective_from || enrollment.effective_from,
            effective_to: draft.effective_to === '' ? null : draft.effective_to,
            class_name:
                filterOptions.classes.find((item) => String(item.id) === resolvedClassId)?.name
                ?? enrollment.class_name,
            section_name: sectionName,
            branch_name:
                filterOptions.branches.find((item) => String(item.id) === draft.branch_id)?.name
                ?? null,
            department_name: departmentName,
            specialization_name: specializationName,
            grade_level_code: selectedGrade?.code ?? enrollment.grade_level_code,
            grade_level_name: selectedGrade?.name ?? enrollment.grade_level_name,
            stage_name: stageName,
            academic_year_name:
                years.find((year) => String(year.id) === draft.academic_year_id)?.name
                ?? enrollment.academic_year_name,
            academic_year_code:
                years.find((year) => String(year.id) === draft.academic_year_id)?.code
                ?? enrollment.academic_year_code,
        };

        const continueWithPlacement = () => saveEnrollmentPlacement(statusValue, nextBase);

        if (
            birthDate === ''
            || nameParts.first_name === ''
            || nameParts.last_name === ''
        ) {
            continueWithPlacement();

            return;
        }

        router.put(
            `/students/${enrollment.student_id}`,
            {
                first_name: nameParts.first_name,
                last_name: nameParts.last_name,
                father_name: nameParts.father_name,
                grandfather_name: nameParts.grandfather_name,
                great_grandfather_name: nameParts.great_grandfather_name,
                birth_date: birthDate,
                gender:
                    draft.gender === '1' || draft.gender === '2'
                        ? Number(draft.gender)
                        : enrollment.student_gender,
                branch_id: draft.branch_id === '' ? null : Number(draft.branch_id),
                department_name: departmentName,
                specialization_name: specializationName,
                stage_name: stageName,
                section_name: sectionName,
            },
            {
                preserveScroll: true,
                preserveState: true,
                only: ['enrollments', 'filters', 'filterOptions', 'authorization'],
                onSuccess: continueWithPlacement,
                onError: (errors) => {
                    // Placement/year/status must still persist even if student name update fails.
                    showInertiaErrors(errors, i18n.errors.saveFailed);
                    continueWithPlacement();
                },
            },
        );
    };

    const save = () => {
        if (saving || !canUpdate) {
            return;
        }

        if (draft.class_id === '' || draft.section_id === '') {
            showError(i18n.errors.missingClassSection);
            return;
        }

        setSaving(true);
        const nextStatus = Number(draft.status);
        const statusChanged = nextStatus !== enrollment.status;

        if (statusChanged) {
            router.post(
                '/enrollments/bulk-status',
                {
                    enrollment_ids: [enrollment.id],
                    status: nextStatus,
                    effective_to:
                        nextStatus === 1
                            ? undefined
                            : draft.effective_to || draft.effective_from || todayIso(),
                },
                {
                    preserveScroll: true,
                    preserveState: true,
                    only: ['enrollments', 'filters', 'filterOptions', 'authorization'],
                    onSuccess: () => savePlacementAndMeta(nextStatus),
                    onError: (errors) => {
                        setSaving(false);
                        showInertiaErrors(errors, i18n.errors.statusFailed);
                    },
                },
            );

            return;
        }

        savePlacementAndMeta(nextStatus);
    };

    return (
        <article className="sis-admission-draft-form sis-student-record-form" dir="rtl" lang="ar">
            <header className="sis-student-record-form__head">
                <h3 className="sis-student-record-form__title">
                    {editing ? draft.quad_name.trim() || name : name}
                </h3>
                <div className="sis-student-record-form__head-meta">
                    {canUpdate ? (
                        editing ? (
                            <StatusLikeButton tone="edit" disabled={saving} onClick={save}>
                                {saving ? i18n.common.saving : i18n.common.save}
                            </StatusLikeButton>
                        ) : (
                            <StatusLikeButton tone="edit" onClick={() => setEditing(true)}>
                                {i18n.common.edit}
                            </StatusLikeButton>
                        )
                    ) : null}
                </div>
            </header>

            <div className="sis-student-record-form__sections">
                <FormSection id={`enr-identity-${enrollment.id}`} title={i18n.enrollments.student}>
                    <div className="sis-admission-draft-rows">
                        <div className="sis-admission-draft-row">
                            <DraftField
                                label={i18n.enrollments.quadName}
                                className="sis-enrollment-name-field"
                                editing={editing}
                                value={draft.quad_name}
                                display={name}
                                onChange={(value) => setField('quad_name', value)}
                            />
                            <DraftField
                                label={i18n.enrollments.studentCode}
                                editing={false}
                                value={displayValue(enrollment.student_code)}
                                display={displayValue(enrollment.student_code)}
                                dir="ltr"
                                onChange={() => undefined}
                            />
                            <DraftOptionalSelect
                                label={i18n.enrollments.gender}
                                editing={editing}
                                value={draft.gender}
                                display={genderDisplay}
                                options={[
                                    { value: '', label: i18n.enrollments.gender },
                                    { value: '1', label: i18n.students.male },
                                    { value: '2', label: i18n.students.female },
                                ]}
                                onChange={(next) => setField('gender', next)}
                            />
                        </div>
                        <div className="sis-admission-draft-row">
                            <DraftOptionalSelect
                                label={i18n.enrollments.academicYear}
                                editing={editing}
                                value={draft.academic_year_id}
                                display={yearDisplay}
                                options={[
                                    { value: '', label: i18n.enrollments.academicYear },
                                    ...yearOptions,
                                ]}
                                onChange={(next) => setField('academic_year_id', next)}
                            />
                            <DraftField
                                label={i18n.enrollments.studentId}
                                editing={false}
                                value={String(enrollment.student_id)}
                                display={String(enrollment.student_id)}
                                dir="ltr"
                                onChange={() => undefined}
                            />
                        </div>
                    </div>
                </FormSection>

                <div className="sis-student-record-form__stack">
                    <FormSection
                        id={`enr-status-${enrollment.id}`}
                        title={i18n.enrollments.statusTabsTitle}
                    >
                        <div className="sis-admission-draft-rows">
                            <div className="sis-admission-draft-row">
                                <DraftOptionalSelect
                                    label={i18n.common.status}
                                    editing={editing}
                                    value={draft.status}
                                    display={statusLabel(enrollment.status, i18n)}
                                    options={[
                                        { value: '1', label: i18n.status.active },
                                        { value: '0', label: i18n.status.inactive },
                                        { value: '2', label: i18n.status.cancelled },
                                        { value: '3', label: i18n.status.transferred },
                                    ]}
                                    onChange={(next) => setField('status', next)}
                                />
                                <DraftField
                                    label={i18n.enrollments.effectiveFrom}
                                    editing={editing}
                                    type="date"
                                    value={draft.effective_from}
                                    display={formatCivilDate(enrollment.effective_from)}
                                    dir="ltr"
                                    onChange={(value) => setField('effective_from', value)}
                                />
                                <DraftField
                                    label={i18n.enrollments.effectiveTo}
                                    editing={editing}
                                    type="date"
                                    value={draft.effective_to}
                                    display={formatCivilDate(enrollment.effective_to)}
                                    dir="ltr"
                                    onChange={(value) => setField('effective_to', value)}
                                />
                            </div>
                        </div>
                    </FormSection>

                    <FormSection
                        id={`enr-placement-${enrollment.id}`}
                        title={i18n.enrollments.editPlacement}
                    >
                        <div className="sis-admission-draft-rows">
                            <div className="sis-admission-draft-row">
                                <DraftOptionalSelect
                                    label={i18n.enrollments.className}
                                    editing={editing}
                                    value={draft.class_id}
                                    display={classDisplay}
                                    options={[
                                        { value: '', label: i18n.enrollments.allClasses },
                                        ...filteredClasses.map((item) => ({
                                            value: String(item.id),
                                            label: item.name,
                                        })),
                                    ]}
                                    onChange={(next) => {
                                        const selected = filterOptions.classes.find(
                                            (item) => String(item.id) === next,
                                        );
                                        setDraft((current) => ({
                                            ...current,
                                            class_id: next,
                                            section_id: '',
                                            grade_level_id: selected?.grade_level_id
                                                ? String(selected.grade_level_id)
                                                : current.grade_level_id,
                                        }));
                                    }}
                                />
                                <DraftOptionalSelect
                                    label={i18n.enrollments.sectionName}
                                    editing={editing}
                                    value={draft.section_id}
                                    display={sectionDisplay}
                                    options={[
                                        { value: '', label: i18n.enrollments.allSections },
                                        ...filteredSections.map((item) => ({
                                            value: String(item.id),
                                            label: item.name,
                                        })),
                                    ]}
                                    onChange={(next) => setField('section_id', next)}
                                />
                                <DraftOptionalSelect
                                    label={i18n.enrollments.branchName}
                                    editing={editing}
                                    value={draft.branch_id}
                                    display={branchDisplay}
                                    options={[
                                        { value: '', label: i18n.enrollments.allBranches },
                                        ...filterOptions.branches.map((item) => ({
                                            value: String(item.id),
                                            label: item.name,
                                        })),
                                    ]}
                                    onChange={(next) => {
                                        setDraft((current) => ({
                                            ...current,
                                            branch_id: next,
                                            department_id: '',
                                            specialization_id: '',
                                        }));
                                    }}
                                />
                                <DraftOptionalSelect
                                    label={i18n.enrollments.departmentName}
                                    editing={editing}
                                    value={draft.department_id}
                                    display={departmentDisplay}
                                    options={[
                                        { value: '', label: i18n.enrollments.allDepartments },
                                        ...filteredDepartments.map((item) => ({
                                            value: String(item.id),
                                            label: item.name,
                                        })),
                                    ]}
                                    onChange={(next) => {
                                        setDraft((current) => ({
                                            ...current,
                                            department_id: next,
                                            specialization_id: '',
                                        }));
                                    }}
                                />
                            </div>
                            <div className="sis-admission-draft-row">
                                <DraftOptionalSelect
                                    label={i18n.enrollments.specialization}
                                    editing={editing}
                                    value={draft.specialization_id}
                                    display={specializationDisplay}
                                    options={[
                                        { value: '', label: i18n.enrollments.allSpecializations },
                                        ...filteredSpecializations.map((item) => ({
                                            value: String(item.id),
                                            label: item.name,
                                        })),
                                    ]}
                                    onChange={(next) => setField('specialization_id', next)}
                                />
                                <DraftOptionalSelect
                                    label={i18n.enrollments.gradeLevel}
                                    editing={editing}
                                    value={draft.grade_level_id}
                                    display={gradeDisplay}
                                    options={[
                                        { value: '', label: i18n.enrollments.gradeLevel },
                                        ...gradeLevels.map((item) => ({
                                            value: String(item.id),
                                            label: item.name,
                                        })),
                                    ]}
                                    onChange={(next) => {
                                        setDraft((current) => {
                                            const classStillValid =
                                                next === ''
                                                || filterOptions.classes.some(
                                                    (item) =>
                                                        String(item.id) === current.class_id
                                                        && (item.grade_level_id === undefined
                                                            || String(item.grade_level_id) === next),
                                                );

                                            return {
                                                ...current,
                                                grade_level_id: next,
                                                class_id: classStillValid ? current.class_id : '',
                                                section_id: classStillValid
                                                    ? current.section_id
                                                    : '',
                                            };
                                        });
                                    }}
                                />
                                <DraftField
                                    label={i18n.enrollments.stageName}
                                    editing={editing}
                                    value={draft.stage_name}
                                    display={stageDisplay}
                                    onChange={(value) => setField('stage_name', value)}
                                />
                            </div>
                        </div>
                    </FormSection>
                </div>
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
    const title =
        count > 1
            ? `${i18n.enrollments.viewManyTitle} (${count})`
            : initialEditing
              ? i18n.enrollments.editTitle
              : i18n.enrollments.viewTitle;

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
                className={`sis-admission-draft-dialog sis-student-view-dialog gap-1.5 p-3 sm:max-w-[min(96vw,92rem)]${count > 1 ? ' sis-student-view-dialog--many' : ''}`}
                dir="rtl"
                lang="ar"
                data-sis-align-exempt=""
                aria-describedby="enrollment-view-dialog-desc"
                onOpenAutoFocus={(event) => event.preventDefault()}
                onCloseAutoFocus={(event) => event.preventDefault()}
                onPointerDownOutside={(event) => {
                    const target = event.target as HTMLElement | null;
                    if (target?.closest('[data-sis-list-select]')) {
                        event.preventDefault();
                    }
                }}
                onFocusOutside={(event) => {
                    const target = event.target as HTMLElement | null;
                    if (target?.closest('[data-sis-list-select]')) {
                        event.preventDefault();
                    }
                }}
            >
                <DialogHeader>
                    <DialogTitle>{title}</DialogTitle>
                    <DialogDescription id="enrollment-view-dialog-desc" className="sr-only">
                        {i18n.enrollments.viewDialogDesc}
                    </DialogDescription>
                </DialogHeader>
                <div className="sis-student-view-dialog__body">
                    {enrollments.map((enrollment) => (
                        <EnrollmentRecordForm
                            key={enrollment.id}
                            enrollment={enrollment}
                            canUpdate={canUpdate}
                            filterOptions={filterOptions}
                            onSaved={onSaved}
                            initialEditing={initialEditing || canUpdate}
                        />
                    ))}
                </div>
                <div className="sis-admission-draft-actions">
                    <StatusLikeButton tone="close" onClick={onClose}>
                        {i18n.window.close}
                    </StatusLikeButton>
                </div>
            </DialogContent>
        </Dialog>
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
        section_id: '',
        branch_id: initialDefaults?.branch_id ? String(initialDefaults.branch_id) : '',
        department_id: initialDefaults?.department_id ? String(initialDefaults.department_id) : '',
        specialization_id: initialDefaults?.specialization_id
            ? String(initialDefaults.specialization_id)
            : '',
        effective_from: initialDefaults?.effective_from?.trim() || todayIso(),
    }));

    const filteredDepartments = useMemo(() => {
        if (draft.branch_id === '') {
            return filterOptions.departments;
        }

        return filterOptions.departments.filter(
            (department) =>
                department.branch_id === null || String(department.branch_id) === draft.branch_id,
        );
    }, [draft.branch_id, filterOptions.departments]);

    const filteredSections = useMemo(() => {
        if (draft.class_id === '') {
            return filterOptions.sections;
        }

        return filterOptions.sections.filter((section) => String(section.class_id) === draft.class_id);
    }, [draft.class_id, filterOptions.sections]);

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
            || draft.section_id === ''
            || draft.effective_from === ''
        ) {
            showError(i18n.errors.requiredFields);
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
                class_id: Number(draft.class_id),
                section_id: Number(draft.section_id),
                effective_from: draft.effective_from,
                ...(draft.specialization_id === ''
                    ? {}
                    : { specialization_id: Number(draft.specialization_id) }),
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
                                label={i18n.students.code}
                                editing={false}
                                value={student?.student_code ?? ''}
                                display={student?.student_code ?? '—'}
                                dir="ltr"
                                onChange={() => undefined}
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
                                        section_id: '',
                                    }))
                                }
                            />
                            <DraftOptionalSelect
                                label={i18n.enrollments.sectionName}
                                editing
                                value={draft.section_id}
                                display={draft.section_id}
                                options={[
                                    { value: '', label: i18n.enrollments.allSections },
                                    ...filteredSections.map((item) => ({
                                        value: String(item.id),
                                        label: item.name,
                                    })),
                                ]}
                                onChange={(next) =>
                                    setDraft((current) => ({
                                        ...current,
                                        section_id: next,
                                    }))
                                }
                            />
                            <DraftOptionalSelect
                                label={i18n.enrollments.specialization}
                                editing
                                value={draft.specialization_id}
                                display={draft.specialization_id}
                                options={[
                                    {
                                        value: '',
                                        label: i18n.enrollments.allSpecializations,
                                    },
                                    ...filterOptions.specializations.map((item) => ({
                                        value: String(item.id),
                                        label: item.name,
                                    })),
                                ]}
                                onChange={(next) =>
                                    setDraft((current) => ({
                                        ...current,
                                        specialization_id: next,
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
                            onCreated?.();
                            onClose();
                            void studentId;
                        }}
                    />
                </div>
            </DialogContent>
        </Dialog>
    );
}
