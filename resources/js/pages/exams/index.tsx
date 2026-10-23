import { router } from '@inertiajs/react';
import { Eye, PlusCircle } from 'lucide-react';
import { useMemo, useState } from 'react';
import {
    AttendanceTable,
    AttendanceWindow,
    useAttendanceMessages,
    useAttendanceSelection,
    useYearFilterSpec,
    type AttendanceColumn,
} from '@/components/attendance/attendance-chrome';
import {
    ExamisPage,
    useExamisEditRibbon,
    useExamisHomeFilters,
    useExamisRequest,
} from '@/components/examis/examis-chrome';
import {
    RegistryListField,
    RegistryTextField,
} from '@/components/organization/registry-sheet';
import type { PageRibbonGroup } from '@/components/sis/page-ribbon-context';
import type { FilterSpec } from '@/components/sis/ribbon-filter';
import { statusChipLabel } from '@/components/sis/status-chip';
import { t } from '@/i18n';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

type ExamRow = {
    id: number;
    school_id: number;
    academic_year_id: number;
    term_id: number;
    exam_type_id: number;
    name: string;
    start_date: string;
    end_date: string;
    status: number;
};

type PageProps = {
    exams: {
        data: ExamRow[];
    };
    names: { terms: Record<number, string> };
    admin: {
        can_create: boolean;
        terms: { id: number; name: string }[];
        exam_types: { id: number; name: string; weight_percentage: number }[];
    };
    filters: {
        academic_year_id: number | null;
        status: number | null;
    };
};

const EXAM_STATUSES = [1, 2, 3, 4, 5];
/** Shared status palette: draft steel, scheduled sky, in progress ok, completed amber, cancelled danger. */
const STATUS_TONE: Record<number, number> = { 1: 0, 2: 3, 3: 1, 4: 2, 5: 4 };

type Draft = {
    name: string;
    term_id: string;
    exam_type_id: string;
    start_date: string;
    end_date: string;
};

const EMPTY_DRAFT: Draft = {
    name: '',
    term_id: '',
    exam_type_id: '',
    start_date: '',
    end_date: '',
};

export default function ExamsIndex(props: PageProps) {
    const i18n = t();
    const breadcrumbs: BreadcrumbItem[] = [
        { title: i18n.exams.title, href: '/exams' },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <ExamsBody {...props} />
        </AppLayout>
    );
}

