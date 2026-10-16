import { FollowUpOverview } from '@/components/admission/follow-up/follow-up-overview';
import {
    FollowUpPage,
    type FollowUpPageProps,
} from '@/components/admission/follow-up/follow-up-page';
import { t } from '@/i18n';

/** «متابعة طلبات التقديم» — the share and count of every category; a card opens the category's list. */
export default function AdmissionFollowUp(props: FollowUpPageProps) {
    const a = t().admission;

    return (
        <FollowUpPage
            props={props}
            title={a.acceptedStudentsDialogTitle}
            breadcrumbs={[
                { title: a.title, href: '/admission' },
                {
                    title: a.acceptedStudentsDialogTitle,
                    href: '/admission/follow-up',
                },
            ]}
            render={(body) => (
                <FollowUpOverview
                    students={body.students}
                    schools={body.schools}
                    defaultSchoolId={body.defaultSchoolId}
                    defaultYearId={body.defaultYearId}
                />
            )}
        />
    );
}
