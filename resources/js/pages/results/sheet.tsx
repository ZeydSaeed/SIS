import { router } from '@inertiajs/react';
import { BadgeCheck, Calculator, Send } from 'lucide-react';
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
    ReconciliationPanel,
    type Reconciliation,
} from '@/components/results/reconciliation-panel';
import {
    PublicationList,
    type Publication,
} from '@/components/results/publication-list';
import { ConfirmDialog } from '@/components/sis/confirm-dialog';
import type { PageRibbonGroup } from '@/components/sis/page-ribbon-context';
import { t } from '@/i18n';
import AppLayout from '@/layouts/app-layout';
import { resolveUiMessage } from '@/lib/resolve-ui-message';
import type { BreadcrumbItem } from '@/types';

type Option = { id: number; name: string };
type ResultCell = {
    version: number;
    weighted_total: string | null;
    incomplete: boolean;
} | null;

type Row = {
    enrollment_id: number;
    student_name: string;
    student_code: string;
    section_name: string;
    seats: number;
    current_grades: number;
    finalized_grades: number;
    operational: ResultCell;
    official: ResultCell;
};

type Filters = {
    academic_year_id: number | null;
    term_id: number | null;
    subject_id: number | null;
    class_id: number | null;
    section_id: number | null;
};

type PageProps = {
    terms: Option[];
    classes: (Option & { sections: Option[] })[];
    subjects: Option[];
    rows: Row[];
    filters: Filters;
    publications: Publication[];
    reconciliation: Reconciliation;
    abilities: { calculate: boolean; finalize: boolean; publish: boolean };
    outcomes: {
        mode: 'calculate' | 'finalize';
        counts: Record<string, number>;
        outcomes: { enrollment_id: number; status: string }[];
    } | null;
};

function newKey(): string {
    return `term-results-${Date.now()}-${Math.random().toString(36).slice(2, 10)}`;
}

export default function ResultsSheet(props: PageProps) {
    const i18n = t();
    const breadcrumbs: BreadcrumbItem[] = [
        { title: i18n.results.title, href: '/results' },
        { title: i18n.results.sheet.title, href: '/results/sheet' },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <SheetBody {...props} />
        </AppLayout>
    );
}

