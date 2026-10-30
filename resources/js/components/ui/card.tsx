import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

type Props = {
    title?: string;
    description?: string;
    children: ReactNode;
    className?: string;
};

export function Card({ title, description, children, className }: Props) {
    return (
        <section className={cn('card', className)}>
            <div className="card-body">
                {title && <h2 className="card-title">{title}</h2>}
                {description && (
                    <p className="text-base-content/70">{description}</p>
                )}
                {children}
            </div>
        </section>
    );
}
