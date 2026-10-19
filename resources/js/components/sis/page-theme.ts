/**
 * Single source of truth for the page shell of every list page (the «متابعة طلبات التقديم» look): students, enrollments,
 * rooms, teachers, curriculum, attendance, leave. The visual rules live in app.css ("SIS TABLE THEME"); a page takes its
 * wrapper classes from here and never spells them out, so a change to the shell is made once.
 */
const LIST_PAGE_BASE =
    'sis-ops-hub sis-admission-page sis-students-page sis-table-theme flex h-full min-h-0 flex-col';

/** Wrapper classes of a themed list page. `extra` adds the page's own marker class (e.g. `sis-teachers-page`). */
export function sisListPageClass(
    options: {
        extra?: string;
        /** Pages that scroll inside their own panels hide the page-level scrollbar instead of clipping. */
        hiddenScroll?: boolean;
        /** Drops the bottom padding (full-bleed boards). */
        flush?: boolean;
        /** The enrollments page rules (progress strip, status tabs). Curriculum has its own and leaves them out. */
        enrollments?: boolean;
    } = {},
): string {
    return [
        LIST_PAGE_BASE,
        options.enrollments === false ? undefined : 'sis-enrollments-page',
        options.extra,
        options.hiddenScroll ? 'sis-scroll-hidden' : 'overflow-hidden',
        options.flush ? undefined : 'pb-4',
    ]
        .filter(Boolean)
        .join(' ');
}
