import { Link, router, usePage } from '@inertiajs/react';
import { ShieldCheck } from 'lucide-react';
import { useState } from 'react';
import CertificateController from '@/actions/App/Http/Controllers/CertificateController';
import DomainController from '@/actions/App/Http/Controllers/DomainController';
import { Alert } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { StatusBadge } from '@/components/ui/status-badge';
import { formatDate } from '@/lib/format';
import type { CertificateSummary } from '@/types';

type Props = {
    slug: string;
    certificate: CertificateSummary | null;
    httpsEnabled: boolean;
    hasDomains: boolean;
};

const BADGE = {
    issuing: { status: 'building', label: 'requesting' },
    active: { status: 'active', label: 'valid' },
    failed: { status: 'failed', label: 'failed' },
} as const;

function Details({ certificate }: { certificate: CertificateSummary }) {
    return (
        <dl className="divide-y divide-base-300 text-sm">
            <div className="flex justify-between gap-4 py-2">
                <dt className="text-base-content/70">Issued</dt>
                <dd className="font-medium">
                    {formatDate(certificate.issued_at)}
                </dd>
            </div>
            <div className="flex justify-between gap-4 py-2">
                <dt className="text-base-content/70">Expires</dt>
                <dd className="font-medium">
                    {formatDate(certificate.expires_at)}
                </dd>
            </div>
            <div className="flex justify-between gap-4 py-2">
                <dt className="text-base-content/70">Covers</dt>
                <dd className="text-right font-medium break-all">
                    {certificate.domains.join(', ')}
                </dd>
            </div>
        </dl>
    );
}

export function CertificateCard({
    slug,
    certificate,
    httpsEnabled,
    hasDomains,
}: Props) {
    const { errors } = usePage().props;
    const [busy, setBusy] = useState(false);

    const request = () =>
        router.post(
            CertificateController.store(slug),
            {},
            {
                preserveScroll: true,
                onStart: () => setBusy(true),
                onFinish: () => setBusy(false),
            },
        );

    const badge = certificate ? BADGE[certificate.status] : null;
    const canRequest = httpsEnabled && hasDomains;
    const hasIssued = certificate?.expires_at != null;

    return (
        <Card
            title="HTTPS certificate"
            actions={
                badge && (
                    <StatusBadge status={badge.status} label={badge.label} />
                )
            }
        >
            {errors.certificate && (
                <Alert variant="error">{errors.certificate}</Alert>
            )}

            {!httpsEnabled && (
                <Alert variant="warning">
                    <span>
                        Automatic HTTPS is off
                        {certificate
                            ? ', so this certificate will not be renewed'
                            : ''}
                        .{' '}
                        <Link
                            href={DomainController.index()}
                            className="link font-medium"
                        >
                            Turn it on in Domains
                        </Link>
                        .
                    </span>
                </Alert>
            )}

            {certificate?.staging && (
                <Alert variant="warning">
                    Test certificate from Let's Encrypt staging: browsers will
                    show a warning.
                </Alert>
            )}

            {certificate?.last_error && (
                <Alert
                    variant={
                        certificate.status === 'failed' ? 'error' : 'warning'
                    }
                >
                    {certificate.last_error}
                </Alert>
            )}

            {certificate && hasIssued && <Details certificate={certificate} />}

            {!certificate && httpsEnabled && !hasDomains && (
                <p className="text-sm text-base-content/70">
                    Connect a domain to this app first. A certificate is
                    requested automatically once its DNS points at this server.
                </p>
            )}

            {canRequest && (
                <div>
                    <Button
                        variant="soft"
                        onClick={request}
                        loading={busy}
                        disabled={certificate?.status === 'issuing'}
                    >
                        <ShieldCheck className="size-4" aria-hidden />
                        {hasIssued ? 'Renew now' : 'Get certificate'}
                    </Button>
                </div>
            )}
        </Card>
    );
}
