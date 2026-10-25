export interface User {
    id: number;
    name: string;
    email: string;
    email_verified_at: string | null;
    is_platform_admin: boolean;
    /** Their own language; null follows the school's. */
    locale: string | null;
}

export interface CurrentSchool {
    id: number;
    name: string;
    setup_completed: boolean;
    currency: string;
    locale: string;
    roles: string[];
    permissions: string[];
}

export interface SchoolSummary {
    id: number;
    name: string;
}

export interface Option {
    value: string;
    label: string;
}

export type PageProps<
    T extends Record<string, unknown> = Record<string, unknown>,
> = T & {
    auth: {
        user: User;
    };
    school: CurrentSchool | null;
    schools: SchoolSummary[];
    /** The language of this request (en, de, hi). */
    locale: string;
    /** Unread notifications in the current school (navigation badge). */
    unreadNotifications: number;
    flash: {
        status: string | null;
    };
};
