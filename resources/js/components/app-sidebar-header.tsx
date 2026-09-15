import { router } from '@inertiajs/react';
import { useCallback, useState } from 'react';
import { Breadcrumbs } from '@/components/breadcrumbs';
import { TitleBarControls } from '@/components/title-bar-controls';
import { TitleBarMenu } from '@/components/title-bar-menu';
import {
    TitleBarRibbon,
    type RibbonActionId,
    type RibbonTab,
} from '@/components/title-bar-ribbon';
import { SidebarTrigger } from '@/components/ui/sidebar';
import appearance from '@/routes/appearance';
import { edit as profileEdit } from '@/routes/profile';
import { dashboard } from '@/routes';
import type { BreadcrumbItem as BreadcrumbItemType } from '@/types';

function runDocumentCommand(command: string, value?: string): void {
    try {
        document.execCommand(command, false, value);
    } catch {
        // Browser may block some commands outside contenteditable contexts.
    }
}

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
    addStudent: '/students',
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

export function AppSidebarHeader({
    breadcrumbs = [],
}: {
    breadcrumbs?: BreadcrumbItemType[];
}) {
    const [activeRibbon, setActiveRibbon] = useState<RibbonTab | null>(null);

    const onRibbonAction = useCallback((id: RibbonActionId) => {
        const addHref = ADD_ROUTES[id];
        if (addHref) {
            setActiveRibbon(null);
            router.visit(addHref);
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
                runDocumentCommand('copy');
                break;
            case 'cut':
                runDocumentCommand('cut');
                break;
            case 'paste':
                void navigator.clipboard
                    ?.readText()
                    .then((text) => {
                        if (!text) {
                            return;
                        }
                        runDocumentCommand('insertText', text);
                    })
                    .catch(() => {
                        runDocumentCommand('paste');
                    });
                break;
            case 'alignStart':
                runDocumentCommand('justifyRight');
                break;
            case 'alignCenter':
                runDocumentCommand('justifyCenter');
                break;
            case 'alignEnd':
                runDocumentCommand('justifyLeft');
                break;
            case 'bold':
                runDocumentCommand('bold');
                break;
            case 'italic':
                runDocumentCommand('italic');
                break;
            case 'underline':
                runDocumentCommand('underline');
                break;
            case 'findReplace':
                findAndReplace();
                break;
            default:
                break;
        }
    }, []);

    return (
        <div className="sis-chrome shrink-0">
            <header className="sis-titlebar flex h-9 shrink-0 items-center gap-3 px-3 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-9 md:px-3">
                <div className="flex min-w-0 flex-1 items-center gap-2">
                    <SidebarTrigger className="sis-titlebar__trigger size-7" />
                    <TitleBarMenu
                        activeRibbon={activeRibbon}
                        onRibbonChange={setActiveRibbon}
                    />
                    <Breadcrumbs breadcrumbs={breadcrumbs} />
                </div>
                <TitleBarControls />
            </header>
            {activeRibbon ? (
                <TitleBarRibbon
                    tab={activeRibbon}
                    onAction={onRibbonAction}
                    onCollapse={() => setActiveRibbon(null)}
                    onFontFamily={(value) => runDocumentCommand('fontName', value)}
                    onFontSize={(value) => {
                        const mapped = String(
                            Math.max(1, Math.min(7, Math.round(Number(value) / 4))),
                        );
                        runDocumentCommand('fontSize', mapped);
                    }}
                />
            ) : null}
        </div>
    );
}
