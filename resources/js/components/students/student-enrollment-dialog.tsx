import { router, usePage } from '@inertiajs/react';
import { useEffect, useMemo, useRef, useState, type FormEvent, type ReactNode } from 'react';
import AppLogo from '@/components/app-logo';
import type { EnrollmentFormFilterOptions } from '@/components/enrollments/enrollment-record-form';
import { StudentStatusBadge } from '@/components/students/student-status-badge';
import { SheetField, SheetSection } from '@/components/sis/admission-sheet';
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
import { useSheetMaximize } from '@/hooks/use-sheet-maximize';
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
import { resolveUiMessage } from '@/lib/resolve-ui-message';

export type StudentEnrollmentCandidate = {
    id: number;
    full_name: string;
    student_code?: string | null;
    status?: number | null;
    is_enrolled?: boolean;
    branch_id?: number | null;
    branch_name?: string | null;
    department_name?: string | null;
    admitted_class_name?: string | null;
    section_name?: string | null;
};

/** Server outcome of POST /enrollments/bulk (shared as flash.bulkEnroll). */
type BulkEnrollOutcome = {
    enrolled: number[];
    skipped: Array<{ student_id: number; error_code: string }>;
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

function isFilled(value: string): boolean {
    return value.trim() !== '';
}

function filledClass(value: string): string {
    return isFilled(value) ? ' sis-admission-draft-field--filled' : '';
}

function invalidClass(invalid: boolean): string {
    return invalid ? ' sis-student-enrollment-sheet__control--invalid' : '';
}

function displayValue(value: string | number | null | undefined): string {
    if (value === null || value === undefined) {
        return '—';
    }

    const text = String(value).trim();

    return text === '' ? '—' : text;
}

function filledControlClass(filled: boolean, editing: boolean): string {
    return `sis-admission-sheet__control${filled ? ' sis-admission-draft-field--filled' : ''}${editing ? '' : ' sis-admission-draft-readonly'}`;
}

function SheetDisplayField({
    label,
    display,
    dir = 'rtl',
    fieldClassName = '',
}: {
    label: string;
    display: string;
    dir?: 'rtl' | 'ltr';
    fieldClassName?: string;
}) {
    return (
        <div className={`sis-admission-sheet__field ${fieldClassName}`.trim()}>
            <span className="sis-admission-sheet__label">{label}</span>
            <div className={filledControlClass(isFilled(display), false)} dir={dir} aria-readonly="true">
                {display}
            </div>
        </div>
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

function RequiredSheetField({
    label,
    name,
    error,
    fieldClassName = '',
    children,
}: {
    label: string;
    name: string;
    error?: string;
    fieldClassName?: string;
    children: ReactNode;
}) {
    return (
        <SheetField label={label} name={name} required error={error} className={fieldClassName}>
            {children}
        </SheetField>
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
    const { showError, showWarning, showSuccess, showInfo, showInertiaErrors } = usePageError();
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
    const { maximized, toggleMaximize, maximizeClassName } = useSheetMaximize(contentRef);
    const singleStudent = students.length === 1 ? students[0] : null;
    const studentNamesLabel =
        students.length === 1 ? i18n.enrollments.quadName : i18n.students.enrollDialogStudents;

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

        if (issue.tone === 'info' || issue.titleKey === 'enrollGuideTitle') {
            showInfo(payload);
            return;
        }

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
        if ('academic_year_id' in patch) {
            keysToClear.push('academic_year_id');
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
        // Arabic in-sheet guidance owns empty required fields (not the browser English tip).
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
                guideFillRequired: i18n.students.enrollGuideFillRequired,
                classLabel: i18n.enrollments.className,
                sectionLabel: i18n.enrollments.sectionName,
            },
        });

        if (!validation.ok) {
            presentIssue(validation.issue);
            return;
        }

        setFieldErrors({});
        const { payload } = validation;
        setSaving(true);
        const idempotencyKey =
            typeof crypto !== 'undefined' && 'randomUUID' in crypto
                ? crypto.randomUUID()
                : `enroll-students-${Date.now()}`;
        const nameById = new Map(
            payload.eligibleStudents.map((student) => [student.id, student.full_name]),
        );

        try {
            // One server call: per-student validation, capacity and outcome stay on the server.
            const outcome = await new Promise<BulkEnrollOutcome | null>((resolve) => {
                router.post(
                    '/enrollments/bulk',
                    {
                        student_ids: payload.eligibleStudents.map((student) => student.id),
                        academic_year_id: payload.academicYearId,
                        class_id: payload.classId,
                        section_id: payload.sectionId,
                        effective_from: payload.effectiveFrom,
                        ...(payload.branchId === null ? {} : { branch_id: payload.branchId }),
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
                                page.props as {
                                    flash?: {
                                        error?: string | null;
                                        bulkEnroll?: BulkEnrollOutcome | null;
                                    };
                                }
                            ).flash;
                            if (flash?.bulkEnroll) {
                                resolve(flash.bulkEnroll);
                                return;
                            }

                            const code = typeof flash?.error === 'string' ? flash.error.trim() : '';
                            window.setTimeout(() => {
                                showError({
                                    title: i18n.students.enrollDialogTitle,
                                    description: i18n.students.enrollServerError,
                                    details: code === '' ? [] : [resolveUiMessage(code)],
                                });
                            }, 0);
                            resolve(null);
                        },
                        onError: (errors) => {
                            showInertiaErrors(errors, i18n.errors.createFailed);
                            resolve(null);
                        },
                    },
                );
            });

            if (outcome === null) {
                return;
            }

            const enrolledCount = outcome.enrolled.length;
            const skippedDetails = outcome.skipped.map((item) => {
                const name = nameById.get(item.student_id) ?? String(item.student_id);

                return item.error_code === 'enrollment.already_enrolled'
                    ? i18n.students.enrollAlreadyRegisteredNamed.replace('{name}', name)
                    : `${name}: ${resolveUiMessage(item.error_code, i18n.students.enrollServerError)}`;
            });

            if (enrolledCount > 0 && skippedDetails.length === 0) {
                showSuccess({
                    title: i18n.students.enrollDialogTitle,
                    description: i18n.students.enrollSuccessNamed.replace(
                        '{count}',
                        String(enrolledCount),
                    ),
                });
                onOpenChange(false);
                onEnrolled?.();
            } else if (enrolledCount > 0) {
                showWarning({
                    title: i18n.students.enrollWarningTitle,
                    description: i18n.students.enrollPartialFailed,
                    details: [
                        i18n.students.enrollSuccessNamed.replace('{count}', String(enrolledCount)),
                        ...skippedDetails,
                    ],
                });
                onEnrolled?.();
            } else if (
                outcome.skipped.length === 1
                && outcome.skipped[0].error_code === 'enrollment.already_enrolled'
            ) {
                showError({
                    title: i18n.students.enrollAlreadyRegisteredTitle,
                    description: skippedDetails[0],
                });
            } else {
                showError({
                    title: i18n.students.enrollDialogTitle,
                    description: i18n.students.enrollServerError,
                    details: skippedDetails,
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
                className={`sis-admission-draft-dialog sis-admission-sheet-dialog sis-student-sheet-dialog sis-enrollment-record-sheet sis-student-enrollment-sheet${maximizeClassName}`}
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
                {maximized ? null : resizeHandles}

                <form
                    ref={formRef}
                    className="sis-admission-draft-form sis-admission-sheet sis-student-record-form"
                    onSubmit={onFormSubmit}
                    noValidate
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

                    <SheetSection id="enroll-create-student" title={i18n.enrollments.student}>
                        <div className="sis-student-record-form__name-line">
                            {singleStudent ? (
                                <SheetDisplayField
                                    label={studentNamesLabel}
                                    display={displayValue(singleStudent.full_name)}
                                />
                            ) : (
                                <div className="sis-admission-sheet__field sis-student-enrollment-sheet__names-field">
                                    <span className="sis-admission-sheet__label">{studentNamesLabel}</span>
                                    <ul
                                        className="sis-student-enrollment-sheet__student-list sis-admission-sheet__control sis-admission-draft-field--filled sis-admission-draft-readonly"
                                        aria-label={studentNamesLabel}
                                    >
                                        {students.map((student) => (
                                            <li key={student.id}>
                                                <span>{displayValue(student.full_name)}</span>
                                                {student.is_enrolled === true ? (
                                                    <span className="sis-student-enrollment-sheet__badge">
                                                        {i18n.students.enrollmentYes}
                                                    </span>
                                                ) : null}
                                            </li>
                                        ))}
                                    </ul>
                                </div>
                            )}
                            {singleStudent ? (
                                <div className="sis-admission-sheet__field sis-student-record-form__status-field">
                                    <span className="sis-admission-sheet__label">{i18n.common.status}</span>
                                    <div
                                        className="sis-student-record-form__status-value"
                                        aria-readonly="true"
                                    >
                                        <StudentStatusBadge status={singleStudent.status ?? 1} />
                                    </div>
                                </div>
                            ) : null}
                        </div>

                        <div className="sis-admission-sheet__row sis-admission-sheet__row--track5">
                            <SheetDisplayField
                                label={i18n.enrollments.studentId}
                                display={
                                    singleStudent
                                        ? displayValue(
                                              singleStudent.student_code ?? singleStudent.id,
                                          )
                                        : displayValue(
                                              students
                                                  .map((student) => student.student_code ?? student.id)
                                                  .join('، '),
                                          )
                                }
                                dir="ltr"
                            />
                            <RequiredSheetField
                                label={i18n.enrollments.academicYear}
                                name="academic_year_id"
                                error={fieldErrors.academic_year_id}
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
                                        aria-invalid={Boolean(fieldErrors.academic_year_id)}
                                        aria-label={i18n.enrollments.academicYear}
                                        className="sis-student-enrollment-sheet__native-year"
                                    />
                                    <div
                                        className={`${filledControlClass(isFilled(yearLabel), false)}${invalidClass(Boolean(fieldErrors.academic_year_id))}`}
                                        aria-hidden="true"
                                        dir="ltr"
                                    >
                                        {yearLabel}
                                    </div>
                                </div>
                            </RequiredSheetField>
                            <RequiredSheetField
                                label={i18n.enrollments.effectiveFrom}
                                name="effective_from"
                                error={fieldErrors.effective_from}
                            >
                                <input
                                    id="effective_from"
                                    name="effective_from"
                                    type="date"
                                    dir="ltr"
                                    required
                                    aria-invalid={Boolean(fieldErrors.effective_from)}
                                    className={`${filledControlClass(isFilled(draft.effective_from), true)}${invalidClass(Boolean(fieldErrors.effective_from))}`}
                                    value={draft.effective_from}
                                    aria-label={i18n.enrollments.effectiveFrom}
                                    onChange={(event) =>
                                        patchDraft({ effective_from: event.target.value })
                                    }
                                />
                            </RequiredSheetField>
                        </div>
                    </SheetSection>

                    <SheetSection
                        id="enroll-create-placement"
                        title={i18n.enrollments.placementDialogTitle}
                    >
                        <div className="sis-admission-sheet__row sis-admission-sheet__row--track5 sis-enrollment-record-sheet__placement-row">
                            <RequiredSheetField
                                label={i18n.enrollments.branchName}
                                name="branch_name"
                                fieldClassName="sis-enrollment-record-sheet__field--wide"
                            >
                                <SheetSelect
                                    id="branch_name"
                                    name="branch_name"
                                    value={draft.branch_name}
                                    options={branchOptions}
                                    allowEmpty
                                    required
                                    invalid={Boolean(fieldErrors.branch_name)}
                                    onChange={(next) => patchDraft({ branch_name: next })}
                                    ariaLabel={i18n.enrollments.branchName}
                                />
                            </RequiredSheetField>
                            <RequiredSheetField
                                label={i18n.enrollments.departmentName}
                                name="department_name"
                                fieldClassName="sis-enrollment-record-sheet__field--wide"
                            >
                                <SheetSelect
                                    id="department_name"
                                    name="department_name"
                                    value={draft.department_name}
                                    options={departmentOptions}
                                    allowEmpty
                                    required
                                    invalid={Boolean(fieldErrors.department_name)}
                                    disabled={draft.branch_name.trim() === ''}
                                    onChange={(next) => patchDraft({ department_name: next })}
                                    ariaLabel={i18n.enrollments.departmentName}
                                />
                            </RequiredSheetField>
                            <RequiredSheetField
                                label={i18n.enrollments.className}
                                name="class_key"
                                fieldClassName="sis-enrollment-record-sheet__field--narrow"
                            >
                                <SheetSelect
                                    id="class_key"
                                    name="class_key"
                                    value={draft.class_key}
                                    options={classOptions}
                                    allowEmpty
                                    required
                                    invalid={Boolean(fieldErrors.class_key)}
                                    onChange={(next) => patchDraft({ class_key: next })}
                                    ariaLabel={i18n.enrollments.className}
                                />
                            </RequiredSheetField>
                            <RequiredSheetField
                                label={i18n.enrollments.sectionName}
                                name="section_code"
                                fieldClassName="sis-enrollment-record-sheet__field--narrow"
                            >
                                <SheetSelect
                                    id="section_code"
                                    name="section_code"
                                    value={draft.section_code}
                                    options={sectionOptions}
                                    allowEmpty
                                    required
                                    invalid={Boolean(fieldErrors.section_code)}
                                    dir="ltr"
                                    onChange={(next) => patchDraft({ section_code: next })}
                                    ariaLabel={i18n.enrollments.sectionName}
                                />
                            </RequiredSheetField>
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
