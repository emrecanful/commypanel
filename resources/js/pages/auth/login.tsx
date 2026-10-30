import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AuthLayout from '@/layouts/auth-layout';
import { fortify } from '@/lib/fortify';

export default function Login() {
    const form = useForm({ email: '', password: '', remember: false });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(fortify.login, {
            onFinish: () => form.reset('password'),
        });
    };

    return (
        <AuthLayout title="Log in" description="Sign in to manage your server.">
            <form onSubmit={submit} className="space-y-4">
                <Input
                    label="Email"
                    type="email"
                    autoComplete="username"
                    autoFocus
                    required
                    value={form.data.email}
                    onChange={(e) => form.setData('email', e.target.value)}
                    error={form.errors.email}
                />
                <Input
                    label="Password"
                    type="password"
                    autoComplete="current-password"
                    required
                    value={form.data.password}
                    onChange={(e) => form.setData('password', e.target.value)}
                    error={form.errors.password}
                />
                <label className="flex items-center gap-2 text-sm">
                    <input
                        type="checkbox"
                        className="checkbox checkbox-sm checkbox-primary"
                        checked={form.data.remember}
                        onChange={(e) =>
                            form.setData('remember', e.target.checked)
                        }
                    />
                    Remember me
                </label>
                <Button
                    type="submit"
                    className="w-full"
                    loading={form.processing}
                >
                    Log in
                </Button>
            </form>
        </AuthLayout>
    );
}
