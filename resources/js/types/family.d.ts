import type { AttendanceStatus, IdName } from './school';

/** One of a parent's children (ChildResource). */
export interface Child {
    id: number;
    first_name: string;
    last_name: string;
    name: string;
    admission_no: string;
    status: string;
    class: IdName | null;
}

/** A child on the family overview (BuildFamilyOverview). Amounts in minor units. */
export interface ChildOverview {
    id: number;
    first_name: string;
    name: string;
    class: string | null;
    attendance: {
        month: string;
        recorded: number;
        absent: number;
        late: number;
        rate: number | null;
    };
    invoices: { open: number; overdue: number; balance: number };
    report_card: {
        id: number;
        term: string;
        published_at: string | null;
    } | null;
}

/** A child's attendance in one month (AttendanceMonthResource). */
export interface AttendanceMonth {
    month: string;
    previous_month: string;
    next_month: string | null;
    days: { date: string; weekday: number; status: AttendanceStatus | null }[];
    totals: Record<AttendanceStatus, number>;
    recorded_days: number;
    attendance_rate: number | null;
}

/** A published report card (PublishedReportCardResource). */
export interface PublishedReportCard {
    id: number;
    student_id: number;
    term: IdName;
    class: string | null;
    overall: { percentage: string | null; grade: string | null };
    attendance_rate: number | null;
    comment: string | null;
    published_at: string | null;
    pdf_url: string;
}

/** A database notification (NotificationResource). */
export interface AppNotification {
    id: string;
    type: string;
    title: string;
    subject: {
        type: 'student' | 'report_card' | 'invoice';
        id: number;
        student_id: number | null;
    } | null;
    read_at: string | null;
    created_at: string | null;
}

/** A cursor-paginated resource collection. */
export interface CursorPaginated<T> {
    data: T[];
    links: { prev: string | null; next: string | null };
    meta: {
        per_page: number;
        next_cursor: string | null;
        prev_cursor: string | null;
    };
}
