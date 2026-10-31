import { router } from '@inertiajs/react';
import { Container, Gauge, Network } from 'lucide-react';
import type { ReactNode } from 'react';
import { PageHeader } from '@/components/page-header';
import { Alert } from '@/components/ui/alert';
import { Tabs, tabPanelProps } from '@/components/ui/tabs';
import type { TabItem } from '@/components/ui/tabs';
import AppLayout from '@/layouts/app-layout';

export type ServerTab = 'overview' | 'docker' | 'nginx';

const PATHS: Record<ServerTab, string> = {
    overview: '/server',
    docker: '/server/docker',
    nginx: '/server/nginx',
};

const TABS: TabItem<ServerTab>[] = [
    {
        id: 'overview',
        label: 'Overview',
        icon: <Gauge className="size-4" aria-hidden />,
    },
    {
        id: 'docker',
        label: 'Docker',
        icon: <Container className="size-4" aria-hidden />,
    },
    {
        id: 'nginx',
        label: 'nginx',
        icon: <Network className="size-4" aria-hidden />,
    },
];

type Props = { active: ServerTab; error: string | null; children: ReactNode };

/** Header and tabs shared by the server pages. Each tab is its own page, so only its data is loaded. */
export function ServerLayout({ active, error, children }: Props) {
    return (
        <AppLayout title="Server">
            <PageHeader
                title="Server"
                description="The machine CommyPanel runs on, and the Docker and nginx that run your apps."
            />
            <Tabs
                tabs={TABS}
                active={active}
                label="Server sections"
                onChange={(id) => {
                    if (id !== active) router.visit(PATHS[id]);
                }}
            />
            <div {...tabPanelProps(active)} className="space-y-6 outline-none">
                {error && (
                    <Alert variant="error">
                        <span>
                            Could not read this from the server: {error}
                        </span>
                    </Alert>
                )}
                {children}
            </div>
        </AppLayout>
    );
}
