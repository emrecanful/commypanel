import type { AppStatus, DeploymentStatus } from '@/types';
import { cn } from '@/lib/utils';

const styles: Record<AppStatus | DeploymentStatus, string> = {
    new: 'badge-neutral',
    deploying: 'badge-info',
    running: 'badge-success',
    failed: 'badge-error',
    queued: 'badge-neutral',
    building: 'badge-info',
    starting: 'badge-info',
    active: 'badge-success',
    superseded: 'badge-neutral',
    stopped: 'badge-warning',
};

const labels: Partial<Record<AppStatus | DeploymentStatus, string>> = {
    active: 'live',
};

type Props = { status: AppStatus | DeploymentStatus; className?: string };

export function StatusBadge({ status, className }: Props) {
    return (
        <span className={cn('badge-soft badge', styles[status], className)}>
            {labels[status] ?? status}
        </span>
    );
}
