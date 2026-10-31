import {
    AlertTriangle,
    CheckCircle2,
    CircleDashed,
    CloudCog,
    Lock,
    LockOpen,
    XCircle,
} from 'lucide-react';
import type { ReactNode } from 'react';
import type { DomainStatus, DomainTls } from '@/types';

type Mark = { icon: ReactNode; label: string; tone: string };

const DNS: Record<DomainStatus, Mark> = {
    verified: {
        icon: <CheckCircle2 className="size-5" aria-hidden />,
        label: 'Connected',
        tone: 'text-success',
    },
    pending: {
        icon: <CircleDashed className="size-5" aria-hidden />,
        label: 'Waiting for DNS',
        tone: 'text-base-content/60',
    },
    misconfigured: {
        icon: <AlertTriangle className="size-5" aria-hidden />,
        label: 'Needs attention',
        tone: 'text-warning',
    },
    proxied: {
        icon: <CloudCog className="size-5" aria-hidden />,
        label: 'Behind a proxy',
        tone: 'text-info',
    },
};

const TLS: Record<DomainTls, Mark> = {
    secured: {
        icon: <Lock className="size-5" aria-hidden />,
        label: 'HTTPS',
        tone: 'text-success',
    },
    pending: {
        icon: <CircleDashed className="size-5 animate-pulse" aria-hidden />,
        label: 'Requesting',
        tone: 'text-info',
    },
    failed: {
        icon: <XCircle className="size-5" aria-hidden />,
        label: 'Failed',
        tone: 'text-error',
    },
    none: {
        icon: <LockOpen className="size-5" aria-hidden />,
        label: 'No HTTPS',
        tone: 'text-base-content/60',
    },
};

function Shown({ mark }: { mark: Mark }) {
    return (
        <span className={`inline-flex items-center gap-2 ${mark.tone}`}>
            {mark.icon}
            <span className="text-sm font-medium whitespace-nowrap">
                {mark.label}
            </span>
        </span>
    );
}

/** DNS state with an icon and a word, so colour is never the only signal. */
export function DomainStatusMark({ status }: { status: DomainStatus }) {
    return <Shown mark={DNS[status]} />;
}

export function DomainTlsMark({ tls }: { tls: DomainTls }) {
    return <Shown mark={TLS[tls]} />;
}
