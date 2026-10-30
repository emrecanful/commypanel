import { Link, router } from '@inertiajs/react';
import DeploymentController from '@/actions/App/Http/Controllers/DeploymentController';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { StatusBadge } from '@/components/ui/status-badge';
import { formatDate } from '@/lib/format';
import type { DeploymentSummary } from '@/types';

type Props = { slug: string; deployments: DeploymentSummary[] };

export function DeploymentsCard({ slug, deployments }: Props) {
    const rollBack = (number: number) =>
        router.post(
            DeploymentController.rollback({ app: slug, deployment: number }),
            {},
            { preserveScroll: true },
        );

    return (
        <Card title="Deployments" flush>
            {deployments.length === 0 ? (
                <p className="text-sm text-base-content/70">
                    Nothing deployed yet. Press Deploy to build and start this
                    app.
                </p>
            ) : (
                <div className="overflow-x-auto">
                    <table className="table">
                        <thead>
                            <tr>
                                <th>Release</th>
                                <th>Status</th>
                                <th>Commit</th>
                                <th>Started</th>
                                <th />
                            </tr>
                        </thead>
                        <tbody>
                            {deployments.map((d) => (
                                <tr key={d.number}>
                                    <td>
                                        <Link
                                            href={DeploymentController.show({
                                                app: slug,
                                                deployment: d.number,
                                            })}
                                            className="link link-primary"
                                        >
                                            #{d.number}
                                        </Link>
                                        {d.is_rollback && (
                                            <span className="ml-2 text-xs text-base-content/60">
                                                rollback
                                            </span>
                                        )}
                                    </td>
                                    <td>
                                        <StatusBadge status={d.status} />
                                    </td>
                                    <td>
                                        <code>{d.commit ?? '-'}</code>
                                    </td>
                                    <td>{formatDate(d.created_at)}</td>
                                    <td className="text-right">
                                        {d.status === 'superseded' &&
                                            d.can_roll_back && (
                                                <Button
                                                    variant="soft"
                                                    size="sm"
                                                    onClick={() =>
                                                        rollBack(d.number)
                                                    }
                                                >
                                                    Roll back to this
                                                </Button>
                                            )}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}
        </Card>
    );
}
