import { router } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { SisListSelect } from '@/components/sis/sis-list-select';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogTitle } from '@/components/ui/dialog';
import { t } from '@/i18n';

export type Option = { id: number; name: string };

/** One request key per submitted form: a repeated click or a retry replays instead of creating twice. */
export function idempotencyKey(prefix: string): string {
    return typeof crypto !== 'undefined' && 'randomUUID' in crypto
        ? `${prefix}-${crypto.randomUUID()}`
        : `${prefix}-${Date.now()}-${Math.random().toString(16).slice(2)}`;
}

/** POST through Inertia with an idempotency key; field errors come back from the FormRequest, rule errors as flash. */
export function submit(
    url: string,
    data: Record<string, string | number | null>,
    key: string,
    callbacks: {
        onSuccess?: () => void;
        onErrors: (e: Record<string, string>) => void;
        onFinish: () => void;
    },
): void {
    router.post(url, data, {
        preserveScroll: true,
        headers: { 'X-Idempotency-Key': key },
        onSuccess: () => callbacks.onSuccess?.(),
        onError: (errors) =>
            callbacks.onErrors(errors as Record<string, string>),
        onFinish: callbacks.onFinish,
    });
}

function Field({
    label,
    error,
    children,
}: {
    label: string;
    error?: string;
    children: React.ReactNode;
}) {
    return (
        <label className="flex min-w-0 flex-col gap-1 text-sm">
            <span>{label}</span>
            {children}
            {error ? (
                <span role="alert" className="text-destructive text-xs">
                    {error}
                </span>
            ) : null}
        </label>
    );
}

const inputClass = 'sis-ops-hub__link min-h-11 px-3 py-2';

