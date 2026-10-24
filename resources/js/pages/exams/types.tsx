import { History, Pencil, PlusCircle } from 'lucide-react';
import { router } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import {
    AttendanceTable,
    AttendanceWindow,
    useAttendanceMessages,
    useAttendanceSelection,
    useYearFilterSpec,
    WindowFact,
    WindowTextArea,
    type AttendanceColumn,
} from '@/components/attendance/attendance-chrome';
import {
    ExamisPage,
    useExamisEditRibbon,
    useExamisHomeFilters,
    useExamisRequest,
} from '@/components/examis/examis-chrome';
import { RegistryTextField } from '@/components/organization/registry-sheet';
import type { PageRibbonGroup } from '@/components/sis/page-ribbon-context';
import { t } from '@/i18n';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

type ExamType = {
    id: number;
    code: string;
    name: string;
    weight_percentage: number;
    in_use: boolean;
    year_weight: number;
    year_version: number | null;
    history: {
        version_no: number;
        weight_percentage: number;
        reason: string;
        created_at: string;
        created_by_name: string | null;
    }[];
};

type PageProps = {
    types: { data: ExamType[] };
    can_manage: boolean;
    filters: { academic_year_id: number | null };
};

type Draft = {
    type: ExamType | null;
    code: string;
    name: string;
    weight_percentage: string;
};

export default function ExamTypes(props: PageProps) {
    const i18n = t();
    const breadcrumbs: BreadcrumbItem[] = [
        { title: i18n.exams.title, href: '/exams' },
        { title: i18n.exams.types.title, href: '/exams/types' },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <TypesBody {...props} />
        </AppLayout>
    );
}

