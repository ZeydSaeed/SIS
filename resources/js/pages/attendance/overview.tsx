import { usePage } from '@inertiajs/react';
import {
    attendanceRailItems,
    railCount,
    type Totals,
} from '@/components/attendance/day-board';
import type { AttendanceAbilities } from '@/components/attendance/attendance-ui';
import {
    ModuleOverview,
    type OverviewCard,
} from '@/components/sis/module-overview';
import { t } from '@/i18n';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

const TONES = [
    'interview',
    'submitted',
    'under-review',
    'transfer',
    'waitlisted',
    'rejected',
    'withdrawn',
];

/** «الحضور» overview: a card per section of the page's side rail (same sections, same counts). */
export default function AttendanceOverview() {
    const shell = usePage<{
        attendanceShell?: {
            abilities: AttendanceAbilities;
            counts?: Record<string, number>;
            totals: Totals;
        } | null;
    }>().props.attendanceShell;
    const breadcrumbs: BreadcrumbItem[] = [
        { title: t().attendance.title, href: '/attendance' },
    ];
    const cards: OverviewCard[] =
        shell === null || shell === undefined
            ? []
            : attendanceRailItems(shell.abilities).map((item, index) => ({
                  key: item.href,
                  label: item.label,
                  icon: item.icon,
                  count: railCount(item.href, shell.counts ?? {}),
                  tone: TONES[index % TONES.length],
                  href: item.href,
              }));

    // The bar and legend: the shown day's marks, the same shares as the side panel's totals card.
    const sheet = t().attendanceSystem.sheet;
    const totals = shell?.totals;
    const segments =
        totals === undefined
            ? []
            : [
                  { key: 'present', label: sheet.present, count: totals.present, tone: 'transfer' },
                  { key: 'absent', label: sheet.absent, count: totals.absent, tone: 'rejected' },
                  { key: 'late', label: sheet.late, count: totals.late, tone: 'waitlisted' },
                  { key: 'excused', label: sheet.excused, count: totals.excused, tone: 'submitted' },
                  { key: 'left_early', label: sheet.leftEarly, count: totals.left_early, tone: 'under-review' },
              ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <ModuleOverview
                title={t().attendance.title}
                listPath="/attendance"
                cards={cards}
                allLabel={t().attendanceSystem.nav.sessions}
                segments={segments}
            />
        </AppLayout>
    );
}
