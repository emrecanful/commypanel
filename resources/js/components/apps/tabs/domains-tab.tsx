import { AppDomains } from '@/components/apps/app-domains';
import { CertificateCard } from '@/components/apps/certificate-card';
import type { AppSettings, ServerAddress } from '@/types';

type Props = {
    app: AppSettings;
    server: ServerAddress;
    https: { enabled: boolean; staging: boolean };
};

export function DomainsTab({ app, server, https }: Props) {
    return (
        <div className="grid items-start gap-6 xl:grid-cols-3">
            <div className="min-w-0 xl:col-span-2">
                <AppDomains
                    slug={app.slug}
                    domains={app.domains}
                    server={server}
                />
            </div>
            <div className="min-w-0">
                <CertificateCard
                    slug={app.slug}
                    certificate={app.certificate}
                    httpsEnabled={https.enabled}
                    hasDomains={app.domains.length > 0}
                />
            </div>
        </div>
    );
}
