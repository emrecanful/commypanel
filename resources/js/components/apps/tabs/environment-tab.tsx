import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import EnvVarController from '@/actions/App/Http/Controllers/EnvVarController';
import { EnvCard } from '@/components/apps/env-card';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';

type Props = { slug: string; keys: string[] };

function ImportCard({ slug }: { slug: string }) {
    const form = useForm({ env: '' });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(EnvVarController.import(slug).url, {
            preserveScroll: true,
            onSuccess: () => form.reset(),
        });
    };

    return (
        <Card
            title="Paste a .env file"
            description="Adds every KEY=value line and overwrites variables with the same name. Existing variables not listed here are kept."
        >
            <form onSubmit={submit} className="space-y-4">
                <div className="w-full">
                    <label
                        htmlFor="env-import"
                        className="mb-1 label-text block px-0!"
                    >
                        Variables
                    </label>
                    <textarea
                        id="env-import"
                        rows={8}
                        className="textarea w-full font-mono text-sm"
                        placeholder={
                            'DATABASE_URL=postgres://...\nAPI_KEY=secret'
                        }
                        value={form.data.env}
                        onChange={(e) => form.setData('env', e.target.value)}
                        spellCheck={false}
                        autoComplete="off"
                    />
                    {form.errors.env && (
                        <span className="mt-1 block text-sm text-error">
                            {form.errors.env}
                        </span>
                    )}
                </div>
                <Button
                    type="submit"
                    loading={form.processing}
                    disabled={form.data.env.trim() === ''}
                >
                    Import variables
                </Button>
            </form>
        </Card>
    );
}

export function EnvironmentTab({ slug, keys }: Props) {
    return (
        <div className="grid items-start gap-6 xl:grid-cols-2">
            <EnvCard slug={slug} keys={keys} />
            <ImportCard slug={slug} />
        </div>
    );
}
