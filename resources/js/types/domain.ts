export type DomainStatus = 'pending' | 'verified' | 'misconfigured' | 'proxied';

export type DomainRow = {
    id: number;
    name: string;
    status: DomainStatus;
    status_label: string;
    app: string | null;
    app_name: string | null;
    redirect_to: string | null;
    found: { a: string[]; aaaa: string[] };
    problems: string[];
    last_checked_at: string | null;
};

export type ServerAddress = { ipv4: string | null; ipv6: string | null };

export type HttpsSettings = {
    enabled: boolean;
    email?: string | null;
    staging: boolean;
};
