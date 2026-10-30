import { Link } from '@inertiajs/react';
import { Boxes, CheckCircle2, Loader, Plus, XCircle } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import AppController from '@/actions/App/Http/Controllers/AppController';
import DeploymentController from '@/actions/App/Http/Controllers/DeploymentController';
import { PageHeader } from '@/components/page-header';
import { Alert } from '@/components/ui/alert';
import { Card } from '@/components/ui/card';
import { StatusBadge } from '@/components/ui/status-badge';
import AppLayout from '@/layouts/app-layout';
import { formatDate } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { DeploymentStatus } from '@/types';

type Props = {
    stats: { apps: number; running: number; deploying: number; failed: number };
    server:
        | { error: string }
        | {
              error: null;
              docker: boolean;
              nginx: boolean;
              disk_free_gb: number;
          };
    recent: {
        app_slug: string;
        app_name: string;
        number: number;
        status: DeploymentStatus;
        commit: string | null;
        created_at: string | null;
    }[];
};

function Stat({
    label,
    value,
    icon: Icon,
    tone,
}: {
    label: string;
    value: number;
    icon: LucideIcon;
    tone: string;
}) {
    return (
        <div className="flex items-center gap-4 rounded-box border border-base-300 bg-base-100 p-5">
            <span
                className={cn(
                    'flex size-11 shrink-0 items-center justify-center rounded-field',
                    tone,
                )}
            >
                <Icon className="size-6" aria-hidden />
            </span>
            <div>
                <p className="text-sm text-base-content/70">{label}</p>
                <p className="text-2xl font-semibold">{value}</p>
            </div>
        </div>
    );
}

function Availability({ label, ok }: { label: string; ok: boolean }) {
    return (
        <li className="flex items-center justify-between py-3">
            <span>{label}</span>
            <span
                className={cn(
                    'badge-soft badge',
                    ok ? 'badge-success' : 'badge-error',
                )}
            >
                {ok ? 'available' : 'not available'}
            </span>
        </li>
    );
}

export default function Dashboard({ stats, server, recent }: Props) {
    return (
        <AppLayout title="Dashboard">
            <PageHeader
                title="Dashboard"
                description="What is running on this server."
                actions={
                    <Link
                        href={AppController.create()}
                        className="btn btn-primary"
                    >
                        <Plus className="size-4" aria-hidden />
                        New app
                    </Link>
                }
            />

            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <Stat
                    label="Apps"
                    value={stats.apps}
                    icon={Boxes}
                    tone="bg-primary/10 text-primary"
                />
                <Stat
                    label="Running"
                    value={stats.running}
                    icon={CheckCircle2}
                    tone="bg-success/15 text-success"
                />
                <Stat
                    label="Deploying"
                    value={stats.deploying}
                    icon={Loader}
                    tone="bg-info/15 text-info"
                />
                <Stat
                    label="Failed"
                    value={stats.failed}
                    icon={XCircle}
                    tone="bg-error/15 text-error"
                />
            </div>

            <div className="grid items-start gap-6 xl:grid-cols-3">
                <Card title="Server">
                    {server.error !== null ? (
                        <Alert variant="warning">{server.error}</Alert>
                    ) : (
                        <ul className="divide-y divide-base-300">
                            <Availability label="Docker" ok={server.docker} />
                            <Availability label="nginx" ok={server.nginx} />
                            <li className="flex items-center justify-between py-3">
                                <span>Free disk space</span>
                                <span className="font-medium">
                                    {server.disk_free_gb} GB
                                </span>
                            </li>
                        </ul>
                    )}
                </Card>

                <div className="min-w-0 xl:col-span-2">
                    <Card title="Recent deployments" flush>
                        {recent.length === 0 ? (
                            <p className="p-5 text-sm text-base-content/70 sm:p-6">
                                Nothing has been deployed yet.
                            </p>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="table">
                                    <thead>
                                        <tr>
                                            <th>App</th>
                                            <th>Release</th>
                                            <th>Status</th>
                                            <th>Started</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {recent.map((d) => (
                                            <tr
                                                key={`${d.app_slug}-${d.number}`}
                                            >
                                                <td>
                                                    <Link
                                                        href={AppController.show(
                                                            d.app_slug,
                                                        )}
                                                        className="link link-primary"
                                                    >
                                                        {d.app_name}
                                                    </Link>
                                                </td>
                                                <td>
                                                    <Link
                                                        href={DeploymentController.show(
                                                            {
                                                                app: d.app_slug,
                                                                deployment:
                                                                    d.number,
                                                            },
                                                        )}
                                                        className="link"
                                                    >
                                                        #{d.number}
                                                    </Link>
                                                </td>
                                                <td>
                                                    <StatusBadge
                                                        status={d.status}
                                                    />
                                                </td>
                                                <td>
                                                    {formatDate(d.created_at)}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </Card>
                </div>
            </div>
        </AppLayout>
    );
}
