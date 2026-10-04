import { router, usePage } from '@inertiajs/react';
import { useEffect, useMemo, useRef, useState, type RefObject } from 'react';
import AppLogo from '@/components/app-logo';
import { ConfirmDialog } from '@/components/sis/confirm-dialog';
import { SheetSection } from '@/components/sis/admission-sheet';
import {
    formatAcademicYearOptionLabel,
    type YearOption,
} from '@/components/sis/ops-year-filter';
import { usePageError } from '@/components/sis/page-error-context';
import { SisListSelect } from '@/components/sis/sis-list-select';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogTitle } from '@/components/ui/dialog';
import { WindowControls } from '@/components/window-controls';
import { useSheetMaximize } from '@/hooks/use-sheet-maximize';
import { useSmoothDialogDrag } from '@/hooks/use-smooth-dialog-drag';
import { t } from '@/i18n';
import {
    resolveSisClassId,
    resolveSisClassKey,
    sisClassSelectOptions,
} from '@/lib/sis-class-section-options';
import type {
    CurriculumFilterOptions,
    CurriculumRow,
} from '@/components/curriculum/curriculum-workspace';

export type CurriculumPlanSheetMode = 'edit' | 'create';

type Draft = {
    name: string;
    academic_year_id: string;
    grade_level_id: string;
    branch_id: string;
    /** الاختصاص — department of the selected branch. */
    department_id: string;
    status: string;
};

function isFilled(value: string): boolean {
    return value.trim() !== '' && value !== '—';
}

function newIdempotencyKey(prefix: string): string {
    if (typeof crypto !== 'undefined' && 'randomUUID' in crypto) {
        return `${prefix}-${crypto.randomUUID()}`;
    }

    return `${prefix}-${Date.now()}-${Math.random().toString(16).slice(2)}`;
}

function filledControlClass(filled: boolean, editing: boolean): string {
    return `sis-admission-sheet__control${filled ? ' sis-admission-draft-field--filled' : ''}${
        editing ? '' : ' sis-admission-draft-readonly'
    }`;
}

function SheetTextField({
    label,
    editing,
    value,
    display,
    onChange,
    dir = 'rtl',
    required = false,
    fieldClassName = '',
    inputRef,
}: {
    label: string;
    editing: boolean;
    value: string;
    display: string;
    onChange: (value: string) => void;
    dir?: 'rtl' | 'ltr';
    required?: boolean;
    fieldClassName?: string;
    inputRef?: RefObject<HTMLInputElement | null>;
}) {
    return (
        <label className={`sis-admission-sheet__field ${fieldClassName}`.trim()}>
            <span className="sis-admission-sheet__label">
                {label}
                {required ? (
                    <span aria-hidden="true">
                        {' '}
                        *
                    </span>
                ) : null}
            </span>
            {editing ? (
                <input
                    ref={inputRef}
                    className={filledControlClass(isFilled(value), true)}
                    value={value}
                    dir={dir}
                    type="text"
                    onChange={(event) => onChange(event.target.value)}
                />
            ) : (
                <div className={filledControlClass(isFilled(display), false)} dir={dir}>
                    {display}
                </div>
            )}
        </label>
    );
}

function SheetListField({
    label,
    editing,
    value,
    display,
    options,
    onChange,
    fieldClassName = '',
    includeBlank = false,
    disabled = false,
    required = false,
}: {
    label: string;
    editing: boolean;
    value: string;
    display: string;
    options: Array<{ value: string; label: string }>;
    onChange: (value: string) => void;
    fieldClassName?: string;
    includeBlank?: boolean;
    disabled?: boolean;
    required?: boolean;
}) {
    return (
        <label className={`sis-admission-sheet__field ${fieldClassName}`.trim()}>
            <span className="sis-admission-sheet__label">
                {label}
                {required ? (
                    <span aria-hidden="true">
                        {' '}
                        *
                    </span>
                ) : null}
            </span>
            {editing ? (
                <SisListSelect
                    value={value}
                    options={options}
                    onChange={onChange}
                    ariaLabel={label}
                    includeBlank={includeBlank}
                    disabled={disabled}
                    className={`sis-admission-sheet-list-select${
                        isFilled(value) ? ' sis-admission-draft-field--filled' : ''
                    }`}
                    triggerClassName={`sis-admission-sheet__control sis-admission-draft-select${
                        isFilled(value) ? ' sis-admission-draft-field--filled' : ''
                    }`}
                    menuClassName="sis-admission-sheet-list-select__menu"
                />
            ) : (
                <div className={filledControlClass(isFilled(display), false)}>{display}</div>
            )}
        </label>
    );
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
            <div className={filledControlClass(isFilled(display), false)} dir={dir}>
                {display}
            </div>
        </div>
    );
}