function SheetBody({
    terms,
    classes,
    subjects,
    rows,
    filters,
    publications,
    reconciliation,
    abilities,
    outcomes,
}: PageProps) {
    const i18n = t();
    const s = i18n.results.sheet;
    const [working, setWorking] = useState(false);
    const [confirmFinalize, setConfirmFinalize] = useState(false);
    const [confirmPublish, setConfirmPublish] = useState(false);
    const sections =
        classes.find((c) => c.id === filters.class_id)?.sections ?? [];
    const ready =
        filters.term_id !== null &&
        filters.subject_id !== null &&
        filters.class_id !== null;
    const outcomeBySeat = new Map(
        (outcomes?.outcomes ?? []).map((o) => [o.enrollment_id, o.status]),
    );
    const statusLabel = (status: string): string =>
        (s.outcomeLabels as Record<string, string>)[status] ??
        resolveUiMessage(status);

    const visit = (patch: Partial<Filters>) =>
        router.get(
            '/results/sheet',
            Object.fromEntries(
                Object.entries({ ...filters, ...patch }).filter(
                    ([, value]) => value !== null && value !== undefined,
                ),
            ),
            { preserveScroll: true },
        );

    const run = (mode: 'calculate' | 'finalize' | 'publish') => {
        setWorking(true);
        router.post(
            `/results/sheet/${mode}`,
            {
                academic_year_id: filters.academic_year_id,
                term_id: filters.term_id,
                subject_id: filters.subject_id,
                class_id: filters.class_id,
                section_id: filters.section_id,
            },
            {
                headers: { 'X-Idempotency-Key': newKey() },
                preserveScroll: true,
                onFinish: () => {
                    setWorking(false);
                    setConfirmFinalize(false);
                },
            },
        );
    };

    const yearSpec = useYearFilterSpec(filters.academic_year_id, (id) =>
        visit({
            academic_year_id: id,
            term_id: null,
            subject_id: null,
            class_id: null,
            section_id: null,
        }),
    );
    useExamisHomeFilters(
        [
            yearSpec,
            optionFilter(s.term, s.term, filters.term_id, terms, (id) =>
                visit({ term_id: id, subject_id: null }),
            ),
            optionFilter(
                s.subject,
                s.subject,
                filters.subject_id,
                subjects,
                (id) => visit({ subject_id: id }),
                { disabled: filters.term_id === null },
            ),
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
        [filters, terms, subjects, classes, yearSpec.options.length],
    );

    const ribbon = useMemo((): PageRibbonGroup[] => {
        const busy = working || !ready || rows.length === 0;
        const commands = [
            ...(abilities.calculate
                ? [
                      {
                          id: 'term-sheet-calculate',
                          label: s.calculate,
                          icon: Calculator,
                          iconTone: 'sky' as const,
                          disabled: busy,
                          onSelect: () => run('calculate'),
                      },
                  ]
                : []),
            ...(abilities.finalize
                ? [
                      {
                          id: 'term-sheet-finalize',
                          label: s.finalize,
                          icon: BadgeCheck,
                          iconTone: 'ok' as const,
                          disabled: busy,
                          onSelect: () => setConfirmFinalize(true),
                      },
                  ]
                : []),
            ...(abilities.publish
                ? [
                      {
                          id: 'term-sheet-publish',
                          label: s.publish,
                          title: `${s.publish} — ${s.publishHint}`,
                          icon: Send,
                          iconTone: 'ok' as const,
                          disabled: busy,
                          onSelect: () => setConfirmPublish(true),
                      },
                  ]
                : []),
        ];

        return commands.length === 0
            ? []
            : [
                  {
                      id: 'term-sheet-actions',
                      label: i18n.examisChrome.actions,
                      commands,
                  },
              ];
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [abilities, working, ready, rows.length, filters]);
    useExamisEditRibbon('termSheet', ribbon);

    useAttendanceMessages(
        filters.term_id !== null && subjects.length === 0
            ? [{ key: 'no-subjects', text: s.noSubjects, tone: 'warning' }]
            : [],
        s.title,
    );

    const result = (cell: ResultCell) =>
        cell === null ? (
            s.none
        ) : (
            <span>
                <span dir="ltr">{cell.weighted_total ?? s.none}</span>
                {cell.incomplete ? ` · ${s.incomplete}` : ''}
                {` · ${s.version.replace(':v', String(cell.version))}`}
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
            id: 'grades',
            header: s.grades,
            kind: 'text',
            cell: (row) =>
                s.gradesValue
                    .replace(':finalized', String(row.finalized_grades))
                    .replace(':current', String(row.current_grades))
                    .replace(':seats', String(row.seats)),
        },
        {
            id: 'operational',
            header: s.operational,
            kind: 'text',
            cell: (row) => result(row.operational),
        },
        {
            id: 'official',
            header: s.official,
            kind: 'text',
            cell: (row) => result(row.official),
        },
        {
            id: 'outcome',
            header: s.outcome,
            kind: 'text',
            cell: (row) => {
                const status = outcomeBySeat.get(row.enrollment_id);

                return status ? statusLabel(status) : '';
            },
        },
    ];

    return (
        <ExamisPage title={s.title} windowId="results.sheet">
            {ready && outcomes ? (
                <p
                    className="text-sm"
                    role="status"
                    data-results-outcome={outcomes.mode}
                >
                    {s.outcome}{' '}
                    {Object.entries(outcomes.counts)
                        .map(
                            ([status, count]) =>
                                `${statusLabel(status)}: ${count}`,
                        )
                        .join(' · ')}
                </p>
            ) : null}
            {ready ? (
                <ReconciliationPanel
                    reconciliation={reconciliation}
                    namesById={
                        new Map(rows.map((x) => [x.enrollment_id, x.student_name]))
                    }
                />
            ) : null}
            {ready ? (
                <PublicationList
                    publications={publications}
                    canWithdraw={abilities.publish}
                    kindLabel={() => s.publishKind}
                    sectionName={(id) =>
                        sections.find((x) => x.id === id)?.name ?? ''
                    }
                />
            ) : null}
            <AttendanceTable
                caption={s.title}
                storageKey="results.sheet"
                columns={columns}
                rows={ready ? rows : []}
                rowKey={(row) => row.enrollment_id}
                emptyText={ready ? s.noRows : s.pickHint}
            />

            <ConfirmDialog
                open={confirmPublish}
                title={s.publishConfirmTitle}
                description={s.publishConfirmDesc}
                confirmLabel={s.publish}
                confirmPending={working}
                tone="danger"
                onConfirm={() => {
                    run('publish');
                    setConfirmPublish(false);
                }}
                onOpenChange={setConfirmPublish}
            />
            <ConfirmDialog
                open={confirmFinalize}
                title={s.finalizeConfirmTitle}
                description={s.finalizeConfirmDesc}
                confirmLabel={s.finalize}
                confirmPending={working}
                tone="danger"
                onConfirm={() => run('finalize')}
                onOpenChange={(open) => {
                    if (!open) {
                        setConfirmFinalize(false);
                    }
                }}
            />
        </ExamisPage>
    );
}
