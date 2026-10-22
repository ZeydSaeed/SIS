import { Head, router } from '@inertiajs/react';
import {
    CheckCircle2,
    CircleDashed,
    CircleSlash,
    Lock,
    Save,
    type LucideIcon,
} from 'lucide-react';
import {
    useCallback,
    useMemo,
    useRef,
    useState,
    type KeyboardEvent,
    type ReactNode,
} from 'react';
import { OpsYearFilter } from '@/components/sis/ops-year-filter';
import {
    useRegisterPageRibbon,
    type PageRibbonGroup,
} from '@/components/sis/page-ribbon-context';
import { sisListPageClass } from '@/components/sis/page-theme';
import { SisListSelect } from '@/components/sis/sis-list-select';
import {
    StatusBadge,
    tableStatusCellClass,
} from '@/components/sis/table-status-badge';
import { Button } from '@/components/ui/button';
import { t } from '@/i18n';
import AppLayout from '@/layouts/app-layout';
import { resolveUiMessage } from '@/lib/resolve-ui-message';
import type { BreadcrumbItem } from '@/types';

type SessionOption = {
    id: number;
    exam_name: string;
    subject_name: string;
    session_date: string;
    status: number;
    max_grade: number;
};

type SessionHeader = SessionOption & {
    academic_year_id: number;
    academic_year_name: string;
    term_name: string;
    start_time: string;
    end_time: string;
    pass_grade: number;
};

type SeatRow = {
    exam_enrollment_id: number;
    seat_number: string | null;
    student_code: string;
    student_name: string;
    class_name: string;
    section_name: string;
    grade_id: number | null;
    score: string | null;
    is_absent: boolean;
    grade_status: number | null;
};

type Outcome = {
    exam_enrollment_id: number;
    status: string;
    grade_id: number | null;
};

type PageProps = {
    sessions: SessionOption[];
    session: SessionHeader | null;
    rows: SeatRow[];
    outcomes: Outcome[] | null;
    scope: { school_wide: boolean };
    filters: { academic_year_id: number | null; session_id: number | null };
};

type Entry = { score: string; absent: boolean };

const EMPTY_ENTRY: Entry = { score: '', absent: false };

/** Session statuses that accept grade entry (in progress / completed) — the server enforces it again per row. */
const ENTRY_OPEN = new Set([2, 3]);
const GRADE_FINALIZED = 4;

function newKey(): string {
    return `grade-sheet-${Date.now()}-${Math.random().toString(36).slice(2, 10)}`;
}

export default function GradesSheet(props: PageProps) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: t().grades.title, href: '/grades' },
        { title: t().grades.sheet.title, href: '/grades/sheet' },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <GradeSheetPage {...props} />
        </AppLayout>
    );
}