export function blankCurriculumPlan(academicYearId: number | null): CurriculumRow {
    return {
        id: 0,
        name: '',
        grade_level_id: 0,
        grade_level_name: null,
        specialization_id: null,
        specialization_name: null,
        department_id: null,
        department_name: null,
        branch_id: null,
        branch_name: null,
        status: 1,
        academic_year_id: academicYearId ?? 0,
        school_id: undefined,
    };
}

function toDraft(plan: CurriculumRow): Draft {
    return {
        name: plan.name ?? '',
        academic_year_id:
            plan.academic_year_id > 0 ? String(plan.academic_year_id) : '',
        grade_level_id: plan.grade_level_id > 0 ? String(plan.grade_level_id) : '',
        branch_id: plan.branch_id !== null ? String(plan.branch_id) : '',
        department_id: plan.department_id !== null ? String(plan.department_id) : '',
        status: String(Number(plan.status) === 1 ? 1 : 2),
    };
}

function suggestPlanName(
    filterOptions: CurriculumFilterOptions,
    branchId: string,
    departmentId: string,
    gradeLevelId: string,
): string {
    const branch = filterOptions.branches.find((item) => String(item.id) === branchId);
    const spec = filterOptions.departments.find((item) => String(item.id) === departmentId);
    const gradeClass = filterOptions.classes.find(
        (item) => String(item.grade_level_id) === gradeLevelId,
    );

    return [branch?.name, spec?.name, gradeClass?.name].filter(Boolean).join(' — ');
}

