export interface User {
    id: number;
    name: string;
    email: string;
    email_verified_at: string | null;
    is_platform_admin: boolean;
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
    /** Unread notifications in the current school (navigation badge). */
    unreadNotifications: number;
    flash: {
        status: string | null;
    };
};
