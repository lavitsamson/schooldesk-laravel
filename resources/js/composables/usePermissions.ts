import { usePage } from '@inertiajs/vue3';

/**
 * Permission checks for showing or hiding UI. The server enforces every permission again;
 * this only avoids offering actions the user cannot take.
 */
export function usePermissions() {
    const page = usePage();

    const can = (permission: string): boolean =>
        page.props.school?.permissions.includes(permission) ?? false;

    return { can };
}