function CurriculumPlanRecordForm({
    plan,
    canManage,
    initialEditing,
    mode = 'edit',
    filterOptions,
    defaultAcademicYearId,
    hideHero = false,
    sheetTitle,
    onClose,
    heroDragProps,
    showWindowControls = true,
    maximized,
    onMaximize,
}: {
    plan: CurriculumRow;
    canManage: boolean;
    initialEditing: boolean;
    mode?: CurriculumPlanSheetMode;
    filterOptions: CurriculumFilterOptions;
    defaultAcademicYearId: number | null;
    hideHero?: boolean;
    sheetTitle?: string;
    onClose?: () => void;
    heroDragProps?: Record<string, unknown>;
    showWindowControls?: boolean;
    maximized?: boolean;
    onMaximize?: () => void;
}) {
    const i18n = t();
    const c = i18n.curriculum;
    const { showInertiaErrors, showSuccess } = usePageError();
    const { academicYears } = usePage().props as { academicYears?: YearOption[] };
    const years = academicYears ?? [];
    const isCreate = mode === 'create';
    const [editing, setEditing] = useState((isCreate || initialEditing) && canManage);
    const [draft, setDraft] = useState(() => {
        const base = toDraft(plan);
        if (isCreate && base.academic_year_id === '' && defaultAcademicYearId) {
            base.academic_year_id = String(defaultAcademicYearId);
        }
        if (isCreate && base.grade_level_id === '' && filterOptions.classes[0]) {
            base.grade_level_id = String(filterOptions.classes[0].grade_level_id);
        }

        return base;
    });
    const [saving, setSaving] = useState(false);
    const [pendingStatus, setPendingStatus] = useState<1 | 2 | null>(null);
    const [confirmPending, setConfirmPending] = useState(false);
    const nameInputRef = useRef<HTMLInputElement | null>(null);

    useEffect(() => {
        const next = toDraft(plan);
        if (isCreate && next.academic_year_id === '' && defaultAcademicYearId) {
            next.academic_year_id = String(defaultAcademicYearId);
        }
        if (isCreate && next.grade_level_id === '' && filterOptions.classes[0]) {
            next.grade_level_id = String(filterOptions.classes[0].grade_level_id);
        }
        setDraft(next);
        setEditing((isCreate || initialEditing) && canManage);
        setPendingStatus(null);
    }, [canManage, defaultAcademicYearId, filterOptions.classes, initialEditing, isCreate, plan]);

    const statusOptions = useMemo(
        () => [
            { value: '1', label: i18n.status.active },
            { value: '2', label: i18n.status.inactive },
        ],
        [i18n.status.active, i18n.status.inactive],
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

    const branchDepartments = useMemo(() => {
        if (draft.branch_id === '') {
            return [];
        }

        const branchId = Number(draft.branch_id);

        return filterOptions.departments.filter((department) => department.branch_id === branchId);
    }, [draft.branch_id, filterOptions.departments]);

    const departmentOptions = useMemo(
        () => branchDepartments.map((item) => ({ value: String(item.id), label: item.name })),
        [branchDepartments],
    );

    const selectedClassKey = useMemo(
        () =>
            resolveSisClassKey(
                filterOptions.classes.find(
                    (item) => String(item.grade_level_id) === draft.grade_level_id,
                )?.id ?? null,
                filterOptions.classes,
            ),
        [draft.grade_level_id, filterOptions.classes],
    );

    const statusDisplay =
        Number(draft.status) === 1 ? i18n.status.active : i18n.status.inactive;
    const yearDisplay =
        formatAcademicYearOptionLabel(
            years.find((year) => year.id === plan.academic_year_id)?.name ?? '',
            years.find((year) => year.id === plan.academic_year_id)?.code ?? '',
        ) || (plan.academic_year_id > 0 ? String(plan.academic_year_id) : '—');
    const gradeDisplay = plan.grade_level_name ?? '—';
    const branchDisplay = plan.branch_name ?? '—';
    const departmentDisplay = plan.department_name ?? '—';
    const schoolDisplay =
        plan.school_id !== undefined && plan.school_id !== null
            ? String(plan.school_id)
            : '—';

    const fieldsEditable = (isCreate || editing) && canManage;
    const structureEditable = isCreate && fieldsEditable;
    const canSave =
        canManage &&
        fieldsEditable &&
        draft.name.trim() !== '' &&
        draft.academic_year_id !== '' &&
        draft.grade_level_id !== '' &&
        !saving;

    const setField = <K extends keyof Draft>(key: K, value: Draft[K]): void => {
        setDraft((current) => ({ ...current, [key]: value }));
    };

    const applySuggestedName = (
        nextBranchId: string,
        nextSpecId: string,
        nextGradeId: string,
        force = false,
    ): void => {
        setDraft((current) => {
            if (!force && current.name.trim() !== '') {
                return {
                    ...current,
                    branch_id: nextBranchId,
                    department_id: nextSpecId,
                    grade_level_id: nextGradeId,
                };
            }

            return {
                ...current,
                branch_id: nextBranchId,
                department_id: nextSpecId,
                grade_level_id: nextGradeId,
                name: suggestPlanName(filterOptions, nextBranchId, nextSpecId, nextGradeId),
            };
        });
    };

    const createPlan = (closeAfter: boolean): void => {
        const planName =
            draft.name.trim() !== ''
                ? draft.name.trim()
                : suggestPlanName(
                      filterOptions,
                      draft.branch_id,
                      draft.department_id,
                      draft.grade_level_id,
                  );

        if (planName === '' || draft.academic_year_id === '' || draft.grade_level_id === '') {
            setSaving(false);

            return;
        }

        router.post(
            '/curriculum/curricula',
            {
                academic_year_id: Number(draft.academic_year_id),
                grade_level_id: Number(draft.grade_level_id),
                name: planName,
                department_id: draft.department_id === '' ? null : Number(draft.department_id),
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
                    if (closeAfter) {
                        onClose?.();

                        return;
                    }

                    const blank = blankCurriculumPlan(
                        Number(draft.academic_year_id) || defaultAcademicYearId,
                    );
                    const next = toDraft(blank);
                    next.academic_year_id = draft.academic_year_id;
                    next.grade_level_id = draft.grade_level_id;
                    next.branch_id = draft.branch_id;
                    setDraft(next);
                    showSuccess({ description: c.planCreatedContinue });
                    requestAnimationFrame(() => {
                        nameInputRef.current?.focus();
                    });
                },
                onFinish: () => setSaving(false),
            },
        );
    };

    const finishSave = (): void => {
        setEditing(false);
        setSaving(false);
        setConfirmPending(false);
        setPendingStatus(null);
    };

    const failSave = (errors: Record<string, string | string[] | undefined>): void => {
        showInertiaErrors(errors, i18n.errors.saveFailed);
        setSaving(false);
        setConfirmPending(false);
        setPendingStatus(null);
    };

    const patchFields = (options?: { onSuccess?: () => void }): void => {
        router.patch(
            `/curriculum/curricula/${plan.id}`,
            {
                name: draft.name.trim(),
                department_id: draft.department_id === '' ? null : Number(draft.department_id),
            },
            {
                preserveScroll: true,
                preserveState: true,
                only: ['curricula', 'curriculum', 'filters', 'filterOptions', 'authorization', 'flash'],
                headers: { 'X-Idempotency-Key': newIdempotencyKey('curriculum') },
                onError: failSave,
                onSuccess: () => {
                    if (options?.onSuccess) {
                        options.onSuccess();

                        return;
                    }

                    finishSave();
                },
                onFinish: () => {
                    if (!options?.onSuccess) {
                        setSaving(false);
                    }
                },
            },
        );
    };

    const postStatus = (
        nextStatus: 1 | 2,
        options?: { onSuccess?: () => void },
    ): void => {
        const url =
            nextStatus === 1
                ? `/curriculum/curricula/${plan.id}/reactivate`
                : `/curriculum/curricula/${plan.id}/deactivate`;

        setConfirmPending(true);
        router.post(
            url,
            {},
            {
                preserveScroll: true,
                preserveState: true,
                only: ['curricula', 'curriculum', 'filters', 'authorization', 'flash'],
                headers: { 'X-Idempotency-Key': newIdempotencyKey('curriculum') },
                onError: failSave,
                onSuccess: () => {
                    if (options?.onSuccess) {
                        options.onSuccess();

                        return;
                    }

                    finishSave();
                },
                onFinish: () => {
                    setConfirmPending(false);
                    setPendingStatus(null);
                    if (!options?.onSuccess) {
                        setSaving(false);
                    }
                },
            },
        );
    };

    const fieldsChanged = (): boolean => {
        const currentDepartment = plan.department_id !== null ? String(plan.department_id) : '';

        return (
            draft.name.trim() !== plan.name.trim() ||
            draft.department_id !== currentDepartment
        );
    };

    const applyStatusWithFields = (nextStatus: 1 | 2): void => {
        const needsFieldPatch = fieldsChanged();

        if (nextStatus === 2) {
            if (needsFieldPatch) {
                patchFields({ onSuccess: () => postStatus(2) });

                return;
            }

            postStatus(2);

            return;
        }

        if (needsFieldPatch) {
            postStatus(1, { onSuccess: () => patchFields() });

            return;
        }

        postStatus(1);
    };

    const save = (closeAfter = false): void => {
        if (!canSave) {
            return;
        }

        if (isCreate) {
            setSaving(true);
            createPlan(closeAfter);

            return;
        }

        const nextStatus = Number(draft.status) === 1 ? 1 : 2;
        const currentStatus = Number(plan.status) === 1 ? 1 : 2;

        if (nextStatus !== currentStatus) {
            setPendingStatus(nextStatus);

            return;
        }

        setSaving(true);
        patchFields();
    };

    const resolvedSheetTitle = sheetTitle ?? (isCreate ? c.createPlan : plan.name);

    return (
        <article
            className="sis-admission-draft-form sis-admission-sheet sis-student-record-form sis-curriculum-subject-sheet"
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
                id={`plan-identity-${isCreate ? 'new' : plan.id}`}
                title={c.planDetailsSection}
            >
                <div className="sis-student-record-form__name-line">
                    <SheetTextField
                        label={c.planName}
                        editing={fieldsEditable}
                        value={draft.name}
                        display={plan.name || '—'}
                        required
                        fieldClassName="sis-enrollment-record-sheet__field--wide"
                        inputRef={isCreate ? nameInputRef : undefined}
                        onChange={(value) => setField('name', value)}
                    />
                    {isCreate ? (
                        <div className="sis-admission-sheet__field sis-student-record-form__status-field">
                            <span className="sis-admission-sheet__label">{i18n.common.status}</span>
                            <div className={filledControlClass(true, false)} aria-readonly="true">
                                {i18n.status.active}
                            </div>
                        </div>
                    ) : (
                        <SheetListField
                            label={i18n.common.status}
                            editing={fieldsEditable}
                            value={draft.status}
                            display={statusDisplay}
                            options={statusOptions}
                            fieldClassName="sis-student-record-form__status-field"
                            onChange={(value) => setField('status', value)}
                        />
                    )}
                </div>
                <div className="sis-admission-sheet__row sis-admission-sheet__row--track5 sis-enrollment-record-sheet__placement-row">
                    {structureEditable ? (
                        <SheetListField
                            label={c.academicYear}
                            editing
                            value={draft.academic_year_id}
                            display={yearDisplay}
                            options={yearOptions}
                            required
                            fieldClassName="sis-enrollment-record-sheet__field--wide"
                            onChange={(value) => setField('academic_year_id', value)}
                        />
                    ) : (
                        <SheetDisplayField
                            label={c.academicYear}
                            display={yearDisplay}
                            fieldClassName="sis-enrollment-record-sheet__field--wide"
                        />
                    )}
                    {structureEditable ? (
                        <SheetListField
                            label={c.gradeLevel}
                            editing
                            value={selectedClassKey}
                            display={gradeDisplay}
                            options={classOptions}
                            required
                            includeBlank={false}
                            fieldClassName="sis-enrollment-record-sheet__field--narrow"
                            onChange={(value) => {
                                const classId = resolveSisClassId(value, filterOptions.classes);
                                const selected = filterOptions.classes.find(
                                    (item) => item.id === classId,
                                );
                                const nextGradeId = selected
                                    ? String(selected.grade_level_id)
                                    : '';
                                applySuggestedName(
                                    draft.branch_id,
                                    draft.department_id,
                                    nextGradeId,
                                );
                            }}
                        />
                    ) : (
                        <SheetDisplayField
                            label={c.gradeLevel}
                            display={gradeDisplay}
                            fieldClassName="sis-enrollment-record-sheet__field--narrow"
                        />
                    )}
                    {!isCreate ? (
                        <SheetDisplayField
                            label={c.schoolIdLabel}
                            display={schoolDisplay}
                            dir="ltr"
                            fieldClassName="sis-enrollment-record-sheet__field--narrow"
                        />
                    ) : null}
                </div>
            </SheetSection>

            <SheetSection
                id={`plan-placement-${isCreate ? 'new' : plan.id}`}
                title={c.planPlacementSection}
            >
                <div className="sis-admission-sheet__row sis-admission-sheet__row--track5 sis-enrollment-record-sheet__placement-row">
                    <SheetListField
                        label={c.branch}
                        editing={fieldsEditable}
                        value={draft.branch_id}
                        display={branchDisplay}
                        includeBlank
                        options={filterOptions.branches.map((item) => ({
                            value: String(item.id),
                            label: item.name,
                        }))}
                        fieldClassName="sis-enrollment-record-sheet__field--wide"
                        onChange={(value) => {
                            applySuggestedName(value, '', draft.grade_level_id, isCreate);
                        }}
                    />
                    <SheetListField
                        label={c.specialization}
                        editing={fieldsEditable}
                        value={draft.department_id}
                        display={departmentDisplay}
                        includeBlank
                        options={departmentOptions}
                        fieldClassName="sis-enrollment-record-sheet__field--wide"
                        onChange={(value) => {
                            applySuggestedName(
                                draft.branch_id,
                                value,
                                draft.grade_level_id,
                                true,
                            );
                        }}
                    />
                </div>
                {isCreate ? (
                    <p className="sis-admission-sheet__hint text-muted-foreground mt-2 text-xs">
                        {c.autoLinkHint}
                    </p>
                ) : null}
            </SheetSection>

            <div className="sis-admission-sheet__actions">
                {onClose ? (
                    <Button type="button" variant="outline" onClick={onClose}>
                        {i18n.dialog.cancel}
                    </Button>
                ) : null}
                {canManage ? (
                    <>
                        {isCreate ? null : (
                            <Button
                                type="button"
                                variant="outline"
                                disabled={editing}
                                onClick={() => setEditing(true)}
                            >
                                {i18n.common.edit}
                            </Button>
                        )}
                        {isCreate ? (
                            <>
                                <Button
                                    type="button"
                                    variant="outline"
                                    disabled={saving || !canSave}
                                    onClick={() => save(true)}
                                >
                                    {saving ? i18n.common.saving : c.saveAndClose}
                                </Button>
                                <Button
                                    type="button"
                                    disabled={saving || !canSave}
                                    onClick={() => save(false)}
                                >
                                    {saving ? i18n.common.saving : c.saveAndAddAnother}
                                </Button>
                            </>
                        ) : (
                            <Button
                                type="button"
                                disabled={saving || !fieldsEditable || !canSave || confirmPending}
                                onClick={() => save()}
                            >
                                {saving ? i18n.common.saving : i18n.common.save}
                            </Button>
                        )}
                    </>
                ) : null}
            </div>

            {isCreate ? null : (
                <ConfirmDialog
                    open={pendingStatus !== null}
                    title={
                        pendingStatus === 2
                            ? c.confirmDeactivatePlan
                            : c.confirmReactivatePlan
                    }
                    description={
                        pendingStatus === 2
                            ? c.confirmDeactivatePlan
                            : c.confirmReactivatePlan
                    }
                    confirmPending={confirmPending}
                    tone={pendingStatus === 2 ? 'danger' : 'default'}
                    onConfirm={() => {
                        if (pendingStatus === null) {
                            return;
                        }
                        setSaving(true);
                        applyStatusWithFields(pendingStatus);
                    }}
                    onOpenChange={(open) => {
                        if (!open) {
                            setPendingStatus(null);
                        }
                    }}
                />
            )}
        </article>
    );
}

