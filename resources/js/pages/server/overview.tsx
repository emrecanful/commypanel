import { ServerLayout } from '@/components/server/server-layout';
import { StateMark, toneOfService } from '@/components/server/state-mark';
import { Card } from '@/components/ui/card';
import { formatBytes, formatUptime } from '@/lib/format';
import { cn } from '@/lib/utils';

type Props = {
    error: string | null;
    host: {
        hostname: string;
        os: string;
        kernel: string;
        arch: string;
        cpus: number;
        uptime_seconds: number;
        load: [number, number, number];
        memory: { total: number; available: number };
        disk: { total: number; free: number };
        services: { name: string; state: string }[];
        agent_version: string;
    } | null;
};

function Meter({
    label,
    used,
    total,
}: {
    label: string;
    used: number;
    total: number;
}) {
    const percent = total > 0 ? Math.round((used / total) * 100) : 0;

    return (
        <div className="space-y-1">
            <div className="flex items-baseline justify-between gap-2 text-sm">
                <span className="font-medium">{label}</span>
                <span className="text-base-content/70">
                    {formatBytes(used)} of {formatBytes(total)} ({percent}%)
                </span>
            </div>
            <progress
                className={cn(
                    'progress w-full',
                    percent >= 90
                        ? 'progress-error'
                        : percent >= 75
                          ? 'progress-warning'
                          : 'progress-primary',
                )}
                value={percent}
                max={100}
                aria-label={`${label} used`}
            />
        </div>
    );
}

function Row({ label, value }: { label: string; value: string }) {
    return (
        <div className="flex flex-wrap justify-between gap-x-4 py-2">
            <dt className="text-base-content/70">{label}</dt>
            <dd className="min-w-0 text-right font-medium break-all">
                {value || '-'}
            </dd>
        </div>
    );
}

export default function Overview({ error, host }: Props) {
    return (
        <ServerLayout active="overview" error={error}>
            {host && (
                <div className="grid items-start gap-6 xl:grid-cols-2">
                    <div className="min-w-0 space-y-6">
                        <Card title="This server">
                            <dl className="divide-y divide-base-300 text-sm">
                                <Row label="Hostname" value={host.hostname} />
                                <Row label="System" value={host.os} />
                                <Row label="Kernel" value={host.kernel} />
                                <Row
                                    label="Processor"
                                    value={`${host.cpus} ${host.cpus === 1 ? 'core' : 'cores'} (${host.arch})`}
                                />
                                <Row
                                    label="Uptime"
                                    value={formatUptime(host.uptime_seconds)}
                                />
                                <Row
                                    label="Load (1, 5, 15 min)"
                                    value={host.load
                                        .map((n) => n.toFixed(2))
                                        .join('  ')}
                                />
                                <Row
                                    label="CommyPanel agent"
                                    value={host.agent_version}
                                />
                            </dl>
                        </Card>
                        <Card title="Resources">
                            <Meter
                                label="Memory"
                                used={host.memory.total - host.memory.available}
                                total={host.memory.total}
                            />
                            <Meter
                                label="Disk"
                                used={host.disk.total - host.disk.free}
                                total={host.disk.total}
                            />
                        </Card>
                    </div>
                    <Card
                        title="Services"
                        description="The programs CommyPanel needs. A failed one is the first place to look when something stops working."
                    >
                        <ul className="divide-y divide-base-300 rounded-field border border-base-300">
                            {host.services.map((service) => (
                                <li
                                    key={service.name}
                                    className="flex flex-wrap items-center justify-between gap-2 px-3 py-2"
                                >
                                    <code className="text-sm">
                                        {service.name}
                                    </code>
                                    <StateMark
                                        tone={toneOfService(service.state)}
                                        label={service.state}
                                    />
                                </li>
                            ))}
                        </ul>
                    </Card>
                </div>
            )}
        </ServerLayout>
    );
}
