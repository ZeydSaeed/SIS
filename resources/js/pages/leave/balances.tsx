import { router } from '@inertiajs/react';
import { Gift, ListOrdered, Scale } from 'lucide-react';
import { useMemo, useState } from 'react';
import {
    AttendanceTable,
    AttendanceWindow,
    WindowFact,
    useAttendanceHomeFilters,
    useAttendanceSearch,
    useAttendanceSelection,
    useYearFilterSpec,
    visitWith,
    type AttendanceColumn,
} from '@/components/attendance/attendance-chrome';
import { dateTime } from '@/components/attendance/attendance-ui';
import {
    LeaveSectionTitle,
    LeavePage,
    LeaveTextArea,
    useLeaveEditRibbon,
    useLeaveWrite,
    type LeaveAbilities,
    type LeaveType,
    type StaffOption,
} from '@/components/leave/leave-chrome';
import {
    RegistryListField,
    RegistryTextField,
} from '@/components/organization/registry-sheet';
import type { ContextMenuItem } from '@/components/sis/context-menu';
import type { PageRibbonGroup } from '@/components/sis/page-ribbon-context';
import type { FilterSpec } from '@/components/sis/ribbon-filter';
import { t } from '@/i18n';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

type BalanceRow = {
    kind: number;
    person_id: number;
    person_name: string;
    person_code: string | null;
    leave_type_id: number;
    type_name: string;
    unit: number;
    annual_entitlement: number | null;
    granted: boolean;
    entitled: number;
    adjusted: number;
    used: number;
    reserved: number;
    balance: number;
    available: number;
};

type LedgerRow = {
    id: number;
    type_name: string;
    entry_type: number;
    quantity: number;
    reason: string | null;
    request_no: number | null;
    created_by_name: string | null;
    created_at: string;
};

type Props = {
    balances: {
        items: BalanceRow[];
        total: number;
        page: number;
        per_page: number;
    };
    types: LeaveType[];
    staff: StaffOption[];
    filters: {
        academic_year_id: number;
        kind: number | null;
        type: number[];
        q: string;
    };
    abilities: LeaveAbilities;
    ledger?: LedgerRow[] | null;
};

const PATH = '/leave/balances';
const key = (r: BalanceRow) =>
    r.kind * 1_000_000_000 + r.person_id * 1000 + r.leave_type_id;

export default function LeaveBalances(props: Props) {
    const l = t().leave;
    const breadcrumbs: BreadcrumbItem[] = [
        { title: l.title, href: '/leave' },
        { title: l.balances, href: PATH },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <BalancesPage {...props} />
        </AppLayout>
    );
}

