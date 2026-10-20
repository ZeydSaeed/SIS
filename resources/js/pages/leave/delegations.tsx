import { PlusCircle, UserMinus } from 'lucide-react';
import { useMemo, useState } from 'react';
import {
    AttendanceTable,
    AttendanceWindow,
    useAttendanceSelection,
    type AttendanceColumn,
} from '@/components/attendance/attendance-chrome';
import {
    LeaveInfoNote,
    LeavePage,
    useLeaveEditRibbon,
    useLeaveWrite,
    type LeaveAbilities,
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

type Delegation = {
    id: number;
    delegator_user_id: number;
    delegator_name: string;
    delegate_user_id: number;
    delegate_name: string;
    date_from: string;
    date_to: string;
    status: number;
    created_at: string;
    revoked_at: string | null;
};

type Props = {
    delegations: {
        items: Delegation[];
        candidates: Array<{ id: number; name: string }>;
    };
    today: string;
    user_id: number;
    abilities: LeaveAbilities;
};

const PATH = '/leave/delegations';

export default function LeaveDelegations(props: Props) {
    const l = t().leave;
    const breadcrumbs: BreadcrumbItem[] = [
        { title: l.title, href: '/leave' },
        { title: l.delegations, href: PATH },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <DelegationsPage {...props} />
        </AppLayout>
    );
}

function DelegationsPage({ delegations, today, user_id, abilities }: Props) {
    const l = t().leave;
    const write = useLeaveWrite();
    const selection = useAttendanceSelection(delegations.items, (d) => d.id);
    const one = selection.targets.length === 1 ? selection.targets[0] : null;
    const [form, setForm] = useState<{
        delegate: string;
        from: string;
        to: string;
    } | null>(null);
    const [ending, setEnding] = useState<Delegation | null>(null);
    const [busy, setBusy] = useState(false);

    // A delegation shows as active only inside its dates and while not revoked.
    const stateOf = (d: Delegation): string =>
        d.status === 2
            ? l.delegationRevoked
            : d.date_to < today
              ? l.delegationEnded
              : d.date_from > today
                ? l.delegationUpcoming
                : l.delegationActive;
    const endable = (d: Delegation | null): d is Delegation =>
        d !== null &&
        d.status === 1 &&
        d.date_to >= today &&
        (abilities.manage || d.delegator_user_id === user_id);

    const save = () => {
        if (form === null) return;
        setBusy(true);
        write(
            'post',
            PATH,
            {
                delegate_user_id: Number(form.delegate),
                date_from: form.from,
                date_to: form.to,
            },
            {
                onDone: () => setForm(null),
                onFinish: () => setBusy(false),
            },
        );
    };
    const end = () => {
        if (ending === null) return;
        setBusy(true);
        write(
            'post',
            `${PATH}/${ending.id}/revoke`,
            {},
            {
                onDone: () => setEnding(null),
                onFinish: () => setBusy(false),
            },
        );
    };

    const columns: AttendanceColumn<Delegation>[] = [
        {
            id: 'delegator',
            header: l.delegator,
            kind: 'name',
            cell: (d) => d.delegator_name,
        },
        {
            id: 'delegate',
            header: l.delegate,
            kind: 'name',
            cell: (d) => d.delegate_name,
        },
        {
            id: 'from',
            header: l.dateFrom,
            kind: 'text',
            cell: (d) => <span dir="ltr">{d.date_from}</span>,
        },
        {
            id: 'to',
            header: l.dateTo,
            kind: 'text',
            cell: (d) => <span dir="ltr">{d.date_to}</span>,
        },
        {
            id: 'status',
            header: l.status,
            kind: 'status',
            tone: (d) => (d.status === 1 && d.date_to >= today ? 1 : 0),
            cell: (d) => stateOf(d),
            text: (d) => stateOf(d),
        },
    ];

    const groups = useMemo(
        (): PageRibbonGroup[] => [
            {
                id: 'leave-delegations-actions',
                label: l.actions,
                commands: [
                    ...(abilities.delegate
                        ? [
                              {
                                  id: 'leave-delegation-add',
                                  label: l.delegationAdd,
                                  icon: PlusCircle,
                                  iconTone: 'ok' as const,
                                  onSelect: () =>
                                      setForm({
                                          delegate: '',
                                          from: today,
                                          to: today,
                                      }),
                              },
                          ]
                        : []),
                    {
                        id: 'leave-delegation-revoke',
                        label: l.delegationRevoke,
                        icon: UserMinus,
                        iconTone: 'danger' as const,
                        tone: 'delete' as const,
                        disabled: !endable(one),
                        title: endable(one) ? undefined : l.onlySelected,
                        onSelect: () => one && setEnding(one),
                    },
                ],
            },
        ],
        // eslint-disable-next-line react-hooks/exhaustive-deps
        [one, abilities, today],
    );
    useLeaveEditRibbon(groups);

    const menuItems = (d: Delegation): ContextMenuItem[] =>
        endable(d)
            ? [
                  {
                      id: 'revoke',
                      label: l.delegationRevoke,
                      icon: UserMinus,
                      danger: true,
                      onSelect: () => setEnding(d),
                  },
              ]
            : [];

    const options = delegations.candidates.map((c) => ({
        value: String(c.id),
        label: c.name,
    }));

    return (
        <LeavePage
            title={l.delegationsTitle}
            current="delegations"
            abilities={abilities}
        >
            <AttendanceTable
                caption={l.delegationsTitle}
                storageKey="leave.delegations"
                rows={delegations.items}
                rowKey={(d) => d.id}
                columns={columns}
                emptyText={l.delegationEmpty}
                selection={selection}
                menuItems={menuItems}
                menuLabel={l.actions}
            />
            <LeaveInfoNote items={[l.delegationsHint]} />

            {form !== null ? (
                <AttendanceWindow
                    title={l.delegationAdd}
                    columns={2}
                    pending={busy}
                    confirmLabel={l.delegationAdd}
                    confirmDisabled={
                        form.delegate === '' ||
                        form.from === '' ||
                        form.to === '' ||
                        form.to < form.from
                    }
                    onConfirm={save}
                    onClose={() => setForm(null)}
                    sections={[
                        {
                            id: 'leave-delegation-form',
                            title: l.delegationsTitle,
                            content: (
                                <>
                                    <RegistryListField
                                        label={l.delegate}
                                        editing
                                        required
                                        fieldClassName="sis-branches-field--wide"
                                        value={form.delegate}
                                        display={
                                            options.find(
                                                (o) => o.value === form.delegate,
                                            )?.label ??
                                            (options.length === 0
                                                ? l.delegationNoCandidates
                                                : l.chooseDelegate)
                                        }
                                        options={options}
                                        onChange={(delegate) =>
                                            setForm({ ...form, delegate })
                                        }
                                    />
                                    <RegistryTextField
                                        label={l.dateFrom}
                                        editing
                                        required
                                        type="date"
                                        dir="ltr"
                                        value={form.from}
                                        onChange={(from) =>
                                            setForm({ ...form, from })
                                        }
                                    />
                                    <RegistryTextField
                                        label={l.dateTo}
                                        editing
                                        required
                                        type="date"
                                        dir="ltr"
                                        value={form.to}
                                        onChange={(to) =>
                                            setForm({ ...form, to })
                                        }
                                    />
                                    <LeaveInfoNote items={[l.delegationsHint]} />
                                </>
                            ),
                        },
                    ]}
                />
            ) : null}

            {ending !== null ? (
                <AttendanceWindow
                    title={l.delegationRevoke}
                    pending={busy}
                    danger
                    confirmLabel={l.delegationRevoke}
                    onConfirm={end}
                    onClose={() => setEnding(null)}
                    sections={[
                        {
                            id: 'leave-delegation-end',
                            title: `${ending.delegator_name} ← ${ending.delegate_name}`,
                            content: (
                                <p className="sis-attendance-window__hint sis-branches-field--wide">
                                    {l.delegationRevokeConfirm}
                                </p>
                            ),
                        },
                    ]}
                />
            ) : null}
        </LeavePage>
    );
}
