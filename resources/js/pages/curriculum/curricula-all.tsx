import { Head, router } from '@inertiajs/react';
import { Eye, Palette, Pencil, Trash2, XCircle } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { CurriculumCreateSheetDialog } from '@/components/curriculum/curriculum-create-sheet';
import {
    CURRICULUM_DISTRIBUTION_ACTIONS,
    CURRICULUM_STATUS_TABS,
    CURRICULUM_SUPERSEDED_ICON,
    editFilterEquals,
    matchesCurriculumEditFilter,
    type CurriculumEditFilter,
} from '@/components/curriculum/curriculum-edit-controls';
import { CurriculumSheet } from '@/components/curriculum/curriculum-sheet';
import {
    curriculumColor,
    curriculumFilterRow,
    postWithIdempotency,
    type CurriculumFilterOptions,
    type CurriculumPagination,
    type CurriculumRow,
    type LinkedSubjectRow,
    type SubjectRow,
    type TeacherOption,
} from '@/components/curriculum/curriculum-workspace';
import { ConfirmDialog } from '@/components/sis/confirm-dialog';
import { useOverviewHome } from '@/components/sis/module-overview';
import { useRegisterPageRibbon, type PageRibbonCommand, type PageRibbonGroup } from '@/components/sis/page-ribbon-context';
import { sisListPageClass } from '@/components/sis/page-theme';
import { useDragScroll } from '@/hooks/use-drag-scroll';
import AppLayout from '@/layouts/app-layout';
import { t } from '@/i18n';
import type { BreadcrumbItem } from '@/types';

type Sheet = { curriculum: CurriculumRow; linkedSubjects: LinkedSubjectRow[] };

type Props = {
    /** Every curriculum of the page, in order (year, class, name), each as on its own page. */
    sheets: Sheet[];
    pagination: CurriculumPagination;
    /** «تحرير ← تعديل»: what the «تعديل منهج» window needs, by the curriculum's year. */
    subjectOptions: SubjectRow[];
    filterOptionsByYear: Record<string, CurriculumFilterOptions>;
    teacherOptionsByYear: Record<string, TeacherOption[]>;
    authorization: { canManage: boolean };
};

const ALL_PATH = '/curriculum/curricula/all';
/** The page's own scrollbar: very thin, on the start (left in RTL), always there. */
const SCROLL_CLASS = 'sis-curricula-all-scroll';
const SCROLL_STYLE = `.${SCROLL_CLASS}::-webkit-scrollbar{width:4px}.${SCROLL_CLASS}::-webkit-scrollbar-track{background:transparent}.${SCROLL_CLASS}::-webkit-scrollbar-thumb{background:var(--sis-steel);border-radius:999px}`;

/** «كل المناهج»: each curriculum in its own card — heading, subjects, teachers, completeness, hour totals. */
export default function CurriculaAll(props: Props) {
    const i18n = t();
    const title = i18n.overview.allCurricula;
    const breadcrumbs: BreadcrumbItem[] = [
        { title: i18n.overview.curriculaTitle, href: '/curriculum/curricula/overview' },
        { title, href: ALL_PATH },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={title} />
            <CurriculaAllBody {...props} />
        </AppLayout>
    );
}

