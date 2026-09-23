import { Head } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import {
    EnrollmentList,
    type EnrollmentAuthorization,
    type EnrollmentFilterOptions,
    type EnrollmentsPayload,
} from '@/components/enrollments/enrollment-list';
import type { PlacementHistoryPayload } from '@/components/enrollments/enrollment-placement-history-dialog';
import { t } from '@/i18n';
import type { BreadcrumbItem } from '@/types';

type PageProps = {
    enrollments: EnrollmentsPayload;
    filters: {
        q: string;
        status: number | null;
        gender: number | null;
        class_id: number | null;
        section_id: number | null;
        department_name: string | null;
        specialization_id: number | null;
        branch_id: number | null;
        department_id: number | null;
        page: number;
        per_page: number;
        academic_year_id: number | null;
    };
    filterOptions: EnrollmentFilterOptions;
    authorization: EnrollmentAuthorization;
    placementHistory?: PlacementHistoryPayload[] | null;
};

export default function EnrollmentsIndex({
    enrollments,
    filters,
    filterOptions,
    authorization,
    placementHistory = null,
}: PageProps) {
    const i18n = t();

    const breadcrumbs: BreadcrumbItem[] = [{ title: i18n.enrollments.title, href: '/enrollments' }];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={i18n.enrollments.title} />
            <EnrollmentList
                enrollments={enrollments}
                filters={filters}
                filterOptions={filterOptions}
                authorization={authorization}
                placementHistory={placementHistory}
            />
        </AppLayout>
    );
}
