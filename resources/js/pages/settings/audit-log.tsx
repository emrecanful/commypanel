import { Link } from '@inertiajs/react';
import { Card } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';

type Entry = {
    id: number;
    action: string;
    user: string | null;
    ip: string | null;
    metadata: Record<string, unknown> | null;
    created_at: string;
};

type PageLink = { url: string | null; label: string; active: boolean };

type Props = {
    logs: { data: Entry[]; links: PageLink[] };
};

export default function AuditLog({ logs }: Props) {
    return (
        <AppLayout title="Audit log">
            <Card
                title="Audit log"
                description="Security-relevant actions on this panel, newest first."
            >
                <div className="overflow-x-auto">
                    <table className="table">
                        <thead>
                            <tr>
                                <th>When</th>
                                <th>Who</th>
                                <th>Action</th>
                                <th>IP</th>
                                <th>Details</th>
                            </tr>
                        </thead>
                        <tbody>
                            {logs.data.map((log) => (
                                <tr key={log.id}>
                                    <td className="whitespace-nowrap">
                                        {new Date(
                                            log.created_at,
                                        ).toLocaleString()}
                                    </td>
                                    <td>{log.user ?? '-'}</td>
                                    <td>
                                        <code>{log.action}</code>
                                    </td>
                                    <td>{log.ip ?? '-'}</td>
                                    <td className="text-sm text-base-content/70">
                                        {log.metadata
                                            ? JSON.stringify(log.metadata)
                                            : ''}
                                    </td>
                                </tr>
                            ))}
                            {logs.data.length === 0 && (
                                <tr>
                                    <td colSpan={5}>Nothing recorded yet.</td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
                <nav
                    className="flex flex-wrap gap-1"
                    aria-label="Audit log pages"
                >
                    {logs.links.map((link, index) =>
                        link.url ? (
                            <Link
                                key={index}
                                href={link.url}
                                preserveScroll
                                className={
                                    link.active
                                        ? 'btn btn-sm btn-primary'
                                        : 'btn btn-text btn-sm'
                                }
                                dangerouslySetInnerHTML={{
                                    __html: link.label,
                                }}
                            />
                        ) : null,
                    )}
                </nav>
            </Card>
        </AppLayout>
    );
}
