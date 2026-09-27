import { router, usePage } from '@inertiajs/react';
import { useEffect, useMemo, useRef, useState, type FormEvent, type ReactNode } from 'react';
import AppLogo from '@/components/app-logo';
import type { EnrollmentFormFilterOptions } from '@/components/enrollments/enrollment-record-form';
import { usePageError } from '@/components/sis/page-error-context';
import { formatAcademicYearOptionLabel } from '@/components/sis/ops-year-filter';
import { SisListSelect } from '@/components/sis/sis-list-select';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogTitle,
} from '@/components/ui/dialog';
import { WindowControls } from '@/components/window-controls';
import { useSmoothDialogDrag } from '@/hooks/use-smooth-dialog-drag';
import {
    admissionBranchSelectOptions,
    admissionClassSelectOptions,
    admissionDepartmentSelectOptions,
    admissionSectionSelectOptions,
    draftPlacementFromStudents,
    type EnrollmentDialogDraft,
    type EnrollmentDialogFieldErrors,
    type EnrollmentDialogFieldKey,
    type EnrollmentDialogIssue,
    validateEnrollmentDialog,
} from '@/lib/enrollment-dialog-resolve';
import { t } from '@/i18n';

export type StudentEnrollmentCandidate = {
    id: number;
    full_name: string;
    student_code?: string | null;
    is_enrolled?: boolean;
    branch_id?: number | null;
    branch_name?: string | null;
    department_name?: string | null;
    admitted_class_name?: string | null;
    section_name?: string | null;
};

type YearOption = {
    id: number;
    name: string;
    code: string;
    is_current: boolean;
};

type Props = {
    open: boolean;
    students: StudentEnrollmentCandidate[];
    academicYearId: number | null;
    filterOptions: EnrollmentFormFilterOptions;
    onOpenChange: (open: boolean) => void;
    onEnrolled?: () => void;
};

function todayIsoDate(): string {
    const now = new Date();
    const year = now.getFullYear();
    const month = String(now.getMonth() + 1).padStart(2, '0');
    const day = String(now.getDate()).padStart(2, '0');

    return `${year}-${month}-${day}`;
}

function filledClass(value: string): string {
    return value.trim() !== '' ? ' sis-admission-draft-field--filled' : '';
}

function invalidClass(invalid: boolean): string {
    return invalid ? ' sis-student-enrollment-sheet__control--invalid' : '';
}

function SheetField({
    label,
    name,
    required = false,
    error,
    hint,
    children,
}: {
    label: string;
    name: string;
    required?: boolean;
    error?: string;
    hint?: string;
    children: ReactNode;
}) {
    return (
        <label className="sis-admission-sheet__field" htmlFor={name}>
            <span className="sis-admission-sheet__label">
                {label}
                {required ? (
                    <span className="sis-student-enrollment-sheet__required" aria-hidden="true">
                        *
                    </span>
                ) : null}
            </span>
            {children}
            {error ? (
                <span id={`${name}-error`} className="sis-admission-sheet__error" role="alert">
                    {error}
                </span>
            ) : hint ? (
                <span className="sis-student-enrollment-sheet__hint">{hint}</span>
            ) : null}
        </label>
    );
}

function SheetSection({
    title,
    children,
}: {
    title: string;
    children: ReactNode;
}) {
    return (
        <section className="sis-admission-sheet__section">
            <h3 className="sis-admission-sheet__banner sis-admission-sheet__banner--accent">{title}</h3>
            <div className="sis-admission-sheet__body">{children}</div>
        </section>
    );
}

function SheetSelect({
    id,
    name,
    value,
    options,
    onChange,
    ariaLabel,
    allowEmpty = false,
    dir = 'rtl',
    disabled = false,
    invalid = false,
    required = false,
}: {
    id?: string;
    name?: string;
    value: string;
    options: Array<{ value: string; label: string }>;
    onChange: (value: string) => void;
    ariaLabel: string;
    allowEmpty?: boolean;
    dir?: 'rtl' | 'ltr';
    disabled?: boolean;
    invalid?: boolean;
    required?: boolean;
}) {
    const filled = filledClass(value);

    return (
        <SisListSelect
            id={id}
            name={name}
            value={value}
            options={options}
            onChange={onChange}
            ariaLabel={ariaLabel}
            dir={dir}
            includeBlank={allowEmpty}
            disabled={disabled}
            invalid={invalid}
            required={required}
            className={`sis-admission-sheet-list-select${filled}`}
            triggerClassName={`sis-admission-sheet__control sis-admission-draft-select${filled}${invalidClass(invalid)}`}
            menuClassName="sis-admission-sheet-list-select__menu"
        />
    );
}

