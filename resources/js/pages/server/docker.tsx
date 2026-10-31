import { Link, router, usePage } from '@inertiajs/react';
import { FileText, Trash } from 'lucide-react';
import { useEffect, useState } from 'react';
import AppController from '@/actions/App/Http/Controllers/AppController';
import ServerDockerController from '@/actions/App/Http/Controllers/ServerDockerController';
import { ServerLayout } from '@/components/server/server-layout';
import { StateMark, toneOfContainer } from '@/components/server/state-mark';
import { Alert } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Modal } from '@/components/ui/modal';
import { formatBytes, formatDate, timeAgo } from '@/lib/format';

type Container = {
    name: string;
    image: string;
    state: string;
    status: string;
    created: number;
    app: { slug: string; name: string } | null;
    release: number | null;
};

type Props = {
    error: string | null;
    docker: {
        available: boolean;
        error: string | null;
        server_version: string;
        api_version: string;
        os: string;
        arch: string;
        kernel_version: string;
        storage_driver: string;
        cgroup_driver: string;
        containers: number;
        running: number;
        paused: number;
        stopped: number;
        images: number;
        cpus: number;
        mem_total: number;
    } | null;
    containers: Container[];
    images: { tags: string[]; size: number; created: number }[];
};

function Stat({ label, value }: { label: string; value: string }) {
    return (
        <div className="min-w-0 rounded-box border border-base-300 px-4 py-3">
            <dt className="text-xs text-base-content/70">{label}</dt>
            <dd className="truncate font-semibold">{value}</dd>
        </div>
    );
}

function LogsModal({
    name,
    onClose,
}: {
    name: string | null;
    onClose: () => void;
}) {
    const [logs, setLogs] = useState<{ name: string; text: string } | null>(
        null,
    );
    const [problem, setProblem] = useState<{
        name: string;
        message: string;
    } | null>(null);

    // Load when a container is chosen; a late answer for another one is ignored.
    useEffect(() => {
        if (name === null) return;

        let current = true;

        fetch(ServerDockerController.logs(name).url, {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        })
            .then(async (response) => {
                const body = (await response.json()) as {
                    logs?: string;
                    error?: string;
                };
                if (!response.ok) throw new Error(body.error);
                if (current) setLogs({ name, text: body.logs ?? '' });
            })
            .catch((e: Error) => {
                if (current) {
                    setProblem({
                        name,
                        message: e.message || 'Could not load the logs.',
                    });
                }
            });

        return () => {
            current = false;
        };
    }, [name]);

    return (
        <Modal
            open={name !== null}
            onClose={onClose}
            title={name ? `Logs of ${name}` : 'Logs'}
            size="lg"
        >
            {problem && problem.name === name ? (
                <Alert variant="error">{problem.message}</Alert>
            ) : logs && logs.name === name ? (
                <pre className="max-h-96 overflow-auto rounded-field border border-base-300 p-3 text-xs">
                    {logs.text || 'The container has not printed anything yet.'}
                </pre>
            ) : (
                <p className="text-sm text-base-content/70">Loading…</p>
            )}
        </Modal>
    );
}

