import { Link, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import AppController from '@/actions/App/Http/Controllers/AppController';
import { RuntimeFields, SourceFields } from '@/components/apps/app-fields';
import type { AppFormData } from '@/components/apps/app-fields';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';

export default function Create() {
    const form = useForm<AppFormData>({
        name: '',
        git_url: '',
        git_branch: 'main',
        container_port: '8080',
        health_path: '/',
        memory_limit_mb: '',
        cpu_limit: '',
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.submit(AppController.store());
    };

    const fields = {
        data: form.data,
        errors: form.errors,
        setData: form.setData,
    };

    return (
        <AppLayout title="New app">
            <PageHeader
                title="New app"
                description="CommyPanel detects .NET, Go and Node.js projects, or builds your own Dockerfile."
            />
            <form onSubmit={submit} className="space-y-6">
                <div className="grid gap-6 xl:grid-cols-2">
                    <Card title="Source">
                        <SourceFields {...fields} />
                    </Card>
                    <Card title="Runtime">
                        <RuntimeFields {...fields} />
                    </Card>
                </div>
                <div className="flex gap-2">
                    <Button type="submit" loading={form.processing}>
                        Create app
                    </Button>
                    <Link href={AppController.index()} className="btn btn-text">
                        Cancel
                    </Link>
                </div>
            </form>
        </AppLayout>
    );
}
