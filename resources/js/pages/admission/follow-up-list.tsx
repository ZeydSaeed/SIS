import { FollowUpList } from '@/components/admission/follow-up/follow-up-list';
import {
    FollowUpPage,
    type FollowUpPageProps,
} from '@/components/admission/follow-up/follow-up-page';
import { t } from '@/i18n';

/** «متابعة طلبات التقديم» › list — the applications of one category (or all), filtered and paged. */
export default function AdmissionFollowUpList(props: FollowUpPageProps) {
    const a = t().admission;

    return (
        <FollowUpPage
            props={props}
            title={`${a.acceptedStudentsDialogTitle} — ${a.followUpOpenList}`}
            breadcrumbs={[
                { title: a.title, href: '/admission' },
                {
                    title: a.acceptedStudentsDialogTitle,
                    href: '/admission/follow-up',
                },
                {
                    title: a.followUpOpenList,
                    href: '/admission/follow-up/list',
                },
            ]}
            render={(body) => <FollowUpList {...body} />}
        />
    );
}
