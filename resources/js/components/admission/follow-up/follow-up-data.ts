import { router, usePage } from '@inertiajs/react';
import {
    ArrowLeftRight,
    GraduationCap,
    Hourglass,
    LogOut,
    MessagesSquare,
    SearchCheck,
    Send,
    XOctagon,
    type LucideIcon,
} from 'lucide-react';
import { useCallback, useMemo } from 'react';
import {
    ADMISSION_STATUS_CONVERTED,
    ADMISSION_STATUS_INTERVIEW,
    ADMISSION_STATUS_REJECTED,
    ADMISSION_STATUS_SUBMITTED,
    ADMISSION_STATUS_UNDER_REVIEW,
    ADMISSION_STATUS_WAITLISTED,
    ADMISSION_STATUS_WITHDRAWN,
    type AdmissionAcceptedStudent,
} from '@/components/admission/admission-workspace';
import { formatAcademicYearOptionLabel } from '@/components/sis/ops-year-filter';
import { t } from '@/i18n';

/** 1 = academic→vocational transfer, 2 = vocational school intake (no kind = vocational). */
const REQUEST_KIND_TRANSFER = 1;
const REQUEST_KIND_VOCATIONAL = 2;

export type FollowUpCategoryKey =
    | 'submitted'
    | 'underReview'
    | 'interview'
    | 'waitlisted'
    | 'vocational'
    | 'transfer'
    | 'rejected'
    | 'withdrawn';

export type FollowUpCategory = {
    key: FollowUpCategoryKey;
    label: string;
    /** The name alone («مُرسل», «مقبول (مهني)»…), for the list's category cell. */
    shortLabel: string;
    icon: LucideIcon;
    /** The reason column shown next to the name on the list (rejected / withdrawn). */
    reason?: 'rejection_reason' | 'withdrawal_reason';
    matches: (student: AdmissionAcceptedStudent) => boolean;
};

const channelOf = (student: AdmissionAcceptedStudent): number =>
    student.request_kind ?? REQUEST_KIND_VOCATIONAL;

/**
 * The categories in display order — the order of the bar, the legend and the cards, as on the EXAMIS staff
 * dashboard (in progress → decided). Each has one colour token, `--sis-follow-up-<key>` (see app.css).
 */
export function followUpCategories(): FollowUpCategory[] {
    const a = t().admission;

    return [
        {
            key: 'submitted',
            label: a.acceptedStatsSubmitted,
            shortLabel: a.statusSubmitted,
            icon: Send,
            matches: (s) => s.status === ADMISSION_STATUS_SUBMITTED,
        },
        {
            key: 'underReview',
            label: a.acceptedStatsUnderReview,
            shortLabel: a.statusUnderReview,
            icon: SearchCheck,
            matches: (s) => s.status === ADMISSION_STATUS_UNDER_REVIEW,
        },
        {
            key: 'interview',
            label: a.acceptedStatsInterview,
            shortLabel: a.statusInterview,
            icon: MessagesSquare,
            matches: (s) => s.status === ADMISSION_STATUS_INTERVIEW,
        },
        {
            key: 'waitlisted',
            label: a.acceptedStatsWaitlisted,
            shortLabel: a.statusWaitlisted,
            icon: Hourglass,
            matches: (s) => s.status === ADMISSION_STATUS_WAITLISTED,
        },
        {
            key: 'vocational',
            label: a.acceptedStatsVocational,
            shortLabel: a.acceptedStatsVocationalShort,
            icon: GraduationCap,
            matches: (s) =>
                s.status === ADMISSION_STATUS_CONVERTED &&
                channelOf(s) === REQUEST_KIND_VOCATIONAL,
        },
        {
            key: 'transfer',
            label: a.acceptedStatsTransfer,
            shortLabel: a.acceptedStatsTransferShort,
            icon: ArrowLeftRight,
            matches: (s) =>
                s.status === ADMISSION_STATUS_CONVERTED &&
                channelOf(s) === REQUEST_KIND_TRANSFER,
        },
        {
            key: 'rejected',
            label: a.acceptedStatsRejected,
            shortLabel: a.statusRejected,
            icon: XOctagon,
            reason: 'rejection_reason',
            matches: (s) => s.status === ADMISSION_STATUS_REJECTED,
        },
        {
            key: 'withdrawn',
            label: a.acceptedStatsWithdrawn,
            shortLabel: a.statusWithdrawn,
            icon: LogOut,
            reason: 'withdrawal_reason',
            matches: (s) => s.status === ADMISSION_STATUS_WITHDRAWN,
        },
    ];
}

