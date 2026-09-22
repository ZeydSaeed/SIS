import { router, usePage } from '@inertiajs/react';
import {
    CheckCircle2,
    CircleSlash,
    ClipboardList,
    Eye,
    GraduationCap,
    PauseCircle,
    Pencil,
    Save,
    Trash2,
    UserMinus,
    Users,
    XCircle,
    type LucideIcon,
} from 'lucide-react';
import {
    forwardRef,
    useCallback,
    useEffect,
    useImperativeHandle,
    useMemo,
    useRef,
    useState,
    type ReactNode,
} from 'react';
import {
    FilterGenderIcon,
    FilterYearIcon,
} from '@/components/enrollments/enrollment-filter-icons';
import { ConfirmDialog } from '@/components/sis/confirm-dialog';
import { usePageError } from '@/components/sis/page-error-context';
import { OpsYearFilter } from '@/components/sis/ops-year-filter';
import { SisListSelect } from '@/components/sis/sis-list-select';
import {
    tableActionIds,
    toggleTableRowChecked,
    toggleTableSelectAll,
} from '@/components/sis/table-row-selection';
import { StudentCreateDialog, StudentViewDialog } from '@/components/students/student-record-form';
import { StudentFileCell } from '@/components/students/student-file-cell';
import { studentRecordCompletenessGaps } from '@/components/students/student-record-gaps';
import { hasPageTextSelection } from '@/hooks/use-page-clipboard';
import { useResizableTableColumns } from '@/hooks/use-resizable-table-columns';
import {
    useRegisterPageRibbon,
    type PageRibbonGroup,
} from '@/components/sis/page-ribbon-context';
import { useRegisterPageTitlebarHome } from '@/components/sis/page-titlebar-home-context';
import { useRegisterPageTitlebarSearch } from '@/components/sis/page-titlebar-search-context';
import { appendEnrollmentHandoff } from '@/lib/enrollment-handoff';
import type {
    StudentAuthorization,
    StudentDetail,
} from '@/components/students/student-details-surface';
import { t } from '@/i18n';

export type { StudentAuthorization };

const STUDENTS_PER_PAGE = 17;
const STUDENT_STATUS_ACTIVE = 1;
const STUDENT_STATUS_WITHDRAWN = 4;

const STUDENT_STATUS_TABS: Array<{
    status: number | null;
    icon: LucideIcon;
    tone: 'light' | 'dark';
}> = [
    { status: null, icon: Users, tone: 'dark' },
    { status: 1, icon: CheckCircle2, tone: 'dark' },
    { status: 0, icon: CircleSlash, tone: 'light' },
    { status: 2, icon: PauseCircle, tone: 'dark' },
    { status: 3, icon: GraduationCap, tone: 'dark' },
    { status: 4, icon: UserMinus, tone: 'light' },
];

const STUDENT_STATUS_ACTIONS: Array<{
    status: number;
    icon: LucideIcon;
    tone: 'light' | 'dark';
}> = [
    { status: 1, icon: CheckCircle2, tone: 'dark' },
    { status: 0, icon: CircleSlash, tone: 'light' },
    { status: 2, icon: PauseCircle, tone: 'dark' },
    { status: 3, icon: GraduationCap, tone: 'dark' },
    { status: 4, icon: UserMinus, tone: 'light' },
];

function clampPercent(value: number): number {
    if (value < 0) {
        return 0;
    }

    if (value > 100) {
        return 100;
    }

    return Math.round(value);
}

function percentForStatus(
    status: number | null,
    progress: StudentsPayload['status_progress'],
): number {
    const match = progress?.stages.find((stage) => stage.status === status);

    return clampPercent(match?.percent ?? 0);
}

function countForStatus(
    status: number | null,
    progress: StudentsPayload['status_progress'],
): number {
    const match = progress?.stages.find((stage) => stage.status === status);
    const count = match?.count ?? 0;

    return count < 0 ? 0 : Math.round(count);
}

export type StudentListItem = {
    id: number;
    student_code: string;
    full_name: string;
    first_name: string;
    father_name?: string | null;
    grandfather_name?: string | null;
    great_grandfather_name?: string | null;
    last_name: string;
    mother_name?: string | null;
    maternal_father_name?: string | null;
    maternal_grandfather_name?: string | null;
    guardian_triple_name?: string | null;
    governorate?: string | null;
    neighborhood?: string | null;
    locality?: string | null;
    house_number?: string | null;
    birth_date: string;
    birth_place?: string | null;
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
    specialization_name?: string | null;
    academic_year_id?: number | null;
    academic_year_name?: string | null;
    academic_year_code?: string | null;
    status: number;
    is_enrolled?: boolean;
};

export type StudentsPayload = {
    data: StudentListItem[];
    meta: {
        page: number;
        per_page: number;
        total: number;
        last_page: number;
    };
    status_progress?: {
        overall_percent: number;
        stages: Array<{ status: number | null; percent: number; count: number }>;
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
        academic_year_id: number | null;
        gender: number | null;
        enrolled: number | null;
    };
    authorization: StudentAuthorization;
    preview: PreviewPayload;
};

type VisitParams = {
    q?: string;
    status?: number | null;
    page?: number;
    per_page?: number;
    student?: number | null;
    academic_year_id?: number | null;
    gender?: number | null;
    enrolled?: number | null;
    quiet?: boolean;
};

function textOrDash(value: string | number | null | undefined): string {
    if (value === null || value === undefined || value === '') {
        return '—';
    }

    return String(value);
}

function studentQuadName(row: StudentListItem): string {
    return [
        row.first_name,
        row.father_name,
        row.grandfather_name,
        row.great_grandfather_name,
        row.last_name,
    ]
        .map((part) => part?.trim() ?? '')
        .filter((part) => part !== '')
        .join(' ');
}

function formatCivilDate(value: string | null | undefined): string {
    if (value === null || value === undefined || value === '') {
        return '—';
    }

    const match = /^(\d{4})-(\d{2})-(\d{2})/.exec(value);

    if (!match) {
        return value;
    }

    return `${match[2]}/${match[3]}/${match[1]}`;
}

const MOBILE_DIGIT_COUNT = 11;

function maskMobileDigits(value: string): string {
    return value.replace(/\D/g, '').slice(0, MOBILE_DIGIT_COUNT);
}

