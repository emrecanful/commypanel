import { useId } from 'react';
import type { SelectHTMLAttributes } from 'react';
import { cn } from '@/lib/utils';

type Props = SelectHTMLAttributes<HTMLSelectElement> & {
    label: string;
    error?: string;
    hint?: string;
};

export function Select({
    label,
    error,
    hint,
    className,
    id,
    children,
    ...props
}: Props) {
    const generated = useId();
    const selectId = id ?? generated;
    const describedBy = error || hint ? `${selectId}-help` : undefined;

    return (
        <div className="w-full">
            <label className="mb-1 label-text block" htmlFor={selectId}>
                {label}
            </label>
            <select
                id={selectId}
                className={cn(
                    'select w-full',
                    error && 'is-invalid',
                    className,
                )}
                aria-invalid={error ? true : undefined}
                aria-describedby={describedBy}
                {...props}
            >
                {children}
            </select>
            {(error || hint) && (
                <span
                    id={describedBy}
                    className={cn(
                        'mt-1 block text-sm',
                        error ? 'text-error' : 'text-base-content/70',
                    )}
                >
                    {error ?? hint}
                </span>
            )}
        </div>
    );
}
