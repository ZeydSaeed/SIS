import { router } from '@inertiajs/react';
import {
    BadgeCheck,
    Calculator,
    FileBadge,
    ListOrdered,
    Send,
    Sigma,
    Stamp,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import {
    AttendanceTable,
    useAttendanceMessages,
    useYearFilterSpec,
    type AttendanceColumn,
} from '@/components/attendance/attendance-chrome';
import {
    ExamisPage,
    optionFilter,
    useExamisEditRibbon,
    useExamisHomeFilters,
} from '@/components/examis/examis-chrome';
import {
    PublicationList,
    type Publication,
} from '@/components/results/publication-list';
import {
    ReconciliationPanel,
    type Reconciliation,
} from '@/components/results/reconciliation-panel';
import { ConfirmDialog } from '@/components/sis/confirm-dialog';
import type { PageRibbonGroup } from '@/components/sis/page-ribbon-context';
import { t } from '@/i18n';
import AppLayout from '@/layouts/app-layout';
import { resolveUiMessage } from '@/lib/resolve-ui-message';
import type { BreadcrumbItem } from '@/types';

type Option = { id: number; name: string };
type Target = 'annual' | 'gpa';
type Mode = 'calculate' | 'finalize';
type Confirm = Target | 'transcript';
type ResultCell = {
    version: number;
    value: string | null;
    incomplete: boolean;
} | null;
type Pair = { operational: ResultCell; official: ResultCell };

type Row = {
    enrollment_id: number;
    student_name: string;
    student_code: string;
    section_name: string;
    term_results: { operational: number; official: number };
    annual: Pair;
    gpa: Pair;
    rank: number | null;
    transcript: { number: string; version: number; up_to_date: boolean } | null;
};

type Filters = {
    academic_year_id: number | null;
    class_id: number | null;
    section_id: number | null;
};

type PageProps = {
    classes: (Option & { sections: Option[] })[];
    rows: Row[];
    ranking: {
        version: number;
        participants: number;
        calculated_at: string;
    } | null;
    filters: Filters;
    publications: Publication[];
    reconciliation: Reconciliation;
    abilities: {
        publish: boolean;
        calculate: boolean;
        finalize: boolean;
        buildRanking: boolean;
        issueTranscript: boolean;
    };
    outcomes: {
        target: Confirm;
        mode: string;
        counts: Record<string, number>;
        outcomes: { enrollment_id: number; status: string }[];
    } | null;
};

function newKey(): string {
    return `year-results-${Date.now()}-${Math.random().toString(36).slice(2, 10)}`;
}

export default function ResultsYearSheet(props: PageProps) {
    const i18n = t();
    const breadcrumbs: BreadcrumbItem[] = [
        { title: i18n.results.title, href: '/results' },
        { title: i18n.results.yearSheet.title, href: '/results/year-sheet' },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <YearSheetBody {...props} />
        </AppLayout>
    );
}

function YearSheetBody({
    classes,
    rows,
    ranking,
    filters,
    publications,
    reconciliation,
    abilities,
    outcomes,
}: PageProps) {
    const i18n = t();
    const s = i18n.results.yearSheet;
    const labels = i18n.results.sheet;
    const [working, setWorking] = useState(false);
    const [confirm, setConfirm] = useState<Confirm | null>(null);
    const [publishing, setPublishing] = useState<Confirm | null>(null);
    const ready = filters.class_id !== null && rows.length > 0;
    const sections =
        classes.find((c) => c.id === filters.class_id)?.sections ?? [];
    const outcomeBySeat = new Map(
        (outcomes?.outcomes ?? []).map((o) => [o.enrollment_id, o.status]),
    );
    const statusLabel = (status: string): string =>
        (labels.outcomeLabels as Record<string, string>)[status] ??
        resolveUiMessage(status);

    const visit = (patch: Partial<Filters>) =>
        router.get(
            '/results/year-sheet',
            Object.fromEntries(
                Object.entries({ ...filters, ...patch }).filter(
                    ([, value]) => value !== null && value !== undefined,
                ),
            ),
            { preserveScroll: true },
        );

    const post = (path: string, body: Record<string, unknown>) => {
        setWorking(true);
        router.post(
            path,
            {
                academic_year_id: filters.academic_year_id,
                class_id: filters.class_id,
                section_id: filters.section_id,
                ...body,
            },
            {
                headers: { 'X-Idempotency-Key': newKey() },
                preserveScroll: true,
                onFinish: () => {
                    setWorking(false);
                    setConfirm(null);
                },
            },
        );
    };
    const run = (target: Target, mode: Mode) =>
        post(`/results/year-sheet/${mode}`, { target });

    const yearSpec = useYearFilterSpec(filters.academic_year_id, (id) =>
        visit({ academic_year_id: id, class_id: null, section_id: null }),
    );
    useExamisHomeFilters(
        [
            yearSpec,
            optionFilter(s.class, s.class, filters.class_id, classes, (id) =>
                visit({ class_id: id, section_id: null }),
            ),
            optionFilter(
                s.section,
                s.section,
                filters.section_id,
                sections,
                (id) => visit({ section_id: id }),
                {
                    emptyLabel: s.allSections,
                    disabled: filters.class_id === null,
                },
            ),
        ],
        [filters, classes, yearSpec.options.length],
    );

    const ribbon = useMemo((): PageRibbonGroup[] => {
        const busy = working || !ready;
        const group = (target: Target, title: string): PageRibbonGroup => ({
            id: `year-sheet-${target}`,
            label: title,
            commands: [
                ...(abilities.calculate
                    ? [
                          {
                              id: `year-sheet-${target}-calculate`,
                              label: s.calculate,
                              icon: target === 'annual' ? Calculator : Sigma,
                              iconTone: 'sky' as const,
                              disabled: busy,
                              onSelect: () => run(target, 'calculate'),
                          },
                      ]
                    : []),
                ...(abilities.finalize
                    ? [
                          {
                              id: `year-sheet-${target}-finalize`,
                              label: s.finalize,
                              icon: BadgeCheck,
                              iconTone: 'ok' as const,
                              disabled: busy,
                              onSelect: () => setConfirm(target),
                          },
                      ]
                    : []),
            ],
        });
        const publish: PageRibbonGroup = {
            id: 'year-sheet-publish',
            label: s.publishActions,
            commands: abilities.publish
                ? (['annual', 'gpa', 'transcript'] as (Target | 'transcript')[]).map((target) => ({
                      id: `year-sheet-${target}-publish`,
                      label: `${s.publish} — ${s.outcomeTargets[target]}`,
                      title: `${s.publish} — ${s.outcomeTargets[target]} — ${s.publishHint}`,
                      icon: Send,
                      iconTone: 'ok' as const,
                      disabled: busy,
                      onSelect: () => setPublishing(target),
                  }))
                : [],
        };
        const ranks: PageRibbonGroup = {
            id: 'year-sheet-ranking',
            label: s.rankingActions,
            commands: [
                ...(abilities.buildRanking
                    ? [
                          {
                              id: 'year-sheet-ranking-build',
                              label: s.buildRanking,
                              icon: ListOrdered,
                              iconTone: 'amber' as const,
                              disabled: busy,
                              onSelect: () =>
                                  post('/results/year-sheet/ranking', {
                                      section_id: null,
                                  }),
                          },
                      ]
                    : []),
                ...(abilities.issueTranscript
                    ? [
                          {
                              id: 'year-sheet-transcripts-issue',
                              label: s.issueTranscripts,
                              icon: Stamp,
                              iconTone: 'rose' as const,
                              disabled: busy,
                              onSelect: () => setConfirm('transcript'),
                          },
                      ]
                    : []),
            ],
        };

        return [
            group('annual', s.annualActions),
            group('gpa', s.gpaActions),
            publish,
            ranks,
        ].filter((g) => g.commands.length > 0);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [abilities, working, ready, filters]);
    useExamisEditRibbon('yearSheet', ribbon);

    useAttendanceMessages(
        filters.class_id !== null
            ? [{ key: 'order', text: s.orderHint, tone: 'info' }]
            : [],
        s.title,
    );

    const result = (cell: ResultCell) =>
        cell === null ? (
            labels.none
        ) : (
            <span>
                <span dir="ltr">{cell.value ?? labels.none}</span>
                {cell.incomplete ? ` · ${labels.incomplete}` : ''}
                {` · ${labels.version.replace(':v', String(cell.version))}`}
            </span>
        );

    const columns: AttendanceColumn<Row>[] = [
        { id: 'student', header: s.student, cell: (row) => row.student_name },
        {
            id: 'code',
            header: s.code,
            kind: 'text',
            cell: (row) => <span dir="ltr">{row.student_code}</span>,
        },
        {
            id: 'section',
            header: s.section,
            kind: 'text',
            cell: (row) => row.section_name,
        },
        {
            id: 'terms',
            header: s.termResults,
            kind: 'text',
            cell: (row) =>
                s.termResultsValue
                    .replace(':official', String(row.term_results.official))
                    .replace(
                        ':operational',
                        String(row.term_results.operational),
                    ),
        },
        {
            id: 'annual_operational',
            header: s.annualOperational,
            kind: 'text',
            cell: (row) => result(row.annual.operational),
        },
        {
            id: 'annual_official',
            header: s.annualOfficial,
            kind: 'text',
            cell: (row) => result(row.annual.official),
        },
        {
            id: 'gpa_operational',
            header: s.gpaOperational,
            kind: 'text',
            cell: (row) => result(row.gpa.operational),
        },
        {
            id: 'gpa_official',
            header: s.gpaOfficial,
            kind: 'text',
            cell: (row) => result(row.gpa.official),
        },
        {
            id: 'rank',
            header: s.rank,
            kind: 'num',
            cell: (row) => row.rank ?? labels.none,
        },
        {
            id: 'transcript',
            header: s.transcript,
            kind: 'text',
            cell: (row) =>
                row.transcript === null ? (
                    labels.none
                ) : (
                    <span>
                        <FileBadge
                            className="inline size-3.5 align-[-2px]"
                            aria-hidden
                        />{' '}
                        <span dir="ltr">{row.transcript.number}</span>
                        {row.transcript.up_to_date
                            ? ''
                            : ` · ${s.transcriptOutdated}`}
                    </span>
                ),
        },
        {
            id: 'outcome',
            header: labels.outcome,
            kind: 'text',
            cell: (row) => {
                const status = outcomeBySeat.get(row.enrollment_id);

                return status ? statusLabel(status) : '';
            },
        },
    ];

    return (
        <ExamisPage title={s.title} windowId="results.year-sheet">
            {filters.class_id !== null ? (
                <p className="text-sm" data-results-ranking>
                    {ranking === null
                        ? s.noRanking
                        : s.rankingInfo
                              .replace(':v', String(ranking.version))
                              .replace(':n', String(ranking.participants))
                              .replace(
                                  ':at',
                                  ranking.calculated_at.slice(0, 16),
                              )}
                </p>
            ) : null}
            {outcomes ? (
                <p
                    className="text-sm"
                    role="status"
                    data-results-outcome={`${outcomes.target}.${outcomes.mode}`}
                >
                    {labels.outcome} {s.outcomeTargets[outcomes.target]} —{' '}
                    {Object.entries(outcomes.counts)
                        .map(
                            ([status, count]) =>
                                `${statusLabel(status)}: ${count}`,
                        )
                        .join(' · ')}
                </p>
            ) : null}
            {filters.class_id !== null ? (
                <ReconciliationPanel
                    reconciliation={reconciliation}
                    namesById={
                        new Map(rows.map((x) => [x.enrollment_id, x.student_name]))
                    }
                />
            ) : null}
            {filters.class_id !== null ? (
                <PublicationList
                    publications={publications}
                    canWithdraw={abilities.publish}
                    kindLabel={(p) =>
                        s.outcomeTargets[p.kind as keyof typeof s.outcomeTargets]
                    }
                    sectionName={(id) =>
                        sections.find((x) => x.id === id)?.name ?? ''
                    }
                />
            ) : null}
            <AttendanceTable
                caption={s.title}
                storageKey="results.year-sheet"
                columns={columns}
                rows={rows}
                rowKey={(row) => row.enrollment_id}
                emptyText={filters.class_id === null ? s.pickHint : s.noRows}
            />

            <ConfirmDialog
                open={publishing !== null}
                title={s.publishConfirmTitle}
                description={s.publishConfirmDesc}
                confirmLabel={`${s.publish} — ${publishing ? s.outcomeTargets[publishing] : ''}`}
                confirmPending={working}
                tone="danger"
                onConfirm={() => {
                    if (publishing) {
                        post('/results/year-sheet/publish', {
                            target: publishing,
                        });
                        setPublishing(null);
                    }
                }}
                onOpenChange={(open) => {
                    if (!open) {
                        setPublishing(null);
                    }
                }}
            />
            <ConfirmDialog
                open={confirm !== null}
                title={
                    confirm === 'transcript'
                        ? s.transcriptsConfirmTitle
                        : s.finalizeConfirmTitle
                }
                description={
                    confirm === 'transcript'
                        ? s.transcriptsConfirmDesc
                        : s.finalizeConfirmDesc
                }
                confirmLabel={
                    confirm === 'transcript'
                        ? s.issueTranscripts
                        : `${s.finalize} — ${confirm ? s.outcomeTargets[confirm] : ''}`
                }
                confirmPending={working}
                tone="danger"
                onConfirm={() => {
                    if (confirm === 'transcript') {
                        post('/results/year-sheet/transcripts', {});
                    } else if (confirm) {
                        run(confirm, 'finalize');
                    }
                }}
                onOpenChange={(open) => {
                    if (!open) {
                        setConfirm(null);
                    }
                }}
            />
        </ExamisPage>
    );
}
