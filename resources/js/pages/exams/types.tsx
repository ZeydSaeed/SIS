import { Pencil, PlusCircle } from 'lucide-react';
import { useMemo, useState } from 'react';
import {
    AttendanceTable,
    AttendanceWindow,
    useAttendanceMessages,
    useAttendanceSelection,
    WindowFact,
    type AttendanceColumn,
} from '@/components/attendance/attendance-chrome';
import {
    ExamisPage,
    useExamisEditRibbon,
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
};

type PageProps = {
    types: { data: ExamType[] };
    can_manage: boolean;
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
function TypesBody({ types, can_manage }: PageProps) {
    const i18n = t();
    const x = i18n.exams.types;
    const request = useExamisRequest();
    const rows = types.data;
    const selection = useAttendanceSelection(rows, (row) => row.id);
    const [draft, setDraft] = useState<Draft | null>(null);
    const [saving, setSaving] = useState(false);
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
                      ],
                  },
              ]
            : [];
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [selection.targets, can_manage]);
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
