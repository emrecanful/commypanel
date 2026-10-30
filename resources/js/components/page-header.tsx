import type { ReactNode } from 'react';

type Props = {
    title: string;
    description?: string;
    badge?: ReactNode;
    actions?: ReactNode;
};

export function PageHeader({ title, description, badge, actions }: Props) {
    return (
        <div className="flex flex-wrap items-start justify-between gap-4">
            <div className="min-w-0 space-y-1">
                <div className="flex flex-wrap items-center gap-3">
                    <h1 className="text-2xl font-semibold tracking-tight">
                        {title}
                    </h1>
                    {badge}
                </div>
                {description && (
                    <p className="text-sm text-base-content/70">
                        {description}
                    </p>
                )}
            </div>
            {actions && (
                <div className="flex flex-wrap items-center gap-2">
                    {actions}
                </div>
            )}
        </div>
    );
}