export function StudentEnrollmentDialog({
    open,
    students,
    academicYearId,
    filterOptions,
    onOpenChange,
    onEnrolled,
}: Props) {
    const i18n = t();
    const { showError, showWarning, showSuccess, showInertiaErrors } = usePageError();
    const { academicYears } = usePage().props as { academicYears?: YearOption[] };
    const years = academicYears ?? [];
    const formRef = useRef<HTMLFormElement>(null);
    const [saving, setSaving] = useState(false);
    const [fieldErrors, setFieldErrors] = useState<EnrollmentDialogFieldErrors>({});
    const [draft, setDraft] = useState<EnrollmentDialogDraft>(() => ({
        academic_year_id: academicYearId && academicYearId > 0 ? String(academicYearId) : '',
        branch_name: '',
        department_name: '',
        class_key: '',
        section_code: '',
        effective_from: todayIsoDate(),
    }));
    const { contentRef, heroDragProps, bringToFront, resizeHandles } = useSmoothDialogDrag(open, {
        resizable: true,
        minSize: { width: 640, height: 360 },
    });

    useEffect(() => {
        if (!open) {
            setSaving(false);
            setFieldErrors({});
            return;
        }

        const placement = draftPlacementFromStudents(students, filterOptions.branches ?? []);
        setDraft({
            academic_year_id: academicYearId && academicYearId > 0 ? String(academicYearId) : '',
            branch_name: placement.branch_name,
            department_name: placement.department_name,
            class_key: placement.class_key,
            section_code: placement.section_code,
            effective_from: todayIsoDate(),
        });
        setFieldErrors({});
    }, [open, academicYearId, students, filterOptions.branches]);

    const yearLabel = useMemo(() => {
        const year = years.find((item) => String(item.id) === draft.academic_year_id);
        if (year === undefined) {
            return draft.academic_year_id || '—';
        }

        return formatAcademicYearOptionLabel(year.name, year.code);
    }, [draft.academic_year_id, years]);

    // SSOT: same catalogs as admission new-request form.
    const branchOptions = useMemo(() => admissionBranchSelectOptions(), []);
    const departmentOptions = useMemo(
        () => admissionDepartmentSelectOptions(draft.branch_name),
        [draft.branch_name],
    );
    const classOptions = useMemo(() => admissionClassSelectOptions(), []);
    const sectionOptions = useMemo(() => admissionSectionSelectOptions(), []);

    const presentIssue = (issue: EnrollmentDialogIssue) => {
        if (issue.fieldErrors) {
            setFieldErrors(issue.fieldErrors);
        }

        const title = i18n.students[issue.titleKey];
        const payload = {
            title,
            description: issue.descriptionKey,
            details: issue.details,
        };

        if (issue.tone === 'warning') {
            showWarning(payload);
            return;
        }
        showError(payload);
    };

    const clearFieldErrors = (...keys: EnrollmentDialogFieldKey[]) => {
        setFieldErrors((current) => {
            let changed = false;
            const next = { ...current };
            for (const key of keys) {
                if (next[key] !== undefined) {
                    delete next[key];
                    changed = true;
                }
            }

            return changed ? next : current;
        });
    };

    const patchDraft = (patch: Partial<EnrollmentDialogDraft>) => {
        setDraft((current) => {
            const next = { ...current, ...patch };

            if ('branch_name' in patch && patch.branch_name !== current.branch_name) {
                next.department_name = '';
            }

            if ('class_key' in patch && patch.class_key !== current.class_key) {
                next.section_code = '';
            }

            return next;
        });

        const keysToClear: EnrollmentDialogFieldKey[] = [];
        if ('branch_name' in patch) {
            keysToClear.push('branch_name', 'department_name');
        } else if ('department_name' in patch) {
            keysToClear.push('department_name');
        }
        if ('class_key' in patch) {
            keysToClear.push('class_key', 'section_code');
        } else if ('section_code' in patch) {
            keysToClear.push('section_code');
        }
        if ('effective_from' in patch) {
            keysToClear.push('effective_from');
        }
        if (keysToClear.length > 0) {
            clearFieldErrors(...keysToClear);
        }
    };

    const onFormSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        const form = formRef.current;
        if (form !== null && !form.checkValidity()) {
            // Native browser bubble only (e.g. "Please fill out this field.").
            form.reportValidity();
            return;
        }
        void submit();
    };

    const submit = async () => {
        if (saving) {
            return;
        }

        const validation = validateEnrollmentDialog({
            students,
            draft,
            filterOptions,
            messages: {
                emptyStudents: i18n.students.enrollEmptyStudents,
                emptyYear: i18n.students.enrollEmptyYear,
                emptyBranch: i18n.students.enrollEmptyBranch,
                emptyDepartment: i18n.students.enrollEmptyDepartment,
                emptyClass: i18n.students.enrollEmptyClass,
                emptySection: i18n.students.enrollEmptySection,
                emptyEffectiveFrom: i18n.students.enrollEmptyEffectiveFrom,
                emptyFields: i18n.students.enrollEmptyFields,
                alreadyRegistered: i18n.students.enrollAlreadyRegistered,
                alreadyRegisteredNamed: i18n.students.enrollAlreadyRegisteredNamed,
                classNotFound: i18n.students.enrollClassNotFound,
                sectionNotFound: i18n.students.enrollSectionNotFound,
                branchRequiredWithDept: i18n.students.enrollBranchRequiredWithDept,
                branchMissingInYear: i18n.students.enrollBranchMissingInYear,
                departmentMissingInBranch: i18n.students.enrollDepartmentMissingInBranch,
                noClassesInYear: i18n.students.enrollNoClassesInYear,
                noSectionsForClass: i18n.students.enrollNoSectionsForClass,
                guideFillRequired: i18n.students.enrollDialogHint,
                classLabel: i18n.enrollments.className,
                sectionLabel: i18n.enrollments.sectionName,
            },
        });

        if (!validation.ok) {
            if (validation.issue.fieldErrors !== undefined) {
                setFieldErrors(validation.issue.fieldErrors);
            }

            // Empty / field-anchored issues → native HTML tip only (no MessageDialog / no z-index).
            const hasFieldAnchors =
                validation.issue.fieldErrors !== undefined
                && Object.keys(validation.issue.fieldErrors).length > 0;
            if (hasFieldAnchors || validation.issue.titleKey === 'enrollWarningTitle') {
                formRef.current?.reportValidity();
                return;
            }

            presentIssue(validation.issue);
            return;
        }

        setFieldErrors({});
        const { payload } = validation;
        setSaving(true);
        let enrolledCount = 0;
        let failedAfterPartial = false;

        try {
            for (const student of payload.eligibleStudents) {
                const idempotencyKey =
                    typeof crypto !== 'undefined' && 'randomUUID' in crypto
                        ? crypto.randomUUID()
                        : `enroll-student-${student.id}-${Date.now()}`;

                try {
                    await new Promise<void>((resolve, reject) => {
                        router.post(
                            '/enrollments',
                            {
                                student_id: student.id,
                                academic_year_id: payload.academicYearId,
                                class_id: payload.classId,
                                section_id: payload.sectionId,
                                effective_from: payload.effectiveFrom,
                                ...(payload.branchId === null
                                    ? {}
                                    : { branch_id: payload.branchId }),
                                ...(payload.departmentId === null
                                    ? {}
                                    : { department_id: payload.departmentId }),
                            },
                            {
                                headers: { 'X-Idempotency-Key': idempotencyKey },
                                preserveScroll: true,
                                preserveState: true,
                                onSuccess: (page) => {
                                    const flash = (
                                        page.props as { flash?: { error?: string | null } }
                                    ).flash?.error;
                                    if (typeof flash === 'string' && flash.trim() !== '') {
                                        const code = flash.trim();
                                        window.setTimeout(() => {
                                            if (code === 'enrollment.already_enrolled') {
                                                showError({
                                                    title: i18n.students
                                                        .enrollAlreadyRegisteredTitle,
                                                    description:
                                                        i18n.students.enrollAlreadyRegisteredNamed.replace(
                                                            '{name}',
                                                            student.full_name,
                                                        ),
                                                });
                                            } else {
                                                showError({
                                                    title: i18n.students.enrollDialogTitle,
                                                    description: i18n.students.enrollServerError,
                                                    details: [code],
                                                });
                                            }
                                        }, 0);
                                        reject(flash);
                                        return;
                                    }

                                    enrolledCount += 1;
                                    resolve();
                                },
                                onError: (errors) => {
                                    showInertiaErrors(errors, i18n.errors.createFailed);
                                    reject(errors);
                                },
                            },
                        );
                    });
                } catch {
                    if (enrolledCount > 0) {
                        failedAfterPartial = true;
                    }
                    break;
                }
            }

            if (enrolledCount > 0 && !failedAfterPartial) {
                showSuccess({
                    title: i18n.students.enrollDialogTitle,
                    description: i18n.students.enrollSuccessNamed.replace(
                        '{count}',
                        String(enrolledCount),
                    ),
                });
                onOpenChange(false);
                onEnrolled?.();
            } else if (failedAfterPartial) {
                showWarning({
                    title: i18n.students.enrollWarningTitle,
                    description: i18n.students.enrollPartialFailed,
                    details: [
                        i18n.students.enrollSuccessNamed.replace(
                            '{count}',
                            String(enrolledCount),
                        ),
                    ],
                });
            }
        } finally {
            setSaving(false);
        }
    };

    return (
        <Dialog
            open={open}
            onOpenChange={(next) => {
                if (!saving) {
                    onOpenChange(next);
                }
            }}
            modal={false}
        >
            <DialogContent
                ref={contentRef}
                className="sis-admission-draft-dialog sis-admission-sheet-dialog sis-student-enrollment-sheet"
                overlayClassName="sis-admission-sheet-dialog__overlay"
                dir="rtl"
                lang="ar"
                onOpenAutoFocus={(event) => event.preventDefault()}
                onCloseAutoFocus={(event) => event.preventDefault()}
                onInteractOutside={(event) => event.preventDefault()}
                onPointerDownOutside={(event) => event.preventDefault()}
                onPointerDownCapture={bringToFront}
            >
                <DialogTitle className="sr-only">{i18n.students.enrollDialogTitle}</DialogTitle>
                {resizeHandles}

                <form
                    ref={formRef}
                    className="sis-admission-sheet sis-student-enrollment-sheet__form"
                    onSubmit={onFormSubmit}
                    noValidate={false}
                >
                    <header className="sis-admission-sheet__hero" {...heroDragProps}>
                        <WindowControls
                            className="sis-admission-sheet__window-controls"
                            label={i18n.window.controls}
                            minimizeLabel={i18n.window.minimize}
                            maximizeLabel={i18n.window.maximize}
                            restoreLabel={i18n.window.restore}
                            closeLabel={i18n.window.close}
                            minimizable={false}
                            maximizable={false}
                            onClose={() => onOpenChange(false)}
                        />
                        <div className="sis-admission-sheet__hero-copy">
                            <p className="sis-admission-sheet__hero-title">
                                {i18n.students.enrollDialogTitle}
                            </p>
                        </div>
                        <div className="sis-admission-sheet__hero-logo">
                            <AppLogo tone="on-dark" className="sis-admission-sheet__logo" />
                        </div>
                    </header>

                    <SheetSection title={i18n.students.enrollDialogStudents}>
                        <div className="sis-admission-sheet__row">
                            <ul className="sis-student-enrollment-sheet__student-list">
                                {students.map((student) => (
                                    <li key={student.id}>
                                        <span>{student.full_name}</span>
                                        {student.is_enrolled === true ? (
                                            <span className="sis-student-enrollment-sheet__badge">
                                                {i18n.students.enrollmentYes}
                                            </span>
                                        ) : null}
                                    </li>
                                ))}
                            </ul>
                        </div>
                    </SheetSection>

                    <SheetSection title={i18n.students.enrollDialogRequired}>
                        <div className="sis-admission-sheet__row sis-admission-sheet__row--2">
                            <SheetField
                                label={i18n.enrollments.academicYear}
                                name="academic_year_id"
                                required
                            >
                                <div className="sis-student-enrollment-sheet__year-wrap">
                                    <input
                                        id="academic_year_id"
                                        name="academic_year_id"
                                        type="text"
                                        required
                                        value={draft.academic_year_id}
                                        readOnly
                                        tabIndex={-1}
                                        aria-label={i18n.enrollments.academicYear}
                                        className="sis-student-enrollment-sheet__native-year"
                                    />
                                    <div
                                        className="sis-admission-sheet__control sis-admission-draft-field--filled"
                                        aria-hidden="true"
                                        dir="ltr"
                                    >
                                        {yearLabel}
                                    </div>
                                </div>
                            </SheetField>
                            <SheetField
                                label={i18n.enrollments.effectiveFrom}
                                name="effective_from"
                                required
                            >
                                <input
                                    id="effective_from"
                                    name="effective_from"
                                    type="date"
                                    dir="ltr"
                                    required
                                    className={`sis-admission-sheet__control${filledClass(draft.effective_from)}`}
                                    value={draft.effective_from}
                                    aria-label={i18n.enrollments.effectiveFrom}
                                    onChange={(event) =>
                                        patchDraft({ effective_from: event.target.value })
                                    }
                                />
                            </SheetField>
                        </div>
                        <div className="sis-admission-sheet__row sis-admission-sheet__row--2">
                            <SheetField label={i18n.admission.branch} name="branch_name" required>
                                <SheetSelect
                                    id="branch_name"
                                    name="branch_name"
                                    value={draft.branch_name}
                                    options={branchOptions}
                                    allowEmpty
                                    required
                                    onChange={(next) => patchDraft({ branch_name: next })}
                                    ariaLabel={i18n.admission.branch}
                                />
                            </SheetField>
                            <SheetField
                                label={i18n.admission.specialization}
                                name="department_name"
                                required
                            >
                                <SheetSelect
                                    id="department_name"
                                    name="department_name"
                                    value={draft.department_name}
                                    options={departmentOptions}
                                    allowEmpty
                                    required
                                    disabled={draft.branch_name.trim() === ''}
                                    onChange={(next) => patchDraft({ department_name: next })}
                                    ariaLabel={i18n.admission.specialization}
                                />
                            </SheetField>
                        </div>
                        <div className="sis-admission-sheet__row sis-admission-sheet__row--2">
                            <SheetField
                                label={i18n.enrollments.className}
                                name="class_key"
                                required
                            >
                                <SheetSelect
                                    id="class_key"
                                    name="class_key"
                                    value={draft.class_key}
                                    options={classOptions}
                                    allowEmpty
                                    required
                                    onChange={(next) => patchDraft({ class_key: next })}
                                    ariaLabel={i18n.enrollments.className}
                                />
                            </SheetField>
                            <SheetField
                                label={i18n.enrollments.sectionName}
                                name="section_code"
                                required
                            >
                                <SheetSelect
                                    id="section_code"
                                    name="section_code"
                                    value={draft.section_code}
                                    options={sectionOptions}
                                    allowEmpty
                                    required
                                    dir="ltr"
                                    onChange={(next) => patchDraft({ section_code: next })}
                                    ariaLabel={i18n.enrollments.sectionName}
                                />
                            </SheetField>
                        </div>
                    </SheetSection>

                    <div className="sis-admission-sheet__actions">
                        <Button
                            type="button"
                            variant="outline"
                            disabled={saving}
                            onClick={() => onOpenChange(false)}
                        >
                            {i18n.dialog.cancel}
                        </Button>
                        <Button type="submit" disabled={saving || students.length === 0}>
                            {saving ? i18n.common.saving : i18n.students.enrollDialogSubmit}
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
}
