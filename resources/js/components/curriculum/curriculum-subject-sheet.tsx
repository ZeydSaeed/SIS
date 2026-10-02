import { router } from '@inertiajs/react';
import { useEffect, useMemo, useRef, useState, type RefObject } from 'react';
import AppLogo from '@/components/app-logo';
import { ConfirmDialog } from '@/components/sis/confirm-dialog';
import { SheetSection } from '@/components/sis/admission-sheet';
import { usePageError } from '@/components/sis/page-error-context';
import { SisListSelect } from '@/components/sis/sis-list-select';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogTitle } from '@/components/ui/dialog';
import { WindowControls } from '@/components/window-controls';
import { useManySheetPageFit } from '@/hooks/use-many-sheet-page-fit';
import { useSheetMaximize } from '@/hooks/use-sheet-maximize';
import { useSmoothDialogDrag } from '@/hooks/use-smooth-dialog-drag';
import { t } from '@/i18n';

export type CurriculumSubjectSheetSubject = {
    id: number;
    code: string;
    name: string;
    name_en?: string | null;
    subject_type: number;
    credit_hours: number | null;
    max_grade: number;
    pass_grade: number;
    status: number;
    prerequisites?: string;
};

export type CurriculumSubjectSheetMode = 'edit' | 'create';

type Draft = {
    code: string;
    name: string;
    name_en: string;
    subject_type: string;
    credit_hours: string;
    max_grade: string;
    pass_grade: string;
    status: string;
    prerequisites: string;
};

/** Blank subject for create-mode SSOT — same sheet as enrollment-style view/edit. */
export function blankCurriculumSubject(): CurriculumSubjectSheetSubject {
    return {
        id: 0,
        code: '',
        name: '',
        name_en: null,
        subject_type: 1,
        credit_hours: 2,
        max_grade: 100,
        pass_grade: 50,
        status: 1,
        prerequisites: '',
    };
}

function isFilled(value: string): boolean {
    return value.trim() !== '' && value !== '—';
}

function newIdempotencyKey(prefix: string): string {
    if (typeof crypto !== 'undefined' && 'randomUUID' in crypto) {
        return `${prefix}-${crypto.randomUUID()}`;
    }

    return `${prefix}-${Date.now()}-${Math.random().toString(16).slice(2)}`;
}

function subjectCodeFromName(name: string): string {
    let hash = 0;
    for (let i = 0; i < name.length; i++) {
        hash = (hash << 5) - hash + name.charCodeAt(i);
        hash |= 0;
    }
    const hex = Math.abs(hash).toString(16).toUpperCase().padStart(8, '0').slice(0, 8);

    return `SUB-${hex}`;
}

function subjectTypeLabel(
    type: number,
    curriculumI18n: ReturnType<typeof t>['curriculum'],
): string {
    if (type === 2) {
        return curriculumI18n.subjectTypeSpecialization;
    }
    if (type === 3) {
        return curriculumI18n.subjectTypePractical;
    }

    return curriculumI18n.subjectTypeCore;
}

