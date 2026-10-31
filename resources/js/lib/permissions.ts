import { usePage } from '@inertiajs/react';

/** Whether the signed-in user holds a permission (the server enforces it; this only hides what would be refused). */
export function useCan(): (permission: string) => boolean {
    const { permissions } = usePage().props.auth;

    return (permission) => permissions.includes(permission);
}
