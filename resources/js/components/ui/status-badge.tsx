import type { AppStatus, DeploymentStatus, DomainStatus } from '@/types';
import { cn } from '@/lib/utils';

const styles: Record<AppStatus | DeploymentStatus | DomainStatus, string> = {
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
    pending: 'badge-neutral',
    verified: 'badge-success',
    misconfigured: 'badge-warning',
    proxied: 'badge-info',
};

const labels: Partial<
    Record<AppStatus | DeploymentStatus | DomainStatus, string>
> = {
    active: 'live',
    verified: 'connected',
    misconfigured: 'needs attention',
};

type Props = {
    status: AppStatus | DeploymentStatus | DomainStatus;
    className?: string;
};

export function StatusBadge({ status, className }: Props) {
    return (
        <span className={cn('badge-soft badge', styles[status], className)}>
            {labels[status] ?? status}
        </span>
    );
}