/** Inside the layout: the page's «تحرير» ribbon registers with it. */
function CurriculaAllBody({ sheets, pagination, subjectOptions, filterOptionsByYear, teacherOptionsByYear, authorization }: Props) {
    const i18n = t();
    const c = i18n.curriculum;
    const canManage = authorization.canManage;
    // The title bar's home button goes back to the curricula cards.
    useOverviewHome('/curriculum/curricula');
    // Smooth browsing: drag (mouse or touch) with a glide, and a smooth wheel.
    const scrollRef = useDragScroll<HTMLDivElement>();

    /** «تحديد»: one curriculum at a time — «تحرير» acts on it. */
    const [selectedId, setSelectedId] = useState<number | null>(null);
    const [editing, setEditing] = useState<Sheet | null>(null);
    const [confirmDelete, setConfirmDelete] = useState<Sheet | null>(null);
    const [deleting, setDeleting] = useState(false);
    /** «تحرير ← حالة» / «توزيع سابق»: which curricula are shown. */
    const [editFilter, setEditFilter] = useState<CurriculumEditFilter>({ kind: 'all' });
    const selected = sheets.find((sheet) => sheet.curriculum.id === selectedId) ?? null;
    // A reload (after a save, another page) keeps the choice only while that curriculum is still shown.
    useEffect(() => {
        setSelectedId((current) => (current !== null && sheets.some((sheet) => sheet.curriculum.id === current) ? current : null));
    }, [sheets]);

    const subjectsByName = useMemo(() => new Map(subjectOptions.map((subject) => [subject.name, subject])), [subjectOptions]);
    /** Each curriculum's «حالة» row: its own status, and the types of its subjects. */
    const filterRows = useMemo(
        () =>
            sheets.map((sheet) =>
                curriculumFilterRow(
                    {
                        ...sheet.curriculum,
                        subjects: sheet.linkedSubjects
                            .filter((line) => line.status === 1)
                            .map((line) => ({
                                name: line.subject_name ?? '',
                                subject_type: line.subject_type ?? 1,
                                credit_hours: line.credit_hours ?? null,
                                max_grade: line.max_grade ?? null,
                            })),
                    },
                    (name) => subjectsByName.get(name),
                ),
            ),
        [sheets, subjectsByName],
    );
    const shown = sheets.filter((_, index) => matchesCurriculumEditFilter(filterRows[index]!, editFilter));

    // «تحرير» — the curricula page's own tab (إجراءات · حالة · توزيعات · توزيع سابق), here on the chosen curriculum.
    const editGroups = useMemo((): PageRibbonGroup[] => {
        const none = selected === null;
        const open = selected !== null && selected.curriculum.status === 1;
        const actionCommands: PageRibbonCommand[] = [
            {
                // «الاختصار واللون» belongs to a subject (its own page); a curriculum has none.
                id: 'subject-appearance',
                label: i18n.appearance.title,
                icon: Palette,
                disabled: true,
                title: i18n.appearance.edit,
                onSelect: () => undefined,
            },
            {
                id: 'view-subjects',
                label: c.view,
                icon: Eye,
                disabled: none,
                title: none ? c.selectCurriculumFirst : c.view,
                onSelect: () => (selected ? router.get(`/curriculum/curricula/${selected.curriculum.id}`) : undefined),
            },
            {
                id: 'edit-selected-subject',
                label: i18n.common.edit,
                icon: Pencil,
                tone: 'edit',
                disabled: !canManage || !open,
                title: none ? c.selectCurriculumFirst : c.editPlan,
                onSelect: () => setEditing(selected),
            },
            {
                id: 'clear-selection',
                label: c.clearSelection,
                icon: XCircle,
                disabled: none,
                onSelect: () => setSelectedId(null),
            },
            {
                id: 'delete-subject',
                label: i18n.common.delete,
                icon: Trash2,
                tone: 'delete',
                disabled: !canManage || none,
                title: c.deleteCurriculum,
                onSelect: () => setConfirmDelete(selected),
            },
        ];
        const statusCommands: PageRibbonCommand[] = CURRICULUM_STATUS_TABS.map((tab) => {
            const label = c[tab.labelKey];
            const count = filterRows.filter((row) => matchesCurriculumEditFilter(row, tab.filter)).length;

            return {
                id: tab.id,
                label,
                title: `${label} (${count})`,
                icon: tab.icon,
                count,
                pressed: editFilterEquals(editFilter, tab.filter),
                onSelect: () => setEditFilter(tab.filter),
            };
        });
        // «توزيعات»: نشط / غير نشط set the chosen curriculum's status; a curriculum has no type of its own.
        const distributionCommands: PageRibbonCommand[] = CURRICULUM_DISTRIBUTION_ACTIONS.map((action) => ({
            id: action.id,
            label: c[action.labelKey],
            title: c[action.labelKey],
            icon: action.icon,
            disabled: !canManage || none || action.apply.kind !== 'status' || selected.curriculum.status === action.apply.value,
            onSelect: () => {
                if (selected && action.apply.kind === 'status') {
                    postWithIdempotency(
                        `/curriculum/curricula/${selected.curriculum.id}/${action.apply.value === 1 ? 'reactivate' : 'deactivate'}`,
                    );
                }
            },
        }));
        const supersededCount = filterRows.filter((row) => matchesCurriculumEditFilter(row, { kind: 'superseded' })).length;

        return [
            { id: 'curriculum-actions', label: c.ribbonActions, commands: actionCommands },
            { id: 'curriculum-status-tabs', label: c.ribbonStatus, commands: statusCommands },
            { id: 'curriculum-distribution', label: c.ribbonDistribution, commands: distributionCommands },
            {
                id: 'curriculum-superseded',
                label: c.ribbonSuperseded,
                commands: [
                    {
                        id: 'curriculum-superseded-action',
                        label: c.ribbonSuperseded,
                        title: `${c.ribbonSuperseded} (${supersededCount})`,
                        icon: CURRICULUM_SUPERSEDED_ICON,
                        count: supersededCount,
                        pressed: editFilter.kind === 'superseded',
                        onSelect: () => setEditFilter({ kind: 'superseded' }),
                    },
                ],
            },
        ];
    }, [c, canManage, editFilter, filterRows, i18n, selected]);
    useRegisterPageRibbon('edit', editGroups);

    const goTo = (page: number): void => {
        if (page >= 1 && page <= pagination.last_page && page !== pagination.page) {
            router.get(ALL_PATH, { page });
        }
    };
    const pages = Array.from({ length: pagination.last_page }, (_, index) => index + 1);
    const editYear = editing ? String(editing.curriculum.academic_year_id) : '';

    return (
        // The page scrolls up and down with a very thin scrollbar on the start (left) side.
        <div
            ref={scrollRef}
            className={`${sisListPageClass({ enrollments: false })} ${SCROLL_CLASS} p-4`}
            style={{ flex: '1 1 0', minHeight: 0, overflowY: 'auto', gap: '2.5rem', touchAction: 'pan-y' }}
            dir="rtl"
            lang="ar"
        >
            <style>{SCROLL_STYLE}</style>
            <h1 className="sr-only">{i18n.overview.allCurricula}</h1>
            {shown.length === 0 ? <p className="px-1 py-2 text-sm">{c.linkedEmptyTitle}</p> : null}
            {shown.map((sheet) => (
                <CurriculumSheet
                    key={sheet.curriculum.id}
                    curriculum={sheet.curriculum}
                    lines={sheet.linkedSubjects}
                    color={curriculumColor(sheet.curriculum)}
                    select={{
                        checked: sheet.curriculum.id === selectedId,
                        onChange: () => setSelectedId((current) => (current === sheet.curriculum.id ? null : sheet.curriculum.id)),
                        label: c.selectCurriculum,
                    }}
                />
            ))}
            {pagination.last_page > 1 ? (
                <nav className="sis-admission-drafts-pagination" aria-label={i18n.common.page}>
                    <ul className="sis-admission-pagination" dir="ltr">
                        <li className="sis-admission-pagination__item">
                            <button
                                type="button"
                                className="sis-admission-pagination__link"
                                aria-label={i18n.common.previous}
                                disabled={pagination.page <= 1}
                                onClick={() => goTo(pagination.page - 1)}
                            >
                                <span aria-hidden="true">&laquo;</span>
                            </button>
                        </li>
                        {pages.map((page) => (
                            <li key={page} className="sis-admission-pagination__item">
                                <button
                                    type="button"
                                    className={
                                        page === pagination.page
                                            ? 'sis-admission-pagination__link sis-admission-pagination__link--active'
                                            : 'sis-admission-pagination__link'
                                    }
                                    aria-label={`${i18n.common.page} ${page}`}
                                    aria-current={page === pagination.page ? 'page' : undefined}
                                    onClick={() => goTo(page)}
                                >
                                    {page}
                                </button>
                            </li>
                        ))}
                        <li className="sis-admission-pagination__item">
                            <button
                                type="button"
                                className="sis-admission-pagination__link"
                                aria-label={i18n.common.next}
                                disabled={pagination.page >= pagination.last_page}
                                onClick={() => goTo(pagination.page + 1)}
                            >
                                <span aria-hidden="true">&raquo;</span>
                            </button>
                        </li>
                    </ul>
                </nav>
            ) : null}

            {editing ? (
                <CurriculumCreateSheetDialog
                    canManage={canManage}
                    filterOptions={
                        filterOptionsByYear[editYear] ?? { classes: [], sections: [], branches: [], departments: [], specializations: [] }
                    }
                    defaultAcademicYearId={editing.curriculum.academic_year_id}
                    subjects={subjectOptions.filter(
                        (subject) => subject.status === 1 || editing.linkedSubjects.some((line) => line.subject_id === subject.id),
                    )}
                    teacherOptions={teacherOptionsByYear[editYear] ?? []}
                    editing={{ curriculum: editing.curriculum, lines: editing.linkedSubjects }}
                    onClose={() => setEditing(null)}
                />
            ) : null}

            <ConfirmDialog
                open={confirmDelete !== null}
                title={c.deleteCurriculum}
                description={c.deleteCurriculumConfirm}
                confirmPending={deleting}
                tone="danger"
                onOpenChange={(open) => (open ? undefined : setConfirmDelete(null))}
                onConfirm={() => {
                    if (confirmDelete === null) {
                        return;
                    }
                    setDeleting(true);
                    router.post(
                        `/curriculum/curricula/${confirmDelete.curriculum.id}/delete`,
                        {},
                        {
                            preserveScroll: true,
                            onFinish: () => {
                                setDeleting(false);
                                setConfirmDelete(null);
                            },
                        },
                    );
                }}
            />
        </div>
    );
}
