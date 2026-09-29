import { Head } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import {
    CurriculumWorkspace,
    type CurriculumPageProps,
} from '@/components/curriculum/curriculum-workspace';
import { t } from '@/i18n';
import type { BreadcrumbItem } from '@/types';

export default function CurriculumIndex(props: CurriculumPageProps) {
    const i18n = t();
    const breadcrumbs: BreadcrumbItem[] = [{ title: i18n.curriculum.title, href: '/curriculum' }];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={i18n.curriculum.title} />
            <CurriculumWorkspace {...props} />
        </AppLayout>
    );
}
