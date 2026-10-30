import { Head, Link, router, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { fortify } from '@/lib/fortify';

type Props = { title: string; children: ReactNode };

const links = [
    { href: '/dashboard', label: 'Dashboard' },
    { href: '/settings/security', label: 'Security' },
];

export default function AppLayout({ title, children }: Props) {
    const { name, auth } = usePage().props;
    const { url } = usePage();

    return (
        <>
            <Head title={title} />
            <div className="min-h-screen bg-base-200">
                <header className="bg-base-100 shadow-sm shadow-base-300/20">
                    <div className="mx-auto flex max-w-6xl items-center gap-6 px-4 py-3">
                        <span className="font-semibold text-primary">
                            {name}
                        </span>
                        <nav
                            className="flex flex-1 gap-1"
                            aria-label="Main navigation"
                        >
                            {links.map((link) => (
                                <Link
                                    key={link.href}
                                    href={link.href}
                                    className={cn(
                                        'btn btn-text btn-sm',
                                        url.startsWith(link.href) &&
                                            'btn-active',
                                    )}
                                >
                                    {link.label}
                                </Link>
                            ))}
                        </nav>
                        <span className="hidden text-sm text-base-content/70 sm:inline">
                            {auth.user.email}
                        </span>
                        <Button
                            variant="soft"
                            size="sm"
                            onClick={() => router.post(fortify.logout)}
                        >
                            Log out
                        </Button>
                    </div>
                </header>
                <main className="mx-auto max-w-6xl space-y-6 px-4 py-8">
                    {children}
                </main>
            </div>
        </>
    );
}
