import { Link, router, useForm, usePage } from '@inertiajs/react';
import { Rocket, Trash2 } from 'lucide-react';
import { useEffect, useState } from 'react';
import type { FormEvent } from 'react';
import AppController from '@/actions/App/Http/Controllers/AppController';
import DeploymentController from '@/actions/App/Http/Controllers/DeploymentController';
import { RuntimeFields, SourceFields } from '@/components/apps/app-fields';
import type { AppFormData } from '@/components/apps/app-fields';
import { CertificateCard } from '@/components/apps/certificate-card';
import { DeleteAppModal } from '@/components/apps/delete-app-modal';
import { AppDomains } from '@/components/apps/app-domains';
import { DeployKeyCard } from '@/components/apps/deploy-key-card';
import { DeploymentsCard } from '@/components/apps/deployments-card';
import { EnvCard } from '@/components/apps/env-card';
import { PageHeader } from '@/components/page-header';
import { Alert } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { StatusBadge } from '@/components/ui/status-badge';
import AppLayout from '@/layouts/app-layout';
import type { AppSettings, DeploymentSummary, ServerAddress } from '@/types';

type Props = {
    app: AppSettings;
    deployments: DeploymentSummary[];
    env_keys: string[];
    https: { enabled: boolean; staging: boolean };
    server: ServerAddress;
};

function Overview({ app }: { app: AppSettings }) {
    const rows: [string, string][] = [
        ['Repository', app.git_url ?? '-'],
        ['Branch', app.git_branch],
        ['Type', app.stack ?? 'Detected on first deploy'],
        ['App port', String(app.container_port)],
        ['Health check', app.health_path],
        ['Live release', app.active_release ? `#${app.active_release}` : '-'],
    ];

    return (
        <Card title="Overview">
            <dl className="divide-y divide-base-300 text-sm">
                {rows.map(([label, value]) => (
                    <div
                        key={label}
                        className="flex flex-wrap justify-between gap-x-4 py-2"
                    >
                        <dt className="text-base-content/70">{label}</dt>
                        <dd className="font-medium break-all">{value}</dd>
                    </div>
                ))}
            </dl>
        </Card>
    );
}

function SettingsCard({ app }: { app: AppSettings }) {
    const form = useForm<AppFormData>({
        name: app.name,
        git_url: app.git_url ?? '',
        git_branch: app.git_branch,
        container_port: String(app.container_port),
        health_path: app.health_path,
        memory_limit_mb: app.memory_limit_mb?.toString() ?? '',
        cpu_limit: app.cpu_limit?.toString() ?? '',
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.submit(AppController.update(app.slug), { preserveScroll: true });
    };

    const fields = {
        data: form.data,
        errors: form.errors,
        setData: form.setData,
    };

    return (
        <form onSubmit={submit} className="space-y-6">
            <Card title="Source" description="Where the code comes from.">
                <SourceFields {...fields} />
            </Card>
            <Card
                title="Runtime"
                description="How the app runs. Changes apply from the next deployment."
            >
                <RuntimeFields {...fields} />
            </Card>
            <Button type="submit" loading={form.processing}>
                Save settings
            </Button>
        </form>
    );
}

export default function Show({
    app,
    deployments,
    env_keys,
    https,
    server,
}: Props) {
    const { errors } = usePage().props;
    const [deploying, setDeploying] = useState(false);

    // While a deployment runs, keep the status and the release list fresh.
    useEffect(() => {
        if (app.status !== 'deploying') return;

        const timer = setInterval(
            () => router.reload({ only: ['app', 'deployments'] }),
            2000,
        );

        return () => clearInterval(timer);
    }, [app.status]);
    const [confirmDelete, setConfirmDelete] = useState(false);

    const deploy = () =>
        router.post(
            DeploymentController.store(app.slug),
            {},
            {
                onStart: () => setDeploying(true),
                onFinish: () => setDeploying(false),
            },
        );

    return (
        <AppLayout title={app.name}>
            <PageHeader
                title={app.name}
                badge={<StatusBadge status={app.status} />}
                description={app.git_url ?? undefined}
                actions={
                    <>
                        <Link
                            href={AppController.index()}
                            className="btn btn-text"
                        >
                            All apps
                        </Link>
                        <Button
                            onClick={deploy}
                            loading={deploying}
                            disabled={app.status === 'deploying'}
                        >
                            <Rocket className="size-4" aria-hidden />
                            Deploy
                        </Button>
                    </>
                }
            />

            {errors.deploy && <Alert variant="error">{errors.deploy}</Alert>}

            <div className="grid items-start gap-6 xl:grid-cols-3">
                <div className="min-w-0 space-y-6 xl:col-span-2">
                    <DeploymentsCard
                        slug={app.slug}
                        deployments={deployments}
                    />
                    <SettingsCard app={app} />
                    <AppDomains
                        slug={app.slug}
                        domains={app.domains}
                        server={server}
                    />
                </div>
                <div className="min-w-0 space-y-6">
                    <Overview app={app} />
                    <CertificateCard
                        slug={app.slug}
                        certificate={app.certificate}
                        httpsEnabled={https.enabled}
                        hasDomains={app.domains.length > 0}
                    />
                    <EnvCard slug={app.slug} keys={env_keys} />
                    <DeployKeyCard
                        slug={app.slug}
                        publicKey={app.deploy_key_public}
                    />
                    <Card
                        title="Delete app"
                        description="Stops the app and removes its containers, images and settings."
                    >
                        <Button
                            variant="error"
                            onClick={() => setConfirmDelete(true)}
                        >
                            <Trash2 className="size-4" aria-hidden />
                            Delete app
                        </Button>
                    </Card>
                </div>
            </div>

            <DeleteAppModal
                slug={app.slug}
                open={confirmDelete}
                onClose={() => setConfirmDelete(false)}
            />
        </AppLayout>
    );
}
