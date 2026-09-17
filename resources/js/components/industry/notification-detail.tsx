import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogTitle,
} from '@/components/industry/dialog';
import { notificationIcon } from '@/components/industry/notification-item';
import { Btn } from '@/components/industry/ui';
import type { AppNotification } from '@/types';

const TONE_CLASS = {
    success: 'ind-tag-success',
    info: 'ind-tag-info',
    warning: 'ind-tag-warning',
    neutral: 'ind-tag-neutral',
} as const;

export function NotificationDetail({
    notification,
    onClose,
}: {
    notification: AppNotification | null;
    onClose: () => void;
}) {
    if (notification === null) {
        return null;
    }

    const Icon = notificationIcon(notification.category);

    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent>
                <DialogTitle className="flex items-center gap-2 pr-8">
                    <Icon
                        size={20}
                        strokeWidth={1.5}
                        className="flex-shrink-0"
                        style={{ color: 'var(--ind-accent-700)' }}
                    />
                    <span>{notification.title}</span>
                </DialogTitle>

                <div className="flex flex-wrap items-center gap-[10px]">
                    <span
                        className={`ind-tag ${TONE_CLASS[notification.tone]}`}
                    >
                        {notification.category_label}
                    </span>
                    <span className="ind-muted text-[12px]">
                        {notification.sent_at}
                    </span>
                </div>

                <p className="m-0 text-[14px] leading-[1.6]">
                    {notification.message}
                </p>

                <DialogFooter>
                    {notification.url && (
                        <a
                            href={notification.url}
                            className="ind-btn ind-btn-primary ind-bp"
                        >
                            <i className="ind-corner tl" />
                            <i className="ind-corner tr" />
                            <i className="ind-corner bl" />
                            <i className="ind-corner br" />
                            Buka Halaman Terkait
                        </a>
                    )}
                    <Btn onClick={onClose}>Tutup</Btn>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
