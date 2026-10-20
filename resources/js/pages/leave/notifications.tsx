import { router } from '@inertiajs/react';
import { BellOff, CheckCheck, Eye, MailOpen } from 'lucide-react';
import { useMemo, useState } from 'react';
import {
    AttendanceTable,
    useAttendanceSelection,
    visitWith,
    type AttendanceColumn,
} from '@/components/attendance/attendance-chrome';
import { dateTime } from '@/components/attendance/attendance-ui';
import {
    LeavePage,
    leaveStatusMeta,
    useLeaveEditRibbon,
    useLeaveWrite,
    type LeaveAbilities,
} from '@/components/leave/leave-chrome';
import type { ContextMenuItem } from '@/components/sis/context-menu';
import type { PageRibbonGroup } from '@/components/sis/page-ribbon-context';
import { t } from '@/i18n';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

type Notice = {
    id: number;
    request_id: number;
    request_no: number;
    kind: number;
    status: number;
    person_name: string | null;
    actor_name: string | null;
    date_from: string;
    date_to: string;
    created_at: string;
    read: boolean;
};

type Props = {
    notifications: {
        items: Notice[];
        total: number;
        page: number;
        per_page: number;
    };
    filters: { unread: boolean; academic_year_id: number };
    abilities: LeaveAbilities;
};

const PATH = '/leave/notifications';

export default function LeaveNotifications(props: Props) {
    const l = t().leave;
    const breadcrumbs: BreadcrumbItem[] = [
        { title: l.title, href: '/leave' },
        { title: l.notifications, href: PATH },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <NotificationsPage {...props} />
        </AppLayout>
    );
}

function NotificationsPage({ notifications, filters, abilities }: Props) {
    const l = t().leave;
    const write = useLeaveWrite();
    const selection = useAttendanceSelection(notifications.items, (n) => n.id);
    const [busy, setBusy] = useState(false);
    const unread = notifications.items.filter((n) => !n.read);
    const targets = selection.targets;

    const text = (n: Notice) =>
        (l.notificationKinds[n.kind] ?? '')
            .replace(':no', String(n.request_no))
            .replace(':person', n.person_name ?? '—');
    // Opening a notification opens its request (read through the module's own access rules) and marks it read.
    const open = (n: Notice) => {
        if (!n.read) {
            write('post', `${PATH}/read`, { ids: [n.id] });
        }
        router.visit('/leave/requests', {
            data: {
                q: String(n.request_no),
                academic_year_id: filters.academic_year_id,
            },
        });
    };
    const markRead = (ids: number[] | null) => {
        setBusy(true);
        write('post', `${PATH}/read`, ids === null ? {} : { ids }, {
            onDone: () => selection.clear(),
            onFinish: () => setBusy(false),
        });
    };

    const columns: AttendanceColumn<Notice>[] = [
        {
            id: 'text',
            header: l.notificationText,
            kind: 'name',
            cell: (n) => (n.read ? text(n) : <strong>{text(n)}</strong>),
            text: (n) => text(n),
        },
        {
            id: 'by',
            header: l.by,
            kind: 'text',
            cell: (n) => n.actor_name ?? '—',
        },
        {
            id: 'status',
            header: l.status,
            kind: 'status',
            tone: (n) => leaveStatusMeta(n.status)?.tone ?? 0,
            cell: (n) => l.statuses[n.status],
        },
        {
            id: 'at',
            header: l.notificationAt,
            kind: 'text',
            cell: (n) => dateTime(n.created_at),
            sort: (n) => n.created_at,
        },
    ];

    const groups = useMemo(
        (): PageRibbonGroup[] => [
            {
                id: 'leave-notifications-actions',
                label: l.actions,
                commands: [
                    {
                        id: 'leave-notice-open',
                        label: l.openRequest,
                        icon: Eye,
                        iconTone: 'sky',
                        disabled: targets.length !== 1,
                        title:
                            targets.length === 1 ? undefined : l.onlySelected,
                        onSelect: () =>
                            targets.length === 1 && open(targets[0]),
                    },
                    {
                        id: 'leave-notice-read',
                        label: l.markRead,
                        icon: MailOpen,
                        iconTone: 'ok',
                        disabled: busy || targets.every((n) => n.read),
                        onSelect: () =>
                            markRead(
                                targets.filter((n) => !n.read).map((n) => n.id),
                            ),
                    },
                    {
                        id: 'leave-notice-read-all',
                        label: l.markAllRead,
                        icon: CheckCheck,
                        iconTone: 'forest',
                        disabled: busy || unread.length === 0,
                        onSelect: () => markRead(null),
                    },
                ],
            },
            {
                id: 'leave-notifications-scope',
                label: l.filters,
                commands: [
                    {
                        id: 'leave-notice-unread',
                        label: l.unreadOnly,
                        icon: BellOff,
                        iconTone: 'amber',
                        pressed: filters.unread,
                        onSelect: () =>
                            visitWith(PATH, {
                                unread: filters.unread ? null : 1,
                            }),
                    },
                ],
            },
        ],
        // eslint-disable-next-line react-hooks/exhaustive-deps
        [targets, busy, unread.length, filters.unread],
    );
    useLeaveEditRibbon(groups);

    const menuItems = (n: Notice): ContextMenuItem[] => [
        {
            id: 'open',
            label: l.openRequest,
            icon: Eye,
            onSelect: () => open(n),
        },
        ...(n.read
            ? []
            : [
                  {
                      id: 'read',
                      label: l.markRead,
                      icon: MailOpen,
                      onSelect: () => markRead([n.id]),
                  },
              ]),
    ];

    return (
        <LeavePage
            title={l.notificationsTitle}
            current="notifications"
            abilities={abilities}
        >
            <AttendanceTable
                caption={l.notificationsTitle}
                storageKey="leave.notifications"
                rows={notifications.items}
                rowKey={(n) => n.id}
                columns={columns}
                emptyText={l.noNotifications}
                selection={selection}
                onOpen={open}
                menuItems={menuItems}
                menuLabel={l.actions}
                server={{
                    page: notifications.page,
                    perPage: notifications.per_page,
                    total: notifications.total,
                    lastPage: Math.max(
                        1,
                        Math.ceil(notifications.total / notifications.per_page),
                    ),
                    onPage: (page, per_page) =>
                        visitWith(PATH, {
                            unread: filters.unread ? 1 : null,
                            page,
                            per_page,
                        }),
                }}
            />
        </LeavePage>
    );
}
