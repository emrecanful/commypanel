import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

type Props = {
    variant?: 'info' | 'success' | 'warning' | 'error';
    children: ReactNode;
    className?: string;
};

const variants = {
    info: 'alert-info',
    success: 'alert-success',
    warning: 'alert-warning',
    error: 'alert-error',
};

export function Alert({ variant = 'info', children, className }: Props) {
    return (
        <div
            role={variant === 'error' ? 'alert' : 'status'}
            className={cn('alert-soft alert', variants[variant], className)}
        >
            {children}
        </div>
    );
}
