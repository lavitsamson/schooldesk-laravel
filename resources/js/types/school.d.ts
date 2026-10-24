export interface IdName {
    id: number;
    name: string;
}

/** A Laravel paginated API resource collection. */
export interface Paginated<T> {
    data: T[];
    meta: {
        current_page: number;
        last_page: number;
        from: number | null;
        to: number | null;
        total: number;
        links: { url: string | null; label: string; active: boolean }[];
    };
}

export interface Term {
    id: number | null;
    name: string;
    starts_on: string;
    ends_on: string;
}

export interface AcademicYear {
    id: number;
    name: string;
    starts_on: string;
    ends_on: string;
    is_current: boolean;
    terms: Term[];
    classes_count: number;
    enrollments_count: number;
    can: { update: boolean; delete: boolean };
}

export interface SchoolClassRow {
    id: number;
    academic_year_id: number;
    name: string;
    section: string | null;
    display_name: string;
    class_teacher: IdName | null;
    students_count?: number;
    subjects_count?: number;
}

export interface StudentRow {
    id: number;
    admission_no: string;
    first_name: string;
    last_name: string;
    name: string;
    status: 'active' | 'left' | 'graduated';
    status_label: string;
    class: { id: number; name: string; roll_no: number | null } | null;
    photo_url: string | null;
}

export interface StudentProfile extends StudentRow {
    date_of_birth: string;
    gender: string | null;
    gender_label: string | null;
    address: string | null;
    can: { update: boolean; delete: boolean };
}

export interface Guardian {
    id: number;
    name: string;
    email: string | null;
    phone: string | null;
    relationship: string;
    relationship_label: string;
    has_portal_access: boolean;
    is_primary?: boolean;
    students?: IdName[];
    can: { update: boolean; invite: boolean; delete: boolean };
}

export interface Enrollment {
    id: number;
    academic_year: string;
    is_current_year: boolean;
    class: IdName;
    roll_no: number | null;
    enrolled_on: string;
    left_on: string | null;
}

export interface StudentFormData {
    admission_no: string;
    first_name: string;
    last_name: string;
    date_of_birth: string;
    gender: string | null;
    address: string;
    photo: File | null;
    class_id: number | null;
    status: string;
    remove_photo: boolean;
}

export interface ImportBatch {
    id: number;
    type_label: string;
    original_name: string;
    status: 'pending' | 'processing' | 'completed' | 'failed';
    status_label: string;
    is_finished: boolean;
    progress: number;
    total_rows: number;
    processed_rows: number;
    failed_rows: number;
    created_rows: number | null;
    updated_rows: number | null;
    failure_message: string | null;
    error_report_url: string | null;
    uploaded_by: string | null;
    created_at: string;
    finished_at: string | null;
}

export interface ImportColumn {
    name: string;
    required: boolean;
    description: string;
}

export type AttendanceStatus = 'present' | 'absent' | 'late' | 'excused';

export interface AttendanceClass {
    id: number;
    name: string;
    students_count: number;
    recorded_count: number;
    is_class_teacher: boolean;
}

export interface AttendanceStudent {
    id: number;
    roll_no: number | null;
    name: string;
    admission_no: string;
    status: AttendanceStatus | null;
    note: string | null;
}

export interface AttendanceCounts {
    present: number;
    absent: number;
    late: number;
    excused: number;
}

export interface AttendanceReport {
    days: string[];
    students: {
        id: number;
        roll_no: number | null;
        name: string;
        admission_no: string;
        /** Date (YYYY-MM-DD) => status letter (P, A, L, E). */
        statuses: Record<string, string>;
        totals: AttendanceCounts;
        rate: number | null;
    }[];
    daily: (AttendanceCounts & { date: string })[];
    totals: AttendanceCounts & { rate: number | null };
}

export interface ImportFailure {
    row: number;
    admission_no: string | null;
    name: string;
    errors: string[];
}

export interface TermOption {
    id: number;
    name: string;
    starts_on: string;
    ends_on: string;
}

export type AssessmentType = 'test' | 'exam' | 'assignment';

export interface GradebookClass {
    id: number;
    name: string;
    is_class_teacher: boolean;
    can_view_results: boolean;
    subjects: {
        id: number;
        name: string;
        code: string;
        teacher: string | null;
        assessments_count: number;
    }[];
}

export interface AssessmentColumn {
    id: number;
    name: string;
    type: AssessmentType;
    type_label: string;
    held_on: string;
    max_marks: number;
    weight: string;
    graded_count: number;
}

/** A percentage with two decimals ("86.50") and its grade label, or nulls without marks. */
export interface Result {
    percentage: string | null;
    grade: string | null;
}

export interface GradebookStudent extends Result {
    id: number;
    roll_no: number | null;
    name: string;
    /** Assessment id => marks. */
    marks: Record<string, string | null>;
}

export interface GradeSheetStudent {
    id: number;
    roll_no: number | null;
    name: string;
    admission_no: string;
    marks: string | null;
    remarks: string | null;
}

export interface ClassResultsStudent {
    id: number;
    name: string;
    admission_no: string;
    /** Subject id => result. */
    results: Record<string, Result>;
    overall: Result;
    attendance_rate: number | null;
}

export interface ReportCardSubject extends Result {
    id: number;
    name: string;
    code: string;
    graded: number;
    assessments_count: number;
    assessments: {
        id: number;
        name: string;
        type: AssessmentType;
        held_on: string;
        max_marks: number;
        weight: string;
        marks: string | null;
        remarks: string | null;
    }[];
}

export interface ReportCardSnapshot {
    student: {
        id: number;
        first_name: string;
        last_name: string;
        admission_no: string;
    };
    class: IdName | null;
    term: TermOption;
    subjects: ReportCardSubject[];
    overall: Result;
    attendance: AttendanceCounts & { days: number; rate: number | null };
    grade_scale: { label: string; min_percent: string; max_percent: string }[];
    calculated_at: string;
}
