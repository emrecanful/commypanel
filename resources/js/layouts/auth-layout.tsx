import { Head, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';

type Props = { title: string; description?: string; children: ReactNode };

export default function AuthLayout({ title, description, children }: Props) {
    const { name } = usePage().props;

    return (
        <>
            <Head title={title} />
            <main className="flex min-h-screen items-center justify-center bg-base-200 p-4">
                <div className="w-full max-w-sm rounded-box bg-base-100 p-8 shadow-lg shadow-base-300/20">
                    <p className="mb-6 text-center text-lg font-semibold text-primary">
                        {name}
                    </p>
                    <h1 className="text-2xl font-semibold">{title}</h1>
                    {description && (
                        <p className="mt-1 mb-6 text-sm text-base-content/70">
                            {description}
                        </p>
                    )}
                    <div className="mt-6">{children}</div>
                </div>
            </main>
        </>
    );
}