function BalancesPage({
    balances,
    types,
    staff,
    filters,
    abilities,
    ledger,
}: Props) {
    const l = t().leave;
    const write = useLeaveWrite();
    const selection = useAttendanceSelection(balances.items, key);
    const [adjusting, setAdjusting] = useState<{
        person: string;
        type: string;
        quantity: string;
        reason: string;
    } | null>(null);
    const [granting, setGranting] = useState<string | null>(null);
    const [ledgerOf, setLedgerOf] = useState<BalanceRow | null>(null);
    const [busy, setBusy] = useState(false);
    const one = selection.targets.length === 1 ? selection.targets[0] : null;
    const deducting = types.filter(
        (x) => x.status === 1 && x.policy?.deducts_balance,
    );
    const visit = (
        next: Partial<Props['filters']> & { page?: number; per_page?: number },
    ) => visitWith(PATH, { ...filters, ...next, page: next.page ?? 1 });

    const openLedger = (row: BalanceRow) => {
        setLedgerOf(row);
        router.reload({
            only: ['ledger'],
            data: {
                ledger: `${row.kind}:${row.person_id}`,
                ledger_type: row.leave_type_id,
            } as never,
            preserveUrl: true,
        } as never);
    };
    const adjust = () => {
        if (adjusting === null) return;
        const [kind, person] = adjusting.person.split(':').map(Number);
        setBusy(true);
        write(
            'post',
            `${PATH}/adjust`,
            {
                academic_year_id: filters.academic_year_id,
                requester_kind: kind,
                person_id: person,
                leave_type_id: Number(adjusting.type),
                quantity: Number(adjusting.quantity),
                reason: adjusting.reason.trim(),
            },
            {
                onDone: () => setAdjusting(null),
                onFinish: () => setBusy(false),
            },
        );
    };
    const grant = () => {
        if (granting === null) return;
        setBusy(true);
        write(
            'post',
            `${PATH}/grant`,
            {
                academic_year_id: filters.academic_year_id,
                leave_type_id: Number(granting),
            },
            { onDone: () => setGranting(null), onFinish: () => setBusy(false) },
        );
    };

    const num = (v: number) => (Number.isInteger(v) ? v : v.toFixed(2));
    const columns: AttendanceColumn<BalanceRow>[] = [
        {
            id: 'person',
            header: l.person,
            kind: 'name',
            cell: (r) => r.person_name,
        },
        {
            id: 'code',
            header: l.personCode,
            kind: 'text',
            cell: (r) => <span dir="ltr">{r.person_code ?? '—'}</span>,
        },
        {
            id: 'kind',
            header: l.kind,
            kind: 'text',
            cell: (r) => l.kinds[r.kind],
        },
        {
            id: 'type',
            header: l.type,
            kind: 'text',
            cell: (r) => `${r.type_name} (${l.unitsPlural[r.unit]})`,
        },
        {
            id: 'entitled',
            header: l.entitled,
            kind: 'num',
            cell: (r) => (r.granted ? num(r.entitled) : l.notGranted),
            sort: (r) => r.entitled,
        },
        {
            id: 'adjusted',
            header: l.adjusted,
            kind: 'num',
            cell: (r) => num(r.adjusted),
        },
        { id: 'used', header: l.used, kind: 'num', cell: (r) => num(r.used) },
        {
            id: 'reserved',
            header: l.reserved,
            kind: 'num',
            cell: (r) => num(r.reserved),
        },
        {
            id: 'balance',
            header: l.balance,
            kind: 'num',
            cell: (r) => num(r.balance),
        },
        {
            id: 'available',
            header: l.available,
            kind: 'status',
            tone: (r) => (r.available <= 0 ? 4 : r.available < 3 ? 2 : 1),
            cell: (r) => num(r.available),
            sort: (r) => r.available,
        },
    ];

    const groups = useMemo(
        (): PageRibbonGroup[] => [
            {
                id: 'leave-balances-actions',
                label: l.actions,
                commands: [
                    {
                        id: 'leave-ledger',
                        label: l.ledger,
                        icon: ListOrdered,
                        iconTone: 'sky',
                        disabled: one === null,
                        title: one === null ? l.onlySelected : undefined,
                        onSelect: () => one && openLedger(one),
                    },
                    ...(abilities.adjust
                        ? [
                              {
                                  id: 'leave-adjust',
                                  label: l.adjust,
                                  icon: Scale,
                                  iconTone: 'amber' as const,
                                  disabled:
                                      staff.length === 0 ||
                                      deducting.length === 0,
                                  onSelect: () =>
                                      setAdjusting({
                                          person: one
                                              ? `${one.kind}:${one.person_id}`
                                              : '',
                                          type: one
                                              ? String(one.leave_type_id)
                                              : '',
                                          quantity: '',
                                          reason: '',
                                      }),
                              },
                              {
                                  id: 'leave-grant',
                                  label: l.grant,
                                  icon: Gift,
                                  iconTone: 'ok' as const,
                                  disabled: deducting.length === 0,
                                  onSelect: () =>
                                      setGranting(
                                          String(
                                              deducting.find(
                                                  (x) =>
                                                      x.policy
                                                          ?.annual_entitlement !==
                                                      null,
                                              )?.id ?? '',
                                          ),
                                      ),
                              },
                          ]
                        : []),
                ],
            },
        ],
        // eslint-disable-next-line react-hooks/exhaustive-deps
        [one, abilities, staff, types],
    );
    useLeaveEditRibbon(groups);
    const yearSpec = useYearFilterSpec(filters.academic_year_id, (year) =>
        visitWith(PATH, { academic_year_id: year }),
    );
    useAttendanceHomeFilters(
        [
            yearSpec,
            {
                key: 'kind',
                type: 'single',
                label: l.kind,
                value: filters.kind === null ? '' : String(filters.kind),
                options: [
                    { value: '', label: l.allKinds },
                    { value: '1', label: l.kindsPlural[1] },
                    { value: '2', label: l.kindsPlural[2] },
                ],
                onChange: (v: string) =>
                    visit({ kind: v === '' ? null : Number(v) }),
            } as FilterSpec,
            {
                key: 'type',
                type: 'multi',
                label: l.type,
                allLabel: l.allTypes,
                values: filters.type.map(String),
                options: deducting.map((x) => ({
                    value: String(x.id),
                    label: x.name_ar,
                })),
                onChange: (values: string[]) =>
                    visit({ type: values.map(Number) }),
            } as FilterSpec,
        ],
        () => visitWith(PATH, { academic_year_id: filters.academic_year_id }),
        [filters, types, yearSpec.options.length],
    );
    useAttendanceSearch(filters.q, l.search, (q) => visit({ q }));

    const menuItems = (row: BalanceRow): ContextMenuItem[] => [
        {
            id: 'ledger',
            label: l.ledger,
            icon: ListOrdered,
            onSelect: () => openLedger(row),
        },
        ...(abilities.adjust
            ? [
                  {
                      id: 'adjust',
                      label: l.adjust,
                      icon: Scale,
                      onSelect: () =>
                          setAdjusting({
                              person: `${row.kind}:${row.person_id}`,
                              type: String(row.leave_type_id),
                              quantity: '',
                              reason: '',
                          }),
                  },
              ]
            : []),
    ];
    const staffOptions = staff.map((s) => ({
        value: `${s.kind}:${s.id}`,
        label: `${s.name} — ${l.kinds[s.kind]}`,
    }));
    const grantType = types.find((x) => String(x.id) === granting) ?? null;

    return (
        <LeavePage
            title={l.balances}
            current={'balances'}
            abilities={abilities}
        >
            <LeaveSectionTitle title={l.balances} count={balances.total} />
            <p className="sis-attendance-window__hint">{l.balancesHint}</p>
            <AttendanceTable
                caption={l.balances}
                storageKey="leave.balances"
                rows={balances.items}
                rowKey={key}
                columns={columns}
                emptyText={l.empty}
                selection={selection}
                onOpen={openLedger}
                menuItems={menuItems}
                menuLabel={l.actions}
                server={{
                    page: balances.page,
                    perPage: balances.per_page,
                    total: balances.total,
                    lastPage: Math.max(
                        1,
                        Math.ceil(balances.total / balances.per_page),
                    ),
                    onPage: (page, per_page) => visit({ page, per_page }),
                }}
            />

            {ledgerOf !== null ? (
                <AttendanceWindow
                    title={`${l.ledgerTitle} — ${ledgerOf.person_name} · ${ledgerOf.type_name}`}
                    wide
                    columns={2}
                    onClose={() => setLedgerOf(null)}
                    sections={[
                        {
                            id: 'leave-ledger',
                            title: l.ledger,
                            plain: true,
                            content: (
                                <AttendanceTable
                                    caption={l.ledger}
                                    storageKey="leave.ledger"
                                    rows={ledger ?? []}
                                    rowKey={(e) => e.id}
                                    fit={false}
                                    emptyText={l.ledgerEmpty}
                                    columns={[
                                        {
                                            id: 'at',
                                            header: l.entryAt,
                                            kind: 'text',
                                            cell: (e) => dateTime(e.created_at),
                                        },
                                        {
                                            id: 'entry',
                                            header: l.entryType,
                                            kind: 'text',
                                            cell: (e) =>
                                                l.entries[e.entry_type],
                                        },
                                        {
                                            id: 'qty',
                                            header: l.entryQuantity,
                                            kind: 'num',
                                            cell: (e) => (
                                                <span dir="ltr">
                                                    {e.quantity > 0
                                                        ? `+${e.quantity}`
                                                        : e.quantity}
                                                </span>
                                            ),
                                        },
                                        {
                                            id: 'req',
                                            header: l.entryRequest,
                                            kind: 'num',
                                            cell: (e) =>
                                                e.request_no === null
                                                    ? '—'
                                                    : `#${e.request_no}`,
                                        },
                                        {
                                            id: 'reason',
                                            header: l.reason,
                                            kind: 'text',
                                            cell: (e) => e.reason ?? '—',
                                        },
                                        {
                                            id: 'by',
                                            header: l.entryBy,
                                            kind: 'text',
                                            cell: (e) =>
                                                e.created_by_name ?? '—',
                                        },
                                    ]}
                                />
                            ),
                        },
                    ]}
                />
            ) : null}

            {adjusting !== null ? (
                <AttendanceWindow
                    title={l.adjustTitle}
                    columns={2}
                    pending={busy}
                    confirmLabel={l.adjust}
                    confirmDisabled={
                        adjusting.person === '' ||
                        adjusting.type === '' ||
                        Number(adjusting.quantity) === 0 ||
                        Number.isNaN(Number(adjusting.quantity)) ||
                        adjusting.reason.trim() === ''
                    }
                    onConfirm={adjust}
                    onClose={() => setAdjusting(null)}
                    sections={[
                        {
                            id: 'leave-adjust',
                            title: l.adjustTitle,
                            content: (
                                <>
                                    <RegistryListField
                                        label={l.person}
                                        editing
                                        required
                                        fieldClassName="sis-branches-field--wide"
                                        value={adjusting.person}
                                        display={
                                            staffOptions.find(
                                                (o) =>
                                                    o.value ===
                                                    adjusting.person,
                                            )?.label ?? l.choosePerson
                                        }
                                        options={staffOptions}
                                        onChange={(person) =>
                                            setAdjusting({
                                                ...adjusting,
                                                person,
                                            })
                                        }
                                    />
                                    <RegistryListField
                                        label={l.type}
                                        editing
                                        required
                                        value={adjusting.type}
                                        display={
                                            deducting.find(
                                                (x) =>
                                                    String(x.id) ===
                                                    adjusting.type,
                                            )?.name_ar ?? l.chooseType
                                        }
                                        options={deducting.map((x) => ({
                                            value: String(x.id),
                                            label: x.name_ar,
                                        }))}
                                        onChange={(type) =>
                                            setAdjusting({ ...adjusting, type })
                                        }
                                    />
                                    <RegistryTextField
                                        label={l.entryQuantity}
                                        editing
                                        required
                                        type="number"
                                        dir="ltr"
                                        value={adjusting.quantity}
                                        onChange={(quantity) =>
                                            setAdjusting({
                                                ...adjusting,
                                                quantity,
                                            })
                                        }
                                    />
                                    <LeaveTextArea
                                        label={l.reason}
                                        required
                                        value={adjusting.reason}
                                        onChange={(reason) =>
                                            setAdjusting({
                                                ...adjusting,
                                                reason,
                                            })
                                        }
                                    />
                                    <p className="sis-attendance-window__hint sis-branches-field--wide">
                                        {l.adjustHint}
                                    </p>
                                </>
                            ),
                        },
                    ]}
                />
            ) : null}

            {granting !== null ? (
                <AttendanceWindow
                    title={l.grantTitle}
                    pending={busy}
                    confirmLabel={l.grant}
                    confirmDisabled={
                        grantType?.policy?.annual_entitlement === null ||
                        grantType === null
                    }
                    onConfirm={grant}
                    onClose={() => setGranting(null)}
                    sections={[
                        {
                            id: 'leave-grant',
                            title: l.grantTitle,
                            content: (
                                <>
                                    <RegistryListField
                                        label={l.type}
                                        editing
                                        required
                                        fieldClassName="sis-branches-field--wide"
                                        value={granting}
                                        display={
                                            grantType?.name_ar ?? l.chooseType
                                        }
                                        options={deducting.map((x) => ({
                                            value: String(x.id),
                                            label: x.name_ar,
                                        }))}
                                        onChange={setGranting}
                                    />
                                    <WindowFact
                                        label={l.annualEntitlement}
                                        value={
                                            grantType?.policy
                                                ?.annual_entitlement ??
                                            l.unlimited
                                        }
                                        wide
                                    />
                                    <p className="sis-attendance-window__hint sis-branches-field--wide">
                                        {l.grantHint}
                                    </p>
                                </>
                            ),
                        },
                    ]}
                />
            ) : null}
        </LeavePage>
    );
}