function toDraft(subject: CurriculumSubjectSheetSubject): Draft {
    return {
        code: subject.code ?? '',
        name: subject.name ?? '',
        name_en: subject.name_en?.trim() ?? '',
        subject_type: String(subject.subject_type ?? 1),
        credit_hours: subject.credit_hours !== null ? String(subject.credit_hours) : '',
        max_grade: String(subject.max_grade ?? 100),
        pass_grade: String(subject.pass_grade ?? 50),
        status: String(Number(subject.status) === 1 ? 1 : 2),
        prerequisites: subject.prerequisites?.trim() ?? '',
    };
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
    type = 'text',
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
    type?: 'text' | 'number';
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
                    type={type}
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
}: {
    label: string;
    editing: boolean;
    value: string;
    display: string;
    options: Array<{ value: string; label: string }>;
    onChange: (value: string) => void;
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
                    includeBlank={false}
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

function SubjectRecordForm({
    subject,
    canManage,
    initialEditing,
    mode = 'edit',
    hideHero = false,
    sheetTitle,
    onClose,
    heroDragProps,
    showWindowControls = true,
    maximized,
    onMaximize,
}: {
    subject: CurriculumSubjectSheetSubject;
    canManage: boolean;
    initialEditing: boolean;
    mode?: CurriculumSubjectSheetMode;
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
    const isCreate = mode === 'create';
    const [editing, setEditing] = useState((isCreate || initialEditing) && canManage);
    const [draft, setDraft] = useState(() => toDraft(subject));
    const [saving, setSaving] = useState(false);
    const [pendingStatus, setPendingStatus] = useState<1 | 2 | null>(null);
    const [confirmPending, setConfirmPending] = useState(false);
    const nameInputRef = useRef<HTMLInputElement | null>(null);

    useEffect(() => {
        setDraft(toDraft(subject));
        setEditing((isCreate || initialEditing) && canManage);
        setPendingStatus(null);
    }, [canManage, initialEditing, isCreate, subject]);

    const typeOptions = useMemo(
        () => [
            { value: '1', label: c.subjectTypeCore },
            { value: '2', label: c.subjectTypeSpecialization },
            { value: '3', label: c.subjectTypePractical },
        ],
        [c.subjectTypeCore, c.subjectTypePractical, c.subjectTypeSpecialization],
    );

    const statusOptions = useMemo(
        () => [
            { value: '1', label: i18n.status.active },
            { value: '2', label: i18n.status.inactive },
        ],
        [i18n.status.active, i18n.status.inactive],
    );

    const statusDisplay =
        Number(draft.status) === 1 ? i18n.status.active : i18n.status.inactive;
    const typeDisplay = subjectTypeLabel(Number(draft.subject_type), c);
    const fieldsEditable = (isCreate || editing) && canManage;
    const canSave =
        canManage &&
        fieldsEditable &&
        draft.name.trim() !== '' &&
        Number(draft.max_grade) > 0 &&
        Number(draft.pass_grade) >= 0 &&
        Number(draft.pass_grade) <= Number(draft.max_grade) &&
        !saving;

    const setField = <K extends keyof Draft>(key: K, value: Draft[K]): void => {
        setDraft((current) => ({ ...current, [key]: value }));
    };

    const createSubject = (closeAfter: boolean): void => {
        const name = draft.name.trim();
        const code = draft.code.trim() || subjectCodeFromName(name);
        const prerequisitesText = draft.prerequisites.trim();

        router.post(
            '/curriculum/subjects',
            {
                code,
                name,
                name_en: draft.name_en.trim() === '' ? null : draft.name_en.trim(),
                subject_type: Number(draft.subject_type),
                credit_hours: draft.credit_hours === '' ? null : Number(draft.credit_hours),
                max_grade: Number(draft.max_grade),
                pass_grade: Number(draft.pass_grade),
                prerequisites_text: prerequisitesText === '' ? null : prerequisitesText,
            },
            {
                preserveScroll: true,
                preserveState: true,
                only: ['subjects', 'filters', 'authorization', 'flash'],
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

                    setDraft(toDraft(blankCurriculumSubject()));
                    showSuccess({ description: c.subjectCreatedContinue });
                    requestAnimationFrame(() => {
                        nameInputRef.current?.focus();
                    });
                },
                onFinish: () => setSaving(false),
            },
        );
    };

    const fieldsChanged = (): boolean => {
        const draftNameEn = draft.name_en.trim();
        const subjectNameEn = (subject.name_en ?? '').trim();
        const draftCredit =
            draft.credit_hours === '' ? null : Number(draft.credit_hours);
        const subjectCredit =
            subject.credit_hours === null || subject.credit_hours === undefined
                ? null
                : Number(subject.credit_hours);

        return (
            draft.name.trim() !== subject.name.trim() ||
            draftNameEn !== subjectNameEn ||
            Number(draft.subject_type) !== Number(subject.subject_type) ||
            draftCredit !== subjectCredit ||
            Number(draft.max_grade) !== Number(subject.max_grade) ||
            Number(draft.pass_grade) !== Number(subject.pass_grade) ||
            draft.prerequisites.trim() !== (subject.prerequisites?.trim() ?? '')
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
        const prerequisitesText = draft.prerequisites.trim();
        router.patch(
            `/curriculum/subjects/${subject.id}`,
            {
                name: draft.name.trim(),
                name_en: draft.name_en.trim() === '' ? null : draft.name_en.trim(),
                subject_type: Number(draft.subject_type),
                credit_hours: draft.credit_hours === '' ? null : Number(draft.credit_hours),
                max_grade: Number(draft.max_grade),
                pass_grade: Number(draft.pass_grade),
                prerequisites_text: prerequisitesText === '' ? null : prerequisitesText,
            },
            {
                preserveScroll: true,
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
                ? `/curriculum/subjects/${subject.id}/reactivate`
                : `/curriculum/subjects/${subject.id}/deactivate`;

        setConfirmPending(true);
        router.post(
            url,
            {},
            {
                preserveScroll: true,
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

    /**
     * UpdateSubjectGuard only finds active subjects — never PATCH after deactivate.
     * Deactivate: patch (if needed) → deactivate. Reactivate: reactivate → patch (if needed).
     */
    const applyStatusWithFields = (nextStatus: 1 | 2): void => {
        const needsFieldPatch = fieldsChanged();

        if (nextStatus === 2) {
            if (needsFieldPatch) {
                patchFields({
                    onSuccess: () => postStatus(2),
                });

                return;
            }

            postStatus(2);

            return;
        }

        if (needsFieldPatch) {
            postStatus(1, {
                onSuccess: () => patchFields(),
            });

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
            createSubject(closeAfter);

            return;
        }

        const nextStatus = Number(draft.status) === 1 ? 1 : 2;
        const currentStatus = Number(subject.status) === 1 ? 1 : 2;

        if (nextStatus !== currentStatus) {
            setPendingStatus(nextStatus);

            return;
        }

        setSaving(true);
        patchFields();
    };

    const resolvedSheetTitle =
        sheetTitle ?? (isCreate ? c.createSubject : subject.name);

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
                id={`subject-identity-${isCreate ? 'new' : subject.id}`}
                title={c.subjectDetailsSection}
            >
                <div className="sis-admission-sheet__row sis-admission-sheet__row--track5">
                    <SheetTextField
                        label={c.subjectName}
                        editing={fieldsEditable}
                        value={draft.name}
                        display={subject.name || '—'}
                        required
                        fieldClassName="sis-enrollment-record-sheet__field--wide"
                        inputRef={isCreate ? nameInputRef : undefined}
                        onChange={(value) => setField('name', value)}
                    />
                </div>
                <div className="sis-student-record-form__name-line sis-curriculum-subject-sheet__en-status-line">
                    <SheetTextField
                        label={c.subjectNameEn}
                        editing={fieldsEditable}
                        value={draft.name_en}
                        display={subject.name_en?.trim() ? subject.name_en : '—'}
                        dir="ltr"
                        fieldClassName="sis-curriculum-subject-sheet__en-name-field"
                        onChange={(value) => setField('name_en', value)}
                    />
                    {isCreate ? (
                        <div className="sis-admission-sheet__field sis-student-record-form__status-field">
                            <span className="sis-admission-sheet__label">{c.subjectStatus}</span>
                            <div
                                className={filledControlClass(true, false)}
                                aria-readonly="true"
                            >
                                {i18n.status.active}
                            </div>
                        </div>
                    ) : (
                        <SheetListField
                            label={c.subjectStatus}
                            editing={fieldsEditable}
                            value={draft.status}
                            display={statusDisplay}
                            options={statusOptions}
                            fieldClassName="sis-student-record-form__status-field"
                            onChange={(value) => setField('status', value)}
                        />
                    )}
                </div>
            </SheetSection>

            <SheetSection
                id={`subject-grades-${isCreate ? 'new' : subject.id}`}
                title={c.subjectGradesSection}
            >
                <div className="sis-admission-sheet__row sis-admission-sheet__row--track5 sis-enrollment-record-sheet__placement-row">
                    <SheetListField
                        label={c.subjectType}
                        editing={fieldsEditable}
                        value={draft.subject_type}
                        display={typeDisplay}
                        options={typeOptions}
                        fieldClassName="sis-enrollment-record-sheet__field--wide"
                        onChange={(value) => setField('subject_type', value)}
                    />
                    <SheetTextField
                        label={c.creditHours}
                        editing={fieldsEditable}
                        value={draft.credit_hours}
                        display={
                            subject.credit_hours !== null ? String(subject.credit_hours) : '—'
                        }
                        type="number"
                        dir="ltr"
                        fieldClassName="sis-enrollment-record-sheet__field--narrow"
                        onChange={(value) => setField('credit_hours', value)}
                    />
                    <SheetTextField
                        label={c.maxGrade}
                        editing={fieldsEditable}
                        value={draft.max_grade}
                        display={String(subject.max_grade)}
                        type="number"
                        dir="ltr"
                        required
                        fieldClassName="sis-enrollment-record-sheet__field--narrow"
                        onChange={(value) => setField('max_grade', value)}
                    />
                    <SheetTextField
                        label={c.passGrade}
                        editing={fieldsEditable}
                        value={draft.pass_grade}
                        display={String(subject.pass_grade)}
                        type="number"
                        dir="ltr"
                        required
                        fieldClassName="sis-enrollment-record-sheet__field--narrow"
                        onChange={(value) => setField('pass_grade', value)}
                    />
                </div>
                <div className="sis-admission-sheet__row sis-admission-sheet__row--track5">
                    <SheetTextField
                        label={c.prerequisitesField}
                        editing={fieldsEditable}
                        value={draft.prerequisites}
                        display={subject.prerequisites?.trim() ? subject.prerequisites : '—'}
                        fieldClassName="sis-enrollment-record-sheet__field--wide"
                        onChange={(value) => setField('prerequisites', value)}
                    />
                </div>
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
                                    {saving ? i18n.common.saving : c.save}
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
                            ? c.confirmDeactivateSubject
                            : c.confirmReactivateSubject
                    }
                    description={
                        pendingStatus === 2
                            ? c.confirmDeactivateSubject
                            : c.confirmReactivateSubject
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
    subjects?: CurriculumSubjectSheetSubject[];
    mode?: CurriculumSubjectSheetMode;
    canManage: boolean;
    initialEditing?: boolean;
    onClose: () => void;
};

/**
 * Subject view/edit/create sheet — same chrome / sizing / actions SSOT as enrollment record sheet.
 */
export function CurriculumSubjectSheetDialog({
    subjects = [],
    mode = 'edit',
    canManage,
    initialEditing = false,
    onClose,
}: Props) {
    const i18n = t();
    const c = i18n.curriculum;
    const isCreate = mode === 'create';
    const list = isCreate ? [blankCurriculumSubject()] : subjects;
    const count = list.length;
    const viewTitle = isCreate ? c.createSubject : c.viewSubjectTitle;
    const dialogTitle = isCreate
        ? c.createSubject
        : count > 1
          ? `${viewTitle} (${count})`
          : viewTitle;

    const { contentRef, heroDragProps, bringToFront, resizeHandles } = useSmoothDialogDrag(true, {
        resizable: true,
        minSize: { width: 520, height: 360 },
    });
    const { maximized, toggleMaximize, maximizeClassName } = useSheetMaximize(contentRef);
    const scrollerRef = useRef<HTMLDivElement | null>(null);
    const subjectIdsKey = list.map((subject) => subject.id).join(',');

    useManySheetPageFit({
        contentRef,
        scrollerRef,
        count,
        maximized,
        mode: 'hug',
        resetKey: subjectIdsKey,
    });

    if (count === 0) {
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
                className={`sis-admission-draft-dialog sis-admission-sheet-dialog sis-student-sheet-dialog sis-enrollment-record-sheet sis-curriculum-subject-sheet${maximizeClassName}${
                    count > 1 ? ' sis-student-sheet-dialog--many' : ''
                }`}
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
                                <p className="sis-admission-sheet__hero-title">{dialogTitle}</p>
                            </div>
                            <div className="sis-admission-sheet__hero-logo">
                                <AppLogo tone="on-dark" className="sis-admission-sheet__logo" />
                            </div>
                        </header>
                        <div
                            ref={scrollerRef}
                            className="sis-student-sheet-dialog__many-scroller"
                            dir="rtl"
                            aria-label={dialogTitle}
                        >
                            {list.map((subject, index) => (
                                <div
                                    key={subject.id}
                                    className="sis-student-sheet-dialog__many-page"
                                    data-subject-page={index}
                                    aria-label={`${subject.name} (${index + 1}/${count})`}
                                >
                                    <SubjectRecordForm
                                        subject={subject}
                                        canManage={canManage}
                                        mode="edit"
                                        initialEditing={initialEditing}
                                        hideHero
                                        sheetTitle={subject.name}
                                        onClose={onClose}
                                    />
                                </div>
                            ))}
                        </div>
                    </>
                ) : (
                    list.map((subject) => (
                        <SubjectRecordForm
                            key={isCreate ? 'create-subject' : subject.id}
                            subject={subject}
                            canManage={canManage}
                            mode={isCreate ? 'create' : 'edit'}
                            initialEditing={isCreate || initialEditing}
                            sheetTitle={isCreate ? c.createSubject : subject.name}
                            onClose={onClose}
                            heroDragProps={maximized ? undefined : heroDragProps}
                            showWindowControls
                            maximized={maximized}
                            onMaximize={toggleMaximize}
                        />
                    ))
                )}
            </DialogContent>
        </Dialog>
    );
}