export function categoryOf(
    student: AdmissionAcceptedStudent,
    categories: FollowUpCategory[],
): FollowUpCategory | null {
    return categories.find((category) => category.matches(student)) ?? null;
}

/** Kebab-case token suffix: `underReview` → `under-review`. */
export const categoryToken = (key: FollowUpCategoryKey): string =>
    key.replace(/[A-Z]/g, (letter) => `-${letter.toLowerCase()}`);

export const FOLLOW_UP_PATH = '/admission/follow-up';
export const FOLLOW_UP_LIST_PATH = '/admission/follow-up/list';

/** The filters of both pages live in the URL, so «رجوع» / «تقدم», the page arrows and X keep them. */
export type FollowUpQuery = {
    /** '' = the user's current school (default), 'all' = every school, or school ids («3,5»). */
    school: string;
    year: string;
    /** '' = every period, or period ids («2,4»). */
    period: string;
    /** '' = every category, or category keys («waitlisted,rejected»). */
    category: string;
    q: string;
    page: number;
};

const CATEGORY_KEYS: readonly string[] = [
    'submitted',
    'underReview',
    'interview',
    'waitlisted',
    'vocational',
    'transfer',
    'rejected',
    'withdrawn',
];

/** The values of a multi-choice URL filter («3,5» → ['3', '5']); '' → none (= all). */
export function filterValues(value: string): string[] {
    return value
        .split(',')
        .map((item) => item.trim())
        .filter((item) => item !== '');
}

function readQuery(url: string): FollowUpQuery {
    const params = new URLSearchParams(
        url.includes('?') ? url.slice(url.indexOf('?') + 1) : '',
    );
    const category = filterValues(params.get('category') ?? '')
        .filter((key) => CATEGORY_KEYS.includes(key))
        .join(',');
    const page = Number(params.get('page') ?? '1');

    return {
        school: params.get('school_id') ?? '',
        year: params.get('academic_year_id') ?? '',
        period: params.get('period_id') ?? '',
        category,
        q: (params.get('q') ?? '').trim(),
        page: Number.isInteger(page) && page > 0 ? page : 1,
    };
}

export function followUpHref(path: string, query: FollowUpQuery): string {
    const params = new URLSearchParams();
    const put = (key: string, value: string) => {
        if (value !== '') {
            params.set(key, value);
        }
    };
    put('academic_year_id', query.year);
    put('school_id', query.school);
    put('period_id', query.period);
    put('category', query.category);
    put('q', query.q);
    if (query.page > 1) {
        params.set('page', String(query.page));
    }
    const search = params.toString();

    return search === '' ? path : `${path}?${search}`;
}

/** One application of the roster; its school travels with the link (the roster spans the user's schools). */
export function followUpShowHref(
    applicationId: number,
    schoolId: number,
    query: FollowUpQuery,
): string {
    const params = new URLSearchParams({ school_id: String(schoolId) });
    if (query.year !== '') {
        params.set('academic_year_id', query.year);
    }

    return `/admission/follow-up/applications/${applicationId}?${params.toString()}`;
}

