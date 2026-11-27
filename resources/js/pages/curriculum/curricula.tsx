import { Head } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import {
    CurriculumWorkspace,
    type CurriculumPageProps,
} from '@/components/curriculum/curriculum-workspace';
import { t } from '@/i18n';
import type { BreadcrumbItem } from '@/types';

/** «المناهج» — a page of its own (/curriculum/curricula), apart from «المواد» (/curriculum). */
export default function CurriculumCurricula(props: CurriculumPageProps) {
    const i18n = t();
    const breadcrumbs: BreadcrumbItem[] = [{ title: i18n.overview.curriculaTitle, href: '/curriculum/curricula' }];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={i18n.overview.curriculaTitle} />
            <CurriculumWorkspace {...props} view="curricula" />
        </AppLayout>
    );
}
