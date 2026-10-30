import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AuthLayout from '@/layouts/auth-layout';
import { fortify } from '@/lib/fortify';

export default function ConfirmPassword() {
    const form = useForm({ password: '' });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(fortify.confirmPassword, {
            onFinish: () => form.reset('password'),
        });
    };

    return (
        <AuthLayout
            title="Confirm your password"
            description="This area is sensitive. Please confirm your password to continue."
        >
            <form onSubmit={submit} className="space-y-4">
                <Input
                    label="Password"
                    type="password"
                    autoComplete="current-password"
                    autoFocus
                    required
                    value={form.data.password}
                    onChange={(e) => form.setData('password', e.target.value)}
                    error={form.errors.password}
                />
                <Button
                    type="submit"
                    className="w-full"
                    loading={form.processing}
                >
                    Confirm
                </Button>
            </form>
        </AuthLayout>
    );
}
