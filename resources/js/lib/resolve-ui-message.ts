import { t } from '@/i18n';

type WorkflowCopy = {
    title: string;
    message: string;
    action_label?: string;
};

type ParsedMessage = {
    key: string;
    params: Record<string, string>;
};

/**
 * Arabic UI message SSOT resolver.
 * Prefer stable keys (domain codes / workflow.* / flash.*) → resources/js/i18n/ar.ts.
 * Backend should flash keys, not prose. Optional params: `flash.x?updated=3&skipped=1`
 */
export function resolveUiMessage(raw: string | null | undefined, fallback?: string): string {
    const i18n = t();
    const text = (raw ?? '').trim();
    const defaultMessage = fallback ?? i18n.errors.generic;

    if (text === '') {
        return defaultMessage;
    }

    const { key, params } = parseMessageKey(text);

    const fromCodes = i18n.errors.codes[key];
    if (fromCodes) {
        return applyParams(fromCodes, params);
    }

    const fromPath = resolveI18nPath(key);
    if (fromPath !== null) {
        return applyParams(fromPath, params);
    }

    const legacyKey = ENGLISH_TO_FLASH_KEY[text] ?? ENGLISH_TO_FLASH_KEY[key];
    if (legacyKey) {
        const legacyPath = resolveI18nPath(legacyKey);
        if (legacyPath !== null) {
            return applyParams(legacyPath, params);
        }
    }

    // Untranslated domain / flash keys must not leak as raw English keys.
    if (/^[a-z][a-z0-9_.]+$/i.test(key) && key.includes('.')) {
        return defaultMessage;
    }

    return text;
}

/** Resolve workflow banner copy from step key (SSOT = ar.workflow). */
export function resolveWorkflowCopy(step: string | null | undefined): WorkflowCopy | null {
    if (step === null || step === undefined || step.trim() === '') {
        return null;
    }

    const i18n = t();
    const map: Record<string, WorkflowCopy> = {
        'student.created': {
            title: i18n.workflow.studentCreatedTitle,
            message: i18n.workflow.studentCreatedMessage,
            action_label: i18n.workflow.studentCreatedAction,
        },
        'enrollment.create': {
            title: i18n.workflow.enrollmentRequiredTitle,
            message: i18n.workflow.enrollmentRequiredMessage,
        },
        'admission.convert': {
            title: i18n.workflow.convertSuccessTitle,
            message: i18n.workflow.convertSuccessMessage,
            action_label: i18n.workflow.convertActionStudents,
        },
        'admission.convert.bulk': {
            title: i18n.workflow.convertBulkTitle,
            message: i18n.workflow.convertBulkMessage,
            action_label: i18n.workflow.convertActionStudents,
        },
        'admission.convert.stay': {
            title: i18n.workflow.convertStayTitle,
            message: i18n.workflow.convertStayMessage,
            action_label: i18n.workflow.convertActionStudents,
        },
    };

    return map[step.trim()] ?? null;
}

function parseMessageKey(raw: string): ParsedMessage {
    const q = raw.indexOf('?');
    if (q === -1) {
        return { key: raw, params: {} };
    }

    const key = raw.slice(0, q);
    const params: Record<string, string> = {};
    const query = raw.slice(q + 1);

    for (const part of query.split('&')) {
        if (part === '') {
            continue;
        }
        const eq = part.indexOf('=');
        const name = eq === -1 ? part : part.slice(0, eq);
        const value = eq === -1 ? '' : part.slice(eq + 1);
        if (name !== '') {
            params[decodeURIComponent(name)] = decodeURIComponent(value);
        }
    }

    return { key, params };
}

function applyParams(template: string, params: Record<string, string>): string {
    if (Object.keys(params).length === 0) {
        return template;
    }

    return template.replace(/:([a-zA-Z_][a-zA-Z0-9_]*)/g, (match, name: string) => {
        return Object.prototype.hasOwnProperty.call(params, name) ? params[name] : match;
    });
}

function resolveI18nPath(path: string): string | null {
    if (!path.includes('.') || path.includes(' ')) {
        return null;
    }

    const parts = path.split('.');
    let current: unknown = t();

    for (const part of parts) {
        if (current === null || typeof current !== 'object') {
            return null;
        }

        current = (current as Record<string, unknown>)[part];
    }

    return typeof current === 'string' && current.trim() !== '' ? current : null;
}

/** Legacy English flash prose → flash.* keys (until callers are fully migrated). */
const ENGLISH_TO_FLASH_KEY: Record<string, string> = {
    'Profile updated.': 'flash.profileUpdated',
    'Password updated.': 'flash.passwordUpdated',
    'Student updated.': 'flash.studentUpdated',
    'Student statuses updated.': 'flash.studentStatusesUpdated',
    'Recommendation rejected.': 'flash.recommendationRejected',
    'Recommendation approved. Manual execution may be required for this action type.':
        'flash.recommendationApprovedManual',
    'Grade entered.': 'flash.grades.entered',
    'Grade corrected.': 'flash.grades.corrected',
    'Grade voided.': 'flash.grades.voided',
    'Grade finalized.': 'flash.grades.finalized',
    'Section attendance saved.': 'flash.attendance.sectionSaved',
    'Attendance session closed.': 'flash.attendance.sessionClosed',
    'Attendance session created.': 'flash.attendance.sessionCreated',
};
