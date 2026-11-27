import { Head, router } from '@inertiajs/react';
import { Eye, Library, Plus } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import AppLayout from '@/layouts/app-layout';
import {
    QuestionFormDialog,
    emptyQuestion,
    type QuestionType,
    type SubjectOption,
} from '@/components/bank/question-forms';
import {
    BankStatusBadge,
    type BankStatus,
} from '@/components/bank/question-status';
import { fill } from '@/components/results/active-publications';
import { EditRibbon } from '@/components/exams/edit-ribbon';
import { DataTable, type DataTableColumn } from '@/components/sis/data-table';
import { PageHeader } from '@/components/sis/page-header';
import { type PageRibbonGroup } from '@/components/sis/page-ribbon-context';
import { SisListSelect } from '@/components/sis/sis-list-select';
import { Button } from '@/components/ui/button';
import { t } from '@/i18n';
import type { BreadcrumbItem } from '@/types';

type Row = {
    id: number;
    subject_id: number;
    subject_name: string;
    subject_name_en: string | null;
    question_status: 'active' | 'retired';
    version_id: number;
    version_no: number;
    type: QuestionType;
    version_status: Exclude<BankStatus, 'retired'>;
    max_mark: string;
    author_name: string | null;
    stem_excerpt: string;
    updated_at: string;
};

type PageProps = {
    filters: {
        subject_id: number | null;
        status: string | null;
        search: string | null;
    };
    questions: { data: Row[]; total: number; page: number; per_page: number };
    subjects: SubjectOption[];
    types: QuestionType[];
    abilities: { author: boolean; approve: boolean; view_answer_key: boolean };
};

const STATUSES = [
    'draft',
    'in_review',
    'approved',
    'rejected',
    'superseded',
    'retired',
] as const;