type Props = {
    mode?: CurriculumPlanSheetMode;
    plan?: CurriculumRow | null;
    canManage: boolean;
    initialEditing?: boolean;
    filterOptions: CurriculumFilterOptions;
    defaultAcademicYearId: number | null;
    onClose: () => void;
};

/**
 * Curriculum plan view/edit/create sheet — same chrome SSOT as subject / enrollment sheets.
 */
export function CurriculumPlanSheetDialog({
    mode = 'edit',
    plan = null,
    canManage,
    initialEditing = false,
    filterOptions,
    defaultAcademicYearId,
    onClose,
}: Props) {
    const i18n = t();
    const c = i18n.curriculum;
    const isCreate = mode === 'create';
    const record = isCreate
        ? blankCurriculumPlan(defaultAcademicYearId)
        : (plan ?? blankCurriculumPlan(defaultAcademicYearId));
    const dialogTitle = isCreate ? c.createPlan : record.name || c.editPlan;

    const { contentRef, heroDragProps, bringToFront, resizeHandles } = useSmoothDialogDrag(true, {
        resizable: true,
        minSize: { width: 520, height: 360 },
    });
    const { maximized, toggleMaximize, maximizeClassName } = useSheetMaximize(contentRef);

    if (!isCreate && (plan === null || plan.id <= 0)) {
        return null;
    }

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
                className={`sis-admission-draft-dialog sis-admission-sheet-dialog sis-student-sheet-dialog sis-enrollment-record-sheet sis-curriculum-subject-sheet${maximizeClassName}`}
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
                <CurriculumPlanRecordForm
                    key={isCreate ? 'create-plan' : record.id}
                    plan={record}
                    canManage={canManage}
                    mode={isCreate ? 'create' : 'edit'}
                    initialEditing={isCreate || initialEditing}
                    filterOptions={filterOptions}
                    defaultAcademicYearId={defaultAcademicYearId}
                    sheetTitle={isCreate ? c.createPlan : record.name}
                    onClose={onClose}
                    heroDragProps={maximized ? undefined : heroDragProps}
                    showWindowControls
                    maximized={maximized}
                    onMaximize={toggleMaximize}
                />
            </DialogContent>
        </Dialog>
    );
}
