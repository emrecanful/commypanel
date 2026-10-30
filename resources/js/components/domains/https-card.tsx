import { router, useForm } from '@inertiajs/react';
import { Lock } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import HttpsController from '@/actions/App/Http/Controllers/HttpsController';
import { Alert } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import type { HttpsSettings } from '@/types';

type Props = { https: HttpsSettings };

export function HttpsCard({ https }: Props) {
    const form = useForm({ agree: false, email: https.email ?? '' });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.submit(HttpsController.update(), { preserveScroll: true });
    };

    const [turningOff, setTurningOff] = useState(false);

    const turnOff = () =>
        router.put(
            HttpsController.update(),
            { agree: false },
            {
                preserveScroll: true,
                onStart: () => setTurningOff(true),
                onFinish: () => setTurningOff(false),
            },
        );

    return (
        <Card
            title="Automatic HTTPS"
            description="CommyPanel gets free certificates from Let's Encrypt for your connected domains and renews them before they expire."
        >
            {https.staging && (
                <Alert variant="warning">
                    Test mode: certificates come from Let's Encrypt's staging
                    service, so browsers will warn about them. Set
                    ACME_STAGING=false on your production server.
                </Alert>
            )}

            {https.enabled ? (
                <div className="space-y-4">
                    <Alert variant="success">
                        <span className="flex items-center gap-2">
                            <Lock className="size-4 shrink-0" aria-hidden />
                            Automatic HTTPS is on
                            {https.email ? ` (contact: ${https.email})` : ''}.
                        </span>
                    </Alert>
                    <Button
                        variant="soft"
                        onClick={turnOff}
                        loading={turningOff}
                    >
                        Turn off
                    </Button>
                </div>
            ) : (
                <form onSubmit={submit} className="space-y-4">
                    <Input
                        label="Contact email (optional)"
                        type="email"
                        placeholder="you@example.com"
                        value={form.data.email}
                        onChange={(e) => form.setData('email', e.target.value)}
                        error={form.errors.email}
                        hint="Sent to Let's Encrypt so they can reach you about your account."
                    />
                    <label className="flex items-start gap-3 text-sm">
                        <input
                            type="checkbox"
                            className="checkbox mt-0.5 checkbox-primary"
                            checked={form.data.agree}
                            onChange={(e) =>
                                form.setData('agree', e.target.checked)
                            }
                        />
                        <span>
                            I agree to the{' '}
                            <a
                                href="https://letsencrypt.org/repository/"
                                target="_blank"
                                rel="noreferrer"
                                className="link link-primary"
                            >
                                Let's Encrypt Subscriber Agreement
                            </a>
                            .
                        </span>
                    </label>
                    <Button
                        type="submit"
                        loading={form.processing}
                        disabled={!form.data.agree}
                    >
                        Turn on automatic HTTPS
                    </Button>
                </form>
            )}
        </Card>
    );
}
