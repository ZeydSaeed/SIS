import type { SisWindowDescriptor } from '@/window/types';

/** Stable logical window IDs — WINDOW-CONTRACT.md */
export const WINDOW_CATALOG: Record<string, SisWindowDescriptor> = {
    'ops.hub': {
        windowId: 'ops.hub',
        title: 'Operational Hub',
        href: '/hub?embed=1',
        minimumSize: { width: 640, height: 480 },
    },
    'student.list': {
        windowId: 'student.list',
        title: 'Students',
        href: '/students',
        minimumSize: { width: 720, height: 520 },
    },
    'enrollment.list': {
        windowId: 'enrollment.list',
        title: 'Enrollments',
        href: '/enrollments',
        minimumSize: { width: 720, height: 520 },
    },
    'enrollment.create': {
        windowId: 'enrollment.create',
        title: 'Enroll student',
        href: '/enrollments/create',
        minimumSize: { width: 560, height: 520 },
    },
    'attendance.list': {
        windowId: 'attendance.list',
        title: 'Attendance',
        href: '/attendance',
        minimumSize: { width: 720, height: 520 },
    },
    'teachers.list': {
        windowId: 'teachers.list',
        title: 'Teachers',
        href: '/teachers',
        minimumSize: { width: 720, height: 520 },
    },
    'timetable.list': {
        windowId: 'timetable.list',
        title: 'Timetable',
        href: '/timetable',
        minimumSize: { width: 720, height: 520 },
    },
    'results.list': {
        windowId: 'results.list',
        title: 'Results',
        href: '/results',
        minimumSize: { width: 720, height: 520 },
    },
    'exams.list': {
        windowId: 'exams.list',
        title: 'Exams',
        href: '/exams',
        minimumSize: { width: 720, height: 520 },
    },
    'grades.list': {
        windowId: 'grades.list',
        title: 'Grades',
        href: '/grades',
        minimumSize: { width: 720, height: 520 },
    },
    'reports.hub': {
        windowId: 'reports.hub',
        title: 'Reports',
        href: '/reports',
        minimumSize: { width: 640, height: 480 },
    },
};

export function resolveWindow(windowId: string): SisWindowDescriptor | null {
    return WINDOW_CATALOG[windowId] ?? null;
}
