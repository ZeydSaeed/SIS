import { router, usePage } from '@inertiajs/react';
import { Pencil } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { useRegisterPageRibbon, type PageRibbonGroup } from '@/components/sis/page-ribbon-context';
import { t } from '@/i18n';
import { directorateSchoolsUrl, SIS_ADMISSION_SCHOOL_SELECTED_EVENT } from '@/lib/organization-registry-event';

function contextSchoolId(props: Record<string, unknown>): number | null {
    const context = props.schoolContext as { schoolId?: number | null } | undefined;

    return context?.schoolId ?? null;
}

/** Admission pages: Edit → تعديل المدرسة opens the school chosen on the page in «المديريات والمدارس». */
export function SchoolEditRibbon({ canManageSchools }: { canManageSchools: boolean }) {
    const i18n = t();
    const page = usePage();
    const [selectedSchoolId, setSelectedSchoolId] = useState<number | null>(() => contextSchoolId(page.props));

    useEffect(() => {
        const onSelected = (event: Event) => {
            setSelectedSchoolId((event as CustomEvent<number | null>).detail ?? null);
        };
        window.addEventListener(SIS_ADMISSION_SCHOOL_SELECTED_EVENT, onSelected);

        return () => window.removeEventListener(SIS_ADMISSION_SCHOOL_SELECTED_EVENT, onSelected);
    }, []);

    const editGroups = useMemo((): PageRibbonGroup[] => {
        if (!canManageSchools) {
            return [];
        }

        return [
            {
                id: 'organization-school',
                label: i18n.directorateSchools.editGroup,
                commands: [
                    {
                        id: 'edit-school',
                        label: i18n.directorateSchools.editSchool,
                        icon: Pencil,
                        tone: 'edit',
                        disabled: selectedSchoolId === null,
                        onSelect: () => router.visit(directorateSchoolsUrl(selectedSchoolId, true)),
                    },
                ],
            },
        ];
    }, [canManageSchools, i18n.directorateSchools.editGroup, i18n.directorateSchools.editSchool, selectedSchoolId]);

    useRegisterPageRibbon('edit', editGroups);

    return null;
}
