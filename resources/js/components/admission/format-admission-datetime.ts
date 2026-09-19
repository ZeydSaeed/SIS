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
    const withTime = value.match(/^(\d{4})-(\d{2})-(\d{2})[T ](\d{2}):(\d{2})/);
    if (withTime !== null) {
        const hour24 = Number(withTime[4]);

        return {
            year: withTime[1],
            month: String(Number(withTime[2])),
            day: String(Number(withTime[3])),
            hour: String(hour24 % 12 === 0 ? 12 : hour24 % 12),
            minute: withTime[5],
            period: hour24 >= 12 ? 'pm' : 'am',
        };
    }

    const dateOnly = value.match(/^(\d{4})-(\d{2})-(\d{2})$/);
    if (dateOnly === null) {
        return null;
    }

    return {
        year: dateOnly[1],
        month: String(Number(dateOnly[2])),
        day: String(Number(dateOnly[3])),
        hour: '12',
        minute: '00',
        period: 'am',
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

/** Local now as `YYYY-MM-DDTHH:mm` for parseAdmissionDateTime. */
export function admissionDateTimeNow(): string {
    const now = new Date();
    const year = String(now.getFullYear());
    const month = String(now.getMonth() + 1).padStart(2, '0');
    const day = String(now.getDate()).padStart(2, '0');
    const hour = String(now.getHours()).padStart(2, '0');
    const minute = String(now.getMinutes()).padStart(2, '0');

    return `${year}-${month}-${day}T${hour}:${minute}`;
}
