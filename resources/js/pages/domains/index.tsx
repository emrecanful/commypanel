import { HttpsCard } from '@/components/domains/https-card';
import { DomainCard } from '@/components/domains/domain-card';
import { ServerAddressCard } from '@/components/domains/server-address-card';
import { PageHeader } from '@/components/page-header';
import { Card } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import type { DomainRow, HttpsSettings, ServerAddress } from '@/types';

type Props = {
    server: ServerAddress;
    https: HttpsSettings;
    domains: DomainRow[];
};

export default function Index({ server, https, domains }: Props) {
    return (
        <AppLayout title="Domains">
            <PageHeader
                title="Domains"
                description="Every domain on this server. Add a domain from the app it belongs to."
            />

            <div className="grid items-start gap-6 xl:grid-cols-2">
                <ServerAddressCard server={server} />
                <HttpsCard https={https} />
            </div>

            {domains.length === 0 ? (
                <Card
                    title="No domains yet"
                    description="Open an app and add its domain there. It will show up here with its DNS status."
                >
                    <span />
                </Card>
            ) : (
                <div className="grid items-start gap-6 xl:grid-cols-2">
                    {domains.map((domain) => (
                        <DomainCard
                            key={domain.id}
                            domain={domain}
                            server={server}
                            showApp
                        />
                    ))}
                </div>
            )}
        </AppLayout>
    );
}
