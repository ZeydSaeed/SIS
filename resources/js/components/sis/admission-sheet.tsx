import type { ReactNode } from 'react';

type SheetSectionProps = {
    title: string;
    tone?: 'accent' | 'dark';
    id?: string;
    children: ReactNode;
};

type SheetFieldProps = {
    label: string;
    name?: string;
    error?: string;
    required?: boolean;
    className?: string;
    children: ReactNode;
};

/** Admission / student sheet section — banner + body (layout SSOT).
 * Fields flow in one section grid; column count adapts to sheet width and
 * extra columns pull cells from the following logical row (see app.css
 * `@container sis-sheet` + `display: contents` on rows).
 */
export function SheetSection({ title, tone = 'accent', id, children }: SheetSectionProps) {
    return (
        <section className="sis-admission-sheet__section" aria-labelledby={id}>
            <h3 id={id} className={`sis-admission-sheet__banner sis-admission-sheet__banner--${tone}`}>
                {title}
            </h3>
            <div className="sis-admission-sheet__body">{children}</div>
        </section>
    );
}

/** Admission / student sheet field — label + control + error (layout SSOT). */
export function SheetField({
    label,
    name,
    error,
    required = false,
    className = '',
    children,
}: SheetFieldProps) {
    return (
        <label
            className={`sis-admission-sheet__field ${className}`.trim()}
            htmlFor={name}
        >
            <span className="sis-admission-sheet__label">
                {label}
                {required ? (
                    <span aria-hidden="true">
                        {' '}
                        *
                    </span>
                ) : null}
            </span>
            {children}
            {error ? (
                <span
                    id={name ? `${name}-error` : undefined}
                    className="sis-admission-sheet__error"
                    role="alert"
                >
                    {error}
                </span>
            ) : null}
        </label>
    );
}
