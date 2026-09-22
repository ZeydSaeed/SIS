import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, Eye, Pencil, Save, Trash2 } from 'lucide-react';
import { useMemo, useState } from 'react';
import AppLayout from '@/layouts/app-layout';
import { ConfirmDialog } from '@/components/sis/confirm-dialog';
import { PageHeader } from '@/components/sis/page-header';
import {
    useRegisterPageRibbon,
    type PageRibbonGroup,
} from '@/components/sis/page-ribbon-context';
import { useRegisterPageTitlebarHome } from '@/components/sis/page-titlebar-home-context';
import { Button } from '@/components/ui/button';
import {
    StudentDetailsSurface,
    type StudentAuthorization,
    type StudentDetail,
} from '@/components/students/student-details-surface';
import { StudentViewDialog } from '@/components/students/student-record-form';
import { t } from '@/i18n';
import type { BreadcrumbItem } from '@/types';

const STUDENT_STATUS_WITHDRAWN = 4;

type PageProps = {
    student: StudentDetail;
    authorization: StudentAuthorization;
    placementOptions?: {
        branches: Array<{ id: number; name: string }>;
        departments: Array<{ id: number; branch_id: number | null; name: string }>;
    };
};

export default function StudentsShow({ student, authorization, placementOptions }: PageProps) {
    const i18n = t();
    const [editing, setEditing] = useState(false);
    const [viewing, setViewing] = useState(false);
    const [deleting, setDeleting] = useState(false);
    const [confirmDelete, setConfirmDelete] = useState(false);
    const canUpdate = authorization.canUpdate;
    const canDelete = canUpdate && student.status !== STUDENT_STATUS_WITHDRAWN;

    const breadcrumbs: BreadcrumbItem[] = [
        { title: i18n.students.title, href: '/students' },
        { title: student.full_name, href: `/students/${student.id}` },
    ];

    useRegisterPageTitlebarHome({
        href: '/students',
        ariaLabel: i18n.students.backToStudents,
    });

    const ribbonGroups = useMemo((): PageRibbonGroup[] => {
        if (!canUpdate) {
            return [];
        }

        return [
            {
                id: 'student-profile-actions',
                label: i18n.common.actions,
                commands: [
                    {
                        id: 'view-student',
                        label: i18n.common.view,
                        icon: Eye,
                        onSelect: () => setViewing(true),
                    },
                    {
                        id: 'edit-student',
                        label: i18n.common.edit,
                        icon: Pencil,
                        onSelect: () => setEditing(true),
                    },
                    {
                        id: 'save-student',
                        label: i18n.common.save,
                        icon: Save,
                        disabled: !editing,
                        onSelect: () => {
                            router.put(
                                `/students/${student.id}`,
                                {
                                    first_name: student.first_name,
                                    last_name: student.last_name,
                                    father_name: student.father_name,
                                    grandfather_name: student.grandfather_name,
                                    great_grandfather_name: student.great_grandfather_name,
                                    birth_date: student.birth_date,
                                    department_name: student.department_name,
                                    specialization_name: student.specialization_name,
                                    admitted_class_name: student.admitted_class_name,
                                },
                                {
                                    preserveScroll: true,
                                    onSuccess: () => setEditing(false),
                                },
                            );
                        },
                    },
                    {
                        id: 'delete-student',
                        label: i18n.common.delete,
                        icon: Trash2,
                        disabled: !canDelete,
                        onSelect: () => setConfirmDelete(true),
                    },
                ],
            },
        ];
    }, [
        canDelete,
        canUpdate,
        editing,
        i18n.common.actions,
        i18n.common.delete,
        i18n.common.edit,
        i18n.common.save,
        i18n.common.view,
        student,
    ]);

    useRegisterPageRibbon('home', ribbonGroups);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={student.full_name} />
            <div className="flex flex-col gap-6 p-4">
                <div className="flex items-center gap-2">
                    <Button asChild variant="ghost" size="sm">
                        <Link href="/students">
                            <ArrowLeft className="me-2 size-4" aria-hidden="true" />
                            {i18n.common.backToList}
                        </Link>
                    </Button>
                </div>

                <PageHeader title={student.full_name} description={i18n.students.showDesc} />

                <div className="border-border rounded-xl border p-6">
                    <StudentDetailsSurface
                        student={student}
                        authorization={authorization}
                        showProfileLink={false}
                    />
                </div>
            </div>

            {viewing ? (
                <StudentViewDialog
                    students={[
                        {
                            ...student,
                            religion: student.religion ?? 1,
                        },
                    ]}
                    canViewPii={authorization.canViewPii}
                    canUpdate={canUpdate}
                    placementOptions={placementOptions}
                    onClose={() => setViewing(false)}
                />
            ) : null}

            <ConfirmDialog
                open={confirmDelete}
                title={i18n.students.deleteTitle}
                description={i18n.students.deleteConfirm}
                confirmLabel={i18n.common.delete}
                tone="danger"
                confirmPending={deleting}
                onConfirm={() => {
                    setDeleting(true);
                    router.post(
                        '/students/bulk-status',
                        {
                            student_ids: [student.id],
                            status: STUDENT_STATUS_WITHDRAWN,
                        },
                        {
                            preserveScroll: true,
                            onFinish: () => {
                                setDeleting(false);
                                setConfirmDelete(false);
                            },
                        },
                    );
                }}
                onOpenChange={(open) => {
                    if (!open && !deleting) {
                        setConfirmDelete(false);
                    }
                }}
            />
        </AppLayout>
    );
}
