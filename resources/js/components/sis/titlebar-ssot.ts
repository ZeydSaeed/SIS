/**
 * Titlebar SSOT — one chrome for every authenticated ops page.
 *
 * Chrome (always present in AppSidebarHeader):
 *   tabs · search · home · utilities · ribbon slot
 *
 * Page adapters (register from a child of AppLayout):
 *   useRegisterPageTitlebarSearch — filters / query for this page
 *   useRegisterPageRibbon         — tab command groups for this page
 *   useRegisterPageTitlebarHome   — module home href for detail views
 *
 * When a page does not register search, the titlebar still shows the field
 * with idle defaults (no commit side effects) so layout stays consistent.
 */

export const TITLEBAR_OPS_ACCENT_PREFIXES = [
    '/admission',
    '/students',
    '/enrollments',
    '/curriculum',
    '/organization',
] as const;

/** Colored outline ribbon icons (ops accent) for student-management surfaces. */
export function isTitlebarOpsAccentPath(path: string): boolean {
    const normalized = path.split('?')[0] ?? path;

    return TITLEBAR_OPS_ACCENT_PREFIXES.some(
        (prefix) => normalized === prefix || normalized.startsWith(`${prefix}/`),
    );
}
