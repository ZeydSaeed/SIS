import { Head } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { EmptyState } from '@/components/sis/empty-state';
import { PageHeader } from '@/components/sis/page-header';
import type { BreadcrumbItem } from '@/types';

type Props = {
    moduleKey: string;
    title: string;
    description: string;
};

export default function ModuleShell({ moduleKey, title, description }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title, href: `/${moduleKey === 'holidays' ? 'holidays' : moduleKey}` },
    ];

    const hrefByKey: Record<string, string> = {
        guardians: '/guardians',
        admission: '/admission',
        curriculum: '/curriculum',
        promotion: '/promotion',
        transfers: '/transfers',
        graduation: '/graduation',
        certificates: '/certificates',
        finance: '/finance',
        holidays: '/holidays',
        health: '/health',
        hr: '/hr',
        documents: '/documents',
        communication: '/communication',
        workflow: '/workflow',
    };

    breadcrumbs[0].href = hrefByKey[moduleKey] ?? `/${moduleKey}`;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={title} />
            <div className="flex flex-col gap-4 p-4" dir="rtl" lang="ar">
                <PageHeader title={title} description={description} />
                <EmptyState
                    title="واجهة التشغيل قيد التجهيز"
                    description="الوحدة موجودة في المخطط الرئيسي للنظام. سيتم ربط القوائم والنماذج بواجهات البرمجة عند اكتمال بوابة هذه الوحدة."
                />
            </div>
        </AppLayout>
    );
}
