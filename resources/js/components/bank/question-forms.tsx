import { router } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { idempotencyKey } from '@/components/exams/exam-forms';
import { SisListSelect } from '@/components/sis/sis-list-select';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogTitle } from '@/components/ui/dialog';
import { t } from '@/i18n';

export type QuestionType =
    | 'single_choice'
    | 'multiple_choice'
    | 'true_false'
    | 'long_answer';

export type SubjectOption = {
    id: number;
    name: string;
    name_en: string | null;
    code: string;
};

/** Values of the form; options are plain texts and `correct` their 0-based indexes (the server assigns A, B, …). */
export type QuestionValues = {
    subject_id: number | null;
    type: QuestionType;
    stem: string;
    max_mark: string;
    options: string[];
    correct: number[];
    answer: boolean | null;
    guidance: string;
};

export const OPTIONS_MIN = 2;
export const OPTIONS_MAX = 8;

export const emptyQuestion = (subjectId: number | null): QuestionValues => ({
    subject_id: subjectId,
    type: 'single_choice',
    stem: '',
    max_mark: '1',
    options: ['', ''],
    correct: [],
    answer: null,
    guidance: '',
});

type Payload = Record<
    string,
    string | number | boolean | null | string[] | number[]
>;

/** POST through Inertia with an idempotency key; field errors come back from the FormRequest, rule errors as flash. */
export function send(
    url: string,
    data: Payload,
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

const inputClass = 'sis-ops-hub__link min-h-11 px-3 py-2';

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
                className="sis-admission-status-reason-dialog max-h-[90vh] overflow-y-auto sm:max-w-xl"
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
                            {busy ? i18n.bank.saving : submitLabel}
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
}

/**
 * Create (POST /bank/questions, with a subject) or edit a draft (POST /bank/questions/versions/{id}/update).
 * Plain text only (DEC-049); the per-type rules are checked again on the server.
 */
