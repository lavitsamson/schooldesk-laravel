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
