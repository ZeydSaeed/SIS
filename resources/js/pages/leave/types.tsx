import {
    History,
    Pencil,
    PlusCircle,
    RotateCcw,
    ScrollText,
    Sparkles,
    Trash2,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import {
    AttendancePage,
    AttendanceTable,
    AttendanceWindow,
    WindowFact,
    WindowTextArea,
    useAttendanceSelection,
    type AttendanceColumn,
} from '@/components/attendance/attendance-chrome';
import {
    LeaveSectionTitle,
    useLeaveEditRibbon,
    useLeaveWrite,
    type LeaveAbilities,
    type LeavePolicy,
    type LeaveType,
} from '@/components/leave/leave-chrome';
import {
    RegistryListField,
    RegistryTextField,
} from '@/components/organization/registry-sheet';
import type { ContextMenuItem } from '@/components/sis/context-menu';
import type { PageRibbonGroup } from '@/components/sis/page-ribbon-context';
import { t } from '@/i18n';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

type Props = { types: LeaveType[]; abilities: LeaveAbilities };

type TypeForm = {
    id: number | null;
    code: string;
    name_ar: string;
    name_en: string;
    description: string;
    applies_to: string;
    unit: string;
    is_sensitive: boolean;
};
type PolicyForm = {
    day_basis: string;
    requires_document: boolean;
    deducts_balance: boolean;
    approval_levels: string;
    max_per_request: string;
    annual_entitlement: string;
};

const PATH = '/leave/types';

export default function LeaveTypes(props: Props) {
    const l = t().leave;
    const breadcrumbs: BreadcrumbItem[] = [
        { title: l.title, href: '/leave' },
        { title: l.types, href: PATH },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <TypesPage {...props} />
        </AppLayout>
    );
}

const policyOf = (p: LeavePolicy | null): PolicyForm => ({
    day_basis: String(p?.day_basis ?? 1),
    requires_document: p?.requires_document ?? false,
    deducts_balance: p?.deducts_balance ?? true,
    approval_levels: String(p?.approval_levels ?? 1),
    max_per_request:
        p?.max_per_request === null || p === null
            ? ''
            : String(p.max_per_request),
    annual_entitlement:
        p?.annual_entitlement === null || p === null
            ? ''
            : String(p.annual_entitlement),
});

function Check({
    label,
    checked,
    onChange,
    hint,
}: {
    label: string;
    checked: boolean;
    onChange: (v: boolean) => void;
    hint?: string;
}) {
    return (
        <label className="sis-admission-sheet__field">
            <span className="sis-admission-sheet__label">{label}</span>
            <span>
                <input
                    type="checkbox"
                    checked={checked}
                    onChange={(e) => onChange(e.target.checked)}
                />{' '}
                {hint ?? ''}
            </span>
        </label>
    );
}

function TypesPage({ types, abilities }: Props) {
    const l = t().leave;
    const write = useLeaveWrite();
    const selection = useAttendanceSelection(types, (x) => x.id);
    const one = selection.targets.length === 1 ? selection.targets[0] : null;
    const [typeForm, setTypeForm] = useState<{
        type: TypeForm;
        policy: PolicyForm;
    } | null>(null);
    const [policyFor, setPolicyFor] = useState<{
        type: LeaveType;
        policy: PolicyForm;
    } | null>(null);
    const [historyOf, setHistoryOf] = useState<LeaveType | null>(null);
    const [busy, setBusy] = useState(false);
    const can = abilities.policies;

    const policyPayload = (p: PolicyForm) => ({
        day_basis: Number(p.day_basis),
        requires_document: p.requires_document,
        deducts_balance: p.deducts_balance,
        approval_levels: Number(p.approval_levels),
        max_per_request:
            p.max_per_request === '' ? null : Number(p.max_per_request),
        annual_entitlement:
            p.annual_entitlement === '' ? null : Number(p.annual_entitlement),
    });
    const saveType = () => {
        if (typeForm === null) return;
        const f = typeForm.type;
        const fields = {
            name_ar: f.name_ar.trim(),
            name_en: f.name_en.trim() || null,
            description: f.description.trim() || null,
            applies_to: Number(f.applies_to),
            unit: Number(f.unit),
            is_sensitive: f.is_sensitive,
            color_hue: null,
        };
        setBusy(true);
        if (f.id === null) {
            write(
                'post',
                PATH,
                {
                    code: f.code.trim().toUpperCase(),
                    ...fields,
                    ...policyPayload(typeForm.policy),
                },
                {
                    onDone: () => setTypeForm(null),
                    onFinish: () => setBusy(false),
                },
            );
        } else {
            write('patch', `${PATH}/${f.id}`, fields, {
                onDone: () => setTypeForm(null),
                onFinish: () => setBusy(false),
            });
        }
    };
    const publish = () => {
        if (policyFor === null) return;
        setBusy(true);
        write(
            'post',
            `${PATH}/${policyFor.type.id}/policy`,
            policyPayload(policyFor.policy),
            {
                onDone: () => setPolicyFor(null),
                onFinish: () => setBusy(false),
            },
        );
    };
    const toggle = (type: LeaveType) => {
        setBusy(true);
        write(
            'post',
            `${PATH}/${type.id}/status`,
            { active: type.status === 1 ? 0 : 1 },
            { onFinish: () => setBusy(false) },
        );
    };
    const blank = (): { type: TypeForm; policy: PolicyForm } => ({
        type: {
            id: null,
            code: '',
            name_ar: '',
            name_en: '',
            description: '',
            applies_to: '3',
            unit: '1',
            is_sensitive: false,
        },
        policy: policyOf(null),
    });
    const editOf = (x: LeaveType) => ({
        type: {
            id: x.id,
            code: x.code,
            name_ar: x.name_ar,
            name_en: x.name_en ?? '',
            description: x.description ?? '',
            applies_to: String(x.applies_to),
            unit: String(x.unit),
            is_sensitive: x.is_sensitive,
        },
        policy: policyOf(x.policy),
    });

    const yesNo = (v: boolean | undefined) => (v ? l.yes : l.no);
    const columns: AttendanceColumn<LeaveType>[] = [
        {
            id: 'code',
            header: l.typeCode,
            kind: 'text',
            cell: (x) => <span dir="ltr">{x.code}</span>,
        },
        { id: 'name', header: l.nameAr, kind: 'name', cell: (x) => x.name_ar },
        {
            id: 'applies',
            header: l.appliesTo,
            kind: 'text',
            cell: (x) => l.audiences[x.applies_to],
        },
        {
            id: 'unit',
            header: l.unit,
            kind: 'text',
            cell: (x) => l.unitsPlural[x.unit],
        },
        {
            id: 'sensitive',
            header: l.sensitive,
            kind: 'text',
            cell: (x) => yesNo(x.is_sensitive),
        },
        {
            id: 'basis',
            header: l.dayBasisLabel,
            kind: 'text',
            cell: (x) => (x.policy ? l.dayBasis[x.policy.day_basis] : '—'),
        },
        {
            id: 'deducts',
            header: l.deductsBalance,
            kind: 'text',
            cell: (x) => yesNo(x.policy?.deducts_balance),
        },
        {
            id: 'document',
            header: l.requiresDocument,
            kind: 'text',
            cell: (x) => yesNo(x.policy?.requires_document),
        },
        {
            id: 'levels',
            header: l.approvalLevels,
            kind: 'num',
            cell: (x) => x.policy?.approval_levels ?? '—',
        },
        {
            id: 'max',
            header: l.maxPerRequest,
            kind: 'num',
            cell: (x) => x.policy?.max_per_request ?? l.unlimited,
        },
        {
            id: 'entitlement',
            header: l.annualEntitlement,
            kind: 'num',
            cell: (x) => x.policy?.annual_entitlement ?? l.unlimited,
        },
        {
            id: 'version',
            header: l.version,
            kind: 'num',
            cell: (x) => x.policy?.version_no ?? '—',
        },
        {
            id: 'status',
            header: l.status,
            kind: 'status',
            tone: (x) => (x.status === 1 ? 1 : 0),
            cell: (x) => (x.status === 1 ? l.typeActive : l.typeInactive),
        },
    ];

    const groups = useMemo(
        (): PageRibbonGroup[] => [
            {
                id: 'leave-types-actions',
                label: l.actions,
                commands: [
                    {
                        id: 'leave-type-history',
                        label: l.policyHistory,
                        icon: History,
                        iconTone: 'sky',
                        disabled: one === null,
                        title: one === null ? l.onlySelected : undefined,
                        onSelect: () => one && setHistoryOf(one),
                    },
                    ...(can
                        ? [
                              {
                                  id: 'leave-type-add',
                                  label: l.addType,
                                  icon: PlusCircle,
                                  iconTone: 'ok' as const,
                                  onSelect: () => setTypeForm(blank()),
                              },
                              {
                                  id: 'leave-type-edit',
                                  label: l.editType,
                                  icon: Pencil,
                                  iconTone: 'amber' as const,
                                  tone: 'edit' as const,
                                  disabled: one === null,
                                  onSelect: () =>
                                      one && setTypeForm(editOf(one)),
                              },
                              {
                                  id: 'leave-type-policy',
                                  label: l.publishPolicy,
                                  icon: ScrollText,
                                  iconTone: 'authority' as const,
                                  disabled: one === null,
                                  onSelect: () =>
                                      one &&
                                      setPolicyFor({
                                          type: one,
                                          policy: policyOf(one.policy),
                                      }),
                              },
                              one?.status === 2
                                  ? {
                                        id: 'leave-type-on',
                                        label: l.reactivateType,
                                        icon: RotateCcw,
                                        iconTone: 'ok' as const,
                                        disabled: busy,
                                        onSelect: () => toggle(one),
                                    }
                                  : {
                                        id: 'leave-type-off',
                                        label: l.deactivateType,
                                        icon: Trash2,
                                        iconTone: 'danger' as const,
                                        tone: 'delete' as const,
                                        disabled: one === null || busy,
                                        onSelect: () => one && toggle(one),
                                    },
                              {
                                  id: 'leave-type-suggested',
                                  label: l.suggested,
                                  icon: Sparkles,
                                  iconTone: 'education' as const,
                                  title: l.suggestedHint,
                                  disabled: busy,
                                  onSelect: () =>
                                      write('post', `${PATH}/suggested`, {}),
                              },
                          ]
                        : []),
                ],
            },
        ],
        // eslint-disable-next-line react-hooks/exhaustive-deps
        [one, can, busy],
    );
    useLeaveEditRibbon('types', abilities, groups);

    const menuItems = (x: LeaveType): ContextMenuItem[] => [
        {
            id: 'history',
            label: l.policyHistory,
            icon: History,
            onSelect: () => setHistoryOf(x),
        },
        ...(can
            ? [
                  {
                      id: 'edit',
                      label: l.editType,
                      icon: Pencil,
                      onSelect: () => setTypeForm(editOf(x)),
                  },
                  {
                      id: 'policy',
                      label: l.publishPolicy,
                      icon: ScrollText,
                      onSelect: () =>
                          setPolicyFor({ type: x, policy: policyOf(x.policy) }),
                  },
                  {
                      id: 'status',
                      label:
                          x.status === 1 ? l.deactivateType : l.reactivateType,
                      icon: x.status === 1 ? Trash2 : RotateCcw,
                      danger: x.status === 1,
                      separator: true,
                      onSelect: () => toggle(x),
                  },
              ]
            : []),
    ];

    const policyFields = (p: PolicyForm, set: (p: PolicyForm) => void) => (
        <>
            <RegistryListField
                label={l.dayBasisLabel}
                editing
                required
                fieldClassName="sis-branches-field--wide"
                value={p.day_basis}
                display={l.dayBasis[Number(p.day_basis)]}
                options={[1, 2].map((v) => ({
                    value: String(v),
                    label: l.dayBasis[v],
                }))}
                onChange={(day_basis) => set({ ...p, day_basis })}
            />
            <Check
                label={l.deductsBalance}
                checked={p.deducts_balance}
                onChange={(deducts_balance) => set({ ...p, deducts_balance })}
            />
            <Check
                label={l.requiresDocument}
                checked={p.requires_document}
                onChange={(requires_document) =>
                    set({ ...p, requires_document })
                }
            />
            <RegistryListField
                label={l.approvalLevels}
                editing
                required
                value={p.approval_levels}
                display={p.approval_levels}
                options={['1', '2', '3'].map((v) => ({ value: v, label: v }))}
                onChange={(approval_levels) => set({ ...p, approval_levels })}
            />
            <RegistryTextField
                label={l.maxPerRequest}
                editing
                type="number"
                dir="ltr"
                value={p.max_per_request}
                onChange={(max_per_request) => set({ ...p, max_per_request })}
            />
            <RegistryTextField
                label={l.annualEntitlement}
                editing
                type="number"
                dir="ltr"
                value={p.annual_entitlement}
                onChange={(annual_entitlement) =>
                    set({ ...p, annual_entitlement })
                }
            />
            <p className="sis-attendance-window__hint sis-branches-field--wide">
                {l.typesHint}
            </p>
        </>
    );

    return (
        <AttendancePage title={l.types}>
            <LeaveSectionTitle title={l.types} count={types.length} />
            <p className="sis-attendance-window__hint">
                {can ? l.typesHint : l.readOnly}
            </p>
            <AttendanceTable
                caption={l.types}
                storageKey="leave.types"
                rows={types}
                rowKey={(x) => x.id}
                columns={columns}
                emptyText={l.empty}
                selection={selection}
                onOpen={(x) => setHistoryOf(x)}
                menuItems={menuItems}
                menuLabel={l.actions}
            />

            {historyOf !== null ? (
                <AttendanceWindow
                    title={`${l.policyHistory} — ${historyOf.name_ar}`}
                    wide
                    columns={2}
                    onClose={() => setHistoryOf(null)}
                    sections={[
                        {
                            id: 'leave-type-info',
                            title: historyOf.name_ar,
                            content: (
                                <>
                                    <WindowFact
                                        label={l.typeCode}
                                        value={
                                            <span dir="ltr">
                                                {historyOf.code}
                                            </span>
                                        }
                                    />
                                    <WindowFact
                                        label={l.nameEn}
                                        value={historyOf.name_en ?? '—'}
                                    />
                                    <WindowFact
                                        label={l.appliesTo}
                                        value={
                                            l.audiences[historyOf.applies_to]
                                        }
                                    />
                                    <WindowFact
                                        label={l.sensitive}
                                        value={yesNo(historyOf.is_sensitive)}
                                    />
                                    <WindowFact
                                        wide
                                        label={l.description}
                                        value={historyOf.description ?? '—'}
                                    />
                                </>
                            ),
                        },
                        {
                            id: 'leave-type-history',
                            title: l.policyHistory,
                            plain: true,
                            content: (
                                <AttendanceTable
                                    caption={l.policyHistory}
                                    storageKey="leave.policy-history"
                                    rows={historyOf.history}
                                    rowKey={(p) => p.id}
                                    fit={false}
                                    emptyText={l.noHistory}
                                    columns={[
                                        {
                                            id: 'v',
                                            header: l.version,
                                            kind: 'num',
                                            cell: (p) => p.version_no,
                                        },
                                        {
                                            id: 'from',
                                            header: l.effectiveFrom,
                                            kind: 'text',
                                            cell: (p) => (
                                                <span dir="ltr">
                                                    {p.effective_from}
                                                </span>
                                            ),
                                        },
                                        {
                                            id: 'to',
                                            header: l.effectiveTo,
                                            kind: 'text',
                                            cell: (p) =>
                                                p.effective_to === null ? (
                                                    l.current
                                                ) : (
                                                    <span dir="ltr">
                                                        {p.effective_to}
                                                    </span>
                                                ),
                                        },
                                        {
                                            id: 'basis',
                                            header: l.dayBasisLabel,
                                            kind: 'text',
                                            cell: (p) =>
                                                l.dayBasis[p.day_basis],
                                        },
                                        {
                                            id: 'deducts',
                                            header: l.deductsBalance,
                                            kind: 'text',
                                            cell: (p) =>
                                                yesNo(p.deducts_balance),
                                        },
                                        {
                                            id: 'doc',
                                            header: l.requiresDocument,
                                            kind: 'text',
                                            cell: (p) =>
                                                yesNo(p.requires_document),
                                        },
                                        {
                                            id: 'levels',
                                            header: l.approvalLevels,
                                            kind: 'num',
                                            cell: (p) => p.approval_levels,
                                        },
                                        {
                                            id: 'max',
                                            header: l.maxPerRequest,
                                            kind: 'num',
                                            cell: (p) =>
                                                p.max_per_request ??
                                                l.unlimited,
                                        },
                                        {
                                            id: 'ent',
                                            header: l.annualEntitlement,
                                            kind: 'num',
                                            cell: (p) =>
                                                p.annual_entitlement ??
                                                l.unlimited,
                                        },
                                    ]}
                                />
                            ),
                        },
                    ]}
                />
            ) : null}

            {typeForm !== null ? (
                <AttendanceWindow
                    title={typeForm.type.id === null ? l.addType : l.editType}
                    columns={2}
                    pending={busy}
                    confirmLabel={l.save}
                    confirmDisabled={
                        typeForm.type.name_ar.trim() === '' ||
                        (typeForm.type.id === null &&
                            !/^[A-Za-z0-9_]{2,30}$/.test(typeForm.type.code))
                    }
                    onConfirm={saveType}
                    onClose={() => setTypeForm(null)}
                    sections={[
                        {
                            id: 'leave-type-form',
                            title:
                                typeForm.type.id === null
                                    ? l.addType
                                    : l.editType,
                            content: (
                                <>
                                    <RegistryTextField
                                        label={l.typeCode}
                                        editing={typeForm.type.id === null}
                                        required
                                        dir="ltr"
                                        value={typeForm.type.code}
                                        onChange={(code) =>
                                            setTypeForm({
                                                ...typeForm,
                                                type: {
                                                    ...typeForm.type,
                                                    code,
                                                },
                                            })
                                        }
                                    />
                                    <RegistryTextField
                                        label={l.nameAr}
                                        editing
                                        required
                                        value={typeForm.type.name_ar}
                                        onChange={(name_ar) =>
                                            setTypeForm({
                                                ...typeForm,
                                                type: {
                                                    ...typeForm.type,
                                                    name_ar,
                                                },
                                            })
                                        }
                                    />
                                    <RegistryTextField
                                        label={l.nameEn}
                                        editing
                                        dir="ltr"
                                        value={typeForm.type.name_en}
                                        onChange={(name_en) =>
                                            setTypeForm({
                                                ...typeForm,
                                                type: {
                                                    ...typeForm.type,
                                                    name_en,
                                                },
                                            })
                                        }
                                    />
                                    <RegistryListField
                                        label={l.appliesTo}
                                        editing
                                        required
                                        value={typeForm.type.applies_to}
                                        display={
                                            l.audiences[
                                                Number(typeForm.type.applies_to)
                                            ]
                                        }
                                        options={[1, 2, 3].map((v) => ({
                                            value: String(v),
                                            label: l.audiences[v],
                                        }))}
                                        onChange={(applies_to) =>
                                            setTypeForm({
                                                ...typeForm,
                                                type: {
                                                    ...typeForm.type,
                                                    applies_to,
                                                },
                                            })
                                        }
                                    />
                                    <RegistryListField
                                        label={l.unit}
                                        editing
                                        required
                                        value={typeForm.type.unit}
                                        display={
                                            l.unitsPlural[
                                                Number(typeForm.type.unit)
                                            ]
                                        }
                                        options={[1, 2].map((v) => ({
                                            value: String(v),
                                            label: l.unitsPlural[v],
                                        }))}
                                        onChange={(unit) =>
                                            setTypeForm({
                                                ...typeForm,
                                                type: {
                                                    ...typeForm.type,
                                                    unit,
                                                },
                                            })
                                        }
                                    />
                                    <Check
                                        label={l.sensitive}
                                        hint={l.sensitiveHint}
                                        checked={typeForm.type.is_sensitive}
                                        onChange={(is_sensitive) =>
                                            setTypeForm({
                                                ...typeForm,
                                                type: {
                                                    ...typeForm.type,
                                                    is_sensitive,
                                                },
                                            })
                                        }
                                    />
                                    <WindowTextArea
                                        label={l.description}
                                        value={typeForm.type.description}
                                        onChange={(description) =>
                                            setTypeForm({
                                                ...typeForm,
                                                type: {
                                                    ...typeForm.type,
                                                    description,
                                                },
                                            })
                                        }
                                    />
                                </>
                            ),
                        },
                        ...(typeForm.type.id === null
                            ? [
                                  {
                                      id: 'leave-type-policy',
                                      title: l.sectionPolicy,
                                      content: policyFields(
                                          typeForm.policy,
                                          (policy) =>
                                              setTypeForm({
                                                  ...typeForm,
                                                  policy,
                                              }),
                                      ),
                                  },
                              ]
                            : []),
                    ]}
                />
            ) : null}

            {policyFor !== null ? (
                <AttendanceWindow
                    title={`${l.publishPolicy} — ${policyFor.type.name_ar}`}
                    columns={2}
                    pending={busy}
                    confirmLabel={l.publishPolicy}
                    onConfirm={publish}
                    onClose={() => setPolicyFor(null)}
                    sections={[
                        {
                            id: 'leave-policy-form',
                            title: `${l.version} ${(policyFor.type.policy?.version_no ?? 0) + 1}`,
                            content: policyFields(policyFor.policy, (policy) =>
                                setPolicyFor({ ...policyFor, policy }),
                            ),
                        },
                    ]}
                />
            ) : null}
        </AttendancePage>
    );
}
