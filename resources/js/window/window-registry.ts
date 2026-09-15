import type { SisWindowDescriptor } from '@/window/types';
import { t } from '@/i18n';

/** Stable logical window IDs — WINDOW-CONTRACT.md (titles Arabic-first). */
export function getWindowCatalog(): Record<string, SisWindowDescriptor> {
    const i18n = t();
    return {
        'ops.dashboard': {
            windowId: 'ops.dashboard',
            title: i18n.dashboard.title,
            href: '/dashboard?desktop=1&embed=1',
            minimumSize: { width: 640, height: 480 },
        },
        'student.list': {
            windowId: 'student.list',
            title: i18n.modules.students,
            href: '/students',
            minimumSize: { width: 720, height: 520 },
        },
        'enrollment.list': {
            windowId: 'enrollment.list',
            title: i18n.modules.enrollments,
            href: '/enrollments',
            minimumSize: { width: 720, height: 520 },
        },
        'enrollment.create': {
            windowId: 'enrollment.create',
            title: i18n.enrollments.enrollStudent,
            href: '/enrollments/create',
            minimumSize: { width: 560, height: 520 },
        },
        'attendance.list': {
            windowId: 'attendance.list',
            title: i18n.modules.attendance,
            href: '/attendance',
            minimumSize: { width: 720, height: 520 },
        },
        'teachers.list': {
            windowId: 'teachers.list',
            title: i18n.modules.teachers,
            href: '/teachers',
            minimumSize: { width: 720, height: 520 },
        },
        'timetable.list': {
            windowId: 'timetable.list',
            title: i18n.modules.timetable,
            href: '/timetable',
            minimumSize: { width: 720, height: 520 },
        },
        'results.list': {
            windowId: 'results.list',
            title: i18n.modules.results,
            href: '/results',
            minimumSize: { width: 720, height: 520 },
        },
        'exams.list': {
            windowId: 'exams.list',
            title: i18n.modules.exams,
            href: '/exams',
            minimumSize: { width: 720, height: 520 },
        },
        'grades.list': {
            windowId: 'grades.list',
            title: i18n.modules.grades,
            href: '/grades',
            minimumSize: { width: 720, height: 520 },
        },
        'reports.hub': {
            windowId: 'reports.hub',
            title: i18n.modules.reports,
            href: '/reports',
            minimumSize: { width: 640, height: 480 },
        },
        'dashboard.home': {
            windowId: 'dashboard.home',
            title: i18n.dashboard.title,
            href: '/dashboard',
            minimumSize: { width: 720, height: 520 },
        },
        'attendance.create': {
            windowId: 'attendance.create',
            title: i18n.attendance.createSession,
            href: '/attendance/create',
            minimumSize: { width: 560, height: 520 },
        },
    };
}

/** @deprecated Prefer getWindowCatalog() for Arabic titles */
export const WINDOW_CATALOG = getWindowCatalog();

export function resolveWindow(windowId: string): SisWindowDescriptor | null {
    return getWindowCatalog()[windowId] ?? null;
}
