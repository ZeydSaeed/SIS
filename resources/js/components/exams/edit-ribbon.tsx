import { usePage } from '@inertiajs/react';
import { useEffect } from 'react';
import {
    useActivePageRibbonTab,
    useRegisterPageRibbon,
    useSetActivePageRibbonTab,
    type PageRibbonGroup,
} from '@/components/sis/page-ribbon-context';

/**
 * Registers the exam pages' commands on «تحرير» and opens it when the page loads, unless another tab is already open
 * (or pinned) — once per page (path), so folding the ribbon by hand keeps it folded.
 *
 * Must render INSIDE AppLayout: the ribbon context provider is one of its descendants' ancestors, not the page's.
 * `groups` must be memoised by the caller.
 */
export function EditRibbon({ groups }: { groups: PageRibbonGroup[] }) {
    useRegisterPageRibbon('edit', groups);
    const activeTab = useActivePageRibbonTab();
    const setActiveTab = useSetActivePageRibbonTab();
    const path = usePage().url.split('?')[0];

    useEffect(() => {
        if (activeTab === null) {
            setActiveTab('edit');
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps -- once per page
    }, [path]);

    return null;
}
