import { router } from '@inertiajs/react';
import { Bell, CalendarDays, LayoutGrid, Monitor } from 'lucide-react';
import { t } from '@/i18n';

type UtilityItem = {
    id: string;
    label: string;
    href: string;
    icon: 'apps' | 'queue' | 'bell' | 'calendar' | 'monitor';
    badge?: string;
};

/** Left-side chrome utilities — apps, queue, alerts, schedule, system status. */
export function TitleBarUtilities() {
    const i18n = t().chrome;
    const items: UtilityItem[] = [
        {
            id: 'apps',
            label: i18n.apps,
            href: '/hub',
            icon: 'apps',
        },
        {
            id: 'queue',
            label: i18n.queue,
            href: '/workflow',
            icon: 'queue',
            badge: '2',
        },
        {
            id: 'notifications',
            label: i18n.notifications,
            href: '/communication',
            icon: 'bell',
        },
        {
            id: 'schedule',
            label: i18n.schedule,
            href: '/reports',
            icon: 'calendar',
        },
        {
            id: 'monitor',
            label: i18n.monitor,
            href: '/intelligence/recommendations',
            icon: 'monitor',
        },
    ];

    return (
        <nav className="sis-titlebar__utilities" aria-label={i18n.utilities} dir="ltr">
            {items.map((item) => (
                <button
                    key={item.id}
                    type="button"
                    className="sis-titlebar__utility"
                    aria-label={item.label}
                    title={item.label}
                    onClick={() => router.visit(item.href)}
                >
                    {item.icon === 'apps' ? (
                        <LayoutGrid className="sis-titlebar__utility-icon" aria-hidden />
                    ) : null}
                    {item.icon === 'queue' ? (
                        <span className="sis-titlebar__utility-badge" aria-hidden>
                            {item.badge ?? '0'}
                        </span>
                    ) : null}
                    {item.icon === 'bell' ? (
                        <Bell className="sis-titlebar__utility-icon" aria-hidden />
                    ) : null}
                    {item.icon === 'calendar' ? (
                        <CalendarDays className="sis-titlebar__utility-icon" aria-hidden />
                    ) : null}
                    {item.icon === 'monitor' ? (
                        <Monitor className="sis-titlebar__utility-icon" aria-hidden />
                    ) : null}
                </button>
            ))}
        </nav>
    );
}
