export type AppStatus = 'new' | 'deploying' | 'running' | 'failed';

export type AppSummary = {
    slug: string;
    name: string;
    stack: string | null;
    status: AppStatus;
    git_url: string | null;
    git_branch: string;
    active_release: number | null;
    last_deployed_at: string | null;
};

export type AppSettings = AppSummary & {
    container_port: number;
    health_path: string;
    memory_limit_mb: number | null;
    cpu_limit: number | null;
    deploy_key_public: string | null;
    domains: { name: string; status: string }[];
    certificate: CertificateSummary | null;
};

export type CertificateSummary = {
    status: 'issuing' | 'active' | 'failed';
    domains: string[];
    staging: boolean;
    issued_at: string | null;
    expires_at: string | null;
    last_error: string | null;
};

export type DeploymentStatus =
    | 'queued'
    | 'building'
    | 'starting'
    | 'active'
    | 'superseded'
    | 'failed'
    | 'stopped';

export type DeploymentSummary = {
    number: number;
    status: DeploymentStatus;
    finished: boolean;
    commit: string | null;
    is_rollback: boolean;
    can_roll_back: boolean;
    error: string | null;
    created_at: string | null;
    finished_at: string | null;
};

export type DeploymentDetail = DeploymentSummary & { log: string };
