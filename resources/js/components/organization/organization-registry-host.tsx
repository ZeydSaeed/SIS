import { router, usePage } from '@inertiajs/react';
import { Pencil } from 'lucide-react';
import { lazy, Suspense, useCallback, useEffect, useMemo, useState } from 'react';
import type { DirectorateRegistry } from '@/components/organization/directorate-registry-sheet';
import type { SchoolRegistry } from '@/components/organization/school-registry-sheet';
import {
    useRegisterPageRibbon,
    useSetActivePageRibbonTab,
    type PageRibbonGroup,
    type PageRibbonTab,
} from '@/components/sis/page-ribbon-context';
import { t } from '@/i18n';
import {
    DIRECTORATE_REGISTRY_QUERY_FLAG,
    SCHOOL_REGISTRY_QUERY_FLAG,
    SIS_ADMISSION_SCHOOL_SELECTED_EVENT,
    SIS_OPEN_DIRECTORATE_REGISTRY_EVENT,
    SIS_OPEN_SCHOOL_REGISTRY_EVENT,
} from '@/lib/organization-registry-event';

const SchoolRegistrySheetDialog = lazy(async () => {
    const mod = await import('@/components/organization/school-registry-sheet');

    return { default: mod.SchoolRegistrySheetDialog };
});

const DirectorateRegistrySheetDialog = lazy(async () => {
    const mod = await import('@/components/organization/directorate-registry-sheet');

    return { default: mod.DirectorateRegistrySheetDialog };
});

type SchoolSheetState = {
    key: number;
    schoolId: number | null;
    mode: 'view' | 'edit';
};

type Props = {
    canManageSchools: boolean;
    canManageDirectorates: boolean;
};

function contextSchoolId(props: Record<string, unknown>): number | null {
    const context = props.schoolContext as { schoolId?: number | null } | undefined;

    return context?.schoolId ?? null;
}

/**
 * Admission pages host the organization registry sheets:
 * Add → مدرسة / مديرية, Edit → تعديل المدرسة (the school chosen on the page).
 * Opening a sheet keeps the ribbon tab open — only the user collapses it.
 */
export function OrganizationRegistryHost({ canManageSchools, canManageDirectorates }: Props) {
    const i18n = t();
    const page = usePage();
    const setActiveRibbonTab = useSetActivePageRibbonTab();
    const [schoolSheet, setSchoolSheet] = useState<SchoolSheetState | null>(null);
    const [directoratesOpen, setDirectoratesOpen] = useState(false);
    const [selectedSchoolId, setSelectedSchoolId] = useState<number | null>(() =>
        contextSchoolId(page.props),
    );

    const schoolRegistry = (page.props.schoolRegistry as SchoolRegistry | null | undefined) ?? null;
    const directorateRegistry =
        (page.props.directorateRegistry as DirectorateRegistry | null | undefined) ?? null;

    const openSchools = useCallback(
        (mode: 'view' | 'edit', schoolId: number | null, ribbonTab: PageRibbonTab) => {
            if (!canManageSchools) {
                return;
            }
            setSchoolSheet((current) => ({ key: (current?.key ?? 0) + 1, schoolId, mode }));
            setActiveRibbonTab(ribbonTab);
            router.reload({ only: ['schoolRegistry'], showProgress: false });
        },
        [canManageSchools, setActiveRibbonTab],
    );

    const openDirectorates = useCallback(() => {
        if (!canManageDirectorates) {
            return;
        }
        setDirectoratesOpen(true);
        setActiveRibbonTab('add');
        router.reload({ only: ['directorateRegistry'], showProgress: false });
    }, [canManageDirectorates, setActiveRibbonTab]);

    useEffect(() => {
        const onSchools = () => openSchools('view', null, 'add');
        const onSelected = (event: Event) => {
            const detail = (event as CustomEvent<number | null>).detail;
            setSelectedSchoolId(detail ?? null);
        };

        window.addEventListener(SIS_OPEN_SCHOOL_REGISTRY_EVENT, onSchools);
        window.addEventListener(SIS_OPEN_DIRECTORATE_REGISTRY_EVENT, openDirectorates);
        window.addEventListener(SIS_ADMISSION_SCHOOL_SELECTED_EVENT, onSelected);

        return () => {
            window.removeEventListener(SIS_OPEN_SCHOOL_REGISTRY_EVENT, onSchools);
            window.removeEventListener(SIS_OPEN_DIRECTORATE_REGISTRY_EVENT, openDirectorates);
            window.removeEventListener(SIS_ADMISSION_SCHOOL_SELECTED_EVENT, onSelected);
        };
    }, [openDirectorates, openSchools]);

    // Arrived from another page via Add → مدرسة / مديرية (?manage_schools=1 / ?manage_directorates=1).
    useEffect(() => {
        const [path, query = ''] = page.url.split('?');
        const params = new URLSearchParams(query);
        const wantsSchools = params.get(SCHOOL_REGISTRY_QUERY_FLAG) === '1';
        const wantsDirectorates = params.get(DIRECTORATE_REGISTRY_QUERY_FLAG) === '1';
        if (!wantsSchools && !wantsDirectorates) {
            return;
        }

        params.delete(SCHOOL_REGISTRY_QUERY_FLAG);
        params.delete(DIRECTORATE_REGISTRY_QUERY_FLAG);
        const next = params.toString();
        router.visit(next === '' ? path : `${path}?${next}`, {
            replace: true,
            preserveState: true,
            preserveScroll: true,
            showProgress: false,
            onSuccess: () => {
                if (wantsSchools) {
                    openSchools('view', null, 'add');
                }
                if (wantsDirectorates) {
                    openDirectorates();
                }
            },
        });
    }, [openDirectorates, openSchools, page.url]);

    const editGroups = useMemo((): PageRibbonGroup[] => {
        if (!canManageSchools) {
            return [];
        }

        return [
            {
                id: 'organization-school',
                label: i18n.schoolRegistry.editGroup,
                commands: [
                    {
                        id: 'edit-school',
                        label: i18n.schoolRegistry.editSchool,
                        icon: Pencil,
                        tone: 'edit',
                        disabled: selectedSchoolId === null,
                        onSelect: () => openSchools('edit', selectedSchoolId, 'edit'),
                    },
                ],
            },
        ];
    }, [
        canManageSchools,
        i18n.schoolRegistry.editGroup,
        i18n.schoolRegistry.editSchool,
        openSchools,
        selectedSchoolId,
    ]);

    useRegisterPageRibbon('edit', editGroups);

    return (
        <>
            {schoolSheet !== null ? (
                <Suspense fallback={null}>
                    <SchoolRegistrySheetDialog
                        key={schoolSheet.key}
                        registry={schoolRegistry}
                        canManage={canManageSchools}
                        initialSchoolId={schoolSheet.schoolId}
                        initialMode={schoolSheet.mode}
                        onClose={() => setSchoolSheet(null)}
                    />
                </Suspense>
            ) : null}
            {directoratesOpen ? (
                <Suspense fallback={null}>
                    <DirectorateRegistrySheetDialog
                        registry={directorateRegistry}
                        canManage={canManageDirectorates}
                        onClose={() => setDirectoratesOpen(false)}
                    />
                </Suspense>
            ) : null}
        </>
    );
}
