import { Head } from '@inertiajs/react';
import {
    CurriculumDetail,
    type CurriculumShowProps,
} from '@/components/curriculum/curriculum-detail';
import AppLayout from '@/layouts/app-layout';
import { t } from '@/i18n';
import type { BreadcrumbItem } from '@/types';

export default function CurriculumShow(props: CurriculumShowProps) {
    const i18n = t();
    const breadcrumbs: BreadcrumbItem[] = [
        { title: i18n.curriculum.title, href: '/curriculum' },
        {
            title: props.curriculum.name,
            href: `/curriculum/curricula/${props.curriculum.id}`,
        },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={props.curriculum.name} />
            <CurriculumDetail {...props} />
        </AppLayout>
    );
}
