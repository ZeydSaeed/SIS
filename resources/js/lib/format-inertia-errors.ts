import { t } from '@/i18n';

export type InertiaErrorBag = Record<string, string | string[] | undefined>;

/** Flatten Inertia / Laravel validation bags into unique message lines. */
export function collectInertiaErrorMessages(errors: InertiaErrorBag | undefined | null): string[] {
    if (!errors) {
        return [];
    }

    const messages: string[] = [];

    for (const value of Object.values(errors)) {
        if (value === undefined || value === null || value === '') {
            continue;
        }

        if (Array.isArray(value)) {
            for (const item of value) {
                const text = String(item).trim();
                if (text !== '') {
                    messages.push(text);
                }
            }
            continue;
        }

        const text = String(value).trim();
        if (text !== '') {
            messages.push(text);
        }
    }

    return Array.from(new Set(messages));
}

/** Map known domain error codes (and fall back) to Arabic UI copy. */
export function resolveErrorMessage(raw: string | null | undefined, fallback?: string): string {
    const i18n = t();
    const text = (raw ?? '').trim();
    const defaultMessage = fallback ?? i18n.errors.generic;

    if (text === '') {
        return defaultMessage;
    }

    const coded = i18n.errors.codes[text as keyof typeof i18n.errors.codes];
    if (coded) {
        return coded;
    }

    // Domain codes look like "enrollment.invalid_status" with no spaces.
    if (/^[a-z][a-z0-9_.]+$/i.test(text) && text.includes('.')) {
        return defaultMessage;
    }

    return text;
}

export function summarizeInertiaErrors(
    errors: InertiaErrorBag | undefined | null,
    fallback?: string,
): { description: string; details: string[] } {
    const i18n = t();
    const messages = collectInertiaErrorMessages(errors).map((message) =>
        resolveErrorMessage(message, message),
    );

    if (messages.length === 0) {
        return {
            description: fallback ?? i18n.errors.generic,
            details: [],
        };
    }

    if (messages.length === 1) {
        return {
            description: messages[0],
            details: [],
        };
    }

    return {
        description: fallback ?? i18n.errors.validationSummary,
        details: messages,
    };
}
