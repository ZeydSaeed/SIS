import type { SisWindowDescriptor } from '@/window/types';
import { t } from '@/i18n';

function embedHref(path: string): string {
    const joiner = path.includes('?') ? '&' : '?';
    return `${path}${joiner}embed=1`;
}

/** Stable logical window IDs — WINDOW-CONTRACT.md (titles Arabic-first). */
export function getWindowCatalog(): Record<string, SisWindowDescriptor> {
    const i18n = t();
    return {
        'ops.dashboard': {
            windowId: 'ops.dashboard',
            title: i18n.dashboard.title,
            href: embedHref('/dashboard?desktop=1'),
            minimumSize: { width: 640, height: 480 },
        },
        'student.list': {
            windowId: 'student.list',
            title: i18n.modules.students,
            href: embedHref('/students'),
            minimumSize: { width: 720, height: 520 },
        },
        'enrollment.list': {
            windowId: 'enrollment.list',
            title: i18n.modules.enrollments,
            href: embedHref('/enrollments'),
            minimumSize: { width: 720, height: 520 },
        },
        'enrollment.create': {
            windowId: 'enrollment.create',
            title: i18n.enrollments.enrollStudent,
            href: embedHref('/enrollments/create'),
            minimumSize: { width: 560, height: 520 },
        },
        'attendance.list': {
            windowId: 'attendance.list',
            title: i18n.modules.attendance,
            href: embedHref('/attendance'),
            minimumSize: { width: 720, height: 520 },
        },
        'teachers.list': {
            windowId: 'teachers.list',
            title: i18n.modules.teachers,
            href: embedHref('/teachers'),
            minimumSize: { width: 720, height: 520 },
        },
        'timetable.list': {
            windowId: 'timetable.list',
            title: i18n.modules.timetable,
            href: embedHref('/timetable'),
            minimumSize: { width: 720, height: 520 },
        },
        'results.list': {
            windowId: 'results.list',
            title: i18n.modules.results,
            href: embedHref('/results'),
            minimumSize: { width: 720, height: 520 },
        },
        'exams.list': {
            windowId: 'exams.list',
            title: i18n.modules.exams,
            href: embedHref('/exams'),
            minimumSize: { width: 720, height: 520 },
        },
        'grades.list': {
            windowId: 'grades.list',
            title: i18n.modules.grades,
            href: embedHref('/grades'),
            minimumSize: { width: 720, height: 520 },
        },
        'reports.hub': {
            windowId: 'reports.hub',
            title: i18n.modules.reports,
            href: embedHref('/reports'),
            minimumSize: { width: 640, height: 480 },
        },
        'dashboard.home': {
            windowId: 'dashboard.home',
            title: i18n.dashboard.title,
            href: embedHref('/dashboard'),
            minimumSize: { width: 720, height: 520 },
        },
        'attendance.create': {
            windowId: 'attendance.create',
            title: i18n.attendance.createSession,
            href: embedHref('/attendance/create'),
            minimumSize: { width: 560, height: 520 },
        },
        'intelligence.recommendations': {
            windowId: 'intelligence.recommendations',
            title: i18n.modules.intelligence,
            href: embedHref('/intelligence/recommendations'),
            minimumSize: { width: 720, height: 520 },
        },
    };
}

/** @deprecated Prefer getWindowCatalog() for Arabic titles */
export const WINDOW_CATALOG = getWindowCatalog();

export function resolveWindow(windowId: string): SisWindowDescriptor | null {
    return getWindowCatalog()[windowId] ?? null;
}

/** Map app route path → windowId for sidebar / menu open. */
export function windowIdForPath(path: string): string | null {
    const clean = path.split('?')[0].replace(/\/$/, '') || '/';
    const map: Record<string, string> = {
        '/dashboard': 'dashboard.home',
        '/students': 'student.list',
        '/enrollments': 'enrollment.list',
        '/enrollments/create': 'enrollment.create',
        '/attendance': 'attendance.list',
        '/attendance/create': 'attendance.create',
        '/teachers': 'teachers.list',
        '/timetable': 'timetable.list',
        '/results': 'results.list',
        '/exams': 'exams.list',
        '/grades': 'grades.list',
        '/reports': 'reports.hub',
        '/intelligence/recommendations': 'intelligence.recommendations',
    };
    return map[clean] ?? null;
}
