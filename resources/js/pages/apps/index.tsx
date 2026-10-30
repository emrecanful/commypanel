import { Link } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import AppController from '@/actions/App/Http/Controllers/AppController';
import { PageHeader } from '@/components/page-header';
import { Card } from '@/components/ui/card';
import { StatusBadge } from '@/components/ui/status-badge';
import AppLayout from '@/layouts/app-layout';
import { formatDate } from '@/lib/format';
import type { AppSummary } from '@/types';

type Props = { apps: AppSummary[] };

export default function Index({ apps }: Props) {
    const newApp = (
        <Link href={AppController.create()} className="btn btn-primary">
            <Plus className="size-4" aria-hidden />
            New app
        </Link>
    );

    return (
        <AppLayout title="Apps">
            <PageHeader
                title="Apps"
                description="Everything deployed on this server."
                actions={newApp}
            />

            {apps.length === 0 ? (
                <Card
                    title="No apps yet"
                    description="Connect a Git repository and CommyPanel will build and run it for you."
                >
                    <div>{newApp}</div>
                </Card>
            ) : (
                <Card flush>
                    <div className="overflow-x-auto">
                        <table className="table">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Status</th>
                                    <th>Type</th>
                                    <th>Live release</th>
                                    <th>Last deploy</th>
                                </tr>
                            </thead>
                            <tbody>
                                {apps.map((app) => (
                                    <tr key={app.slug}>
                                        <td>
                                            <Link
                                                href={AppController.show(
                                                    app.slug,
                                                )}
                                                className="link font-medium link-primary"
                                            >
                                                {app.name}
                                            </Link>
                                        </td>
                                        <td>
                                            <StatusBadge status={app.status} />
                                        </td>
                                        <td>{app.stack ?? '-'}</td>
                                        <td>
                                            {app.active_release
                                                ? `#${app.active_release}`
                                                : '-'}
                                        </td>
                                        <td>
                                            {formatDate(app.last_deployed_at)}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </Card>
            )}
        </AppLayout>
    );
}
