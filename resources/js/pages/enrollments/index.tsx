import { Head } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import {
    EnrollmentList,
    enrollmentsOverviewCards,
    type EnrollmentAuthorization,
    type EnrollmentFilterOptions,
    type EnrollmentsPayload,
} from '@/components/enrollments/enrollment-list';
import type { PlacementHistoryPayload } from '@/components/enrollments/enrollment-placement-history-dialog';
import { ModuleOverview, useIsOverview } from '@/components/sis/module-overview';
import { t } from '@/i18n';
import type { BreadcrumbItem } from '@/types';

type PageProps = {
    enrollments: EnrollmentsPayload;
    filters: {
        q: string;
        status: number | null;
        gender: number | null;
        class_id: number[];
        section_id: number[];
        department_name: string | null;
        specialization_id: number | null;
        branch_id: number[];
        department_id: number[];
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
    const overview = useIsOverview();

    const breadcrumbs: BreadcrumbItem[] = [{ title: i18n.enrollments.title, href: '/enrollments' }];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            {overview ? (
                <ModuleOverview
                    title={i18n.enrollments.title}
                    listPath="/enrollments"
                    cards={enrollmentsOverviewCards(enrollments, i18n)}
                    allLabel={i18n.overview.allEnrollments}
                />
            ) : (
                <>
                    <Head title={i18n.enrollments.title} />
                    <EnrollmentList
                        enrollments={enrollments}
                        filters={filters}
                        filterOptions={filterOptions}
                        authorization={authorization}
                        placementHistory={placementHistory}
                    />
                </>
            )}
        </AppLayout>
    );
}
