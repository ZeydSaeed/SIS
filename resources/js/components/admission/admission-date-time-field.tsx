import { useState } from 'react';
import { parseAdmissionDateTime } from '@/components/admission/format-admission-datetime';
import { SisListSelect } from '@/components/sis/sis-list-select';
import { t } from '@/i18n';

type DayPeriod = 'am' | 'pm';

type Props = {
    name: string;
    error?: string;
    required?: boolean;
    dateOnly?: boolean;
    defaultValue?: string;
    idPrefix?: string;
    onValueChange?: (value: string) => void;
    boundStart?: string;
    boundEnd?: string;
};

const DAYS = Array.from({ length: 31 }, (_, day) => String(day + 1));
const MONTHS = Array.from({ length: 12 }, (_, month) => String(month + 1));
const YEARS = Array.from({ length: 21 }, (_, offset) => String(2020 + offset)); // 2020–2040
const HOURS = ['1', '2', '3', '4', '5', '6', '7', '8', '9', '10', '11', '12'] as const;
const MINUTES = Array.from({ length: 60 }, (_, minute) => String(minute).padStart(2, '0'));

function toTwentyFourHour(hour12: number, period: DayPeriod): number {
    if (period === 'am') {
        return hour12 === 12 ? 0 : hour12;
    }

    return hour12 === 12 ? 12 : hour12 + 12;
}

function combineDateTime(
    day: string,
    month: string,
    year: string,
    hour12: string,
    minute: string,
    period: DayPeriod,
): string {
    if (day === '' || month === '' || year === '') {
        return '';
    }

    const hour = String(toTwentyFourHour(Number(hour12), period)).padStart(2, '0');

    return `${year}-${month.padStart(2, '0')}-${day.padStart(2, '0')}T${hour}:${minute}`;
}

function dateOnly(value?: string): string | null {
    if (!value) {
        return null;
    }

    const match = /^(\d{4}-\d{2}-\d{2})/.exec(value);

    return match ? match[1] : null;
}

function monthsWithinBounds(year: string, boundStart?: string, boundEnd?: string): string[] {
    const start = dateOnly(boundStart);
    const end = dateOnly(boundEnd);

    if (year === '' || start === null || end === null) {
        return MONTHS;
    }

    const startYear = start.slice(0, 4);
    const endYear = end.slice(0, 4);
    const startMonth = Number(start.slice(5, 7));
    const endMonth = Number(end.slice(5, 7));
    let from = 1;
    let to = 12;

    if (year === startYear) {
        from = startMonth;
    }

    if (year === endYear) {
        to = endMonth;
    }

    if (from > to) {
        return [];
    }

    return Array.from({ length: to - from + 1 }, (_, index) => String(from + index));
}

function daysWithinBounds(
    year: string,
    month: string,
    boundStart?: string,
    boundEnd?: string,
): string[] {
    if (year === '' || month === '') {
        return DAYS;
    }

    const monthCount = new Date(Number(year), Number(month), 0).getDate();
    const start = dateOnly(boundStart);
    const end = dateOnly(boundEnd);
    let from = 1;
    let to = monthCount;
    const paddedMonth = month.padStart(2, '0');

    if (start !== null && year === start.slice(0, 4) && paddedMonth === start.slice(5, 7)) {
        from = Number(start.slice(8, 10));
    }

    if (end !== null && year === end.slice(0, 4) && paddedMonth === end.slice(5, 7)) {
        to = Math.min(to, Number(end.slice(8, 10)));
    }

    if (from > to) {
        return [];
    }

    return Array.from({ length: to - from + 1 }, (_, index) => String(from + index));
}

type DatePartProps = {
    id?: string;
    className: string;
    value: string;
    options: readonly string[];
    required?: boolean;
    includeBlank?: boolean;
    ariaLabel: string;
    onChange: (value: string) => void;
};

function DatePart({
    id,
    className,
    value,
    options,
    required = false,
    includeBlank = true,
    ariaLabel,
    onChange,
}: DatePartProps) {
    return (
        <span className={`sis-admission-datetime__unit ${className}`}>
            <span className="sis-admission-datetime__face" aria-hidden="true">
                {value}
            </span>
            <SisListSelect
                variant="overlay"
                id={id}
                value={value}
                options={options.map((item) => ({ value: item, label: item }))}
                includeBlank={includeBlank}
                required={required}
                dir="ltr"
                ariaLabel={ariaLabel}
                onChange={onChange}
            />
        </span>
    );
}