function displayMobile(value: string | null | undefined): string {
    const digits = maskMobileDigits(value ?? '');

    return digits === '' ? '—' : digits;
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

function CellScroll({ children }: { children: ReactNode }) {
    return <div className="sis-students-table__cell-scroll">{children}</div>;
}

function HighlightedText({ text, query }: { text: string; query: string }) {
    return (
        <>
            {searchSegments(text, query).map((segment, segmentIndex) =>
                segment.hit ? (
                    <mark key={`hit-${segmentIndex}`} className="sis-admission-search-hit">
                        {segment.text}
                    </mark>
                ) : (
                    <span key={`plain-${segmentIndex}`}>{segment.text}</span>
                ),
            )}
        </>
    );
}

function searchSegments(
    text: string,
    query: string,
): Array<{ text: string; hit: boolean }> {
    const tokens = query
        .trim()
        .split(/\s+/)
        .map((token) => token.trim())
        .filter((token) => token.length > 0);

    if (text === '' || tokens.length === 0) {
        return [{ text, hit: false }];
    }

    const pattern = tokens
        .map((token) => token.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'))
        .join('|');
    const matcher = new RegExp(`(${pattern})`, 'giu');
    const parts = text.split(matcher);

    return parts
        .filter((part) => part !== '')
        .map((part) => ({
            text: part,
            hit: tokens.some((token) =>
                part.toLocaleLowerCase('ar').includes(token.toLocaleLowerCase('ar')),
            ),
        }));
}

function visiblePages(current: number, totalPages: number): number[] {
    const windowSize = 5;
    if (totalPages <= windowSize) {
        return Array.from({ length: totalPages }, (_, index) => index + 1);
    }

    const half = Math.floor(windowSize / 2);
    let start = Math.max(1, current - half);
    let end = start + windowSize - 1;
    if (end > totalPages) {
        end = totalPages;
        start = Math.max(1, end - windowSize + 1);
    }

    return Array.from({ length: end - start + 1 }, (_, index) => start + index);
}

function statusTabLabel(status: number | null, i18n: ReturnType<typeof t>): string {
    if (status === null) {
        return i18n.students.allStatuses;
    }

    const labels: Record<number, string> = {
        0: i18n.status.inactive,
        1: i18n.status.active,
        2: i18n.status.suspended,
        3: i18n.status.graduated,
        4: i18n.status.withdrawn,
    };

    return labels[status] ?? String(status);
}

function statusTone(status: number): 'light' | 'dark' {
    return status === 0 || status === 4 ? 'light' : 'dark';
}

function emptyToNull(value: string): string | null {
    const trimmed = value.trim();

    return trimmed === '' ? null : trimmed;
}

function dateInputValue(value: string | null | undefined): string {
    if (value === null || value === undefined || value === '') {
        return '';
    }

    return value.slice(0, 10);
}

function parseOptionalInt(value: string): number | null {
    const trimmed = value.trim();
    if (trimmed === '') {
        return null;
    }

    const parsed = Number.parseInt(trimmed, 10);

    return Number.isFinite(parsed) ? parsed : null;
}

function TableEditInput({
    value,
    label,
    type = 'text',
    dir,
    maxLength,
    inputMode,
    onChange,
}: {
    value: string;
    label: string;
    type?: string;
    dir?: 'ltr' | 'rtl';
    maxLength?: number;
    inputMode?: 'numeric' | 'tel' | 'text';
    onChange: (value: string) => void;
}) {
    return (
        <input
            type={type}
            className="sis-students-table__edit-input"
            value={value}
            aria-label={label}
            dir={dir}
            maxLength={maxLength}
            inputMode={inputMode}
            autoComplete="off"
            onClick={(event) => event.stopPropagation()}
            onChange={(event) => onChange(event.target.value)}
        />
    );
}

type StudentRowHandle = {
    save: () => Promise<void>;
};

type StudentEditorRowProps = {
    row: StudentListItem;
    rowNumber: number;
    canSelect: boolean;
    canViewPii: boolean;
    selected: boolean;
    checked: boolean;
    editing: boolean;
    applyingStatus: boolean;
    search: string;
    onSelect: (studentId: number) => void;
    onToggleChecked: (studentId: number) => void;
    onOpenStudentFile: (row: StudentListItem) => void;
};

const StudentEditorRow = forwardRef<StudentRowHandle, StudentEditorRowProps>(
    function StudentEditorRow(
        {
            row,
            rowNumber,
            canSelect,
            canViewPii,
            selected,
            checked,
            editing,
            applyingStatus,
            search,
            onSelect,
            onToggleChecked,
            onOpenStudentFile,
        },
        ref,
    ) {
        const i18n = t();
        const workflowI18n = i18n.workflow;
        const { showInertiaErrors } = usePageError();
        const name = studentQuadName(row);
        const profileGaps = studentRecordCompletenessGaps(
            row as unknown as Record<string, unknown>,
        );
        const [firstName, setFirstName] = useState(row.first_name);
        const [fatherName, setFatherName] = useState(row.father_name ?? '');
        const [grandfatherName, setGrandfatherName] = useState(row.grandfather_name ?? '');
        const [greatGrandfatherName, setGreatGrandfatherName] = useState(
            row.great_grandfather_name ?? '',
        );
        const [lastName, setLastName] = useState(row.last_name);
        const [birthDate, setBirthDate] = useState(dateInputValue(row.birth_date));
        const [gender, setGender] = useState(row.gender);
        const [transferDocumentNumber, setTransferDocumentNumber] = useState(
            row.transfer_document_number == null ? '' : String(row.transfer_document_number),
        );
        const [previousSchoolName, setPreviousSchoolName] = useState(
            row.previous_school_name ?? '',
        );
        const [mobile, setMobile] = useState(() => maskMobileDigits(row.mobile ?? ''));
        const [saving, setSaving] = useState(false);

        useEffect(() => {
            if (editing) {
                return;
            }

            setFirstName(row.first_name);
            setFatherName(row.father_name ?? '');
            setGrandfatherName(row.grandfather_name ?? '');
            setGreatGrandfatherName(row.great_grandfather_name ?? '');
            setLastName(row.last_name);
            setBirthDate(dateInputValue(row.birth_date));
            setGender(row.gender);
            setTransferDocumentNumber(
                row.transfer_document_number == null ? '' : String(row.transfer_document_number),
            );
            setPreviousSchoolName(row.previous_school_name ?? '');
            setMobile(maskMobileDigits(row.mobile ?? ''));
        }, [editing, row]);

        const save = useCallback((): Promise<void> => {
            if (saving) {
                return Promise.resolve();
            }

            setSaving(true);

            const payload: Record<string, string | number | null> = {
                first_name: firstName.trim(),
                last_name: lastName.trim(),
                father_name: emptyToNull(fatherName),
                grandfather_name: emptyToNull(grandfatherName),
                great_grandfather_name: emptyToNull(greatGrandfatherName),
                birth_date: birthDate,
                governorate: row.governorate ?? null,
                neighborhood: row.neighborhood ?? null,
                gender,
                previous_school_name: emptyToNull(previousSchoolName),
                transfer_document_number: parseOptionalInt(transferDocumentNumber),
                transfer_document_date: row.transfer_document_date ?? null,
                mother_name: row.mother_name ?? null,
                maternal_father_name: row.maternal_father_name ?? null,
                maternal_grandfather_name: row.maternal_grandfather_name ?? null,
                guardian_triple_name: row.guardian_triple_name ?? null,
                locality: row.locality ?? null,
                house_number: row.house_number ?? null,
                birth_place: row.birth_place ?? null,
                registration_place: row.registration_place ?? null,
                nationality: row.nationality ?? null,
                religion: row.religion,
                mawalid_date: row.mawalid_date ?? null,
                school_start_date: row.school_start_date ?? null,
                notes: row.notes ?? null,
                school_name: row.school_name ?? null,
            };

            if (canViewPii) {
                payload.mobile = emptyToNull(mobile);
                payload.national_id = row.national_id ?? null;
                payload.guardian_mobile = row.guardian_mobile ?? null;
                payload.email = row.email ?? null;
            }

            return new Promise((resolve, reject) => {
                router.put(`/students/${row.id}`, payload, {
                    preserveScroll: true,
                    preserveState: true,
                    onSuccess: () => resolve(),
                    onError: (errors) => {
                        showInertiaErrors(errors, i18n.errors.saveFailed);
                        reject(new Error('student-row-save-failed'));
                    },
                    onFinish: () => setSaving(false),
                });
            });
        }, [
            birthDate,
            canViewPii,
            fatherName,
            firstName,
            gender,
            grandfatherName,
            greatGrandfatherName,
            i18n.errors.saveFailed,
            lastName,
            mobile,
            previousSchoolName,
            row,
            saving,
            showInertiaErrors,
            transferDocumentNumber,
        ]);

        useImperativeHandle(ref, () => ({ save }), [save]);

        return (
            <tr
                className={selected || checked ? 'sis-admission-periods-table__row--selected' : undefined}
                aria-selected={selected || checked}
                onClick={() => {
                    if (hasPageTextSelection()) {
                        return;
                    }

                    onSelect(row.id);
                }}
            >
                {canSelect ? (
                    <td className="sis-admission-drafts-table__select">
                        <input
                            type="checkbox"
                            checked={checked}
                            disabled={applyingStatus}
                            aria-label={`${i18n.students.selectStudent}: ${name}`}
                            onClick={(event) => event.stopPropagation()}
                            onChange={() => onToggleChecked(row.id)}
                        />
                    </td>
                ) : null}
                <td className="sis-admission-drafts-table__num">
                    <span dir="ltr">{rowNumber}</span>
                </td>
                <td className="sis-admission-drafts-table__name">
                    {editing ? (
                        <div className="sis-students-table__name-edit">
                            <input
                                className="sis-students-table__edit-input"
                                value={firstName}
                                aria-label={i18n.students.firstName}
                                onClick={(event) => event.stopPropagation()}
                                onChange={(event) => setFirstName(event.target.value)}
                            />
                            <input
                                className="sis-students-table__edit-input"
                                value={fatherName}
                                aria-label={i18n.students.fatherName}
                                onClick={(event) => event.stopPropagation()}
                                onChange={(event) => setFatherName(event.target.value)}
                            />
                            <input
                                className="sis-students-table__edit-input"
                                value={grandfatherName}
                                aria-label={i18n.students.grandfatherName}
                                onClick={(event) => event.stopPropagation()}
                                onChange={(event) => setGrandfatherName(event.target.value)}
                            />
                            <input
                                className="sis-students-table__edit-input"
                                value={greatGrandfatherName}
                                aria-label={i18n.students.greatGrandfatherName}
                                onClick={(event) => event.stopPropagation()}
                                onChange={(event) => setGreatGrandfatherName(event.target.value)}
                            />
                            <input
                                className="sis-students-table__edit-input"
                                value={lastName}
                                aria-label={i18n.students.familyName}
                                onClick={(event) => event.stopPropagation()}
                                onChange={(event) => setLastName(event.target.value)}
                            />
                        </div>
                    ) : (
                        <CellScroll>
                            <HighlightedText text={name} query={search} />
                        </CellScroll>
                    )}
                </td>
                <td
                    className={
                        profileGaps.length > 0
                            ? 'sis-admission-drafts-table__enroll-action sis-admission-drafts-table__enroll-action--file-incomplete'
                            : 'sis-admission-drafts-table__enroll-action sis-admission-drafts-table__enroll-action--file-complete'
                    }
                >
                    <StudentFileCell
                        gaps={profileGaps}
                        completeLabel={workflowI18n.studentFileComplete}
                        continueLabel={workflowI18n.studentFileContinue}
                        expandLabel={workflowI18n.profileCompletenessGaps}
                        onContinue={() => onOpenStudentFile(row)}
                    />
                </td>
                <td className="sis-admission-drafts-table__text sis-students-table__nowrap sis-students-table__birth">
                    {editing ? (
                        <input
                            type="date"
                            className="sis-students-table__edit-input"
                            value={birthDate}
                            aria-label={i18n.students.birthDate}
                            onClick={(event) => event.stopPropagation()}
                            onChange={(event) => setBirthDate(event.target.value)}
                        />
                    ) : (
                        <CellScroll>
                            <span dir="ltr">{formatCivilDate(row.birth_date)}</span>
                        </CellScroll>
                    )}
                </td>
                <td className="sis-admission-drafts-table__text">
                    {editing ? (
                        <div
                            onClick={(event) => event.stopPropagation()}
                            onPointerDown={(event) => event.stopPropagation()}
                        >
                            <SisListSelect
                                value={String(gender)}
                                options={[
                                    { value: '1', label: i18n.students.male },
                                    { value: '2', label: i18n.students.female },
                                ]}
                                onChange={(next) => setGender(Number(next))}
                                triggerClassName="sis-students-table__edit-input"
                                dir="rtl"
                                ariaLabel={i18n.students.gender}
                            />
                        </div>
                    ) : (
                        <CellScroll>{genderLabel(row.gender, i18n)}</CellScroll>
                    )}
                </td>
                <td className="sis-admission-drafts-table__text sis-students-table__nowrap">
                    {editing ? (
                        <TableEditInput
                            value={transferDocumentNumber}
                            label={i18n.students.transferDocumentNumber}
                            dir="ltr"
                            onChange={setTransferDocumentNumber}
                        />
                    ) : (
                        <CellScroll>
                            <span dir="ltr">{textOrDash(row.transfer_document_number)}</span>
                        </CellScroll>
                    )}
                </td>
                <td className="sis-admission-drafts-table__text sis-admission-drafts-table__text--wide">
                    {editing ? (
                        <TableEditInput
                            value={previousSchoolName}
                            label={i18n.students.previousSchoolName}
                            onChange={setPreviousSchoolName}
                        />
                    ) : (
                        <CellScroll>{textOrDash(row.previous_school_name)}</CellScroll>
                    )}
                </td>
                <td
                    className={
                        row.is_enrolled
                            ? 'sis-students-table__enrollment sis-students-table__enrollment--yes'
                            : 'sis-students-table__enrollment sis-students-table__enrollment--no'
                    }
                >
                    <CellScroll>
                        {row.is_enrolled ? i18n.students.enrollmentYes : i18n.students.enrollmentNo}
                    </CellScroll>
                </td>
                <td className="sis-admission-drafts-table__text sis-students-table__nowrap sis-students-table__mobile">
                    {editing && canViewPii ? (
                        <TableEditInput
                            value={mobile}
                            label={i18n.students.mobile}
                            dir="ltr"
                            maxLength={MOBILE_DIGIT_COUNT}
                            inputMode="numeric"
                            onChange={(value) => setMobile(maskMobileDigits(value))}
                        />
                    ) : (
                        <CellScroll>
                            <span dir="ltr">{displayMobile(row.mobile)}</span>
                        </CellScroll>
                    )}
                </td>
                <td
                    className={`sis-students-table__status sis-students-table__status--tone-${statusTone(row.status)}`}
                    data-status={row.status}
                >
                    <CellScroll>{statusTabLabel(row.status, i18n)}</CellScroll>
                </td>
            </tr>
        );
    },
);

export function StudentList({
    students,
    filters,
    authorization,
    preview,
}: StudentListProps) {
    const i18n = t();
    const { showInertiaErrors, showError } = usePageError();
    const page = usePage();
    const { academicYears } = page.props as {
        academicYears?: Array<{ id: number; name: string; code: string; is_current: boolean }>;
    };
    const selectedAcademicYear =
        academicYears?.find((year) => year.id === filters.academic_year_id) ?? null;
    const genderValue = filters.gender === 1 || filters.gender === 2 ? String(filters.gender) : '';
    const genderFilterLabel =
        genderValue === '1'
            ? i18n.students.male
            : genderValue === '2'
              ? i18n.students.female
              : i18n.students.gender;
    const enrolledValue =
        filters.enrolled === 1 || filters.enrolled === 0 ? String(filters.enrolled) : '';
    const enrolledFilterLabel =
        enrolledValue === '1'
            ? i18n.students.enrolledStudents
            : enrolledValue === '0'
              ? i18n.students.notEnrolledStudents
              : i18n.students.enrollmentFilter;
    const [selectedId, setSelectedId] = useState<number | null>(null);
    const [checkedIds, setCheckedIds] = useState<number[]>([]);
    const [editing, setEditing] = useState(false);
    const [editingIds, setEditingIds] = useState<number[]>([]);
    const [savingRows, setSavingRows] = useState(false);
    const [viewingStudents, setViewingStudents] = useState<StudentListItem[] | null>(null);
    const [viewingAsFileContinue, setViewingAsFileContinue] = useState(false);
    const [creatingStudent, setCreatingStudent] = useState(false);
    const [deleteTarget, setDeleteTarget] = useState<StudentListItem | null>(null);
    const [deleting, setDeleting] = useState(false);
    const [applyingStatus, setApplyingStatus] = useState(false);
    const selectAllRef = useRef<HTMLInputElement>(null);
    const tableRef = useRef<HTMLTableElement>(null);
    const filtersRef = useRef(filters);
    const searchDraftRef = useRef(filters.q);
    const checkedIdsRef = useRef(checkedIds);
    const selectedIdRef = useRef(selectedId);
    const applyingStatusRef = useRef(false);
    const createIntentHandledRef = useRef(false);
    const rowRefs = useRef(new Map<number, StudentRowHandle>());
    filtersRef.current = filters;
    checkedIdsRef.current = checkedIds;
    selectedIdRef.current = selectedId;
    const rows = students?.data ?? [];
    const pagination = students?.meta ?? {
        page: filters.page,
        per_page: filters.per_page,
        total: 0,
        last_page: 1,
    };
    const rowOffset = (pagination.page - 1) * pagination.per_page;
    const canSelect = authorization.canUpdate;

    useEffect(() => {
        const query = page.url.includes('?') ? page.url.slice(page.url.indexOf('?') + 1) : '';
        const params = new URLSearchParams(query);
        if (params.get('create') !== '1') {
            createIntentHandledRef.current = false;

            return;
        }

        if (createIntentHandledRef.current) {
            return;
        }

        createIntentHandledRef.current = true;

        if (authorization.canCreate !== false) {
            setCreatingStudent(true);
        }

        params.delete('create');
        const next = params.toString();
        router.get(next === '' ? '/students' : `/students?${next}`, {}, {
            replace: true,
            preserveState: true,
            preserveScroll: true,
        });
    }, [authorization.canCreate, page.url]);

    useResizableTableColumns(tableRef, {
        storageKey: 'students.list',
        columnSignature: `${canSelect ? 'select' : 'readonly'}:file:enrolled`,
        enabled: rows.length > 0,
    });

    const rowIds = useMemo(() => rows.map((row) => row.id), [rows]);
    const visibleCheckedIds = useMemo(
        () => checkedIds.filter((id) => rowIds.includes(id)),
        [checkedIds, rowIds],
    );
    const actionIds = useMemo(
        () => tableActionIds(visibleCheckedIds, selectedId),
        [selectedId, visibleCheckedIds],
    );
    const allChecked = rowIds.length > 0 && visibleCheckedIds.length === rowIds.length;
    const someChecked = visibleCheckedIds.length > 0 && !allChecked;
    const canApplyStatus = canSelect && actionIds.length > 0 && !applyingStatus;
    const filtersBusy = applyingStatus;
    const overallPercent = clampPercent(students.status_progress?.overall_percent ?? 0);

    useEffect(() => {
        if (selectAllRef.current) {
            selectAllRef.current.indeterminate = someChecked;
        }
    }, [someChecked]);

    useRegisterPageTitlebarHome({
        href: '/students',
        ariaLabel: i18n.students.backToStudents,
    });

    const visitList = useCallback((params: VisitParams) => {
        const current = filtersRef.current;
        const nextStatus = 'status' in params ? params.status : current.status;
        const nextStudent = 'student' in params ? params.student : undefined;
        const nextQuery = ('q' in params ? params.q : current.q) ?? '';
        const nextYear =
            'academic_year_id' in params ? params.academic_year_id : current.academic_year_id;
        const nextGender = 'gender' in params ? params.gender : current.gender;
        const nextEnrolled = 'enrolled' in params ? params.enrolled : current.enrolled;

        router.get(
            '/students',
            {
                q: nextQuery.trim() || undefined,
                page: params.page ?? current.page,
                per_page: STUDENTS_PER_PAGE,
                status: nextStatus ?? undefined,
                student: nextStudent ?? undefined,
                academic_year_id: nextYear ?? undefined,
                gender: nextGender ?? undefined,
                enrolled: nextEnrolled === 0 || nextEnrolled === 1 ? nextEnrolled : undefined,
            },
            {
                preserveState: true,
                preserveScroll: true,
                replace: nextStudent === undefined,
                only: ['students', 'filters', 'preview', 'authorization'],
                showProgress: params.quiet !== true,
            },
        );
    }, []);

    const commitSearch = useCallback(
        (query: string) => {
            visitList({ q: query, page: 1, student: undefined, quiet: true });
        },
        [visitList],
    );

    const selectRow = useCallback((studentId: number) => {
        setSelectedId(studentId);
        setCheckedIds((previous) => {
            if (previous.length > 1 && previous.includes(studentId)) {
                return previous;
            }

            return [studentId];
        });
        setEditing(false);
        setEditingIds([]);
    }, []);

    const bindRowRef = useCallback(
        (studentId: number) => (handle: StudentRowHandle | null) => {
            if (handle === null) {
                rowRefs.current.delete(studentId);

                return;
            }

            rowRefs.current.set(studentId, handle);
        },
        [],
    );

    const clearSelection = useCallback(() => {
        setSelectedId(null);
        setCheckedIds([]);
        setEditing(false);
        setEditingIds([]);
        setSavingRows(false);
    }, []);

    const clearStructureFilters = useCallback(() => {
        searchDraftRef.current = '';
        visitList({
            q: '',
            gender: null,
            enrolled: null,
            page: 1,
            student: undefined,
        });
    }, [visitList]);

    useEffect(() => {
        const table = tableRef.current;

        if (table === null) {
            return;
        }

        const onWheel = (event: WheelEvent) => {
            const target = event.target;

            if (!(target instanceof Element)) {
                return;
            }

            const cell = target.closest('td');

            if (!(cell instanceof HTMLTableCellElement) || !table.contains(cell)) {
                return;
            }

            if (
                cell.classList.contains('sis-admission-drafts-table__select')
                || cell.classList.contains('sis-admission-drafts-table__num')
                || cell.querySelector('input, select, textarea') !== null
            ) {
                return;
            }

            const scroller =
                target.closest('.sis-students-table__cell-scroll')
                ?? cell.querySelector(':scope > .sis-students-table__cell-scroll');

            if (!(scroller instanceof HTMLElement)) {
                return;
            }

            if (scroller.scrollWidth <= scroller.clientWidth + 1) {
                return;
            }

            const deltaX = event.deltaX;
            const deltaY = event.deltaY;
            const rtl = getComputedStyle(scroller).direction === 'rtl';
            const delta = deltaX !== 0 ? deltaX : (rtl ? -deltaY : deltaY);

            if (delta === 0) {
                return;
            }

            event.preventDefault();
            scroller.scrollLeft += delta;
        };

        table.addEventListener('wheel', onWheel, { passive: false });

        return () => table.removeEventListener('wheel', onWheel);
    }, [rows]);

    const toggleChecked = useCallback((studentId: number) => {
        const next = toggleTableRowChecked(checkedIds, studentId);
        setCheckedIds(next.checkedIds);
        setSelectedId(next.selectedId);
        if (next.selectedId === null) {
            setEditing(false);
            setEditingIds([]);
        }
    }, [checkedIds]);

    const toggleAll = useCallback(() => {
        const next = toggleTableSelectAll(checkedIds, rowIds, selectedId);
        setCheckedIds(next.checkedIds);
        setSelectedId(next.selectedId);
        if (next.selectedId === null) {
            setEditing(false);
            setEditingIds([]);
        }
    }, [checkedIds, rowIds, selectedId]);

    const resolveActionIds = useCallback((): number[] => {
        const fromUi = tableActionIds(checkedIdsRef.current, selectedIdRef.current);
        if (fromUi.length > 0) {
            return fromUi;
        }

        return actionIds;
    }, [actionIds]);

    const applyStatus = useCallback(
        (status: number) => {
            const studentIds = resolveActionIds();
            if (!canSelect || studentIds.length === 0 || applyingStatusRef.current) {
                return;
            }

            applyingStatusRef.current = true;
            setApplyingStatus(true);
            router.post(
                '/students/bulk-status',
                {
                    student_ids: studentIds.map((id) => Number(id)),
                    status: Number(status),
                },
                {
                    preserveScroll: true,
                    preserveState: true,
                    only: ['students', 'filters', 'preview', 'authorization'],
                    onError: (errors) => showInertiaErrors(errors, i18n.errors.statusFailed),
                    onFinish: () => {
                        applyingStatusRef.current = false;
                        setApplyingStatus(false);
                    },
                },
            );
        },
        [canSelect, i18n.errors.statusFailed, resolveActionIds, showInertiaErrors],
    );

    const enrollSelected = useCallback(() => {
        if (!authorization.canEnroll || filtersBusy || savingRows) {
            return;
        }

        const selected = actionIds
            .map((id) => rows.find((row) => row.id === id) ?? null)
            .filter((row): row is StudentListItem => row !== null);

        if (selected.length === 0) {
            showError(i18n.students.enrollNeedsSelection);

            return;
        }

        const eligible = selected.filter(
            (row) => !row.is_enrolled && row.status === STUDENT_STATUS_ACTIVE,
        );

        if (eligible.length === 0) {
            showError(i18n.students.enrollNeedsEligible);

            return;
        }

        const handoffStudents = eligible.map((row) => ({
            id: row.id,
            full_name: studentQuadName(row) || row.full_name,
        }));

        appendEnrollmentHandoff({
            academic_year_id: filters.academic_year_id,
            students: handoffStudents,
        });

        clearSelection();
    }, [
        actionIds,
        authorization.canEnroll,
        clearSelection,
        filters.academic_year_id,
        filtersBusy,
        i18n.students.enrollNeedsEligible,
        i18n.students.enrollNeedsSelection,
        rows,
        savingRows,
        showError,
    ]);

    const onStatusTabClick = useCallback(
        (status: number | null, isActive: boolean) => {
            if (isActive) {
                return;
            }

            visitList({
                status,
                page: 1,
                student: undefined,
            });
        },
        [visitList],
    );

    const goPage = useCallback(
        (page: number) => {
            if (page < 1 || page > pagination.last_page || page === pagination.page) {
                return;
            }

            visitList({ page, student: undefined });
        },
        [pagination.last_page, pagination.page, visitList],
    );

    const selectedStudent = rows.find((row) => row.id === selectedId) ?? null;
    const editTargetIds = actionIds;
    const hasEditTargets = editTargetIds.length > 0;
    const hasActiveSelection = hasEditTargets || editing;
    const viewTargets = useMemo(() => {
        const selected = new Set(actionIds);

        return rows.filter((row) => selected.has(row.id));
    }, [actionIds, rows]);
    const hasViewTargets = viewTargets.length > 0;
    const canDelete =
        canSelect && selectedStudent !== null && selectedStudent.status !== STUDENT_STATUS_WITHDRAWN;

    const openStudentFile = useCallback(
        (row: StudentListItem) => {
            setViewingAsFileContinue(true);
            setViewingStudents([
                {
                    ...row,
                    academic_year_id: row.academic_year_id ?? filters.academic_year_id,
                    academic_year_name: row.academic_year_name ?? selectedAcademicYear?.name ?? null,
                    academic_year_code: row.academic_year_code ?? selectedAcademicYear?.code ?? null,
                },
            ]);
        },
        [filters.academic_year_id, selectedAcademicYear?.code, selectedAcademicYear?.name],
    );

    const startEditing = useCallback(() => {
        if (!canSelect || editTargetIds.length === 0) {
            return;
        }

        setEditingIds(editTargetIds);
        setEditing(true);
    }, [canSelect, editTargetIds]);

    const saveEditingRows = useCallback(() => {
        if (savingRows || editingIds.length === 0) {
            return;
        }

        void (async () => {
            setSavingRows(true);
            try {
                for (const id of editingIds) {
                    await rowRefs.current.get(id)?.save();
                }
                setEditing(false);
                setEditingIds([]);
            } catch {
                // Stay in edit mode so the user can correct validation errors.
                // Row save already opened the shared error dialog.
            } finally {
                setSavingRows(false);
            }
        })();
    }, [editingIds, savingRows]);

    const ribbonGroups = useMemo((): PageRibbonGroup[] => {
        const commands: PageRibbonGroup['commands'] = [
                    {
                        id: 'view-student',
                label: i18n.common.view,
                        icon: Eye,
                disabled: !hasViewTargets,
                onSelect: () => {
                    setViewingAsFileContinue(false);
                    setViewingStudents(
                        viewTargets.map((row) => ({
                            ...row,
                            academic_year_id: row.academic_year_id ?? filters.academic_year_id,
                            academic_year_name:
                                row.academic_year_name ?? selectedAcademicYear?.name ?? null,
                            academic_year_code:
                                row.academic_year_code ?? selectedAcademicYear?.code ?? null,
                        })),
                    );
                },
            },
        ];

        if (canSelect) {
            commands.push(
                {
                    id: 'edit-student',
                    label: i18n.common.edit,
                    icon: Pencil,
                    tone: 'edit',
                    disabled: !hasEditTargets || savingRows,
                    onSelect: startEditing,
                },
                {
                    id: 'save-student',
                    label: i18n.common.save,
                    icon: Save,
                    tone: 'save',
                    disabled: !editing || savingRows,
                    onSelect: saveEditingRows,
                },
                {
                    id: 'cancel-student-selection',
                    label: i18n.common.cancel,
                    icon: XCircle,
                    disabled: !hasActiveSelection || savingRows,
                    onSelect: clearSelection,
                },
                {
                    id: 'delete-student',
                    label: i18n.common.delete,
                    icon: Trash2,
                    tone: 'delete',
                    disabled: !canDelete || savingRows,
                        onSelect: () => {
                        if (selectedStudent !== null) {
                            setDeleteTarget(selectedStudent);
                            }
                        },
                    },
            );
        }

        return [
            {
                id: 'student-list-actions',
                label: i18n.common.actions,
                commands,
            },
        ];
    }, [
        canDelete,
        canSelect,
        clearSelection,
        editing,
        hasActiveSelection,
        hasEditTargets,
        hasViewTargets,
        i18n.common.actions,
        i18n.common.cancel,
        i18n.common.delete,
        i18n.common.edit,
        i18n.common.save,
        i18n.common.view,
        saveEditingRows,
        savingRows,
        selectedStudent,
        startEditing,
        viewTargets,
        filters.academic_year_id,
        selectedAcademicYear,
    ]);

    useRegisterPageRibbon('home', ribbonGroups);

    const titlebarSearch = useMemo(
        () => ({
            committedQuery: filters.q,
            label: i18n.students.searchAria,
            placeholder: i18n.students.search,
            onDraftChange: (query: string) => {
                searchDraftRef.current = query;
            },
            onCommit: commitSearch,
        }),
        [commitSearch, filters.q, i18n.students.search, i18n.students.searchAria],
    );

    useRegisterPageTitlebarSearch(titlebarSearch);

    const confirmDelete = useCallback(() => {
        if (deleteTarget === null) {
            return;
        }

        setDeleting(true);
        router.post(
            '/students/bulk-status',
            {
                student_ids: [deleteTarget.id],
                status: STUDENT_STATUS_WITHDRAWN,
            },
            {
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => {
                    if (selectedId === deleteTarget.id) {
                        setSelectedId(null);
                    }
                    setEditing(false);
                    setEditingIds([]);
                    setCheckedIds((current) => current.filter((id) => id !== deleteTarget.id));
                },
                onError: (errors) => showInertiaErrors(errors, i18n.errors.deleteFailed),
                onFinish: () => {
                    setDeleting(false);
                    setDeleteTarget(null);
                },
            },
        );
    }, [deleteTarget, i18n.errors.deleteFailed, selectedId, showInertiaErrors]);

    const emptyMessage = filters.q ? i18n.students.emptySearch : i18n.students.emptyDesc;

    return (
        <div
            className="sis-ops-hub sis-admission-page sis-students-page sis-enrollments-page flex h-full min-h-0 flex-col overflow-hidden pb-4"
            dir="rtl"
            lang="ar"
        >
            <div className="sis-enrollments-control-strip">
                <div
                    className="sis-enrollments-filters-bar sis-enrollments-filters-bar--filter"
                    role="search"
                    aria-label={i18n.students.structureFiltersTitle}
                    aria-busy={filtersBusy || undefined}
                >
                    <div className="sis-enrollments-filter-chip sis-enrollments-filter-chip--year" dir="rtl">
                        <span className="sis-enrollments-filter-chip__icon" aria-hidden="true">
                            <FilterYearIcon />
                        </span>
                        <span className="sis-enrollments-filter-chip__control">
                            <OpsYearFilter
                                action="/students"
                                academicYearId={filters.academic_year_id}
                                extraParams={{
                                    get q() {
                                        const value = searchDraftRef.current.trim();

                                        return value === '' ? undefined : value;
                                    },
                                    per_page: STUDENTS_PER_PAGE,
                                    status: filters.status ?? undefined,
                                    gender: filters.gender ?? undefined,
                                    enrolled:
                                        filters.enrolled === 0 || filters.enrolled === 1
                                            ? filters.enrolled
                                            : undefined,
                                }}
                                onYearChange={(yearId) => {
                                    if (filtersBusy) {
                                        return;
                                    }

                                    visitList({
                                        academic_year_id: yearId,
                                        page: 1,
                                        student: undefined,
                                    });
                                }}
                                label={i18n.students.academicYear}
                                showLabel={false}
                                compact
                                showCurrentBadge={false}
                                disabled={filtersBusy}
                                controlClassName="sis-admission-year-control"
                            />
                        </span>
                    </div>

                    <div className="sis-enrollments-filter-chip sis-enrollments-filter-chip--gender" dir="rtl">
                        <span className="sis-enrollments-filter-chip__icon" aria-hidden="true">
                            <FilterGenderIcon />
                        </span>
                        <span className="sis-admission-select-fit">
                            <span className="sis-admission-select-fit__mirror" aria-hidden="true">
                                {genderFilterLabel}
                            </span>
                            <SisListSelect
                                value={genderValue}
                                options={[
                                    { value: '', label: i18n.students.gender },
                                    { value: '1', label: i18n.students.male },
                                    { value: '2', label: i18n.students.female },
                                ]}
                                onChange={(next) => {
                                    if (filtersBusy) {
                                        return;
                                    }

                                    visitList({
                                        gender: next === '1' || next === '2' ? Number(next) : null,
                                        page: 1,
                                        student: undefined,
                                    });
                                }}
                                disabled={filtersBusy}
                                triggerClassName="sis-ops-hub__link px-2 py-1 min-h-0 min-w-0 sis-admission-year-control"
                                dir="rtl"
                                ariaLabel={i18n.students.filterByGender}
                            />
                        </span>
                    </div>

                    <div className="sis-enrollments-filter-chip sis-enrollments-filter-chip--enrollment" dir="rtl">
                        <span className="sis-enrollments-filter-chip__icon" aria-hidden="true">
                            <ClipboardList className="sis-enrollments-filter-chip__lucide" />
                        </span>
                        <span className="sis-admission-select-fit">
                            <span className="sis-admission-select-fit__mirror" aria-hidden="true">
                                {enrolledFilterLabel}
                            </span>
                            <SisListSelect
                                value={enrolledValue}
                                options={[
                                    { value: '', label: i18n.students.enrollmentFilter },
                                    { value: '1', label: i18n.students.enrolledStudents },
                                    { value: '0', label: i18n.students.notEnrolledStudents },
                                ]}
                                onChange={(next) => {
                                    if (filtersBusy) {
                                        return;
                                    }

                                    visitList({
                                        enrolled: next === '1' || next === '0' ? Number(next) : null,
                                        page: 1,
                                        student: undefined,
                                    });
                                }}
                                disabled={filtersBusy}
                                triggerClassName="sis-ops-hub__link px-2 py-1 min-h-0 min-w-0 sis-admission-year-control"
                                dir="rtl"
                                ariaLabel={i18n.students.filterByEnrollment}
                            />
                        </span>
                    </div>

                    <button
                        type="button"
                        className="sis-enrollments-filter-clear"
                        onClick={clearStructureFilters}
                        disabled={filtersBusy}
                        aria-label={i18n.students.clearFiltersAria}
                        title={i18n.students.clearFiltersAria}
                    >
                        {i18n.students.clearFilters}
                    </button>
                    {actionIds.length > 0 ? (
                        <button
                            type="button"
                            className="sis-enrollments-filter-clear"
                            onClick={clearSelection}
                            disabled={filtersBusy || savingRows}
                            aria-label={i18n.students.clearSelectionAria}
                            title={i18n.students.clearSelectionAria}
                        >
                            {i18n.students.clearSelection}
                        </button>
                    ) : null}
                </div>

                {canSelect || authorization.canEnroll ? (
                    <div
                        className="sis-admission-drafts-transitions sis-enrollments-status-actions"
                        role="toolbar"
                        aria-label={i18n.students.statusActionsTitle}
                    >
                        <div className="sis-admission-drafts-table__transitions">
                            {authorization.canEnroll ? (
                                <button
                                    type="button"
                                    className="sis-admission-drafts-table__transition sis-admission-drafts-table__transition--tone-dark sis-enrollments-status-action sis-students-enroll-action"
                                    disabled={actionIds.length === 0 || filtersBusy || savingRows}
                                    aria-label={i18n.students.enrollAction}
                                    title={
                                        actionIds.length > 0
                                            ? i18n.students.enrollAction
                                            : i18n.students.enrollNeedsSelection
                                    }
                                    onClick={enrollSelected}
                                >
                                    <ClipboardList
                                        className="sis-enrollments-status-action__icon"
                                        aria-hidden="true"
                                    />
                                    <span className="sis-enrollments-status-action__label">
                                        {i18n.students.enrollAction}
                                    </span>
                                </button>
                            ) : null}
                            {canSelect
                                ? STUDENT_STATUS_ACTIONS.map((action) => {
                                      const Icon = action.icon;
                                      const actionLabel = statusTabLabel(action.status, i18n);

                                      return (
                                          <button
                                              key={action.status}
                                              type="button"
                                              className={`sis-admission-drafts-table__transition sis-admission-drafts-table__transition--tone-${action.tone} sis-enrollments-status-action`}
                                              data-status={action.status}
                                              disabled={!canApplyStatus}
                                              aria-label={actionLabel}
                                              title={
                                                  canApplyStatus
                                                      ? actionLabel
                                                      : i18n.students.statusNeedsSelection
                                              }
                                              onClick={() => applyStatus(action.status)}
                                          >
                                              <Icon
                                                  className="sis-enrollments-status-action__icon"
                                                  aria-hidden="true"
                                              />
                                              <span className="sis-enrollments-status-action__label">
                                                  {actionLabel}
                                              </span>
                                          </button>
                                      );
                                  })
                                : null}
                        </div>
                    </div>
                ) : null}
            </div>

            <section
                aria-label={i18n.students.statusTabsTitle}
                className="sis-admission-progress sis-students-tabs sis-enrollments-status-tabs sis-students-status-tabs"
            >
                <ol className="sis-admission-progress__track" dir="rtl" role="tablist">
                    {STUDENT_STATUS_TABS.map((tab) => {
                        const Icon = tab.icon;
                        const isActive = filters.status === tab.status;
                        const label = statusTabLabel(tab.status, i18n);
                        const percent = percentForStatus(tab.status, students.status_progress);
                        const count = countForStatus(tab.status, students.status_progress);
                        const statusClass =
                            tab.status === null
                                ? 'sis-admission-progress__segment--status-all'
                                : `sis-admission-progress__segment--status-${tab.status}`;

                        return (
                            <li key={tab.status ?? 'all'} className="sis-admission-progress__item">
                                <button
                                    type="button"
                                    role="tab"
                                    className={`sis-admission-progress__segment ${statusClass} sis-admission-progress__segment--tone-${tab.tone}${isActive ? ' sis-admission-progress__segment--active' : ''}`}
                                    aria-label={`${label} ${count}`}
                                    title={label}
                                    aria-selected={isActive}
                                    aria-pressed={isActive}
                                    aria-current={isActive ? 'true' : undefined}
                                    data-active={isActive ? 'true' : undefined}
                                    onClick={() => onStatusTabClick(tab.status, isActive)}
                                >
                                    <span
                                        className="sis-admission-progress__fill"
                                        style={{ width: `${percent}%` }}
                                        aria-hidden="true"
                                    />
                                    <span className="sis-admission-progress__content">
                                        <Icon className="sis-admission-progress__icon" aria-hidden="true" />
                                        <span className="sis-admission-progress__label">{label}</span>
                                        <span className="sis-admission-progress__count" dir="ltr">
                                            {count}
                                        </span>
                                    </span>
                                </button>
                            </li>
                        );
                    })}
                </ol>
            </section>

            <div
                className="sis-admission-progress__overall-block sis-enrollments-progress"
                role="group"
                aria-label={i18n.students.overallProgress}
            >
                <div
                    className="sis-admission-progress__overall-track"
                    role="progressbar"
                    aria-label={i18n.students.overallProgress}
                    aria-valuemin={0}
                    aria-valuemax={100}
                    aria-valuenow={overallPercent}
                    data-contrast={overallPercent >= 45 ? 'light' : 'dark'}
                    dir="rtl"
                >
                    <span
                        className="sis-admission-progress__overall-fill"
                        style={{ width: `${overallPercent}%` }}
                    />
                    <span className="sis-admission-progress__overall-value" dir="ltr">
                        {overallPercent}%
                    </span>
                </div>
            </div>

            <div className="sis-admission-page-body">
                <section aria-label={i18n.students.tableCaption} className="flex min-h-0 flex-1 flex-col">
                    {rows.length === 0 ? (
                        <p className="text-sm">{emptyMessage}</p>
                    ) : (
                        <>
                            <div className="sis-admission-periods-table sis-admission-drafts-table">
                                <div className="sis-admission-drafts-table__scroller">
                                    <table ref={tableRef}>
                                        <thead>
                                            <tr>
                                                {canSelect ? (
                                                    <th className="sis-admission-drafts-table__select">
                                                        <input
                                                            ref={selectAllRef}
                                                            type="checkbox"
                                                            checked={allChecked}
                                                            disabled={applyingStatus || savingRows}
                                                            aria-label={i18n.students.selectAllStudents}
                                                            onChange={toggleAll}
                                                        />
                                                    </th>
                                                ) : null}
                                                <th className="sis-admission-drafts-table__num">
                                                    {i18n.students.seq}
                                                </th>
                                                <th className="sis-admission-drafts-table__name-head">
                                                    {i18n.students.quadName}
                                                </th>
                                                <th className="sis-admission-drafts-table__enroll-head">
                                                    {i18n.workflow.studentFileColumn}
                                                </th>
                                                <th className="sis-students-table__birth">{i18n.students.birthDate}</th>
                                                <th>{i18n.students.gender}</th>
                                                <th>{i18n.students.transferDocumentNumber}</th>
                                                <th>{i18n.students.previousSchoolName}</th>
                                                <th className="sis-students-table__enrollment-head">
                                                    {i18n.students.enrollmentColumn}
                                                </th>
                                                <th className="sis-students-table__mobile">{i18n.students.mobile}</th>
                                                <th>{i18n.students.statusTabsTitle}</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {rows.map((row, index) => {
                                                const selected = selectedId === row.id;
                                                const checked = visibleCheckedIds.includes(row.id);

                                                return (
                                                    <StudentEditorRow
                                                        key={row.id}
                                                        ref={bindRowRef(row.id)}
                                                        row={row}
                                                        rowNumber={rowOffset + index + 1}
                                                        canSelect={canSelect}
                                                        canViewPii={authorization.canViewPii}
                                                        selected={selected}
                                                        checked={checked}
                                                        editing={editing && editingIds.includes(row.id)}
                                                        applyingStatus={applyingStatus}
                                                        search={filters.q}
                                                        onSelect={selectRow}
                                                        onToggleChecked={toggleChecked}
                                                        onOpenStudentFile={openStudentFile}
                                                    />
                                                );
                                            })}
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            {pagination.total > 0 ? (
                                <nav
                                    className="sis-admission-drafts-pagination"
                                    aria-label={i18n.common.page}
                                >
                                    <ul className="sis-admission-pagination" dir="ltr">
                                        <li className="sis-admission-pagination__item">
                                            <button
                                                type="button"
                                                className="sis-admission-pagination__link"
                                                aria-label={i18n.common.previous}
                                                disabled={pagination.page <= 1}
                                                onClick={() => goPage(pagination.page - 1)}
                                            >
                                                <span aria-hidden="true">&laquo;</span>
                                            </button>
                                        </li>
                                        {visiblePages(pagination.page, pagination.last_page).map(
                                            (pageNum) => (
                                                <li
                                                    key={pageNum}
                                                    className="sis-admission-pagination__item"
                                                >
                                                    <button
                                                        type="button"
                                                        className={
                                                            pageNum === pagination.page
                                                                ? 'sis-admission-pagination__link sis-admission-pagination__link--active'
                                                                : 'sis-admission-pagination__link'
                                                        }
                                                        aria-label={`${i18n.common.page} ${pageNum}`}
                                                        aria-current={
                                                            pageNum === pagination.page ? 'page' : undefined
                                                        }
                                                        onClick={() => goPage(pageNum)}
                                                    >
                                                        {pageNum}
                                                    </button>
                                                </li>
                                            ),
                                        )}
                                        <li className="sis-admission-pagination__item">
                                            <button
                                                type="button"
                                                className="sis-admission-pagination__link"
                                                aria-label={i18n.common.next}
                                                disabled={pagination.page >= pagination.last_page}
                                                onClick={() => goPage(pagination.page + 1)}
                                            >
                                                <span aria-hidden="true">&raquo;</span>
                                            </button>
                                        </li>
                                    </ul>
                                </nav>
                            ) : null}
                        </>
                    )}
                </section>
            </div>

            {!authorization.canViewPii ? (
                <p className="text-muted-foreground text-xs">{i18n.students.piiHidden}</p>
            ) : null}

            {viewingStudents !== null && viewingStudents.length > 0 ? (
                <StudentViewDialog
                    students={viewingStudents}
                    canViewPii={authorization.canViewPii}
                    canUpdate={authorization.canUpdate}
                    title={
                        viewingAsFileContinue
                            ? i18n.workflow.studentFileDialogTitle
                            : undefined
                    }
                    proceedLabel={i18n.common.save}
                    initialEditing={viewingAsFileContinue}
                    onClose={() => {
                        setViewingStudents(null);
                        setViewingAsFileContinue(false);
                    }}
                    onSaved={(updated) => {
                        setViewingStudents((current) =>
                            current === null
                                ? current
                                : current.map((row) => (row.id === updated.id ? { ...row, ...updated } : row)),
                        );
                    }}
                    onProceed={() => {
                        setViewingStudents(null);
                        setViewingAsFileContinue(false);
                        router.reload({ only: ['students', 'filters', 'authorization'] });
                    }}
                />
            ) : null}

            {creatingStudent ? (
                <StudentCreateDialog
                    canViewPii={authorization.canViewPii}
                    onClose={() => setCreatingStudent(false)}
                />
            ) : null}

            <ConfirmDialog
                open={deleteTarget !== null}
                title={i18n.students.deleteTitle}
                description={i18n.students.deleteConfirm}
                confirmLabel={i18n.common.delete}
                tone="danger"
                confirmPending={deleting}
                onConfirm={confirmDelete}
                onOpenChange={(open) => {
                    if (!open && !deleting) {
                        setDeleteTarget(null);
                    }
                }}
            />
        </div>
    );
}
