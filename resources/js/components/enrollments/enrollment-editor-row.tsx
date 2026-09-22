import { router, usePage } from '@inertiajs/react';
import {
    forwardRef,
    useCallback,
    useEffect,
    useImperativeHandle,
    useMemo,
    useState,
    type ReactNode,
} from 'react';
import {
    formatAcademicYearOptionLabel,
    type YearOption,
} from '@/components/sis/ops-year-filter';
import { SisListSelect } from '@/components/sis/sis-list-select';
import { usePageError } from '@/components/sis/page-error-context';
import { hasPageTextSelection } from '@/hooks/use-page-clipboard';
import { t } from '@/i18n';
import { isEnrollmentRegistrationComplete } from '@/lib/enrollment-registration-state';
import type {
    EnrollmentFilterOptions,
    EnrollmentListItem,
} from '@/components/enrollments/enrollment-types';

export type EnrollmentRowHandle = {
    save: () => Promise<void>;
};

type EnrollmentEditorRowProps = {
    row: EnrollmentListItem;
    rowNumber: number;
    canSelect: boolean;
    selected: boolean;
    checked: boolean;
    editing: boolean;
    busy: boolean;
    search: string;
    filterOptions: EnrollmentFilterOptions;
    onSelect: (enrollmentId: number) => void;
    onToggleChecked: (enrollmentId: number) => void;
    onRegisterSave: (enrollmentId: number, save: (() => Promise<void>) | null) => void;
};

function textOrDash(value: string | number | null | undefined): string {
    if (value === null || value === undefined || value === '') {
        return '—';
    }

    return String(value);
}

function studentQuadName(row: EnrollmentListItem): string {
    const parts = [
        row.student_first_name,
        row.student_father_name,
        row.student_grandfather_name,
        row.student_great_grandfather_name,
        row.student_last_name,
    ]
        .map((part) => part?.trim() ?? '')
        .filter((part) => part !== '');

    if (parts.length > 0) {
        return parts.join(' ');
    }

    return row.student_full_name?.trim() || '—';
}

