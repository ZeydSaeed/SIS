import { router, usePage } from '@inertiajs/react';
import { useCallback, useState } from 'react';
import { TitleBarControls } from '@/components/title-bar-controls';
import { TitleBarHome } from '@/components/title-bar-home';
import { TitleBarMenu } from '@/components/title-bar-menu';
import {
    TitleBarRibbon,
    type RibbonActionId,
    type RibbonTab,
} from '@/components/title-bar-ribbon';
import { TitleBarUtilities } from '@/components/title-bar-utilities';
import { SisSearchField } from '@/components/sis/sis-search-field';
import { usePageTitlebarSearch } from '@/components/sis/page-titlebar-search-context';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { applyPageAlignment } from '@/hooks/use-page-alignment';
import {
    pageClipboardCopy,
    pageClipboardCut,
    pageClipboardPaste,
} from '@/hooks/use-page-clipboard';
import { togglePageTextStyle } from '@/hooks/use-page-text-style';
import appearance from '@/routes/appearance';
import { edit as profileEdit } from '@/routes/profile';
import { dashboard } from '@/routes';
import type { BreadcrumbItem as BreadcrumbItemType } from '@/types';

function findAndReplace(): void {
    const findText = window.prompt('بحث عن:');
    if (!findText) {
        return;
    }

    const replaceText = window.prompt('استبدال بـ:', '');
    if (replaceText === null) {
        return;
    }

    const selection = window.getSelection();
    const active = document.activeElement;

    if (
        active instanceof HTMLInputElement ||
        active instanceof HTMLTextAreaElement
    ) {
        const { value, selectionStart, selectionEnd } = active;
        const start = selectionStart ?? 0;
        const end = selectionEnd ?? value.length;
        const scope = value.slice(start, end) || value;
        const nextScope = scope.split(findText).join(replaceText);

        if (scope === value) {
            active.value = nextScope;
        } else {
            active.value =
                value.slice(0, start) + nextScope + value.slice(end);
        }

        active.dispatchEvent(new Event('input', { bubbles: true }));
        return;
    }

    if (selection && !selection.isCollapsed) {
        const range = selection.getRangeAt(0);
        const text = range.toString().split(findText).join(replaceText);
        range.deleteContents();
        range.insertNode(document.createTextNode(text));
    }
}

const ADD_ROUTES: Partial<Record<RibbonActionId, string>> = {
    addSchool: '/admission',
    addTeacher: '/teachers',
    addHoliday: '/holidays',
    addCurriculum: '/curriculum',
    addGrades: '/grades',
    addDocument: '/documents',
    addAttendance: '/attendance/create',
    addGuardian: '/guardians',
    addExam: '/exams',
    addTimetable: '/timetable',
    addSection: '/enrollments/create',
    addSubject: '/curriculum',
};

const SETTINGS_ROUTES: Partial<Record<RibbonActionId, string>> = {
    settingsAppearance: appearance.edit.url(),
    settingsTimetable: '/timetable',
    settingsAccount: profileEdit.url(),
    settingsNotes: '/documents',
    settingsDefinitions: '/curriculum',
    settingsPractical: '/curriculum',
    settingsLabs: '/curriculum',
    settingsReports: '/reports',
    settingsScheduling: '/timetable',
    settingsDocuments: '/documents',
    settingsTasks: '/workflow',
    settingsStats: '/reports',
};

const LISTS_ROUTES: Partial<Record<RibbonActionId, string>> = {
    listStudents: '/students',
    listCommunications: '/communication',
    listSchools: '/admission',
    listDepartments: '/curriculum',
    listSections: '/enrollments',
    listWorkshops: '/hr',
    listEmployees: '/hr',
    listTeachers: '/teachers',
    listEquipment: '/documents',
    listGuardians: '/guardians',
};

const TOOLS_ROUTES: Partial<Record<RibbonActionId, string>> = {
    toolsCertificates: '/certificates',
    toolsDbMaintenance: '/reports',
    toolsMobileSync: '/communication',
    toolsEvaluations: '/grades',
    toolsInstallWizard: '/admission',
    toolsStudentRequirements: '/students',
    toolsTeacherRequirements: '/teachers',
};

const REPORTS_ROUTES: Partial<Record<RibbonActionId, string>> = {
    reportsStandard: '/reports',
    reportsCharts: '/reports',
};