function ExamsBody({ exams, names, admin, filters }: PageProps) {
    const i18n = t();
    const a = i18n.exams.admin;
    const request = useExamisRequest();
    const [draft, setDraft] = useState<Draft | null>(null);
    const [saving, setSaving] = useState(false);
    const rows = exams.data;
    const selection = useAttendanceSelection(rows, (row) => row.id);
    const canCreate = admin.can_create && filters.academic_year_id !== null;

    const visit = (patch: Partial<PageProps['filters']>) =>
        router.get(
            '/exams',
            Object.fromEntries(
                Object.entries({ ...filters, ...patch }).filter(
                    ([, value]) => value !== null && value !== undefined,
                ),
            ),
            { preserveScroll: true },
        );
    const open = (row: ExamRow) => router.visit(`/exams/${row.id}`);

    const yearSpec = useYearFilterSpec(filters.academic_year_id, (id) =>
        visit({ academic_year_id: id }),
    );
    const statusSpec: FilterSpec = {
        key: 'status',
        type: 'single',
        label: i18n.common.status,
        value: filters.status === null ? '' : String(filters.status),
        options: [
            { value: '', label: `${i18n.common.status}: —` },
            ...EXAM_STATUSES.map((status) => ({
                value: String(status),
                label: statusChipLabel('exam', status),
            })),
        ],
        onChange: (value) =>
            visit({ status: value === '' ? null : Number(value) }),
    };
    useExamisHomeFilters(
        [yearSpec, statusSpec],
        [filters, yearSpec.options.length],
    );

    const ribbon = useMemo((): PageRibbonGroup[] => {
        const target = selection.targets[0];

        return [
            {
                id: 'exams-actions',
                label: i18n.examisChrome.actions,
                commands: [
                    {
                        id: 'exams-view',
                        label: i18n.common.view,
                        icon: Eye,
                        iconTone: 'sky',
                        disabled: target === undefined,
                        onSelect: () => target && open(target),
                    },
                    ...(canCreate
                        ? [
                              {
                                  id: 'exams-add',
                                  label: a.newExam,
                                  icon: PlusCircle,
                                  iconTone: 'ok' as const,
                                  onSelect: () => setDraft(EMPTY_DRAFT),
                              },
                          ]
                        : []),
                ],
            },
        ];
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [selection.targets, canCreate]);
    useExamisEditRibbon('exams', ribbon);

    // The create form offers existing types and terms only: say why it cannot open instead of an empty form.
    useAttendanceMessages(
        canCreate && admin.exam_types.length === 0
            ? [{ key: 'no-types', text: a.noExamTypes, tone: 'warning' }]
            : canCreate && admin.terms.length === 0
              ? [{ key: 'no-terms', text: a.noTerms, tone: 'warning' }]
              : [],
        i18n.exams.title,
    );

    const save = async () => {
        if (draft === null || filters.academic_year_id === null) {
            return;
        }
        setSaving(true);
        const ok = await request(
            '/exams',
            { academic_year_id: filters.academic_year_id, ...draft },
            'exam-create',
        );
        setSaving(false);
        if (ok) {
            setDraft(null);
        }
    };

    const columns: AttendanceColumn<ExamRow>[] = [
        {
            id: 'name',
            header: i18n.exams.name,
            kind: 'name',
            cell: (row) => row.name,
        },
        {
            id: 'term',
            header: i18n.exams.term,
            kind: 'text',
            cell: (row) => names.terms[row.term_id] ?? String(row.term_id),
        },
        {
            id: 'dates',
            header: i18n.exams.dates,
            kind: 'text',
            cell: (row) => (
                <span dir="ltr">
                    {row.start_date} – {row.end_date}
                </span>
            ),
        },
        {
            id: 'status',
            header: i18n.common.status,
            kind: 'status',
            tone: (row) => STATUS_TONE[row.status] ?? 0,
            cell: (row) => statusChipLabel('exam', row.status),
        },
    ];

    const typeOptions = admin.exam_types.map((type) => ({
        value: String(type.id),
        label: `${type.name} (${type.weight_percentage}%)`,
    }));
    const termOptions = admin.terms.map((term) => ({
        value: String(term.id),
        label: term.name,
    }));
    const ready =
        draft !== null &&
        draft.name.trim() !== '' &&
        draft.term_id !== '' &&
        draft.exam_type_id !== '' &&
        draft.start_date !== '' &&
        draft.end_date !== '';

    return (
        <ExamisPage title={i18n.exams.title} windowId="exams.index">
            <AttendanceTable
                caption={i18n.exams.tableCaption}
                storageKey="exams.index"
                rows={rows}
                rowKey={(row) => row.id}
                columns={columns}
                selection={selection}
                onOpen={open}
                emptyText={
                    filters.academic_year_id
                        ? i18n.exams.emptyDesc
                        : i18n.exams.selectYearContext
                }
            />

            {draft !== null &&
            admin.exam_types.length > 0 &&
            admin.terms.length > 0 ? (
                <AttendanceWindow
                    title={a.newExam}
                    columns={2}
                    pending={saving}
                    confirmLabel={a.create}
                    confirmDisabled={!ready}
                    onConfirm={() => void save()}
                    onClose={() => setDraft(null)}
                    sections={[
                        {
                            id: 'exam-create',
                            title: a.newExam,
                            content: (
                                <div
                                    data-window-id="exams.create"
                                    className="contents"
                                >
                                    <RegistryTextField
                                        label={a.examName}
                                        editing
                                        required
                                        fieldClassName="sis-branches-field--wide"
                                        value={draft.name}
                                        onChange={(name) =>
                                            setDraft({ ...draft, name })
                                        }
                                    />
                                    <RegistryListField
                                        label={a.term}
                                        editing
                                        required
                                        value={draft.term_id}
                                        display={
                                            termOptions.find(
                                                (o) =>
                                                    o.value === draft.term_id,
                                            )?.label ?? '—'
                                        }
                                        options={termOptions}
                                        onChange={(term_id) =>
                                            setDraft({ ...draft, term_id })
                                        }
                                    />
                                    <RegistryListField
                                        label={a.examType}
                                        editing
                                        required
                                        value={draft.exam_type_id}
                                        display={
                                            typeOptions.find(
                                                (o) =>
                                                    o.value ===
                                                    draft.exam_type_id,
                                            )?.label ?? '—'
                                        }
                                        options={typeOptions}
                                        onChange={(exam_type_id) =>
                                            setDraft({ ...draft, exam_type_id })
                                        }
                                    />
                                    <RegistryTextField
                                        label={a.startDate}
                                        editing
                                        required
                                        type="date"
                                        dir="ltr"
                                        value={draft.start_date}
                                        onChange={(start_date) =>
                                            setDraft({ ...draft, start_date })
                                        }
                                    />
                                    <RegistryTextField
                                        label={a.endDate}
                                        editing
                                        required
                                        type="date"
                                        dir="ltr"
                                        value={draft.end_date}
                                        onChange={(end_date) =>
                                            setDraft({ ...draft, end_date })
                                        }
                                    />
                                </div>
                            ),
                        },
                    ]}
                />
            ) : null}
        </ExamisPage>
    );
}
