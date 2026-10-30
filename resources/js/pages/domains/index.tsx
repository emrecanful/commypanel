import { AddDomainCard } from '@/components/domains/add-domain-card';
import { DomainCard } from '@/components/domains/domain-card';
import { ServerAddressCard } from '@/components/domains/server-address-card';
import { PageHeader } from '@/components/page-header';
import { Card } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import type { DomainRow, ServerAddress } from '@/types';

type Props = {
    server: ServerAddress;
    domains: DomainRow[];
    apps: { slug: string; name: string }[];
};

export default function Index({ server, domains, apps }: Props) {
    return (
        <AppLayout title="Domains">
            <PageHeader
                title="Domains"
                description="Connect your domains and choose which app each one serves."
            />

            <div className="grid items-start gap-6 xl:grid-cols-2">
                <ServerAddressCard server={server} />
                <AddDomainCard apps={apps} />
            </div>

            {domains.length === 0 ? (
                <Card
                    title="No domains yet"
                    description="Add a domain above, create the DNS records shown here, and it will connect automatically."
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
                            apps={apps}
                        />
                    ))}
                </div>
            )}
        </AppLayout>
    );
}
