export type EnrollmentListItem = {
    id: number;
    student_id: number;
    school_id: number;
    academic_year_id: number;
    academic_year_name?: string | null;
    academic_year_code?: string | null;
    class_id: number;
    section_id: number;
    enrollment_number: string;
    status: number;
    effective_from: string;
    effective_to: string | null;
    specialization_id?: number | null;
    branch_id?: number | null;
    department_id?: number | null;
    student_code?: string | null;
    student_full_name?: string | null;
    student_first_name?: string | null;
    student_father_name?: string | null;
    student_grandfather_name?: string | null;
    student_great_grandfather_name?: string | null;
    student_last_name?: string | null;
    student_gender?: number | null;
    student_birth_date?: string | null;
    student_status?: number | null;
    class_code?: string | null;
    class_name?: string | null;
    section_code?: string | null;
    section_name?: string | null;
    specialization_code?: string | null;
    specialization_name?: string | null;
    branch_code?: string | null;
    branch_name?: string | null;
    grade_level_code?: string | null;
    grade_level_name?: string | null;
    department_name?: string | null;
    stage_name?: string | null;
};

export type EnrollmentFilterOptions = {
    branches: Array<{ id: number; code: string; name: string }>;
    classes: Array<{ id: number; code: string; name: string; grade_level_id?: number }>;
    sections: Array<{ id: number; class_id: number; code: string; name: string }>;
    departments: Array<{ id: number; branch_id: number | null; code: string; name: string }>;
    specializations: Array<{
        id: number;
        department_id: number | null;
        code: string;
        name: string;
    }>;
    grade_levels?: Array<{
        id: number;
        code: string;
        name: string;
        education_stage: number;
    }>;
};
