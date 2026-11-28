import { Head } from '@inertiajs/react';
import { ResultsBoard } from '@/components/results/results-board';
import AppLayout from '@/layouts/app-layout';
import { t } from '@/i18n';
import type { BreadcrumbItem } from '@/types';

/**
 * «النتائج» (owner's request, 2026-11-28): the former term-results list is gone; the page now shows the board laid
 * out like the cyber_security control panel — pictures, bars, styling and order only, with no data behind it.
 * The other results pages (/results/show, /term, /transcript, /publications) are unchanged.
 */
export default function ResultsIndex() {
    const i18n = t();
    const breadcrumbs: BreadcrumbItem[] = [{ title: i18n.modules.results, href: '/results' }];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={i18n.modules.results} />
            <div className="sis-results-board-page sis-scroll-y-thin flex h-full min-h-0 flex-col p-4" dir="rtl" lang="ar">
                <h1 className="sr-only">{i18n.modules.results}</h1>
                <ResultsBoard />
            </div>
        </AppLayout>
    );
}
