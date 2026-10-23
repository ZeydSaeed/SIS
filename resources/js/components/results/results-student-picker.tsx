import { router } from '@inertiajs/react';
import { FileBadge, ListTree, ScrollText } from 'lucide-react';
import { useMemo } from 'react';
import {
    AttendanceTable,
    useYearFilterSpec,
} from '@/components/attendance/attendance-chrome';
import {
    useExamisEditRibbon,
    useExamisHomeFilters,
} from '@/components/examis/examis-chrome';
import type { PageRibbonGroup } from '@/components/sis/page-ribbon-context';
import { useRegisterPageTitlebarSearch } from '@/components/sis/page-titlebar-search-context';
import { t } from '@/i18n';

export type PickerStudent = {
    enrollment_id: number;
    student_name: string;
    student_code: string;
    placement: string;
};

export type ResultsPicker = {
    q: string;
    candidates: PickerStudent[];
    student: PickerStudent | null;
};

/** A stable empty list: a new `[]` per render would re-register the ribbon in a loop (React #185). */
const NO_GROUPS: PageRibbonGroup[] = [];

type StudentView = 'terms' | 'summary' | 'transcript';

const VIEW_PATH: Record<StudentView, string> = {
    terms: '/results',
    summary: '/results/show',
    transcript: '/results/transcript',
};

/**
 * The chrome of the student result pages: the year on «الصفحة الرئيسية», the student search in the title bar
 * (server-side, at most 20 matches — no raw enrollment id) and, on «تحرير», the views of the chosen student.
 */
export function useResultsStudentChrome(
    view: StudentView,
    academicYearId: number | null,
    picker: ResultsPicker,
    pageGroups: PageRibbonGroup[] = NO_GROUPS,
): void {
    const r = t().results;
    const path = VIEW_PATH[view];
    const enrollmentId = picker.student?.enrollment_id;

    const yearSpec = useYearFilterSpec(academicYearId, (id) =>
        router.get(path, { academic_year_id: id }),
    );
    useExamisHomeFilters([yearSpec], [academicYearId, yearSpec.options.length]);

    const search = useMemo(
        () => ({
            committedQuery: picker.q,
            label: r.picker.label,
            placeholder: r.picker.placeholder,
            onDraftChange: () => undefined,
            onCommit: (query: string) =>
                router.get(
                    path,
                    {
                        academic_year_id: academicYearId ?? undefined,
                        enrollment_id: enrollmentId,
                        q: query.trim() === '' ? undefined : query.trim(),
                    },
                    { preserveState: true, preserveScroll: true },
                ),
        }),
        [picker.q, path, academicYearId, enrollmentId, r.picker],
    );
    useRegisterPageTitlebarSearch(search);

    const groups = useMemo((): PageRibbonGroup[] => {
        const views: Array<{
            key: StudentView;
            label: string;
            icon: typeof ScrollText;
            tone: 'steel' | 'sky' | 'rose';
        }> = [
            {
                key: 'terms',
                label: r.views.terms,
                icon: ListTree,
                tone: 'steel',
            },
            {
                key: 'summary',
                label: r.views.summary,
                icon: ScrollText,
                tone: 'sky',
            },
            {
                key: 'transcript',
                label: r.views.transcript,
                icon: FileBadge,
                tone: 'rose',
            },
        ];

        return [
            ...pageGroups,
            {
                id: 'results-student-views',
                label: picker.student
                    ? `${r.picker.current}: ${picker.student.student_name}`
                    : r.picker.current,
                commands: views.map((v) => ({
                    id: `results-student-${v.key}`,
                    label: v.label,
                    icon: v.icon,
                    iconTone: v.tone,
                    pressed: v.key === view,
                    disabled: enrollmentId === undefined,
                    onSelect: () =>
                        v.key !== view &&
                        router.visit(
                            `${VIEW_PATH[v.key]}?academic_year_id=${academicYearId ?? ''}&enrollment_id=${enrollmentId ?? ''}`,
                        ),
                })),
            },
        ];
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [view, academicYearId, enrollmentId, pageGroups]);
    useExamisEditRibbon(
        view === 'transcript' ? 'transcript' : 'results',
        groups,
    );
}

/** The chosen student and, after a title-bar search, the matching students (choose = open this page for them). */
export function ResultsStudentPicker({
    path,
    academicYearId,
    picker,
}: {
    path: string;
    academicYearId: number | null;
    picker: ResultsPicker;
}) {
    const r = t().results.picker;
    const choose = (student: PickerStudent) =>
        router.get(path, {
            academic_year_id: academicYearId ?? undefined,
            enrollment_id: student.enrollment_id,
        });

    return (
        <section
            className="flex flex-col gap-2"
            aria-label={r.title}
            data-results-picker
        >
            {picker.student ? (
                <p className="text-sm" role="status" data-picked-student>
                    {r.current}: <strong>{picker.student.student_name}</strong>{' '}
                    <span dir="ltr">({picker.student.student_code})</span>
                    {picker.student.placement !== ''
                        ? ` — ${picker.student.placement}`
                        : null}
                </p>
            ) : null}
            {picker.q !== '' ? (
                <div data-picker-candidates>
                    <AttendanceTable
                        caption={r.title}
                        storageKey="results.picker"
                        rows={picker.candidates}
                        rowKey={(row) => row.enrollment_id}
                        fit={false}
                        pageSize={20}
                        onOpen={choose}
                        emptyText={r.none}
                        columns={[
                            {
                                id: 'student',
                                header: r.current,
                                kind: 'plain',
                                cell: (row) => (
                                    <button
                                        type="button"
                                        className="underline"
                                        onClick={() => choose(row)}
                                    >
                                        {row.student_name}
                                    </button>
                                ),
                            },
                            {
                                id: 'code',
                                header: t().results.sheet.code,
                                kind: 'text',
                                cell: (row) => (
                                    <span dir="ltr">{row.student_code}</span>
                                ),
                            },
                            {
                                id: 'placement',
                                header: t().results.sheet.section,
                                kind: 'text',
                                cell: (row) => row.placement || '—',
                            },
                        ]}
                    />
                </div>
            ) : null}
        </section>
    );
}