const HELP_ROUTES: Partial<Record<RibbonActionId, string>> = {
    helpLiveSupport: '/communication',
    helpDocsSupport: '/documents',
    helpUpdate: '/dashboard',
};

export function AppSidebarHeader({
    breadcrumbs: _breadcrumbs = [],
}: {
    breadcrumbs?: BreadcrumbItemType[];
}) {
    const [activeRibbon, setActiveRibbon] = useState<RibbonTab | null>(null);
    const page = usePage();

    const onRibbonAction = useCallback((id: RibbonActionId) => {
        if (id === 'addStudent') {
            setActiveRibbon(null);
            router.visit('/admission/converted?create_student=1');

            return;
        }

        const addHref = ADD_ROUTES[id];
        if (addHref) {
            setActiveRibbon(null);
            router.visit(addHref);
            return;
        }

        const settingsHref = SETTINGS_ROUTES[id];
        if (settingsHref) {
            setActiveRibbon(null);
            router.visit(settingsHref);
            return;
        }

        const listsHref = LISTS_ROUTES[id];
        if (listsHref) {
            setActiveRibbon(null);
            router.visit(listsHref);
            return;
        }

        const toolsHref = TOOLS_ROUTES[id];
        if (toolsHref) {
            setActiveRibbon(null);
            router.visit(toolsHref);
            return;
        }

        const reportsHref = REPORTS_ROUTES[id];
        if (reportsHref) {
            setActiveRibbon(null);
            router.visit(reportsHref);
            return;
        }

        const helpHref = HELP_ROUTES[id];
        if (helpHref) {
            setActiveRibbon(null);
            router.visit(helpHref);
            return;
        }

        switch (id) {
            case 'print':
                window.print();
                break;
            case 'account':
                setActiveRibbon(null);
                router.visit(profileEdit.url());
                break;
            case 'options':
                setActiveRibbon(null);
                router.visit(appearance.edit.url());
                break;
            case 'close':
                setActiveRibbon(null);
                router.visit(dashboard());
                break;
            case 'copy':
                void pageClipboardCopy();
                break;
            case 'cut':
                void pageClipboardCut();
                break;
            case 'paste':
                void pageClipboardPaste();
                break;
            case 'alignStart':
                applyPageAlignment('start');
                break;
            case 'alignCenter':
                applyPageAlignment('center');
                break;
            case 'alignEnd':
                applyPageAlignment('end');
                break;
            case 'bold':
                togglePageTextStyle('bold');
                break;
            case 'italic':
                togglePageTextStyle('italic');
                break;
            case 'underline':
                togglePageTextStyle('underline');
                break;
            case 'findReplace':
                findAndReplace();
                break;
            default:
                break;
        }
    }, [page.url]);

    const titlebarSearch = usePageTitlebarSearch();

    return (
        <div className="sis-chrome shrink-0">
            <header
                className={
                    titlebarSearch
                        ? 'sis-titlebar sis-titlebar--with-search min-h-10 shrink-0 items-center px-3 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:min-h-10 md:px-3'
                        : 'sis-titlebar flex min-h-10 shrink-0 items-center gap-2 px-3 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:min-h-10 md:px-3'
                }
                dir="rtl"
            >
                <div className="sis-titlebar__start">
                    <SidebarTrigger className="sis-titlebar__trigger size-7 shrink-0" />
                    <TitleBarMenu
                        activeRibbon={activeRibbon}
                        onRibbonChange={setActiveRibbon}
                    />
                </div>
                {titlebarSearch ? (
                    <div className="sis-titlebar__search-slot">
                        <div className="sis-titlebar__search">
                            <SisSearchField
                                committedQuery={titlebarSearch.committedQuery}
                                label={titlebarSearch.label}
                                placeholder={titlebarSearch.placeholder}
                                onDraftChange={titlebarSearch.onDraftChange}
                                onCommit={titlebarSearch.onCommit}
                            />
                        </div>
                    </div>
                ) : null}
                <div className="sis-titlebar__leading flex shrink-0 items-center gap-1" dir="ltr">
                    <TitleBarControls />
                    <TitleBarHome />
                    <TitleBarUtilities />
                </div>
            </header>
            {activeRibbon ? (
                <div className="sis-ribbon-overlay" role="presentation">
                    <TitleBarRibbon
                        tab={activeRibbon}
                        onAction={onRibbonAction}
                        onCollapse={() => setActiveRibbon(null)}
                    />
                </div>
            ) : null}
        </div>
    );
}
