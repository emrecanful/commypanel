import { Server } from 'lucide-react';
import { cn } from '@/lib/utils';

type Props = { name: string; showName?: boolean; className?: string };

export function Brand({ name, showName = true, className }: Props) {
    return (
        <span className={cn('flex items-center gap-3', className)}>
            <span className="flex size-9 shrink-0 items-center justify-center rounded-field bg-primary text-primary-content">
                <Server className="size-5" aria-hidden />
            </span>
            {showName ? (
                <span className="text-lg font-semibold tracking-tight">
                    {name}
                </span>
            ) : (
                <span className="sr-only">{name}</span>
            )}
        </span>
    );
}