export default function Docker({ error, docker, containers, images }: Props) {
    const { errors } = usePage().props;
    const [logsFor, setLogsFor] = useState<string | null>(null);
    const [pruning, setPruning] = useState(false);

    const prune = () =>
        router.post(
            ServerDockerController.prune(),
            {},
            {
                preserveScroll: true,
                onStart: () => setPruning(true),
                onFinish: () => setPruning(false),
            },
        );

    return (
        <ServerLayout active="docker" error={error}>
            {errors.docker && <Alert variant="error">{errors.docker}</Alert>}

            {docker && !docker.available && (
                <Alert variant="error">
                    <span>
                        Docker is not reachable
                        {docker.error ? `: ${docker.error}` : '.'} Apps cannot
                        be built or started until it is back; the panel and
                        nginx keep working. Check the Docker service on the
                        Overview tab.
                    </span>
                </Alert>
            )}

            {docker?.available && (
                <>
                    <Card
                        title="Docker"
                        description={`Engine ${docker.server_version}, API ${docker.api_version}, ${docker.os}/${docker.arch}`}
                    >
                        <dl className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                            <Stat
                                label="Running"
                                value={`${docker.running} of ${docker.containers}`}
                            />
                            <Stat
                                label="Stopped"
                                value={String(docker.stopped)}
                            />
                            <Stat
                                label="Images"
                                value={String(docker.images)}
                            />
                            <Stat
                                label="Storage driver"
                                value={docker.storage_driver}
                            />
                            <Stat
                                label="Cgroup driver"
                                value={docker.cgroup_driver}
                            />
                            <Stat label="CPUs" value={String(docker.cpus)} />
                            <Stat
                                label="Memory"
                                value={formatBytes(docker.mem_total)}
                            />
                            <Stat
                                label="Kernel"
                                value={docker.kernel_version}
                            />
                        </dl>
                    </Card>

                    <Card
                        title="Containers"
                        description="The containers CommyPanel started for your apps."
                        flush
                    >
                        {containers.length === 0 ? (
                            <p className="p-5 text-sm text-base-content/70 sm:p-6">
                                No containers yet. Deploy an app and it shows up
                                here.
                            </p>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="table">
                                    <thead>
                                        <tr>
                                            <th>State</th>
                                            <th>Container</th>
                                            <th>App</th>
                                            <th>Image</th>
                                            <th>Since</th>
                                            <th />
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {containers.map((c) => (
                                            <tr key={c.name}>
                                                <td>
                                                    <StateMark
                                                        tone={toneOfContainer(
                                                            c.state,
                                                        )}
                                                        label={c.state}
                                                    />
                                                </td>
                                                <td>
                                                    <code>{c.name}</code>
                                                    <p className="text-xs text-base-content/60">
                                                        {c.status}
                                                    </p>
                                                </td>
                                                <td>
                                                    {c.app ? (
                                                        <Link
                                                            href={AppController.show(
                                                                c.app.slug,
                                                            )}
                                                            className="link link-primary"
                                                        >
                                                            {c.app.name}
                                                            {c.release
                                                                ? ` #${c.release}`
                                                                : ''}
                                                        </Link>
                                                    ) : (
                                                        <span className="text-base-content/60">
                                                            -
                                                        </span>
                                                    )}
                                                </td>
                                                <td className="break-all">
                                                    <code className="text-xs">
                                                        {c.image}
                                                    </code>
                                                </td>
                                                <td
                                                    className="whitespace-nowrap"
                                                    title={formatDate(
                                                        new Date(
                                                            c.created * 1000,
                                                        ).toISOString(),
                                                    )}
                                                >
                                                    {timeAgo(
                                                        new Date(
                                                            c.created * 1000,
                                                        ).toISOString(),
                                                    )}
                                                </td>
                                                <td className="text-right">
                                                    <Button
                                                        variant="soft"
                                                        size="sm"
                                                        onClick={() =>
                                                            setLogsFor(c.name)
                                                        }
                                                    >
                                                        <FileText
                                                            className="size-4"
                                                            aria-hidden
                                                        />
                                                        Logs
                                                    </Button>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </Card>

                    <Card
                        title="Images"
                        description="Images built from your apps. Old releases keep theirs so you can roll back."
                        actions={
                            <Button
                                variant="soft"
                                size="sm"
                                onClick={prune}
                                loading={pruning}
                            >
                                <Trash className="size-4" aria-hidden />
                                Remove unused images
                            </Button>
                        }
                        flush
                    >
                        {images.length === 0 ? (
                            <p className="p-5 text-sm text-base-content/70 sm:p-6">
                                No images yet.
                            </p>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="table">
                                    <thead>
                                        <tr>
                                            <th>Image</th>
                                            <th>Size</th>
                                            <th>Built</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {images.map((image) => (
                                            <tr key={image.tags.join()}>
                                                <td className="break-all">
                                                    <code>
                                                        {image.tags.join(', ')}
                                                    </code>
                                                </td>
                                                <td className="whitespace-nowrap">
                                                    {formatBytes(image.size)}
                                                </td>
                                                <td className="whitespace-nowrap">
                                                    {timeAgo(
                                                        new Date(
                                                            image.created *
                                                                1000,
                                                        ).toISOString(),
                                                    )}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </Card>
                </>
            )}

            <LogsModal name={logsFor} onClose={() => setLogsFor(null)} />
        </ServerLayout>
    );
}
