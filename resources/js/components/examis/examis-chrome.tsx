import { Head, router, usePage } from '@inertiajs/react';
import {
    Calculator,
    ClipboardPen,
    FileBadge,
    GraduationCap,
    ListChecks,
    NotebookPen,
    ScrollText,
    Settings2,
    type LucideIcon,
} from 'lucide-react';
import { useMemo, type ReactNode } from 'react';
import { useAttendanceHomeFilters } from '@/components/attendance/attendance-chrome';
import { newIdempotencyKey } from '@/components/organization/registry-sheet';
import { usePageError } from '@/components/sis/page-error-context';
import {
    useRegisterPageRibbon,
    type PageRibbonCommand,
    type PageRibbonGroup,
} from '@/components/sis/page-ribbon-context';
import { sisListPageClass } from '@/components/sis/page-theme';
import type { FilterSpec } from '@/components/sis/ribbon-filter';
import { t } from '@/i18n';

/** The exams / grades / results sections — one icon and colour each, on the «تحرير» tab of every page of the three. */
export type ExamisSection =
    | 'exams'
    | 'types'
    | 'gradeSheet'
    | 'grades'
    | 'termSheet'
    | 'yearSheet'
    | 'results'
    | 'transcript';

const MODULE: Record<ExamisSection, 'exams' | 'grades' | 'results'> = {
    exams: 'exams',
    types: 'exams',
    gradeSheet: 'grades',
    grades: 'grades',
    termSheet: 'results',
    yearSheet: 'results',
    results: 'results',
    transcript: 'results',
};

const SECTIONS: Array<{
    key: ExamisSection;
    href: string;
    icon: LucideIcon;
    tone: NonNullable<PageRibbonCommand['iconTone']>;
}> = [
    { key: 'exams', href: '/exams', icon: NotebookPen, tone: 'education' },
    { key: 'types', href: '/exams/types', icon: Settings2, tone: 'authority' },
    {
        key: 'gradeSheet',
        href: '/grades/sheet',
        icon: ClipboardPen,
        tone: 'growth',
    },
    { key: 'grades', href: '/grades', icon: ListChecks, tone: 'forest' },
    {
        key: 'termSheet',
        href: '/results/sheet',
        icon: Calculator,
        tone: 'amber',
    },
    {
        key: 'yearSheet',
        href: '/results/year-sheet',
        icon: GraduationCap,
        tone: 'sky',
    },
    { key: 'results', href: '/results', icon: ScrollText, tone: 'steel' },
    {
        key: 'transcript',
        href: '/results/transcript',
        icon: FileBadge,
        tone: 'rose',
    },
];

type ExamisShell = { sections: Partial<Record<ExamisSection, boolean>> };

/**
 * «تحرير»: the page's own command groups, then the sections of the page's module the viewer may open (server-computed
 * `examisShell`; the sidebar links the three modules, so each ribbon lists only its own module's pages).
 */
export function useExamisEditRibbon(
    current: ExamisSection | null,
    pageGroups: PageRibbonGroup[],
): void {
    const allowed = usePage<{ examisShell?: ExamisShell | null }>().props
        .examisShell?.sections;
    const groups = useMemo((): PageRibbonGroup[] => {
        const c = t().examisChrome;
        const sections: PageRibbonGroup = {
            id: 'examis-sections',
            label: current === null ? c.sections : t().modules[MODULE[current]],
            commands: SECTIONS.filter(
                (s) =>
                    allowed?.[s.key] === true &&
                    (current === null || MODULE[s.key] === MODULE[current]),
            ).map((s): PageRibbonCommand => ({
                id: `examis-section-${s.key}`,
                label: c.sectionLabels[s.key],
                icon: s.icon,
                iconTone: s.tone,
                pressed: s.key === current,
                onSelect: () =>
                    s.key === current ? undefined : router.visit(s.href),
            })),
        };

        return [...pageGroups, sections];
    }, [allowed, current, pageGroups]);
    useRegisterPageRibbon('edit', groups);
}

/** «الصفحة الرئيسية»: the page's filters (same ribbon fields as the attendance and leave pages). */
export function useExamisHomeFilters(
    specs: FilterSpec[],
    deps: unknown[],
): void {
    useAttendanceHomeFilters(specs, null, deps);
}

/** A single-choice ribbon filter over `{id, name}` options; the empty choice («—» or `emptyLabel`) means none. */
export function optionFilter(
    key: string,
    label: string,
    value: number | null,
    options: Array<{ id: number; name: string }>,
    onChange: (value: number | null) => void,
    extra: { emptyLabel?: string; disabled?: boolean } = {},
): FilterSpec {
    return {
        key,
        type: 'single',
        label,
        disabled: extra.disabled,
        value: value === null ? '' : String(value),
        options: [
            { value: '', label: extra.emptyLabel ?? `${label}: —` },
            ...options.map((o) => ({ value: String(o.id), label: o.name })),
        ],
        onChange: (next: string) => onChange(next === '' ? null : Number(next)),
    };
}

/** Page shell — the same layout classes as the other list pages («الغرف الدراسية» / «المعلمون»). */
export function ExamisPage({
    title,
    windowId,
    children,
}: {
    title: string;
    windowId: string;
    children: ReactNode;
}) {
    return (
        <>
            <Head title={title} />
            <div
                className={sisListPageClass()}
                dir="rtl"
                lang="ar"
                data-window-id={windowId}
            >
                <div className="sis-admission-page-body flex min-h-0 flex-1 flex-col gap-3">
                    {children}
                </div>
            </div>
        </>
    );
}

/** A write from a window: new idempotency key per attempt; server validation errors open the shared error dialog. */
export function useExamisRequest() {
    const { showInertiaErrors } = usePageError();

    return (
        url: string,
        data: Record<string, unknown>,
        keyPrefix: string,
    ): Promise<boolean> =>
        new Promise((resolve) => {
            router.post(url, data as never, {
                preserveScroll: true,
                headers: { 'X-Idempotency-Key': newIdempotencyKey(keyPrefix) },
                onError: (errors) => {
                    showInertiaErrors(errors, t().errors.saveFailed);
                    resolve(false);
                },
                onSuccess: () => resolve(true),
            });
        });
}