export function QuestionFormDialog({
    open,
    mode,
    url,
    initial,
    subjects,
    types,
    onClose,
}: {
    open: boolean;
    mode: 'create' | 'edit';
    url: string;
    initial: QuestionValues;
    subjects: SubjectOption[];
    types: QuestionType[];
    onClose: () => void;
}) {
    const b = t().bank;
    const [values, setValues] = useState<QuestionValues>(initial);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [busy, setBusy] = useState(false);
    const [key, setKey] = useState(() => idempotencyKey('bank'));

    useEffect(() => {
        if (open) {
            setValues(initial);
            setErrors({});
            setKey(idempotencyKey('bank'));
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps -- reset only when the dialog opens
    }, [open]);

    const set = <K extends keyof QuestionValues>(
        field: K,
        value: QuestionValues[K],
    ) => setValues((current) => ({ ...current, [field]: value }));

    const hasOptions =
        values.type === 'single_choice' || values.type === 'multiple_choice';

    const setOption = (index: number, text: string) =>
        set(
            'options',
            values.options.map((o, i) => (i === index ? text : o)),
        );
    const removeOption = (index: number) =>
        setValues((current) => ({
            ...current,
            options: current.options.filter((_, i) => i !== index),
            correct: current.correct
                .filter((c) => c !== index)
                .map((c) => (c > index ? c - 1 : c)),
        }));
    const toggleCorrect = (index: number) =>
        set(
            'correct',
            values.type === 'single_choice'
                ? [index]
                : values.correct.includes(index)
                  ? values.correct.filter((c) => c !== index)
                  : [...values.correct, index].sort((x, y) => x - y),
        );

    const submit = () => {
        setBusy(true);
        const data: Payload = {
            type: values.type,
            stem: values.stem,
            max_mark: values.max_mark.trim(),
        };
        if (mode === 'create') {
            data.subject_id = values.subject_id;
        }
        if (hasOptions) {
            data.options = values.options;
            data.correct = values.correct;
        } else if (values.type === 'true_false') {
            data.answer = values.answer;
        } else {
            data.guidance = values.guidance;
        }
        send(url, data, key, {
            onSuccess: onClose,
            onErrors: setErrors,
            onFinish: () => setBusy(false),
        });
    };

    const optionError = Object.entries(errors).find(([field]) =>
        field.startsWith('options'),
    )?.[1];
    const correctError = Object.entries(errors).find(([field]) =>
        field.startsWith('correct'),
    )?.[1];

    return (
        <FormDialog
            open={open}
            title={mode === 'create' ? b.newQuestion : b.editDraft}
            busy={busy}
            submitLabel={mode === 'create' ? b.create : b.save}
            onClose={onClose}
            onSubmit={submit}
        >
            {mode === 'create' ? (
                <Field label={b.subject} error={errors.subject_id}>
                    <SisListSelect
                        ariaLabel={b.subject}
                        value={values.subject_id?.toString() ?? ''}
                        includeBlank
                        options={subjects.map((s) => ({
                            value: String(s.id),
                            label: s.name,
                        }))}
                        onChange={(v) =>
                            set('subject_id', v === '' ? null : Number(v))
                        }
                    />
                </Field>
            ) : null}
            <Field label={b.type} error={errors.type}>
                <SisListSelect
                    ariaLabel={b.type}
                    value={values.type}
                    options={types.map((type) => ({
                        value: type,
                        label: b.types[type],
                    }))}
                    onChange={(v) =>
                        setValues((current) => ({
                            ...current,
                            type: v as QuestionType,
                            correct:
                                v === 'single_choice'
                                    ? current.correct.slice(0, 1)
                                    : current.correct,
                        }))
                    }
                />
            </Field>
            <Field label={b.stem} error={errors.stem}>
                <textarea
                    className={`${inputClass} min-h-28`}
                    value={values.stem}
                    maxLength={5000}
                    required
                    onChange={(event) => set('stem', event.target.value)}
                />
            </Field>
            <Field label={b.maxMark} error={errors.max_mark}>
                <input
                    className={inputClass}
                    inputMode="decimal"
                    dir="ltr"
                    value={values.max_mark}
                    required
                    onChange={(event) => set('max_mark', event.target.value)}
                />
            </Field>

            {hasOptions ? (
                <fieldset className="flex flex-col gap-2 text-sm">
                    <legend className="mb-1">
                        {values.type === 'single_choice'
                            ? b.optionsSingleHint
                            : b.optionsMultipleHint}
                    </legend>
                    {values.options.map((option, index) => (
                        <div
                            // eslint-disable-next-line react/no-array-index-key -- options are positional
                            key={index}
                            className="flex items-center gap-2"
                        >
                            <input
                                type={
                                    values.type === 'single_choice'
                                        ? 'radio'
                                        : 'checkbox'
                                }
                                name="bank-correct"
                                className="size-5"
                                checked={values.correct.includes(index)}
                                aria-label={`${b.correct} ${b.optionKeys[index]}`}
                                onChange={() => toggleCorrect(index)}
                            />
                            <span aria-hidden className="w-5 text-center">
                                {b.optionKeys[index]}
                            </span>
                            <input
                                className={`${inputClass} min-w-0 flex-1`}
                                value={option}
                                maxLength={1000}
                                aria-label={`${b.option} ${b.optionKeys[index]}`}
                                onChange={(event) =>
                                    setOption(index, event.target.value)
                                }
                            />
                            <Button
                                type="button"
                                variant="outline"
                                disabled={values.options.length <= OPTIONS_MIN}
                                onClick={() => removeOption(index)}
                                aria-label={`${b.removeOption} ${b.optionKeys[index]}`}
                            >
                                ×
                            </Button>
                        </div>
                    ))}
                    <div>
                        <Button
                            type="button"
                            variant="outline"
                            disabled={values.options.length >= OPTIONS_MAX}
                            onClick={() =>
                                set('options', [...values.options, ''])
                            }
                        >
                            {b.addOption}
                        </Button>
                    </div>
                    {optionError || correctError ? (
                        <span role="alert" className="text-destructive text-xs">
                            {optionError ?? correctError}
                        </span>
                    ) : null}
                </fieldset>
            ) : null}

            {values.type === 'true_false' ? (
                <fieldset className="flex flex-col gap-2 text-sm">
                    <legend className="mb-1">{b.answer}</legend>
                    {[true, false].map((choice) => (
                        <label
                            key={String(choice)}
                            className="flex min-h-11 items-center gap-2"
                        >
                            <input
                                type="radio"
                                name="bank-answer"
                                className="size-5"
                                checked={values.answer === choice}
                                onChange={() => set('answer', choice)}
                            />
                            {choice ? b.answerTrue : b.answerFalse}
                        </label>
                    ))}
                    {errors.answer ? (
                        <span role="alert" className="text-destructive text-xs">
                            {errors.answer}
                        </span>
                    ) : null}
                </fieldset>
            ) : null}

            {values.type === 'long_answer' ? (
                <Field label={b.guidance} error={errors.guidance}>
                    <textarea
                        className={`${inputClass} min-h-24`}
                        value={values.guidance}
                        maxLength={5000}
                        onChange={(event) =>
                            set('guidance', event.target.value)
                        }
                    />
                </Field>
            ) : null}
        </FormDialog>
    );
}

/** A decision note (reject: required) or a retirement reason, posted with its own key. */
export function NoteDialog({
    open,
    title,
    label,
    submitLabel,
    required,
    url,
    extra,
    field,
    onClose,
}: {
    open: boolean;
    title: string;
    label: string;
    submitLabel: string;
    required: boolean;
    url: string;
    extra: Payload;
    field: 'note' | 'reason';
    onClose: () => void;
}) {
    const [text, setText] = useState('');
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [busy, setBusy] = useState(false);
    const [key, setKey] = useState(() => idempotencyKey('bank'));

    useEffect(() => {
        if (open) {
            setText('');
            setErrors({});
            setKey(idempotencyKey('bank'));
        }
    }, [open]);

    return (
        <FormDialog
            open={open}
            title={title}
            busy={busy}
            submitLabel={submitLabel}
            onClose={onClose}
            onSubmit={() => {
                setBusy(true);
                send(url, { ...extra, [field]: text }, key, {
                    onSuccess: onClose,
                    onErrors: setErrors,
                    onFinish: () => setBusy(false),
                });
            }}
        >
            <Field label={label} error={errors[field]}>
                <textarea
                    className={`${inputClass} min-h-24`}
                    value={text}
                    maxLength={2000}
                    required={required}
                    onChange={(event) => setText(event.target.value)}
                />
            </Field>
        </FormDialog>
    );
}
