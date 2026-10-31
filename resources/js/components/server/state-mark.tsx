import {
    CheckCircle2,
    CircleDashed,
    CircleHelp,
    Loader2,
    XCircle,
} from 'lucide-react';
import type { ReactNode } from 'react';

type Tone = 'good' | 'bad' | 'idle' | 'busy' | 'unknown';

const TONES: Record<Tone, { icon: ReactNode; tone: string }> = {
    good: {
        icon: <CheckCircle2 className="size-5" aria-hidden />,
        tone: 'text-success',
    },
    bad: {
        icon: <XCircle className="size-5" aria-hidden />,
        tone: 'text-error',
    },
    idle: {
        icon: <CircleDashed className="size-5" aria-hidden />,
        tone: 'text-base-content/60',
    },
    busy: {
        icon: <Loader2 className="size-5 animate-spin" aria-hidden />,
        tone: 'text-info',
    },
    unknown: {
        icon: <CircleHelp className="size-5" aria-hidden />,
        tone: 'text-base-content/60',
    },
};

export function toneOfService(state: string): Tone {
    if (state === 'active') return 'good';
    if (state === 'failed') return 'bad';
    if (state === 'activating' || state === 'reloading') return 'busy';
    if (state === 'inactive' || state === 'deactivating') return 'idle';

    return 'unknown';
}

export function toneOfContainer(state: string): Tone {
    if (state === 'running') return 'good';
    if (state === 'dead' || state === 'oomkilled') return 'bad';
    if (state === 'restarting' || state === 'created') return 'busy';
    if (state === 'exited' || state === 'paused') return 'idle';

    return 'unknown';
}

/** An icon and a word, so colour is never the only signal. */
export function StateMark({ tone, label }: { tone: Tone; label: string }) {
    const mark = TONES[tone];

    return (
        <span className={`inline-flex items-center gap-2 ${mark.tone}`}>
            {mark.icon}
            <span className="text-sm font-medium whitespace-nowrap">
                {label}
            </span>
        </span>
    );
}