export default function BankQuestionsIndex({
    filters,
    questions,
    subjects,
    types,
    abilities,
}: PageProps) {
    const i18n = t();
    const b = i18n.bank;
    const statusLabel = b.statuses as Record<string, string>;
    const [creating, setCreating] = useState(false);
    const [search, setSearch] = useState(filters.search ?? '');
    const filtered =
        filters.subject_id !== null ||
        filters.status !== null ||
        (filters.search ?? '') !== '';
    // Server-side filtering: the table shows the loading state while a visit is on its way.
    const [loading, setLoading] = useState(false);
    useEffect(() => {
        const offStart = router.on('start', () => setLoading(true));
        const offFinish = router.on('finish', () => setLoading(false));

        return () => {
            offStart();
            offFinish();
        };
    }, []);
    const [selectedId, setSelectedId] = useState<number | null>(
        questions.data[0]?.id ?? null,
    );
    useEffect(() => {
        if (!questions.data.some((row) => row.id === selectedId)) {
            setSelectedId(questions.data[0]?.id ?? null);
        }
    }, [questions.data, selectedId]);

    const ribbon = useMemo((): PageRibbonGroup[] => {
        const commands: PageRibbonGroup['commands'] = [];
        if (abilities.author) {
            commands.push({
                id: 'bank-new',
                label: b.newQuestion,
                icon: Plus,
                disabled: subjects.length === 0,
                onSelect: () => setCreating(true),
            });
        }
        commands.push({
            id: 'bank-view',
            label: i18n.common.view,
            icon: Eye,
            disabled: selectedId === null,
            onSelect: () =>
                selectedId !== null &&
                router.visit(`/bank/questions/${selectedId}`),
        });

        return [{ id: 'bank', label: b.title, commands }];
    }, [abilities.author, b, i18n.common.view, selectedId, subjects.length]);

    const visit = (next: Partial<PageProps['filters']> & { page?: number }) => {
        const merged = { ...filters, ...next };
        router.get(
            '/bank/questions',
            {
                subject_id: merged.subject_id ?? undefined,
                status: merged.status ?? undefined,
                search: merged.search || undefined,
                page: next.page ?? undefined,
            },
            { preserveScroll: true, preserveState: true },
        );
    };

    const breadcrumbs: BreadcrumbItem[] = [
        { title: i18n.exams.title, href: '/exams' },
        { title: b.title, href: '/bank/questions' },
    ];

    /** A retired question shows «موقوف» whatever its latest version is. */
    const statusOf = (row: Row): BankStatus =>
        row.question_status === 'retired' ? 'retired' : row.version_status;

    const columns: DataTableColumn<Row>[] = [
        {
            id: 'stem',
            header: b.stem,
            cell: (row) => (
                <span className="line-clamp-2 whitespace-pre-line">
                    {row.stem_excerpt}
                </span>
            ),
        },
        {
            id: 'subject',
            header: b.subject,
            cell: (row) => row.subject_name,
            hideOnMobile: true,
        },
        {
            id: 'type',
            header: b.type,
            cell: (row) => b.types[row.type],
            hideOnMobile: true,
        },
        {
            id: 'status',
            header: i18n.common.status,
            cell: (row) => (
                <span className="inline-flex flex-wrap items-center gap-x-2">
                    <BankStatusBadge status={statusOf(row)} />
                    <span className="text-muted-foreground text-xs">
                        {b.versionShort} <bdi dir="ltr">{row.version_no}</bdi>
                    </span>
                </span>
            ),
            sort: (row) => statusLabel[statusOf(row)],
        },
        {
            id: 'author',
            header: b.author,
            cell: (row) => row.author_name ?? '—',
            hideOnMobile: true,
        },
        {
            id: 'open',
            header: i18n.common.open,
            cell: (row) => (
                <a href={`/bank/questions/${row.id}`} className="underline">
                    {i18n.common.view}
                </a>
            ),
        },
    ];

    const lastPage = Math.max(
        1,
        Math.ceil(questions.total / questions.per_page),
    );

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <EditRibbon groups={ribbon} />
            <Head title={b.title} />
            <div className="sis-table-theme sis-ops-hub flex min-w-0 flex-col gap-4 p-4">
                <PageHeader
                    title={b.title}
                    description={b.description}
                    icon={<Library className="size-6" aria-hidden />}
                />

                <div className="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-end">
                    <label className="flex min-w-0 flex-col gap-1 text-sm sm:w-56">
                        <span>{b.subject}</span>
                        <SisListSelect
                            ariaLabel={b.subject}
                            value={filters.subject_id?.toString() ?? ''}
                            options={[
                                { value: '', label: b.allSubjects },
                                ...subjects.map((s) => ({
                                    value: String(s.id),
                                    label: s.name,
                                })),
                            ]}
                            onChange={(v) =>
                                visit({
                                    subject_id: v === '' ? null : Number(v),
                                })
                            }
                        />
                    </label>
                    <label className="flex min-w-0 flex-col gap-1 text-sm sm:w-48">
                        <span>{i18n.common.status}</span>
                        <SisListSelect
                            ariaLabel={i18n.common.status}
                            value={filters.status ?? ''}
                            options={[
                                { value: '', label: b.allStatuses },
                                ...STATUSES.map((s) => ({
                                    value: s,
                                    label: statusLabel[s],
                                })),
                            ]}
                            onChange={(v) =>
                                visit({ status: v === '' ? null : v })
                            }
                        />
                    </label>
                    <form
                        className="flex min-w-0 items-end gap-2"
                        role="search"
                        onSubmit={(event) => {
                            event.preventDefault();
                            visit({ search: search.trim() || null });
                        }}
                    >
                        <label className="flex min-w-0 flex-col gap-1 text-sm sm:w-64">
                            <span>{b.search}</span>
                            <input
                                type="search"
                                className="sis-ops-hub__link min-h-11 px-3 py-2"
                                value={search}
                                maxLength={100}
                                placeholder={b.searchPlaceholder}
                                onChange={(event) =>
                                    setSearch(event.target.value)
                                }
                            />
                        </label>
                        <Button type="submit" variant="outline">
                            {b.searchButton}
                        </Button>
                    </form>
                </div>

                {abilities.author && subjects.length === 0 ? (
                    <p className="text-muted-foreground text-sm">
                        {b.noSubjects}
                    </p>
                ) : null}

                <div className="flex flex-wrap items-center gap-3 text-sm">
                    <p aria-live="polite" className="m-0">
                        {fill(b.resultCount, { n: questions.total })}
                    </p>
                    {filtered ? (
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => {
                                setSearch('');
                                visit({
                                    subject_id: null,
                                    status: null,
                                    search: null,
                                });
                            }}
                        >
                            {b.clearFilters}
                        </Button>
                    ) : null}
                </div>

                <DataTable
                    columns={columns}
                    rows={questions.data}
                    rowKey={(row) => row.id}
                    loading={loading}
                    onRowClick={(row) => setSelectedId(row.id)}
                    isRowSelected={(row) => row.id === selectedId}
                    getRowAriaLabel={(row) => row.stem_excerpt}
                    emptyTitle={filtered ? b.noResultsTitle : b.emptyTitle}
                    emptyDescription={
                        filtered
                            ? b.noResultsDesc
                            : abilities.author && subjects.length > 0
                              ? b.emptyAuthorDesc
                              : b.emptyDesc
                    }
                    caption={b.tableCaption}
                    mobileCard={(row) => (
                        <div className="flex flex-col gap-1">
                            <a
                                href={`/bank/questions/${row.id}`}
                                className="font-medium underline"
                            >
                                {row.stem_excerpt}
                            </a>
                            <span className="text-muted-foreground text-sm">
                                {row.subject_name} · {b.types[row.type]}
                            </span>
                            <BankStatusBadge status={statusOf(row)} />
                        </div>
                    )}
                />

                {questions.total > questions.per_page ? (
                    <nav
                        className="flex items-center gap-3 text-sm"
                        aria-label={i18n.common.page}
                    >
                        <Button
                            type="button"
                            variant="outline"
                            disabled={questions.page <= 1}
                            onClick={() => visit({ page: questions.page - 1 })}
                        >
                            {i18n.common.previous}
                        </Button>
                        <span>
                            {i18n.common.page}{' '}
                            <bdi dir="ltr">
                                {questions.page} / {lastPage}
                            </bdi>
                        </span>
                        <Button
                            type="button"
                            variant="outline"
                            disabled={questions.page >= lastPage}
                            onClick={() => visit({ page: questions.page + 1 })}
                        >
                            {i18n.common.next}
                        </Button>
                    </nav>
                ) : null}
            </div>

            <QuestionFormDialog
                open={creating}
                mode="create"
                url="/bank/questions"
                initial={emptyQuestion(
                    filters.subject_id ?? subjects[0]?.id ?? null,
                )}
                subjects={subjects}
                types={types}
                onClose={() => setCreating(false)}
            />
        </AppLayout>
    );
}