/** «أنواع الامتحانات» — the school's exam types and their weights. */
function TypesBody({ types, can_manage, filters }: PageProps) {
    const i18n = t();
    const x = i18n.exams.types;
    const request = useExamisRequest();
    const rows = types.data;
    const selection = useAttendanceSelection(rows, (row) => row.id);
    const [draft, setDraft] = useState<Draft | null>(null);
    const [saving, setSaving] = useState(false);
    const [versioning, setVersioning] = useState<{
        type: ExamType;
        weight: string;
        reason: string;
    } | null>(null);
    const yearSpec = useYearFilterSpec(filters.academic_year_id, (id) =>
        router.get('/exams/types', { academic_year_id: id }, { preserveScroll: true }),
    );
    useExamisHomeFilters([yearSpec], [filters, yearSpec.options.length]);
    const total = rows.reduce((sum, type) => sum + type.weight_percentage, 0);

    const edit = (type: ExamType | null) =>
        setDraft({
            type,
            code: type?.code ?? '',
            name: type?.name ?? '',
            weight_percentage: type ? String(type.weight_percentage) : '',
        });

    const ribbon = useMemo((): PageRibbonGroup[] => {
        const single =
            selection.targets.length === 1 ? selection.targets[0] : null;

        return can_manage
            ? [
                  {
                      id: 'exam-types-actions',
                      label: x.actions,
                      commands: [
                          {
                              id: 'exam-types-add',
                              label: x.add,
                              icon: PlusCircle,
                              iconTone: 'ok',
                              onSelect: () => edit(null),
                          },
                          {
                              id: 'exam-types-edit',
                              label: x.edit,
                              icon: Pencil,
                              iconTone: 'amber',
                              tone: 'edit',
                              disabled: single === null,
                              onSelect: () => single && edit(single),
                          },
                          {
                              id: 'exam-types-weight-version',
                              label: x.weightVersion,
                              title: `${x.weightVersion} — ${x.weightVersionHint}`,
                              icon: History,
                              iconTone: 'sky',
                              disabled:
                                  single === null ||
                                  filters.academic_year_id === null,
                              onSelect: () =>
                                  single &&
                                  setVersioning({
                                      type: single,
                                      weight: String(single.year_weight),
                                      reason: '',
                                  }),
                          },
                      ],
                  },
              ]
            : [];
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [selection.targets, can_manage, filters.academic_year_id]);
    useExamisEditRibbon('types', ribbon);

    useAttendanceMessages(
        can_manage
            ? []
            : [{ key: 'read-only', text: x.readOnly, tone: 'info' }],
        x.title,
    );

    const save = async () => {
        if (draft === null) {
            return;
        }
        setSaving(true);
        const ok = await request(
            draft.type ? `/exams/types/${draft.type.id}` : '/exams/types',
            {
                code: draft.code,
                name: draft.name,
                weight_percentage: draft.weight_percentage,
            },
            'exam-type',
        );
        setSaving(false);
        if (ok) {
            setDraft(null);
        }
    };

    const saveVersion = async () => {
        if (versioning === null || filters.academic_year_id === null) {
            return;
        }
        setSaving(true);
        const ok = await request(
            `/exams/types/${versioning.type.id}/weights`,
            {
                academic_year_id: filters.academic_year_id,
                weight_percentage: versioning.weight,
                reason: versioning.reason,
            },
            'exam-type-weight',
        );
        setSaving(false);
        if (ok) {
            setVersioning(null);
        }
    };

    const columns: AttendanceColumn<ExamType>[] = [
        { id: 'name', header: x.name, kind: 'name', cell: (row) => row.name },
        {
            id: 'code',
            header: x.code,
            kind: 'text',
            cell: (row) => <span dir="ltr">{row.code}</span>,
        },
        {
            id: 'weight',
            header: x.weight,
            kind: 'num',
            cell: (row) => row.weight_percentage,
            sort: (row) => row.weight_percentage,
        },
        {
            id: 'year_weight',
            header: x.yearWeight,
            kind: 'num',
            cell: (row) =>
                row.year_version === null
                    ? `${row.year_weight} (${x.baseWeight})`
                    : `${row.year_weight} (${x.versionLabel.replace(':v', String(row.year_version))})`,
            sort: (row) => row.year_weight,
        },
        {
            id: 'usage',
            header: x.usage,
            kind: 'text',
            cell: (row) => (row.in_use ? x.inUse : x.unused),
        },
    ];
    const locked = draft?.type?.in_use ?? false;

    return (
        <ExamisPage title={x.title} windowId="exams.types">
            {rows.length > 0 ? (
                <p className="text-sm" role="status" data-types-total>
                    {x.total.replace(':total', String(total))} — {x.totalHint}
                </p>
            ) : null}
            <AttendanceTable
                caption={x.caption}
                storageKey="exams.types"
                rows={rows}
                rowKey={(row) => row.id}
                columns={columns}
                selection={selection}
                onOpen={can_manage ? (row) => edit(row) : undefined}
                emptyText={x.emptyDesc}
            />

            {versioning !== null ? (
                <AttendanceWindow
                    title={`${x.weightVersion} — ${versioning.type.name}`}
                    pending={saving}
                    confirmLabel={x.save}
                    confirmDisabled={
                        versioning.weight === '' ||
                        versioning.reason.trim().length < 3
                    }
                    onConfirm={() => void saveVersion()}
                    onClose={() => setVersioning(null)}
                    sections={[
                        {
                            id: 'exams-types-weight-version',
                            title: x.weightVersionHint,
                            content: (
                                <>
                                    <RegistryTextField
                                        label={x.weight}
                                        editing
                                        required
                                        type="number"
                                        dir="ltr"
                                        value={versioning.weight}
                                        onChange={(weight) =>
                                            setVersioning({
                                                ...versioning,
                                                weight,
                                            })
                                        }
                                    />
                                    <WindowTextArea
                                        label={x.weightReason}
                                        value={versioning.reason}
                                        onChange={(reason) =>
                                            setVersioning({
                                                ...versioning,
                                                reason,
                                            })
                                        }
                                    />
                                    {versioning.type.history.map((h) => (
                                        <WindowFact
                                            key={h.version_no}
                                            label={x.versionLabel.replace(':v', String(h.version_no))}
                                            value={`${h.weight_percentage}% — ${h.reason} — ${h.created_by_name ?? ''} ${h.created_at.slice(0, 10)}`}
                                        />
                                    ))}
                                </>
                            ),
                        },
                    ]}
                />
            ) : null}

            {draft !== null ? (
                <AttendanceWindow
                    title={draft.type ? x.edit : x.add}
                    pending={saving}
                    confirmLabel={x.save}
                    confirmDisabled={
                        draft.name.trim() === '' ||
                        draft.code.trim() === '' ||
                        draft.weight_percentage === ''
                    }
                    onConfirm={() => void save()}
                    onClose={() => setDraft(null)}
                    sections={[
                        {
                            id: draft.type
                                ? 'exams-types-edit'
                                : 'exams-types-create',
                            title: x.title,
                            content: (
                                <>
                                    <RegistryTextField
                                        label={x.name}
                                        editing
                                        required
                                        value={draft.name}
                                        onChange={(name) =>
                                            setDraft({ ...draft, name })
                                        }
                                    />
                                    {locked ? (
                                        <>
                                            <WindowFact
                                                label={x.code}
                                                value={
                                                    <span dir="ltr">
                                                        {draft.code}
                                                    </span>
                                                }
                                            />
                                            <WindowFact
                                                label={x.weight}
                                                value={draft.weight_percentage}
                                            />
                                            <p className="text-sm">{x.inUse}</p>
                                        </>
                                    ) : (
                                        <>
                                            <RegistryTextField
                                                label={x.code}
                                                editing
                                                required
                                                dir="ltr"
                                                value={draft.code}
                                                onChange={(code) =>
                                                    setDraft({ ...draft, code })
                                                }
                                            />
                                            <RegistryTextField
                                                label={x.weight}
                                                editing
                                                required
                                                type="number"
                                                dir="ltr"
                                                value={draft.weight_percentage}
                                                onChange={(weight_percentage) =>
                                                    setDraft({
                                                        ...draft,
                                                        weight_percentage,
                                                    })
                                                }
                                            />
                                        </>
                                    )}
                                </>
                            ),
                        },
                    ]}
                />
            ) : null}
        </ExamisPage>
    );
}
