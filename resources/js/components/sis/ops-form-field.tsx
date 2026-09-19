import type { ChangeEvent, ReactNode } from 'react';

type OpsFormFieldProps = {
    label: string;
    name: string;
    error?: string;
    children: ReactNode;
    hint?: string;
};

/** Accessible labeled field wrapper for ops forms (DIALOG/FORM Level 0). */
export function OpsFormField({ label, name, error, children, hint }: OpsFormFieldProps) {
    const errorId = error ? `${name}-error` : undefined;
    const hintId = hint ? `${name}-hint` : undefined;

    return (
        <label className="flex flex-col gap-1 text-sm" htmlFor={name}>
            <span>{label}</span>
            {children}
            {hint ? (
                <span id={hintId} className="text-muted-foreground text-xs">
                    {hint}
                </span>
            ) : null}
            {error ? (
                <span id={errorId} className="text-sm text-[color:var(--sis-powder-blush)]" role="alert">
                    {error}
                </span>
            ) : null}
        </label>
    );
}

type OpsTextInputProps = {
    name: string;
    type?: string;
    required?: boolean;
    defaultValue?: string | number;
    min?: number;
    dir?: 'ltr' | 'rtl';
    error?: string;
    placeholder?: string;
    className?: string;
    onChange?: (event: ChangeEvent<HTMLInputElement>) => void;
};

export function OpsTextInput({
    name,
    type = 'text',
    required,
    defaultValue,
    min,
    dir = 'ltr',
    error,
    placeholder,
    className,
    onChange,
}: OpsTextInputProps) {
    return (
        <input
            id={name}
            name={name}
            type={type}
            required={required}
            defaultValue={defaultValue}
            min={min}
            placeholder={placeholder}
            className={['sis-ops-hub__link min-h-11 px-3 py-2', className].filter(Boolean).join(' ')}
            dir={dir}
            aria-invalid={error ? true : undefined}
            aria-describedby={error ? `${name}-error` : undefined}
            onChange={onChange}
        />
    );
}
