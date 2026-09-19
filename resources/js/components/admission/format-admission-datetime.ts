import { t } from '@/i18n';

export type AdmissionDateTimeParts = {
    day: string;
    month: string;
    year: string;
    hour: string;
    minute: string;
    period: 'am' | 'pm';
};

/** Split ISO/Postgres datetime into D/M/YYYY parts and 12h time — same as the period card. */
export function parseAdmissionDateTime(value: string): AdmissionDateTimeParts | null {
    const match = value.match(/^(\d{4})-(\d{2})-(\d{2})[T ](\d{2}):(\d{2})/);
    if (match === null) {
        return null;
    }

    const hour24 = Number(match[4]);

    return {
        year: match[1],
        month: String(Number(match[2])),
        day: String(Number(match[3])),
        hour: String(hour24 % 12 === 0 ? 12 : hour24 % 12),
        minute: match[5],
        period: hour24 >= 12 ? 'pm' : 'am',
    };
}

export function formatAdmissionDateTime(value: string): string {
    const parts = parseAdmissionDateTime(value);
    if (parts === null) {
        return value;
    }

    const i18n = t().admission;
    const periodLabel = parts.period === 'pm' ? i18n.timePm : i18n.timeAm;

    return `${parts.day}/${parts.month}/${parts.year} ${parts.hour}:${parts.minute} ${periodLabel}`;
}
