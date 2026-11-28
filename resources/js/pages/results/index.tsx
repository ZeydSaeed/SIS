import {
    ChartPie,
    ClipboardPen,
    Eye,
    FileChartColumn,
    FileCog,
    GraduationCap,
    Link2,
    MessageCircleQuestion,
    ScrollText,
    ShieldCheck,
    UserCog,
    type LucideIcon,
} from 'lucide-react';
import { ModuleOverview, type OverviewCard } from '@/components/sis/module-overview';
import AppLayout from '@/layouts/app-layout';
import { t } from '@/i18n';
import type { BreadcrumbItem } from '@/types';

/** The tones the «المناهج» overview gives its cards, in turn. */
const TONES = ['submitted', 'interview', 'waitlisted', 'transfer', 'under-review', 'vocational', 'rejected'];

/** A card's own colour, as each curriculum's on «المناهج» (golden-angle hue by its place). */
const colorOf = (place: number): string => `hsl(${Math.round((place * 137.508) % 360)} 70% 45%)`;

/**
 * «النتائج» (owner's request, 2026-11-28): the cyber_security control panel's items, laid out exactly like the
 * «المناهج» overview — the share bar with its legend, then one card per item with its bare icon in its own colour.
 * Pictures, bars, styling and order only: nothing is read (no teachers, subjects, curricula, grades or students), so
 * every share is 0. Only «إدخال الدرجات» opens a page (/results/entry-window, design only); the others open nothing. The other results pages (/results/show, /term, /transcript,
 * /publications) are unchanged.
 */
export default function ResultsIndex() {
    const i18n = t();
    const b = i18n.results.board;
    const breadcrumbs: BreadcrumbItem[] = [{ title: i18n.modules.results, href: '/results' }];
    const items: Array<{ key: string; lines: string[]; icon: LucideIcon; href?: string }> = [
        { key: 'entry', lines: [b.entry, b.entrySubtitle], icon: ClipboardPen, href: '/results/entry-window' },
        { key: 'links', lines: [b.links], icon: Link2 },
        { key: 'teachers', lines: [b.teachers], icon: UserCog },
        { key: 'students', lines: [b.students], icon: GraduationCap },
        { key: 'permissions', lines: [b.permissions], icon: ShieldCheck },
        { key: 'appeals', lines: [b.appeals], icon: MessageCircleQuestion },
        { key: 'card', lines: [b.card], icon: FileCog },
        { key: 'teacher-view', lines: [b.teacherView], icon: Eye },
        { key: 'subjects', lines: [b.subjects], icon: ChartPie },
        { key: 'results', lines: [b.results], icon: ScrollText },
        { key: 'reports', lines: [b.reports], icon: FileChartColumn },
    ];
    const cards: OverviewCard[] = items.map((item, index) => ({
        key: item.key,
        label: item.lines.join(' — '),
        lines: item.lines,
        icon: item.icon,
        bareIcon: true,
        tone: TONES[index % TONES.length],
        color: colorOf(index + 1),
        href: item.href,
    }));

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <ModuleOverview
                title={i18n.modules.results}
                listPath="/results"
                cards={cards}
                segments={cards.map((card) => ({
                    key: card.key,
                    label: card.lines?.[0] ?? card.label,
                    count: 0,
                    tone: card.tone,
                    share: 0,
                    color: card.color,
                }))}
            />
        </AppLayout>
    );
}
