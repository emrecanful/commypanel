import { router, useForm } from '@inertiajs/react';
import { Wand2 } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import ServerAddressController from '@/actions/App/Http/Controllers/ServerAddressController';
import { Alert } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import type { ServerAddress } from '@/types';

type Props = { server: ServerAddress };

export function ServerAddressCard({ server }: Props) {
    const form = useForm({ ipv4: server.ipv4 ?? '', ipv6: server.ipv6 ?? '' });
    const [detecting, setDetecting] = useState(false);
    const configured = server.ipv4 !== null || server.ipv6 !== null;

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.submit(ServerAddressController.update(), { preserveScroll: true });
    };

    const detect = () =>
        router.post(
            ServerAddressController.detect(),
            {},
            {
                preserveScroll: true,
                onStart: () => setDetecting(true),
                onFinish: () => setDetecting(false),
                onSuccess: () => router.reload({ only: ['server'] }),
            },
        );

    return (
        <Card
            title="Server address"
            description="Your domains must point at these addresses."
        >
            {!configured && (
                <Alert variant="warning">
                    Set this server's public IP address first. Detecting it asks
                    api.ipify.org once, only when you press the button.
                </Alert>
            )}
            <form onSubmit={submit} className="space-y-4">
                <Input
                    label="IPv4 address"
                    placeholder="203.0.113.7"
                    value={form.data.ipv4}
                    onChange={(e) => form.setData('ipv4', e.target.value)}
                    error={form.errors.ipv4}
                />
                <Input
                    label="IPv6 address (optional)"
                    placeholder="2001:db8::7"
                    value={form.data.ipv6}
                    onChange={(e) => form.setData('ipv6', e.target.value)}
                    error={form.errors.ipv6}
                />
                <div className="flex flex-wrap gap-2">
                    <Button type="submit" loading={form.processing}>
                        Save address
                    </Button>
                    <Button variant="soft" onClick={detect} loading={detecting}>
                        <Wand2 className="size-4" aria-hidden />
                        Detect automatically
                    </Button>
                </div>
            </form>
        </Card>
    );
}
