import { useForm } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import type { FormEvent } from 'react';
import DomainController from '@/actions/App/Http/Controllers/DomainController';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';

type Props = { apps: { slug: string; name: string }[] };

export function AddDomainCard({ apps }: Props) {
    const form = useForm({ name: '', app: '' });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.submit(DomainController.store(), {
            preserveScroll: true,
            onSuccess: () => form.reset(),
        });
    };

    return (
        <Card
            title="Add a domain"
            description="Use a domain or subdomain you control, for example app.example.com."
        >
            <form onSubmit={submit} className="space-y-4">
                <Input
                    label="Domain"
                    placeholder="app.example.com"
                    required
                    value={form.data.name}
                    onChange={(e) => form.setData('name', e.target.value)}
                    error={form.errors.name}
                />
                <Select
                    label="Serve this app"
                    value={form.data.app}
                    onChange={(e) => form.setData('app', e.target.value)}
                    error={form.errors.app}
                    hint="You can assign or change this later."
                >
                    <option value="">Not assigned yet</option>
                    {apps.map((app) => (
                        <option key={app.slug} value={app.slug}>
                            {app.name}
                        </option>
                    ))}
                </Select>
                <Button type="submit" loading={form.processing}>
                    <Plus className="size-4" aria-hidden />
                    Add domain
                </Button>
            </form>
        </Card>
    );
}