function FormDialog({
    open,
    title,
    busy,
    submitLabel,
    onClose,
    onSubmit,
    children,
}: {
    open: boolean;
    title: string;
    busy: boolean;
    submitLabel: string;
    onClose: () => void;
    onSubmit: () => void;
    children: React.ReactNode;
}) {
    const i18n = t();

    return (
        <Dialog
            open={open}
            onOpenChange={(next) => {
                if (!next && !busy) onClose();
            }}
            modal={false}
        >
            <DialogContent
                className="sis-admission-status-reason-dialog sm:max-w-lg"
                showOverlay={false}
            >
                <DialogTitle>{title}</DialogTitle>
                <form
                    className="flex flex-col gap-3"
                    onSubmit={(event) => {
                        event.preventDefault();
                        if (!busy) onSubmit();
                    }}
                >
                    {children}
                    <div className="sis-admission-status-reason-dialog__actions">
                        <Button
                            type="button"
                            variant="outline"
                            disabled={busy}
                            onClick={onClose}
                        >
                            {i18n.dialog.cancel}
                        </Button>
                        <Button type="submit" disabled={busy}>
                            {busy ? i18n.exams.admin.saving : submitLabel}
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
}

export type ExamValues = {
    name: string;
    term_id: number | null;
    exam_type_id: number | null;
    start_date: string;
    end_date: string;
};

/**
 * Create (POST /exams) or edit (POST /exams/{id}/update) an exam. Type and term are offered only where the server
 * allows them (`allowTypeAndTerm`: a new or Draft exam).
 */
export function ExamFormDialog({
    open,
    mode,
    url,
    academicYearId,
    initial,
    terms,
    examTypes,
    allowTypeAndTerm,
    onClose,
}: {
    open: boolean;
    mode: 'create' | 'edit';
    url: string;
    academicYearId: number | null;
    initial: ExamValues;
    terms: Option[];
    examTypes: Option[];
    allowTypeAndTerm: boolean;
    onClose: () => void;
}) {
    const a = t().exams.admin;
    const [values, setValues] = useState<ExamValues>(initial);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [busy, setBusy] = useState(false);
    const [key, setKey] = useState(() => idempotencyKey('exam'));

    useEffect(() => {
        if (open) {
            setValues(initial);
            setErrors({});
            setKey(idempotencyKey('exam'));
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps -- reset only when the dialog opens
    }, [open]);

    const set = <K extends keyof ExamValues>(field: K, value: ExamValues[K]) =>
        setValues((current) => ({ ...current, [field]: value }));

    const send = () => {
        setBusy(true);
        const data: Record<string, string | number | null> = {
            name: values.name.trim(),
            start_date: values.start_date,
            end_date: values.end_date,
        };
        if (mode === 'create') {
            data.academic_year_id = academicYearId;
        }
        if (allowTypeAndTerm) {
            data.term_id = values.term_id;
            data.exam_type_id = values.exam_type_id;
        }
        submit(url, data, key, {
            onSuccess: onClose,
            onErrors: setErrors,
            onFinish: () => setBusy(false),
        });
    };

    return (
        <FormDialog
            open={open}
            title={mode === 'create' ? a.newExam : a.editExam}
            busy={busy}
            submitLabel={mode === 'create' ? a.create : a.save}
            onClose={onClose}
            onSubmit={send}
        >
            <Field label={a.examName} error={errors.name}>
                <input
                    className={inputClass}
                    value={values.name}
                    maxLength={255}
                    required
                    onChange={(e) => set('name', e.target.value)}
                />
            </Field>
            {allowTypeAndTerm ? (
                <>
                    <Field label={a.term} error={errors.term_id}>
                        <SisListSelect
                            ariaLabel={a.term}
                            value={values.term_id?.toString() ?? ''}
                            includeBlank
                            options={terms.map((o) => ({
                                value: String(o.id),
                                label: o.name,
                            }))}
                            onChange={(v) =>
                                set('term_id', v === '' ? null : Number(v))
                            }
                        />
                    </Field>
                    <Field label={a.examType} error={errors.exam_type_id}>
                        <SisListSelect
                            ariaLabel={a.examType}
                            value={values.exam_type_id?.toString() ?? ''}
                            includeBlank
                            options={examTypes.map((o) => ({
                                value: String(o.id),
                                label: o.name,
                            }))}
                            onChange={(v) =>
                                set('exam_type_id', v === '' ? null : Number(v))
                            }
                        />
                    </Field>
                </>
            ) : null}
            <div className="grid gap-3 sm:grid-cols-2">
                <Field label={a.startDate} error={errors.start_date}>
                    <input
                        type="date"
                        className={inputClass}
                        value={values.start_date}
                        required
                        onChange={(e) => set('start_date', e.target.value)}
                    />
                </Field>
                <Field label={a.endDate} error={errors.end_date}>
                    <input
                        type="date"
                        className={inputClass}
                        value={values.end_date}
                        required
                        onChange={(e) => set('end_date', e.target.value)}
                    />
                </Field>
            </div>
        </FormDialog>
    );
}

export type SessionValues = {
    subject_id: number | null;
    session_date: string;
    start_time: string;
    end_time: string;
    max_grade: string;
    pass_grade: string;
};

/** `HH:MM` from the time input → `HH:MM:SS` the FormRequest expects. */
const seconds = (time: string): string =>
    time.length === 5 ? `${time}:00` : time;

/** Create (POST /exams/{id}/sessions) or edit (POST …/sessions/{s}/update) a session; the subject is fixed once created. */
export function SessionFormDialog({
    open,
    mode,
    url,
    initial,
    subjects,
    onClose,
}: {
    open: boolean;
    mode: 'create' | 'edit';
    url: string;
    initial: SessionValues;
    subjects: Option[];
    onClose: () => void;
}) {
    const a = t().exams.admin;
    const [values, setValues] = useState<SessionValues>(initial);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [busy, setBusy] = useState(false);
    const [key, setKey] = useState(() => idempotencyKey('session'));

    useEffect(() => {
        if (open) {
            setValues(initial);
            setErrors({});
            setKey(idempotencyKey('session'));
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps -- reset only when the dialog opens
    }, [open]);

    const set = <K extends keyof SessionValues>(
        field: K,
        value: SessionValues[K],
    ) => setValues((current) => ({ ...current, [field]: value }));

    const send = () => {
        setBusy(true);
        const data: Record<string, string | number | null> = {
            session_date: values.session_date,
            start_time: seconds(values.start_time),
            end_time: seconds(values.end_time),
            max_grade:
                values.max_grade === '' ? null : Number(values.max_grade),
            pass_grade:
                values.pass_grade === '' ? null : Number(values.pass_grade),
        };
        if (mode === 'create') {
            data.subject_id = values.subject_id;
        }
        submit(url, data, key, {
            onSuccess: onClose,
            onErrors: setErrors,
            onFinish: () => setBusy(false),
        });
    };

    return (
        <FormDialog
            open={open}
            title={mode === 'create' ? a.newSession : a.editSession}
            busy={busy}
            submitLabel={mode === 'create' ? a.addSession : a.save}
            onClose={onClose}
            onSubmit={send}
        >
            {mode === 'create' ? (
                <Field label={a.subject} error={errors.subject_id}>
                    <SisListSelect
                        ariaLabel={a.subject}
                        value={values.subject_id?.toString() ?? ''}
                        includeBlank
                        options={subjects.map((o) => ({
                            value: String(o.id),
                            label: o.name,
                        }))}
                        onChange={(v) =>
                            set('subject_id', v === '' ? null : Number(v))
                        }
                    />
                </Field>
            ) : null}
            <Field label={a.sessionDate} error={errors.session_date}>
                <input
                    type="date"
                    className={inputClass}
                    value={values.session_date}
                    required
                    onChange={(e) => set('session_date', e.target.value)}
                />
            </Field>
            <div className="grid gap-3 sm:grid-cols-2">
                <Field label={a.startTime} error={errors.start_time}>
                    <input
                        type="time"
                        className={inputClass}
                        value={values.start_time.slice(0, 5)}
                        required
                        onChange={(e) => set('start_time', e.target.value)}
                    />
                </Field>
                <Field label={a.endTime} error={errors.end_time}>
                    <input
                        type="time"
                        className={inputClass}
                        value={values.end_time.slice(0, 5)}
                        required
                        onChange={(e) => set('end_time', e.target.value)}
                    />
                </Field>
                <Field label={a.maxGrade} error={errors.max_grade}>
                    <input
                        type="number"
                        min={1}
                        inputMode="numeric"
                        className={inputClass}
                        value={values.max_grade}
                        onChange={(e) => set('max_grade', e.target.value)}
                    />
                </Field>
                <Field label={a.passGrade} error={errors.pass_grade}>
                    <input
                        type="number"
                        min={0}
                        inputMode="numeric"
                        className={inputClass}
                        value={values.pass_grade}
                        onChange={(e) => set('pass_grade', e.target.value)}
                    />
                </Field>
            </div>
        </FormDialog>
    );
}
