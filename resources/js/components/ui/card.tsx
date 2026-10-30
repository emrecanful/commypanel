import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

type Props = {
    title?: string;
    description?: string;
    actions?: ReactNode;
    children: ReactNode;
    className?: string;
    /** Remove the body padding, e.g. for a table that spans the card. */
    flush?: boolean;
};

export function Card({
    title,
    description,
    actions,
    children,
    className,
    flush = false,
}: Props) {
    return (
        <section
            className={cn(
                'w-full min-w-0 overflow-hidden rounded-box border border-base-300 bg-base-100',
                className,
            )}
        >
            {(title || actions) && (
                <header className="flex flex-wrap items-start justify-between gap-3 border-b border-base-300 px-5 py-4 sm:px-6">
                    <div className="min-w-0 space-y-1">
                        {title && (
                            <h2 className="text-base font-semibold">{title}</h2>
                        )}
                        {description && (
                            <p className="text-sm text-base-content/70">
                                {description}
                            </p>
                        )}
                    </div>
                    {actions}
                </header>
            )}
            <div className={cn(!flush && 'space-y-4 p-5 sm:p-6')}>
                {children}
            </div>
        </section>
    );
}
