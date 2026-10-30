import { PageHeader } from '@/components/page-header';
import { ThemePicker } from '@/components/theme-picker';
import { Card } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';

export default function Appearance() {
    return (
        <AppLayout title="Appearance">
            <PageHeader
                title="Appearance"
                description="Pick how CommyPanel looks. The choice is remembered in this browser."
            />
            <Card title="Theme">
                <ThemePicker />
            </Card>
        </AppLayout>
    );
}
