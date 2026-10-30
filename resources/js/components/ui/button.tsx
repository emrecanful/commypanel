import type { ButtonHTMLAttributes } from 'react';
import { cn } from '@/lib/utils';

type Variant =
    | 'primary'
    | 'secondary'
    | 'accent'
    | 'success'
    | 'warning'
    | 'error'
    | 'ghost'
    | 'soft';
type Size = 'sm' | 'md' | 'lg';

const variants: Record<Variant, string> = {
    primary: 'btn-primary',
    secondary: 'btn-secondary',
    accent: 'btn-accent',
    success: 'btn-success',
    warning: 'btn-warning',
    error: 'btn-error',
    ghost: 'btn-text',
    soft: 'btn-soft',
};

const sizes: Record<Size, string> = {
    sm: 'btn-sm',
    md: '',
    lg: 'btn-lg',
};

type Props = ButtonHTMLAttributes<HTMLButtonElement> & {
    variant?: Variant;
    size?: Size;
    loading?: boolean;
};

export function Button({
    variant = 'primary',
    size = 'md',
    loading = false,
    className,
    children,
    disabled,
    type = 'button',
    ...props
}: Props) {
    return (
        <button
            type={type}
            className={cn('btn', variants[variant], sizes[size], className)}
            disabled={disabled || loading}
            {...props}
        >
            {loading && (
                <span
                    className="loading loading-xs loading-spinner"
                    aria-hidden
                />
            )}
            {children}
        </button>
    );
}