/** The URL filters of the page, and a visit that changes some of them (page 1 unless given). */
export function useFollowUpQuery(path: string): {
    query: FollowUpQuery;
    visit: (patch: Partial<FollowUpQuery>, toPath?: string) => void;
} {
    const { url } = usePage();
    const query = useMemo(() => readQuery(url), [url]);
    const visit = useCallback(
        (patch: Partial<FollowUpQuery>, toPath: string = path) => {
            const href = followUpHref(toPath, { ...query, page: 1, ...patch });
            if (toPath === path) {
                // Same page: the roster is already here — filters and pages change on the client, instantly.
                router.push({
                    url: href,
                    preserveScroll: true,
                    preserveState: true,
                });

                return;
            }
            router.visit(href, { preserveScroll: true, viewTransition: true });
        },
        [path, query],
    );

    return { query, visit };
}

export type FollowUpFilterOptions = {
    schools: Array<{ value: string; label: string }>;
    years: Array<{ value: string; label: string }>;
    periods: Array<{ value: string; label: string }>;
};

/**
 * The roster narrowed by the URL filters (schools, year, periods, search — not the categories), and the options of
 * each filter. The schools default to the user's current school, the year to the page's year (else the first).
 * `schoolIds` / `periodIds` empty = every school / period.
 */
export function useFollowUpRoster(
    students: AdmissionAcceptedStudent[],
    query: FollowUpQuery,
    schools: Array<{ id: number; name: string }>,
    defaultSchoolId: number | null,
    defaultYearId: number | null,
): {
    rows: AdmissionAcceptedStudent[];
    schoolIds: string[];
    periodIds: string[];
    yearId: string;
    options: FollowUpFilterOptions;
} {
    const years = useMemo(() => {
        const map = new Map<number, string>();
        for (const student of students) {
            if (!map.has(student.academic_year_id)) {
                map.set(student.academic_year_id, student.academic_year_name);
            }
        }

        return [...map.entries()]
            .sort((left, right) => left[1].localeCompare(right[1], 'ar'))
            .map(([id, name]) => ({
                value: String(id),
                label: formatAcademicYearOptionLabel(name, ''),
            }));
    }, [students]);

    const wantedYear =
        query.year !== ''
            ? query.year
            : defaultYearId === null
              ? ''
              : String(defaultYearId);
    const yearId = years.some((year) => year.value === wantedYear)
        ? wantedYear
        : (years[0]?.value ?? '');

    const schoolIds = useMemo(
        () =>
            query.school === 'all'
                ? []
                : query.school !== ''
                  ? filterValues(query.school)
                  : defaultSchoolId !== null &&
                      schools.some((school) => school.id === defaultSchoolId)
                    ? [String(defaultSchoolId)]
                    : [],
        [defaultSchoolId, query.school, schools],
    );
    const periodIds = useMemo(() => filterValues(query.period), [query.period]);

    const periods = useMemo(() => {
        const map = new Map<number, string>();
        for (const student of students) {
            if (yearId === '' || student.academic_year_id === Number(yearId)) {
                map.set(student.period_id, student.period_name);
            }
        }

        return [...map.entries()]
            .sort((left, right) => left[1].localeCompare(right[1], 'ar'))
            .map(([id, name]) => ({ value: String(id), label: name }));
    }, [students, yearId]);

    const rows = useMemo(() => {
        const needle = query.q.toLocaleLowerCase('ar');

        return students.filter(
            (student) =>
                (schoolIds.length === 0 ||
                    schoolIds.includes(String(student.school_id))) &&
                (yearId === '' ||
                    student.academic_year_id === Number(yearId)) &&
                (periodIds.length === 0 ||
                    periodIds.includes(String(student.period_id))) &&
                (needle === '' ||
                    [
                        student.full_name,
                        student.rejection_reason,
                        student.withdrawal_reason,
                    ].some((value) =>
                        (value ?? '').toLocaleLowerCase('ar').includes(needle),
                    )),
        );
    }, [periodIds, query.q, schoolIds, students, yearId]);

    const options = useMemo(
        (): FollowUpFilterOptions => ({
            schools: schools.map((school) => ({
                value: String(school.id),
                label: school.name,
            })),
            years,
            periods,
        }),
        [periods, schools, years],
    );

    return { rows, schoolIds, periodIds, yearId, options };
}
