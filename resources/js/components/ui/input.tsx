import type { InputHTMLAttributes } from 'react';
import { useId } from 'react';
import { cn } from '@/lib/utils';

type Props = InputHTMLAttributes<HTMLInputElement> & {
    label: string;
    error?: string;
    hint?: string;
};

export function Input({ label, error, hint, className, id, ...props }: Props) {
    const generated = useId();
    const inputId = id ?? generated;
    const describedBy = error || hint ? `${inputId}-help` : undefined;

    return (
        <div className="w-full">
            <label className="mb-1 label-text block px-0!" htmlFor={inputId}>
                {label}
            </label>
            <input
                id={inputId}
                className={cn('input w-full', error && 'is-invalid', className)}
                aria-invalid={error ? true : undefined}
                aria-describedby={describedBy}
                {...props}
            />
            {(error || hint) && (
                <span
                    id={describedBy}
                    className={cn(
                        'mt-1 block text-sm',
                        error ? 'text-error' : 'text-base-content/60',
                    )}
                >
                    {error ?? hint}
                </span>
            )}
        </div>
    );
}
