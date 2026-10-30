import { Card } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';

export default function Dashboard() {
    return (
        <AppLayout title="Dashboard">
            <Card
                title="Welcome to CommyPanel"
                description="Apps, domains and databases will show up here."
            >
                <p className="text-sm text-base-content/70">
                    Nothing is deployed yet.
                </p>
            </Card>
        </AppLayout>
    );
}
