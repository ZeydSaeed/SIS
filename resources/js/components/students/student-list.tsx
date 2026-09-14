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
import { t } from '@/i18n';

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
    const i18n = t();
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
            header: i18n.students.code,
            cell: (row) => (
                <span className="font-mono text-xs" dir="ltr">
                    {row.student_code}
                </span>
            ),
        },
        {
            id: 'name',
            header: i18n.students.name,
            cell: (row) => row.full_name,
        },
        {
            id: 'status',
            header: i18n.common.status,
            cell: (row) => <StudentStatusBadge status={row.status} />,
            hideOnMobile: true,
        },
        {
            id: 'birth_date',
            header: i18n.students.birthDate,
            cell: (row) => <span dir="ltr">{row.birth_date}</span>,
            hideOnMobile: true,
        },
        {
            id: 'actions',
            header: '',
            cell: (row) => (
                <Button asChild size="sm" variant="ghost">
                    <Link
                        href={`/students/${row.id}`}
                        aria-label={`${i18n.students.openProfile}: ${row.full_name}`}
                    >
                        {i18n.students.view}
                    </Link>
                </Button>
            ),
        },
    ];

    const previewError = preview && 'error' in preview ? preview.error : null;
    const previewStudent = preview && 'student' in preview ? preview.student : null;
    const previewAuthorization =
        preview && 'authorization' in preview ? preview.authorization : null;

    return (
        <div className="sis-ops-hub flex flex-col gap-6" dir="rtl" lang="ar">
            <PageHeader
                title={i18n.students.title}
                description={i18n.students.description}
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
                    placeholder={i18n.students.searchPlaceholder}
                    aria-label={i18n.students.searchAria}
                    className="max-w-md"
                    dir="rtl"
                />
                <Button type="button" onClick={submitSearch}>
                    {i18n.students.search}
                </Button>
            </div>

            <DataTable
                columns={columns}
                rows={students.data}
                rowKey={(row) => row.id}
                caption={i18n.students.tableCaption}
                emptyTitle={i18n.students.emptyTitle}
                emptyDescription={filters.q ? i18n.students.emptySearch : i18n.students.emptyDesc}
                onRowClick={(row) => openPreview(row.id)}
                getRowAriaLabel={(row) => `${i18n.students.view}: ${row.full_name}`}
                mobileCard={(row) => (
                    <button
                        type="button"
                        className="border-border hover:bg-muted/40 w-full rounded-xl border p-4 text-start"
                        onClick={() => openPreview(row.id)}
                        aria-label={`${i18n.students.view}: ${row.full_name}`}
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
                        {i18n.common.page}{' '}
                        <span dir="ltr">
                            {students.meta.page}
                        </span>{' '}
                        {i18n.common.of}{' '}
                        <span dir="ltr">{students.meta.last_page}</span> ·{' '}
                        <span dir="ltr">{students.meta.total}</span> {i18n.common.total}
                    </p>
                    <div className="flex gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            disabled={students.meta.page <= 1}
                            onClick={() =>
                                visitList({ page: students.meta.page - 1, student: undefined })
                            }
                        >
                            {i18n.common.previous}
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            disabled={students.meta.page >= students.meta.last_page}
                            onClick={() =>
                                visitList({ page: students.meta.page + 1, student: undefined })
                            }
                        >
                            {i18n.common.next}
                        </Button>
                    </div>
                </div>
            ) : null}

            {!authorization.canViewPii ? (
                <p className="text-muted-foreground text-xs">{i18n.students.piiHidden}</p>
            ) : null}

            {!isMobile ? (
                <Dialog open={dialogOpen} onOpenChange={(open) => !open && closePreview()}>
                    <DialogContent className="max-h-[85vh] overflow-y-auto sm:max-w-2xl" dir="rtl">
                        <DialogHeader>
                            <DialogTitle>{i18n.students.detailsTitle}</DialogTitle>
                            <DialogDescription>{i18n.students.detailsDesc}</DialogDescription>
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
                    <SheetContent side="bottom" className="max-h-[85vh] overflow-y-auto" dir="rtl">
                        <SheetHeader>
                            <SheetTitle>{i18n.students.detailsTitle}</SheetTitle>
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
