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
    first_name: string;
    father_name?: string | null;
    grandfather_name?: string | null;
    great_grandfather_name?: string | null;
    last_name: string;
    guardian_triple_name?: string | null;
    governorate?: string | null;
    neighborhood?: string | null;
    locality?: string | null;
    house_number?: string | null;
    birth_date: string;
    registration_place?: string | null;
    gender: number;
    nationality?: string | null;
    religion: number;
    mawalid_date?: string | null;
    national_id?: string | null;
    previous_school_name?: string | null;
    transfer_document_number?: number | null;
    transfer_document_date?: string | null;
    school_start_date?: string | null;
    admitted_class_name?: string | null;
    notes?: string | null;
    mobile?: string | null;
    guardian_mobile?: string | null;
    email?: string | null;
    school_name?: string | null;
    department_name?: string | null;
    stage_name?: string | null;
    section_name?: string | null;
    status: number;
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

function textOrDash(value: string | number | null | undefined): string {
    if (value === null || value === undefined || value === '') {
        return '—';
    }

    return String(value);
}

function genderLabel(gender: number, i18n: ReturnType<typeof t>): string {
    if (gender === 1) {
        return i18n.students.male;
    }
    if (gender === 2) {
        return i18n.students.female;
    }

    return String(gender);
}

function religionLabel(religion: number, i18n: ReturnType<typeof t>): string {
    if (religion === 1) {
        return i18n.students.religionMuslim;
    }
    if (religion === 2) {
        return i18n.students.religionChristian;
    }
    if (religion === 3) {
        return i18n.students.religionOther;
    }

    return String(religion);
}

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
            id: 'first_name',
            header: i18n.students.firstName,
            cell: (row) => row.first_name,
        },
        {
            id: 'father_name',
            header: i18n.students.fatherName,
            cell: (row) => textOrDash(row.father_name),
            hideOnMobile: true,
        },
        {
            id: 'grandfather_name',
            header: i18n.students.grandfatherName,
            cell: (row) => textOrDash(row.grandfather_name),
            hideOnMobile: true,
        },
        {
            id: 'great_grandfather_name',
            header: i18n.students.greatGrandfatherName,
            cell: (row) => textOrDash(row.great_grandfather_name),
            hideOnMobile: true,
        },
        {
            id: 'last_name',
            header: i18n.students.familyName,
            cell: (row) => row.last_name,
        },
        {
            id: 'guardian_triple_name',
            header: i18n.students.guardianTripleName,
            cell: (row) => textOrDash(row.guardian_triple_name),
            hideOnMobile: true,
        },
        {
            id: 'governorate',
            header: i18n.students.governorate,
            cell: (row) => textOrDash(row.governorate),
            hideOnMobile: true,
        },
        {
            id: 'neighborhood',
            header: i18n.students.neighborhood,
            cell: (row) => textOrDash(row.neighborhood),
            hideOnMobile: true,
        },
        {
            id: 'locality',
            header: i18n.students.locality,
            cell: (row) => textOrDash(row.locality),
            hideOnMobile: true,
        },
        {
            id: 'house_number',
            header: i18n.students.houseNumber,
            cell: (row) => <span dir="ltr">{textOrDash(row.house_number)}</span>,
            hideOnMobile: true,
        },
        {
            id: 'birth_date',
            header: i18n.students.birthDate,
            cell: (row) => <span dir="ltr">{row.birth_date}</span>,
        },
        {
            id: 'registration_place',
            header: i18n.students.registrationPlace,
            cell: (row) => textOrDash(row.registration_place),
            hideOnMobile: true,
        },
        {
            id: 'gender',
            header: i18n.students.gender,
            cell: (row) => genderLabel(row.gender, i18n),
        },
        {
            id: 'nationality',
            header: i18n.students.nationality,
            cell: (row) => textOrDash(row.nationality),
            hideOnMobile: true,
        },
        {
            id: 'religion',
            header: i18n.students.religion,
            cell: (row) => religionLabel(row.religion, i18n),
            hideOnMobile: true,
        },
        {
            id: 'mawalid_date',
            header: i18n.students.mawalidDate,
            cell: (row) => <span dir="ltr">{textOrDash(row.mawalid_date)}</span>,
            hideOnMobile: true,
        },
        {
            id: 'national_id',
            header: i18n.students.nationalId,
            cell: (row) =>
                authorization.canViewPii ? (
                    <span dir="ltr">{textOrDash(row.national_id)}</span>
                ) : (
                    '—'
                ),
            hideOnMobile: true,
        },
        {
            id: 'previous_school_name',
            header: i18n.students.previousSchoolName,
            cell: (row) => textOrDash(row.previous_school_name),
            hideOnMobile: true,
        },
        {
            id: 'transfer_document_number',
            header: i18n.students.transferDocumentNumber,
            cell: (row) => <span dir="ltr">{textOrDash(row.transfer_document_number)}</span>,
            hideOnMobile: true,
        },
        {
            id: 'transfer_document_date',
            header: i18n.students.transferDocumentDate,
            cell: (row) => <span dir="ltr">{textOrDash(row.transfer_document_date)}</span>,
            hideOnMobile: true,
        },
        {
            id: 'school_start_date',
            header: i18n.students.schoolStartDate,
            cell: (row) => <span dir="ltr">{textOrDash(row.school_start_date)}</span>,
            hideOnMobile: true,
        },
        {
            id: 'admitted_class_name',
            header: i18n.students.admittedClassName,
            cell: (row) => textOrDash(row.admitted_class_name),
            hideOnMobile: true,
        },
        {
            id: 'notes',
            header: i18n.students.notes,
            cell: (row) => (
                <span className="line-clamp-2 max-w-[14rem] whitespace-pre-wrap">
                    {textOrDash(row.notes)}
                </span>
            ),
            hideOnMobile: true,
        },
        {
            id: 'mobile',
            header: i18n.students.mobile,
            cell: (row) =>
                authorization.canViewPii ? (
                    <span dir="ltr">{textOrDash(row.mobile)}</span>
                ) : (
                    '—'
                ),
            hideOnMobile: true,
        },
        {
            id: 'guardian_mobile',
            header: i18n.students.guardianMobile,
            cell: (row) =>
                authorization.canViewPii ? (
                    <span dir="ltr">{textOrDash(row.guardian_mobile)}</span>
                ) : (
                    '—'
                ),
            hideOnMobile: true,
        },
        {
            id: 'email',
            header: i18n.students.email,
            cell: (row) =>
                authorization.canViewPii ? (
                    <span dir="ltr">{textOrDash(row.email)}</span>
                ) : (
                    '—'
                ),
            hideOnMobile: true,
        },
        {
            id: 'school_name',
            header: i18n.students.schoolName,
            cell: (row) => textOrDash(row.school_name),
            hideOnMobile: true,
        },
        {
            id: 'department_name',
            header: i18n.students.departmentName,
            cell: (row) => textOrDash(row.department_name),
            hideOnMobile: true,
        },
        {
            id: 'stage_name',
            header: i18n.students.stageName,
            cell: (row) => textOrDash(row.stage_name),
            hideOnMobile: true,
        },
        {
            id: 'section_name',
            header: i18n.students.sectionName,
            cell: (row) => textOrDash(row.section_name),
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
            <div className="grid w-full grid-cols-1 items-center gap-3 sm:grid-cols-[1fr_auto_1fr]">
                <div className="flex justify-start">
                    <PageHeader
                        title={i18n.students.title}
                        icon={
                            <GraduationCap className="text-primary size-8" aria-hidden="true" />
                        }
                    />
                </div>

                <div className="flex w-full items-center justify-center gap-3 sm:w-auto">
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
                        className="w-full max-w-md sm:w-64 md:w-80"
                        dir="rtl"
                    />
                    <Button type="button" className="shrink-0" onClick={submitSearch}>
                        {i18n.students.search}
                    </Button>
                </div>

                <div className="hidden sm:block" aria-hidden="true" />
            </div>

            <section aria-label={i18n.students.title} className="sis-students-table-wrap flex flex-col gap-3">
                <DataTable
                    columns={columns}
                    rows={students?.data ?? []}
                    rowKey={(row) => row.id}
                    emptyTitle={i18n.students.emptyTitle}
                    emptyDescription={
                        filters.q ? i18n.students.emptySearch : i18n.students.emptyDesc
                    }
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
                                    <p className="text-muted-foreground mt-1 text-xs">
                                        {row.first_name} {row.last_name}
                                    </p>
                                    <p className="text-muted-foreground mt-1 text-xs" dir="ltr">
                                        {row.birth_date}
                                    </p>
                                </div>
                                <StudentStatusBadge status={row.status} />
                            </div>
                        </button>
                    )}
                />
            </section>

            {students?.meta && students.meta.last_page > 1 ? (
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <p className="text-muted-foreground text-sm">
                        {i18n.common.page}{' '}
                        <span dir="ltr">{students.meta.page}</span> {i18n.common.of}{' '}
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
                    <DialogContent className="max-h-[85vh] overflow-y-auto sm:max-w-3xl" dir="rtl">
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