function isoDate(value: string | null | undefined): string {
    if (value === null || value === undefined || value === '') {
        return '';
    }

    const match = /^(\d{4}-\d{2}-\d{2})/.exec(value);

    return match ? match[1] : value;
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

function genderLabelFor(gender: number | null | undefined, i18n: ReturnType<typeof t>): string {
    if (gender === 1) {
        return i18n.students.male;
    }

    if (gender === 2) {
        return i18n.students.female;
    }

    return '—';
}

function CellScroll({ children }: { children: ReactNode }) {
    return <div className="sis-students-table__cell-scroll">{children}</div>;
}

function HighlightedText({ text, query }: { text: string; query: string }) {
    const tokens = query
        .trim()
        .split(/\s+/)
        .map((token) => token.trim())
        .filter((token) => token.length > 0);

    if (text === '' || tokens.length === 0) {
        return <>{text}</>;
    }

    const pattern = tokens
        .map((token) => token.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'))
        .join('|');
    const matcher = new RegExp(`(${pattern})`, 'giu');
    const parts = text.split(matcher);

    return (
        <>
            {parts
                .filter((part) => part !== '')
                .map((part, segmentIndex) => {
                    const hit = tokens.some((token) =>
                        part.toLocaleLowerCase('ar').includes(token.toLocaleLowerCase('ar')),
                    );

                    return hit ? (
                        <mark key={`hit-${segmentIndex}`} className="sis-admission-search-hit">
                            {part}
                        </mark>
                    ) : (
                        <span key={`plain-${segmentIndex}`}>{part}</span>
                    );
                })}
        </>
    );
}

function resolveGradeLevelId(
    row: EnrollmentListItem,
    filterOptions: EnrollmentFilterOptions,
): string {
    const fromClass = filterOptions.classes.find((item) => item.id === row.class_id);
    if (fromClass?.grade_level_id) {
        return String(fromClass.grade_level_id);
    }

    const levels = filterOptions.grade_levels ?? [];
    const byCode = levels.find((item) => item.code === row.grade_level_code);
    if (byCode) {
        return String(byCode.id);
    }

    const byName = levels.find((item) => item.name === row.grade_level_name);
    if (byName) {
        return String(byName.id);
    }

    return '';
}

function SelectCell({
    value,
    label,
    options,
    onChange,
}: {
    value: string;
    label: string;
    options: Array<{ value: string; label: string }>;
    onChange: (value: string) => void;
}) {
    return (
        <div
            onClick={(event) => event.stopPropagation()}
            onPointerDown={(event) => event.stopPropagation()}
        >
            <SisListSelect
                value={value}
                options={options}
                onChange={onChange}
                triggerClassName="sis-students-table__edit-input"
                dir="rtl"
                ariaLabel={label}
            />
        </div>
    );
}

export const EnrollmentEditorRow = forwardRef<EnrollmentRowHandle, EnrollmentEditorRowProps>(
    function EnrollmentEditorRow(
        {
            row,
            rowNumber,
            canSelect,
            selected,
            checked,
            editing,
            busy,
            search,
            filterOptions,
            onSelect,
            onToggleChecked,
            onRegisterSave,
        },
        ref,
    ) {
        const i18n = t();
        const { showError, showInertiaErrors } = usePageError();
        const { academicYears } = usePage().props as { academicYears?: YearOption[] };
        const years = academicYears ?? [];
        const name = studentQuadName(row);

        const [gender, setGender] = useState(
            row.student_gender === 1 || row.student_gender === 2 ? String(row.student_gender) : '',
        );
        const [classId, setClassId] = useState(
            row.class_id > 0 ? String(row.class_id) : '',
        );
        const [sectionId, setSectionId] = useState(
            row.section_id > 0 ? String(row.section_id) : '',
        );
        const [branchId, setBranchId] = useState(row.branch_id ? String(row.branch_id) : '');
        const [departmentId, setDepartmentId] = useState(
            row.department_id ? String(row.department_id) : '',
        );
        const [specializationId, setSpecializationId] = useState(
            row.specialization_id ? String(row.specialization_id) : '',
        );
        const [gradeLevelId, setGradeLevelId] = useState(resolveGradeLevelId(row, filterOptions));
        const [stageName, setStageName] = useState(row.stage_name ?? '');
        const [academicYearId, setAcademicYearId] = useState(
            row.academic_year_id > 0 ? String(row.academic_year_id) : '',
        );
        const [effectiveFrom, setEffectiveFrom] = useState(isoDate(row.effective_from));
        const [effectiveTo, setEffectiveTo] = useState(isoDate(row.effective_to));
        const [saving, setSaving] = useState(false);

        const registrationComplete = isEnrollmentRegistrationComplete(
            editing
                ? {
                      academic_year_id: academicYearId,
                      class_id: classId,
                      section_id: sectionId,
                      effective_from: effectiveFrom,
                  }
                : {
                      academic_year_id: row.academic_year_id,
                      class_id: row.class_id,
                      section_id: row.section_id,
                      effective_from: row.effective_from,
                  },
        );

        useEffect(() => {
            if (editing) {
                return;
            }

            setGender(
                row.student_gender === 1 || row.student_gender === 2
                    ? String(row.student_gender)
                    : '',
            );
            setClassId(row.class_id > 0 ? String(row.class_id) : '');
            setSectionId(row.section_id > 0 ? String(row.section_id) : '');
            setBranchId(row.branch_id ? String(row.branch_id) : '');
            setDepartmentId(row.department_id ? String(row.department_id) : '');
            setSpecializationId(row.specialization_id ? String(row.specialization_id) : '');
            setGradeLevelId(resolveGradeLevelId(row, filterOptions));
            setStageName(row.stage_name ?? '');
            setAcademicYearId(row.academic_year_id > 0 ? String(row.academic_year_id) : '');
            setEffectiveFrom(isoDate(row.effective_from));
            setEffectiveTo(isoDate(row.effective_to));
        }, [editing, filterOptions, row]);

        const filteredClasses = useMemo(() => {
            const byGrade =
                gradeLevelId === ''
                    ? filterOptions.classes
                    : filterOptions.classes.filter(
                          (item) =>
                              item.grade_level_id === undefined
                              || String(item.grade_level_id) === gradeLevelId,
                      );

            if (classId === '' || byGrade.some((item) => String(item.id) === classId)) {
                return byGrade;
            }

            const current = filterOptions.classes.find((item) => String(item.id) === classId);

            return current ? [current, ...byGrade] : byGrade;
        }, [classId, filterOptions.classes, gradeLevelId]);

        const filteredSections = useMemo(() => {
            const byClass =
                classId === ''
                    ? filterOptions.sections
                    : filterOptions.sections.filter(
                          (section) => String(section.class_id) === classId,
                      );

            if (sectionId === '' || byClass.some((item) => String(item.id) === sectionId)) {
                return byClass;
            }

            const current = filterOptions.sections.find((item) => String(item.id) === sectionId);

            return current ? [current, ...byClass] : byClass;
        }, [classId, filterOptions.sections, sectionId]);

        const filteredDepartments = useMemo(() => {
            if (branchId === '') {
                return filterOptions.departments;
            }

            return filterOptions.departments.filter(
                (department) =>
                    department.branch_id === null || String(department.branch_id) === branchId,
            );
        }, [branchId, filterOptions.departments]);

        const filteredSpecializations = useMemo(() => {
            if (departmentId === '') {
                return filterOptions.specializations;
            }

            return filterOptions.specializations.filter(
                (item) =>
                    item.department_id === null || String(item.department_id) === departmentId,
            );
        }, [departmentId, filterOptions.specializations]);

        const yearOptions = useMemo(() => {
            const options = years.map((year) => ({
                value: String(year.id),
                label: formatAcademicYearOptionLabel(year.name, year.code),
            }));
            const currentId = String(row.academic_year_id);
            if (currentId !== '' && !options.some((option) => option.value === currentId)) {
                options.unshift({
                    value: currentId,
                    label:
                        formatAcademicYearOptionLabel(
                            row.academic_year_name ?? '',
                            row.academic_year_code ?? '',
                        ) || currentId,
                });
            }

            return options;
        }, [row.academic_year_code, row.academic_year_id, row.academic_year_name, years]);

        const yearDisplay =
            formatAcademicYearOptionLabel(
                row.academic_year_name
                    ?? years.find((year) => year.id === row.academic_year_id)?.name
                    ?? '',
                row.academic_year_code
                    ?? years.find((year) => year.id === row.academic_year_id)?.code
                    ?? '',
            ) || textOrDash(row.academic_year_id);

        const save = useCallback((): Promise<void> => {
            const resolvedClassId =
                classId !== '' ? classId : String(row.class_id > 0 ? row.class_id : '');
            let resolvedSectionId =
                sectionId !== '' ? sectionId : String(row.section_id > 0 ? row.section_id : '');

            if (resolvedClassId !== '' && resolvedSectionId === '') {
                const firstSection = filterOptions.sections.find(
                    (section) => String(section.class_id) === resolvedClassId,
                );
                resolvedSectionId = firstSection ? String(firstSection.id) : '';
            }

            if (resolvedClassId === '' || resolvedSectionId === '') {
                showError(i18n.errors.missingClassSection);
                return Promise.reject(new Error('enrollment-row-invalid'));
            }

            setSaving(true);
            const payload = {
                class_id: Number(resolvedClassId),
                section_id: Number(resolvedSectionId),
                specialization_id:
                    specializationId === '' ? null : Number(specializationId),
                branch_id: branchId === '' ? null : Number(branchId),
                department_id: departmentId === '' ? null : Number(departmentId),
                academic_year_id: Number(academicYearId) || row.academic_year_id,
                stage_name: stageName.trim() === '' ? null : stageName.trim(),
                ...(gender === '1' || gender === '2' ? { gender: Number(gender) } : {}),
                effective_from: effectiveFrom || row.effective_from,
                ...(effectiveTo === ''
                    ? { clear_effective_to: true }
                    : { effective_to: effectiveTo }),
            };

            return new Promise<void>((resolve, reject) => {
                router.put(`/enrollments/${row.id}`, payload, {
                    preserveScroll: true,
                    preserveState: true,
                    onSuccess: () => resolve(),
                    onError: (errors) => {
                        showInertiaErrors(errors, i18n.errors.placementFailed);
                        reject(new Error('enrollment-row-save-failed'));
                    },
                    onFinish: () => setSaving(false),
                });
            });
        }, [
            academicYearId,
            branchId,
            classId,
            departmentId,
            effectiveFrom,
            effectiveTo,
            filterOptions.sections,
            gender,
            i18n.errors.missingClassSection,
            i18n.errors.placementFailed,
            row.academic_year_id,
            row.class_id,
            row.effective_from,
            row.id,
            row.section_id,
            sectionId,
            showError,
            showInertiaErrors,
            specializationId,
            stageName,
        ]);

        useImperativeHandle(ref, () => ({ save }), [save]);

        useEffect(() => {
            onRegisterSave(row.id, save);

            return () => onRegisterSave(row.id, null);
        }, [onRegisterSave, row.id, save]);

        return (
            <tr
                className={
                    selected || checked ? 'sis-admission-periods-table__row--selected' : undefined
                }
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
                            disabled={busy || saving}
                            aria-label={`${i18n.enrollments.selectEnrollment}: ${name}`}
                            onClick={(event) => event.stopPropagation()}
                            onChange={() => onToggleChecked(row.id)}
                        />
                    </td>
                ) : null}
                <td className="sis-admission-drafts-table__num">
                    <span dir="ltr">{rowNumber}</span>
                </td>
                <td className="sis-admission-drafts-table__name">
                    <CellScroll>
                        <HighlightedText text={name} query={search} />
                    </CellScroll>
                </td>
                <td className="sis-admission-drafts-table__text sis-students-table__nowrap">
                    <span dir="ltr">{textOrDash(row.student_code)}</span>
                </td>
                <td className="sis-admission-drafts-table__text">
                    {editing ? (
                        <SelectCell
                            value={gender}
                            label={i18n.enrollments.gender}
                            options={[
                                { value: '', label: i18n.enrollments.gender },
                                { value: '1', label: i18n.students.male },
                                { value: '2', label: i18n.students.female },
                            ]}
                            onChange={setGender}
                        />
                    ) : (
                        genderLabelFor(row.student_gender, i18n)
                    )}
                </td>
                <td className="sis-admission-drafts-table__text">
                    {editing ? (
                        <SelectCell
                            value={academicYearId}
                            label={i18n.enrollments.academicYear}
                            options={[
                                { value: '', label: i18n.enrollments.academicYear },
                                ...yearOptions,
                            ]}
                            onChange={setAcademicYearId}
                        />
                    ) : (
                        <CellScroll>{yearDisplay}</CellScroll>
                    )}
                </td>
                <td className="sis-admission-drafts-table__text">
                    {editing ? (
                        <SelectCell
                            value={classId}
                            label={i18n.enrollments.className}
                            options={[
                                { value: '', label: i18n.enrollments.className },
                                ...filteredClasses.map((item) => ({
                                    value: String(item.id),
                                    label: item.name,
                                })),
                            ]}
                            onChange={(next) => {
                                const selectedClass = filterOptions.classes.find(
                                    (item) => String(item.id) === next,
                                );
                                setClassId(next);
                                const firstSection =
                                    next === ''
                                        ? undefined
                                        : filterOptions.sections.find(
                                              (section) => String(section.class_id) === next,
                                          );
                                setSectionId(firstSection ? String(firstSection.id) : '');
                                if (selectedClass?.grade_level_id) {
                                    setGradeLevelId(String(selectedClass.grade_level_id));
                                }
                            }}
                        />
                    ) : (
                        <CellScroll>{textOrDash(row.class_name ?? row.class_code)}</CellScroll>
                    )}
                </td>
                <td className="sis-admission-drafts-table__text">
                    {editing ? (
                        <SelectCell
                            value={sectionId}
                            label={i18n.enrollments.sectionName}
                            options={[
                                { value: '', label: i18n.enrollments.sectionName },
                                ...filteredSections.map((item) => ({
                                    value: String(item.id),
                                    label: item.name,
                                })),
                            ]}
                            onChange={setSectionId}
                        />
                    ) : (
                        <CellScroll>{textOrDash(row.section_name ?? row.section_code)}</CellScroll>
                    )}
                </td>
                <td className="sis-admission-drafts-table__text">
                    {editing ? (
                        <SelectCell
                            value={branchId}
                            label={i18n.enrollments.branchName}
                            options={[
                                { value: '', label: i18n.enrollments.allBranches },
                                ...filterOptions.branches.map((item) => ({
                                    value: String(item.id),
                                    label: item.name,
                                })),
                            ]}
                            onChange={(next) => {
                                setBranchId(next);
                                setDepartmentId('');
                                setSpecializationId('');
                            }}
                        />
                    ) : (
                        <CellScroll>{textOrDash(row.branch_name ?? row.branch_code)}</CellScroll>
                    )}
                </td>
                <td className="sis-admission-drafts-table__text">
                    {editing ? (
                        <SelectCell
                            value={departmentId}
                            label={i18n.enrollments.departmentName}
                            options={[
                                { value: '', label: i18n.enrollments.allDepartments },
                                ...filteredDepartments.map((item) => ({
                                    value: String(item.id),
                                    label: item.name,
                                })),
                            ]}
                            onChange={(next) => {
                                setDepartmentId(next);
                                setSpecializationId('');
                            }}
                        />
                    ) : (
                        <CellScroll>{textOrDash(row.department_name)}</CellScroll>
                    )}
                </td>
                <td className="sis-admission-drafts-table__text">
                    {editing ? (
                        <SelectCell
                            value={specializationId}
                            label={i18n.enrollments.specialization}
                            options={[
                                { value: '', label: i18n.enrollments.allSpecializations },
                                ...filteredSpecializations.map((item) => ({
                                    value: String(item.id),
                                    label: item.name,
                                })),
                            ]}
                            onChange={setSpecializationId}
                        />
                    ) : (
                        <CellScroll>{textOrDash(row.specialization_name)}</CellScroll>
                    )}
                </td>
                <td className="sis-admission-drafts-table__text">
                    {editing ? (
                        <input
                            className="sis-students-table__edit-input"
                            value={stageName}
                            aria-label={i18n.enrollments.stageName}
                            onClick={(event) => event.stopPropagation()}
                            onChange={(event) => setStageName(event.target.value)}
                        />
                    ) : (
                        <CellScroll>{textOrDash(row.stage_name)}</CellScroll>
                    )}
                </td>
                <td className="sis-admission-drafts-table__text sis-students-table__nowrap">
                    {editing ? (
                        <input
                            type="date"
                            className="sis-students-table__edit-input"
                            value={effectiveFrom}
                            aria-label={i18n.enrollments.effectiveFrom}
                            dir="ltr"
                            onClick={(event) => event.stopPropagation()}
                            onChange={(event) => setEffectiveFrom(event.target.value)}
                        />
                    ) : (
                        <span dir="ltr">{formatCivilDate(row.effective_from)}</span>
                    )}
                </td>
                <td className="sis-admission-drafts-table__text sis-students-table__nowrap">
                    {editing ? (
                        <input
                            type="date"
                            className="sis-students-table__edit-input"
                            value={effectiveTo}
                            aria-label={i18n.enrollments.effectiveTo}
                            dir="ltr"
                            onClick={(event) => event.stopPropagation()}
                            onChange={(event) => setEffectiveTo(event.target.value)}
                        />
                    ) : (
                        <span dir="ltr">{formatCivilDate(row.effective_to)}</span>
                    )}
                </td>
                <td
                    className={
                        registrationComplete
                            ? 'sis-admission-drafts-table__enroll-action sis-admission-drafts-table__enroll-action--file-complete'
                            : 'sis-admission-drafts-table__enroll-action sis-admission-drafts-table__enroll-action--file-incomplete'
                    }
                >
                    <span className="sis-admission-enroll-status-text">
                        {registrationComplete
                            ? i18n.enrollments.registrationComplete
                            : i18n.enrollments.registrationIncomplete}
                    </span>
                </td>
            </tr>
        );
    },
);