function GradeSheetPage({
    sessions,
    session,
    rows,
    outcomes,
    scope,
    filters,
}: PageProps) {
    const i18n = t();
    const s = i18n.grades.sheet;
    const [entries, setEntries] = useState<Record<number, Entry>>({});
    const [processing, setProcessing] = useState(false);
    const submitKey = useRef<string | null>(null);
    const inputs = useRef<Record<number, HTMLInputElement | null>>({});

    const max = session?.max_grade ?? 0;
    const entryOpen = session !== null && ENTRY_OPEN.has(session.status);
    const outcomeBySeat = useMemo(
        () => new Map((outcomes ?? []).map((o) => [o.exam_enrollment_id, o])),
        [outcomes],
    );
    const openRows = rows.filter((row) => row.grade_id === null);

    const invalid = (entry: Entry | undefined): boolean => {
        if (!entry || entry.absent || entry.score.trim() === '') {
            return false;
        }
        const value = Number(entry.score);

        return (
            !Number.isFinite(value) ||
            value < 0 ||
            value > max ||
            !/^\d+(\.\d{1,2})?$/.test(entry.score.trim())
        );
    };
    const filled = (entry: Entry | undefined): boolean =>
        entry !== undefined && (entry.absent || entry.score.trim() !== '');
    const toSave = openRows.filter((row) =>
        filled(entries[row.exam_enrollment_id]),
    );
    const anyInvalid = toSave.some((row) =>
        invalid(entries[row.exam_enrollment_id]),
    );
    const canSave =
        entryOpen && !processing && toSave.length > 0 && !anyInvalid;

    const setEntry = (seat: number, patch: Partial<Entry>) =>
        setEntries((current) => ({
            ...current,
            [seat]: { ...EMPTY_ENTRY, ...current[seat], ...patch },
        }));

    const save = useCallback(() => {
        if (!session || !canSave) {
            return;
        }
        submitKey.current ??= newKey();
        setProcessing(true);
        router.post(
            `/grades/sheet/${session.id}`,
            {
                rows: toSave.map((row) => {
                    const entry = entries[row.exam_enrollment_id];

                    return {
                        exam_enrollment_id: row.exam_enrollment_id,
                        score: entry.absent ? null : entry.score.trim(),
                        is_absent: entry.absent,
                    };
                }),
            },
            {
                headers: { 'X-Idempotency-Key': submitKey.current },
                preserveScroll: true,
                preserveState: true,
                onSuccess: (page) => {
                    // A response arrived: the next save is a new submission. Rows the server stored drop their draft;
                    // rejected rows keep what was typed so it can be fixed.
                    submitKey.current = null;
                    const stored = new Set(
                        ((page.props as unknown as PageProps).outcomes ?? [])
                            .filter(
                                (o) =>
                                    o.status === 'saved' ||
                                    o.status === 'replayed',
                            )
                            .map((o) => o.exam_enrollment_id),
                    );
                    setEntries((current) =>
                        Object.fromEntries(
                            Object.entries(current).filter(
                                ([seat]) => !stored.has(Number(seat)),
                            ),
                        ),
                    );
                },
                onFinish: () => setProcessing(false),
            },
        );
    }, [session, canSave, toSave, entries]);

    // The ribbon registers into shared layout state: its definition must only change when what it shows changes,
    // so the save action is read through a ref instead of being a dependency (a new closure every render).
    const saveRef = useRef(save);
    saveRef.current = save;
    const readyCount = toSave.length;
    const hasSession = session !== null;
    const ribbon = useMemo(
        (): PageRibbonGroup[] => [
            {
                id: 'grade-sheet-actions',
                label: i18n.common.actions,
                commands: [
                    {
                        id: 'grade-sheet-save',
                        label: processing ? s.saving : s.save,
                        icon: Save,
                        iconTone: 'ok',
                        tone: 'save',
                        count: readyCount,
                        disabled: !canSave,
                        title:
                            !entryOpen && hasSession
                                ? s.entryClosed
                                : readyCount === 0
                                  ? s.nothingToSave
                                  : undefined,
                        onSelect: () => saveRef.current(),
                    },
                ],
            },
        ],
        [i18n, s, processing, readyCount, canSave, entryOpen, hasSession],
    );
    useRegisterPageRibbon('edit', ribbon);

    const focusNext = (
        index: number,
        event: KeyboardEvent<HTMLInputElement>,
    ) => {
        if (event.key !== 'Enter') {
            return;
        }
        event.preventDefault();
        const next = openRows[index + 1];
        if (next) {
            inputs.current[next.exam_enrollment_id]?.focus();
        }
    };

    const state = (
        row: SeatRow,
    ): { tone: string; icon: LucideIcon; label: string } => {
        const outcome = outcomeBySeat.get(row.exam_enrollment_id);
        if (row.grade_id !== null) {
            return row.grade_status === GRADE_FINALIZED
                ? { tone: 'transfer', icon: Lock, label: s.storedFinal }
                : {
                      tone: 'transfer',
                      icon: CheckCircle2,
                      label: outcome ? s.saved : s.stored,
                  };
        }
        const entry = entries[row.exam_enrollment_id];
        if (invalid(entry)) {
            return { tone: 'rejected', icon: CircleSlash, label: s.invalid };
        }
        if (
            outcome &&
            outcome.status !== 'saved' &&
            outcome.status !== 'replayed'
        ) {
            return {
                tone: 'rejected',
                icon: CircleSlash,
                label: resolveUiMessage(outcome.status),
            };
        }

        return filled(entry)
            ? { tone: 'submitted', icon: CheckCircle2, label: s.ready }
            : { tone: 'waitlisted', icon: CircleDashed, label: s.pending };
    };

    const sessionOptions = sessions.map((option) => ({
        value: String(option.id),
        label: `${option.subject_name} — ${option.exam_name} (${option.session_date})`,
    }));
    const visitSession = (value: string) =>
        router.get(
            '/grades/sheet',
            {
                academic_year_id: filters.academic_year_id ?? undefined,
                session_id: value || undefined,
            },
            { preserveScroll: true },
        );
    const done = rows.filter((row) => row.grade_id !== null).length;

    return (
        <>
            <Head title={s.title} />
            <div
                className={sisListPageClass()}
                dir="rtl"
                lang="ar"
                data-window-id="grades.sheet"
            >
                <div className="sis-admission-page-body flex min-h-0 flex-1 flex-col gap-3">
                    <div className="flex flex-wrap items-end gap-3">
                        <OpsYearFilter
                            action="/grades/sheet"
                            academicYearId={filters.academic_year_id}
                            compact
                        />
                        <label className="flex min-w-64 flex-col gap-1 text-sm">
                            <span>{s.session}</span>
                            <SisListSelect
                                value={
                                    filters.session_id
                                        ? String(filters.session_id)
                                        : ''
                                }
                                options={sessionOptions}
                                onChange={visitSession}
                                ariaLabel={s.chooseSession}
                                includeBlank
                                disabled={sessions.length === 0}
                            />
                        </label>
                    </div>

                    {sessions.length === 0 ? (
                        <p className="text-sm">{s.noSessions}</p>
                    ) : null}

                    {session ? (
                        <>
                            <dl
                                className="grid grid-cols-2 gap-x-6 gap-y-1 text-sm sm:grid-cols-4"
                                aria-label={s.title}
                            >
                                <Fact
                                    label={s.exam}
                                    value={session.exam_name}
                                />
                                <Fact
                                    label={s.subject}
                                    value={session.subject_name}
                                />
                                <Fact
                                    label={s.term}
                                    value={session.term_name}
                                />
                                <Fact
                                    label={s.year}
                                    value={session.academic_year_name}
                                />
                                <Fact
                                    label={s.date}
                                    value={
                                        <span dir="ltr">{`${session.session_date} ${session.start_time}–${session.end_time}`}</span>
                                    }
                                />
                                <Fact
                                    label={s.maxGrade}
                                    value={
                                        <span dir="ltr">
                                            {session.max_grade}
                                        </span>
                                    }
                                />
                                <Fact
                                    label={s.passGrade}
                                    value={
                                        <span dir="ltr">
                                            {session.pass_grade}
                                        </span>
                                    }
                                />
                                <Fact
                                    label={s.scope}
                                    value={
                                        scope.school_wide
                                            ? s.scopeAll
                                            : s.scopeOwn
                                    }
                                />
                            </dl>
                            <p className="text-sm" role="status">
                                {entryOpen
                                    ? s.counts
                                          .replace(':done', String(done))
                                          .replace(
                                              ':total',
                                              String(rows.length),
                                          )
                                    : s.entryClosed}
                                {' · '}
                                {s.correctionNote}
                                {entryOpen ? ` · ${s.keyboardHint}` : null}
                            </p>
                        </>
                    ) : sessions.length > 0 ? (
                        <p className="text-sm">{s.pickSessionHint}</p>
                    ) : null}

                    {session && rows.length === 0 ? (
                        <p className="text-sm">{s.noRows}</p>
                    ) : null}

                    {session && rows.length > 0 ? (
                        <section
                            aria-label={s.title}
                            className="flex min-h-0 flex-1 flex-col"
                        >
                            <div className="sis-admission-periods-table sis-admission-drafts-table">
                                <div className="sis-admission-drafts-table__scroller">
                                    <table>
                                        <thead>
                                            <tr>
                                                <th className="sis-admission-drafts-table__num">
                                                    #
                                                </th>
                                                <th className="sis-admission-drafts-table__text sis-students-table__nowrap">
                                                    {s.seat}
                                                </th>
                                                <th className="sis-admission-drafts-table__text sis-students-table__nowrap">
                                                    {s.code}
                                                </th>
                                                <th>{s.studentName}</th>
                                                <th>{s.classSection}</th>
                                                <th className="sis-admission-drafts-table__text sis-students-table__nowrap">
                                                    {s.score}
                                                </th>
                                                <th className="sis-admission-drafts-table__text sis-students-table__nowrap">
                                                    {s.absent}
                                                </th>
                                                <th>{s.state}</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {rows.map((row, index) => {
                                                const entry =
                                                    entries[
                                                        row.exam_enrollment_id
                                                    ];
                                                const stored =
                                                    row.grade_id !== null;
                                                const openIndex =
                                                    openRows.indexOf(row);
                                                const st = state(row);
                                                const bad = invalid(entry);

                                                return (
                                                    <tr
                                                        key={
                                                            row.exam_enrollment_id
                                                        }
                                                        data-seat={
                                                            row.exam_enrollment_id
                                                        }
                                                    >
                                                        <td className="sis-admission-drafts-table__num">
                                                            {index + 1}
                                                        </td>
                                                        <td
                                                            className="sis-admission-drafts-table__text sis-students-table__nowrap"
                                                            dir="ltr"
                                                        >
                                                            {row.seat_number ??
                                                                '—'}
                                                        </td>
                                                        <td
                                                            className="sis-admission-drafts-table__text sis-students-table__nowrap"
                                                            dir="ltr"
                                                        >
                                                            {row.student_code}
                                                        </td>
                                                        <td className="sis-admission-drafts-table__name">
                                                            <div className="sis-students-table__cell-scroll">
                                                                {
                                                                    row.student_name
                                                                }
                                                            </div>
                                                        </td>
                                                        <td className="sis-admission-drafts-table__text sis-students-table__nowrap">
                                                            <div className="sis-students-table__cell-scroll">{`${row.class_name} / ${row.section_name}`}</div>
                                                        </td>
                                                        <td className="sis-admission-drafts-table__text sis-students-table__nowrap">
                                                            {stored ? (
                                                                <span dir="ltr">
                                                                    {row.is_absent
                                                                        ? s.absent
                                                                        : row.score}
                                                                </span>
                                                            ) : (
                                                                <input
                                                                    ref={(
                                                                        el,
                                                                    ) => {
                                                                        inputs.current[
                                                                            row.exam_enrollment_id
                                                                        ] = el;
                                                                    }}
                                                                    type="number"
                                                                    inputMode="decimal"
                                                                    min={0}
                                                                    max={max}
                                                                    step="0.01"
                                                                    dir="ltr"
                                                                    className="sis-ops-hub__link min-h-9 w-24 px-2 py-1"
                                                                    aria-label={`${s.score} — ${row.student_name}`}
                                                                    aria-invalid={
                                                                        bad
                                                                    }
                                                                    disabled={
                                                                        !entryOpen ||
                                                                        processing ||
                                                                        entry?.absent
                                                                    }
                                                                    value={
                                                                        entry?.score ??
                                                                        ''
                                                                    }
                                                                    onChange={(
                                                                        e,
                                                                    ) =>
                                                                        setEntry(
                                                                            row.exam_enrollment_id,
                                                                            {
                                                                                score: e
                                                                                    .target
                                                                                    .value,
                                                                            },
                                                                        )
                                                                    }
                                                                    onKeyDown={(
                                                                        e,
                                                                    ) =>
                                                                        focusNext(
                                                                            openIndex,
                                                                            e,
                                                                        )
                                                                    }
                                                                />
                                                            )}
                                                        </td>
                                                        <td className="sis-admission-drafts-table__text sis-students-table__nowrap">
                                                            {stored ? null : (
                                                                <input
                                                                    type="checkbox"
                                                                    aria-label={`${s.absent} — ${row.student_name}`}
                                                                    disabled={
                                                                        !entryOpen ||
                                                                        processing
                                                                    }
                                                                    checked={
                                                                        entry?.absent ??
                                                                        false
                                                                    }
                                                                    onChange={(
                                                                        e,
                                                                    ) =>
                                                                        setEntry(
                                                                            row.exam_enrollment_id,
                                                                            {
                                                                                absent: e
                                                                                    .target
                                                                                    .checked,
                                                                                score: '',
                                                                            },
                                                                        )
                                                                    }
                                                                />
                                                            )}
                                                        </td>
                                                        <td
                                                            className={tableStatusCellClass(
                                                                st.tone,
                                                            )}
                                                        >
                                                            <div className="sis-students-table__cell-scroll">
                                                                <StatusBadge
                                                                    icon={
                                                                        st.icon
                                                                    }
                                                                >
                                                                    {st.label}
                                                                </StatusBadge>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                );
                                            })}
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <div className="flex justify-end pt-3">
                                <Button
                                    type="button"
                                    onClick={save}
                                    disabled={!canSave}
                                >
                                    {processing
                                        ? s.saving
                                        : `${s.save} (${toSave.length})`}
                                </Button>
                            </div>
                        </section>
                    ) : null}
                </div>
            </div>
        </>
    );
}

function Fact({ label, value }: { label: string; value: ReactNode }) {
    return (
        <div className="flex gap-1">
            <dt className="font-semibold">{label}:</dt>
            <dd>{value}</dd>
        </div>
    );
}