/** RTL date-on-right (D/M/Y) / 12-hour time-on-left. Digits stay English. */
export function AdmissionDateTimeField({
    name,
    error,
    required = false,
    dateOnly = false,
    defaultValue,
    idPrefix,
    onValueChange,
    boundStart,
    boundEnd,
}: Props) {
    const i18n = t().admission;
    const initial = parseAdmissionDateTime(defaultValue ?? '');
    const [day, setDay] = useState(initial?.day ?? '');
    const [month, setMonth] = useState(initial?.month ?? '');
    const [year, setYear] = useState(initial?.year ?? '');
    const [hour, setHour] = useState(initial?.hour ?? '8');
    const [minute, setMinute] = useState(initial?.minute ?? '00');
    const [period, setPeriod] = useState<DayPeriod>(initial?.period ?? 'am');
    const describedBy = error ? `${name}-error` : undefined;
    const value = combineDateTime(day, month, year, hour, minute, period);
    const controlId = idPrefix ? `${idPrefix}-${name}` : name;
    const monthOptions = monthsWithinBounds(year, boundStart, boundEnd);
    const dayOptions = daysWithinBounds(year, month, boundStart, boundEnd);

    const emit = (
        nextDay: string,
        nextMonth: string,
        nextYear: string,
        nextHour: string,
        nextMinute: string,
        nextPeriod: DayPeriod,
    ) => {
        onValueChange?.(combineDateTime(nextDay, nextMonth, nextYear, nextHour, nextMinute, nextPeriod));
    };

    return (
        <div
            className={`sis-admission-datetime${dateOnly ? ' sis-admission-datetime--date-only' : ''}`}
            data-sis-align-exempt=""
            aria-invalid={error ? true : undefined}
            aria-describedby={describedBy}
        >
            <input type="hidden" name={name} value={value} />
            <div className="sis-admission-datetime__date" dir="rtl" lang="en">
                <DatePart
                    id={controlId}
                    className="sis-admission-datetime__day"
                    value={day}
                    options={dayOptions}
                    required={required}
                    ariaLabel={i18n.dayLabel}
                    onChange={(next) => {
                        setDay(next);
                        emit(next, month, year, hour, minute, period);
                    }}
                />
                <span className="sis-admission-datetime__slash" aria-hidden="true">
                    /
                </span>
                <span className="sis-admission-datetime__unit sis-admission-datetime__month">
                    <span className="sis-admission-datetime__face" aria-hidden="true">
                        {month}
                    </span>
                    <SisListSelect
                        variant="overlay"
                        value={month}
                        options={monthOptions.map((item) => ({ value: item, label: item }))}
                        includeBlank
                        required={required}
                        dir="ltr"
                        ariaLabel={i18n.monthLabel}
                        onChange={(next) => {
                            setMonth(next);
                            emit(day, next, year, hour, minute, period);
                        }}
                    />
                </span>
                <span className="sis-admission-datetime__slash" aria-hidden="true">
                    /
                </span>
                <DatePart
                    className="sis-admission-datetime__year"
                    value={year}
                    options={YEARS}
                    required={required}
                    ariaLabel={i18n.yearLabel}
                    onChange={(next) => {
                        setYear(next);
                        emit(day, month, next, hour, minute, period);
                    }}
                />
            </div>
            {dateOnly ? null : (
                <div className="sis-admission-datetime__clock" dir="rtl">
                    <div className="sis-admission-datetime__digits" dir="ltr" lang="en">
                        <DatePart
                            className="sis-admission-datetime__hour"
                            value={hour}
                            options={HOURS}
                            includeBlank={false}
                            ariaLabel={i18n.hourLabel}
                            onChange={(next) => {
                                setHour(next);
                                emit(day, month, year, next, minute, period);
                            }}
                        />
                        <span className="sis-admission-datetime__colon" aria-hidden="true">
                            :
                        </span>
                        <DatePart
                            className="sis-admission-datetime__minute"
                            value={minute}
                            options={MINUTES}
                            includeBlank={false}
                            ariaLabel={i18n.minuteLabel}
                            onChange={(next) => {
                                setMinute(next);
                                emit(day, month, year, hour, next, period);
                            }}
                        />
                    </div>
                    <select
                        className="sis-admission-datetime__period"
                        dir="rtl"
                        value={period}
                        aria-label={i18n.dayPeriod}
                        onChange={(event) => {
                            const next = event.target.value as DayPeriod;
                            setPeriod(next);
                            emit(day, month, year, hour, minute, next);
                        }}
                    >
                        <option value="am">{i18n.timeAm}</option>
                        <option value="pm">{i18n.timePm}</option>
                    </select>
                </div>
            )}
        </div>
    );
}
