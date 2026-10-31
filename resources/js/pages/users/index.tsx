import { Link, router, usePage } from '@inertiajs/react';
import { Lock, Pencil, Plus, ShieldCheck, Trash2 } from 'lucide-react';
import { useState } from 'react';
import UserController from '@/actions/App/Http/Controllers/UserController';
import { PageHeader } from '@/components/page-header';
import { Alert } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { ConfirmModal } from '@/components/ui/confirm-modal';
import AppLayout from '@/layouts/app-layout';
import { formatDate } from '@/lib/format';
import { useCan } from '@/lib/permissions';

type Row = {
    id: number;
    name: string;
    email: string;
    is_super: boolean;
    is_you: boolean;
    role: string | null;
    two_factor: boolean;
    created_at: string | null;
    can_edit: boolean;
    can_delete: boolean;
};

export default function Index({ users }: { users: Row[] }) {
    const can = useCan();
    const { errors } = usePage().props;
    const [deleting, setDeleting] = useState<Row | null>(null);
    const [busy, setBusy] = useState(false);

    const remove = () => {
        if (!deleting) return;
        router.delete(UserController.destroy(deleting.id), {
            preserveScroll: true,
            onStart: () => setBusy(true),
            onFinish: () => {
                setBusy(false);
                setDeleting(null);
            },
        });
    };

    return (
        <AppLayout title="Users">
            <PageHeader
                title="Users"
                description="Everyone who can sign in to this panel, and the role that decides what they may do."
                actions={
                    can('users.create') && (
                        <Link
                            href={UserController.create()}
                            className="btn btn-primary"
                        >
                            <Plus className="size-4" aria-hidden />
                            New user
                        </Link>
                    )
                }
            />

            {errors.user && <Alert variant="error">{errors.user}</Alert>}

            <Card flush>
                <div className="overflow-x-auto">
                    <table className="table">
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Role</th>
                                <th>Two-factor</th>
                                <th>Added</th>
                                <th />
                            </tr>
                        </thead>
                        <tbody>
                            {users.map((user) => (
                                <tr key={user.id}>
                                    <td className="min-w-48">
                                        <div className="font-medium">
                                            {user.name}
                                            {user.is_you && (
                                                <span className="ml-2 text-xs font-normal text-base-content/60">
                                                    (you)
                                                </span>
                                            )}
                                        </div>
                                        <div className="text-sm break-all text-base-content/70">
                                            {user.email}
                                        </div>
                                    </td>
                                    <td>
                                        {user.is_super ? (
                                            <span
                                                className="badge-soft badge badge-primary"
                                                title="Can do everything. Cannot be changed or deleted here; use admin:reset on the server to recover it."
                                            >
                                                <ShieldCheck
                                                    className="size-3.5"
                                                    aria-hidden
                                                />
                                                Super user
                                            </span>
                                        ) : user.role ? (
                                            <span className="badge-soft badge-neutral badge">
                                                {user.role}
                                            </span>
                                        ) : (
                                            <span className="text-sm text-base-content/60">
                                                No role
                                            </span>
                                        )}
                                    </td>
                                    <td className="text-sm whitespace-nowrap">
                                        {user.two_factor ? 'On' : 'Off'}
                                    </td>
                                    <td className="whitespace-nowrap">
                                        {formatDate(user.created_at)}
                                    </td>
                                    <td className="text-right whitespace-nowrap">
                                        {user.is_super ? (
                                            <span
                                                className="inline-flex items-center gap-1 text-xs text-base-content/60"
                                                title="Protected"
                                            >
                                                <Lock
                                                    className="size-3.5"
                                                    aria-hidden
                                                />
                                                Protected
                                            </span>
                                        ) : (
                                            <div className="flex justify-end gap-1">
                                                {user.can_edit &&
                                                    can('users.update') && (
                                                        <Link
                                                            href={UserController.edit(
                                                                user.id,
                                                            )}
                                                            className="btn btn-soft btn-sm"
                                                        >
                                                            <Pencil
                                                                className="size-4"
                                                                aria-hidden
                                                            />
                                                            Edit
                                                        </Link>
                                                    )}
                                                {user.can_delete &&
                                                    can('users.delete') && (
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                            aria-label={`Delete ${user.name}`}
                                                            onClick={() =>
                                                                setDeleting(
                                                                    user,
                                                                )
                                                            }
                                                        >
                                                            <Trash2
                                                                className="size-4"
                                                                aria-hidden
                                                            />
                                                        </Button>
                                                    )}
                                            </div>
                                        )}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </Card>

            <ConfirmModal
                open={deleting !== null}
                title={`Delete ${deleting?.name ?? ''}?`}
                confirmLabel="Delete user"
                loading={busy}
                onConfirm={remove}
                onClose={() => setDeleting(null)}
            >
                {deleting?.email} loses access right away and is signed out
                everywhere. This cannot be undone.
            </ConfirmModal>
        </AppLayout>
    );
}
