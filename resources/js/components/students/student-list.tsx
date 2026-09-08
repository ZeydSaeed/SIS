import { Link, router } from '@inertiajs/react';
import { GraduationCap } from 'lucide-react';
import { useEffect, useState } from 'react';
import { DataTable, type DataTableColumn } from '@/components/sis/data-table';
import { PageHeader } from '@/components/sis/page-header';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Sheet, SheetContent, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import {
    StudentDetailsSurface,
    type StudentAuthorization,
    type StudentDetail,
} from '@/components/students/student-details-surface';
import { StudentStatusBadge } from '@/components/students/student-status-badge';
import { useIsMobile } from '@/hooks/use-mobile';

export type { StudentAuthorization };

export type StudentListItem = {
    id: number;
    student_code: string;
    full_name: string;
    status: number;
    gender: number;
    birth_date: string;
};

export type StudentsPayload = {
    data: StudentListItem[];
    meta: {
        page: number;
        per_page: number;
        total: number;
        last_page: number;
    };
};

export type PreviewPayload =
    | null
    | { error: 'not_found' | 'forbidden' }
    | { student: StudentDetail; authorization: StudentAuthorization };

type StudentListProps = {
    students: StudentsPayload;
    filters: {
        q: string;
        status: number | null;
        page: number;
        per_page: number;
    };
    authorization: StudentAuthorization;
    preview: PreviewPayload;
};

export function StudentList({ students, filters, authorization, preview }: StudentListProps) {
    const isMobile = useIsMobile();
    const [search, setSearch] = useState(filters.q);
    const [dialogOpen, setDialogOpen] = useState(false);

    useEffect(() => {
        setSearch(filters.q);
    }, [filters.q]);

    useEffect(() => {
        if (preview && !isMobile) {
            setDialogOpen(true);
        }
    }, [preview, isMobile]);

    const visitList = (params: Record<string, string | number | null | undefined>) => {
        router.get(
            '/students',
            {
                q: params.q ?? filters.q,
                page: params.page ?? filters.page,
                per_page: params.per_page ?? filters.per_page,
                status: params.status ?? filters.status ?? undefined,
                student: params.student ?? undefined,
            },
            {
                preserveState: true,
                preserveScroll: true,
                replace: params.student === undefined,
            },
        );
    };

    const submitSearch = () => {
        visitList({ q: search, page: 1, student: undefined });
    };

    const openPreview = (studentId: number) => {
        if (isMobile) {
            router.visit(`/students/${studentId}`);

            return;
        }

        visitList({ student: studentId });
    };

    const closePreview = () => {
        setDialogOpen(false);
        visitList({ student: undefined });
    };

    const columns: DataTableColumn<StudentListItem>[] = [
        {
            id: 'code',
            header: 'Code',
            cell: (row) => (
                <span className="font-mono text-xs" dir="ltr">
                    {row.student_code}
                </span>
            ),
        },
        {
            id: 'name',
            header: 'Name',
            cell: (row) => row.full_name,
        },
        {
            id: 'status',
            header: 'Status',
            cell: (row) => <StudentStatusBadge status={row.status} />,
            hideOnMobile: true,
        },
        {
            id: 'birth_date',
            header: 'Birth date',
            cell: (row) => <span dir="ltr">{row.birth_date}</span>,
            hideOnMobile: true,
        },
        {
            id: 'actions',
            header: '',
            cell: (row) => (
                <Button asChild size="sm" variant="ghost">
                    <Link href={`/students/${row.id}`}>View</Link>
                </Button>
            ),
        },
    ];

    const previewError =
        preview && 'error' in preview ? preview.error : null;
    const previewStudent =
        preview && 'student' in preview ? preview.student : null;
    const previewAuthorization =
        preview && 'authorization' in preview ? preview.authorization : null;

    return (
        <div className="flex flex-col gap-6">
            <PageHeader
                title="Students"
                description="School-scoped student directory — read-only reference surface."
                icon={<GraduationCap className="text-primary size-8" aria-hidden="true" />}
            />

            <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
                <Input
                    value={search}
                    onChange={(event) => setSearch(event.target.value)}
                    onKeyDown={(event) => {
                        if (event.key === 'Enter') {
                            submitSearch();
                        }
                    }}
                    placeholder="Search by name or code"
                    aria-label="Search students"
                    className="max-w-md"
                />
                <Button type="button" onClick={submitSearch}>
                    Search
                </Button>
            </div>

            <DataTable
                columns={columns}
                rows={students.data}
                rowKey={(row) => row.id}
                caption="Student directory"
                emptyTitle="No students found"
                emptyDescription={
                    filters.q
                        ? 'Try a different search term.'
                        : 'No students are available for the current school context.'
                }
                onRowClick={(row) => openPreview(row.id)}
                mobileCard={(row) => (
                    <button
                        type="button"
                        className="border-border hover:bg-muted/40 w-full rounded-xl border p-4 text-start"
                        onClick={() => openPreview(row.id)}
                    >
                        <div className="flex items-start justify-between gap-3">
                            <div>
                                <p className="font-medium">{row.full_name}</p>
                                <p className="text-muted-foreground mt-1 text-xs" dir="ltr">
                                    {row.student_code}
                                </p>
                            </div>
                            <StudentStatusBadge status={row.status} />
                        </div>
                    </button>
                )}
            />

            {students.meta.last_page > 1 ? (
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <p className="text-muted-foreground text-sm">
                        Page {students.meta.page} of {students.meta.last_page} ·{' '}
                        <span dir="ltr">{students.meta.total}</span> total
                    </p>
                    <div className="flex gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            disabled={students.meta.page <= 1}
                            onClick={() => visitList({ page: students.meta.page - 1, student: undefined })}
                        >
                            Previous
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            disabled={students.meta.page >= students.meta.last_page}
                            onClick={() => visitList({ page: students.meta.page + 1, student: undefined })}
                        >
                            Next
                        </Button>
                    </div>
                </div>
            ) : null}

            {!authorization.canViewPii ? (
                <p className="text-muted-foreground text-xs">
                    Sensitive fields are hidden — PII visibility is controlled by server authorization.
                </p>
            ) : null}

            {!isMobile ? (
                <Dialog open={dialogOpen} onOpenChange={(open) => !open && closePreview()}>
                    <DialogContent className="max-h-[85vh] overflow-y-auto sm:max-w-2xl">
                        <DialogHeader>
                            <DialogTitle>Student details</DialogTitle>
                            <DialogDescription>
                                Desktop preview surface — canonical route remains /students/:id.
                            </DialogDescription>
                        </DialogHeader>
                        <StudentDetailsSurface
                            student={previewStudent}
                            authorization={previewAuthorization}
                            error={previewError}
                            showProfileLink
                        />
                    </DialogContent>
                </Dialog>
            ) : null}

            {isMobile && previewStudent ? (
                <Sheet open onOpenChange={(open) => !open && closePreview()}>
                    <SheetContent side="bottom" className="max-h-[85vh] overflow-y-auto">
                        <SheetHeader>
                            <SheetTitle>Student details</SheetTitle>
                        </SheetHeader>
                        <StudentDetailsSurface
                            student={previewStudent}
                            authorization={previewAuthorization}
                            error={previewError}
                            showProfileLink
                        />
                    </SheetContent>
                </Sheet>
            ) : null}
        </div>
    );
}
