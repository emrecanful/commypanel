import type { ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import { Modal } from '@/components/ui/modal';

type Props = {
    open: boolean;
    title: string;
    children: ReactNode;
    confirmLabel: string;
    loading?: boolean;
    onConfirm: () => void;
    onClose: () => void;
};

/** A yes/no dialog for destructive actions that need no typed confirmation. */
export function ConfirmModal({
    open,
    title,
    children,
    confirmLabel,
    loading = false,
    onConfirm,
    onClose,
}: Props) {
    return (
        <Modal
            open={open}
            onClose={onClose}
            title={title}
            size="sm"
            footer={
                <>
                    <Button variant="ghost" onClick={onClose}>
                        Cancel
                    </Button>
                    <Button
                        variant="error"
                        loading={loading}
                        onClick={onConfirm}
                    >
                        {confirmLabel}
                    </Button>
                </>
            }
        >
            {children}
        </Modal>
    );
}
