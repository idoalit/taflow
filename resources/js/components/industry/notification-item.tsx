import {
    Bell,
    CalendarDays,
    CheckCircle2,
    Clock,
    FileText,
    type LucideIcon,
} from 'lucide-react';
import type { AppNotification, NotificationCategory } from '@/types';

const ICONS: Record<NotificationCategory, LucideIcon> = {
    approval: CheckCircle2,
    schedule: CalendarDays,
    reminder: Clock,
    document: FileText,
};

export function notificationIcon(category: NotificationCategory): LucideIcon {
    return ICONS[category] ?? Bell;
}

/**
 * One row in the bell dropdown or on the Notifikasi page. Unread rows carry the
 * accent wash, a dot and a heavier title.
 */
export function NotificationRow({
    notification,
    onOpen,
    variant,
}: {
    notification: AppNotification;
    onOpen: (notification: AppNotification) => void;
    variant: 'preview' | 'full';
}) {
    const Icon = notificationIcon(notification.category);
    const isPreview = variant === 'preview';

    return (
        <button
            type="button"
            onClick={() => onOpen(notification)}
            className="flex w-full cursor-pointer items-start gap-[10px] border-0 text-left"
            style={{
                background: notification.unread
                    ? 'var(--ind-accent-100)'
                    : 'transparent',
                padding: isPreview ? '8px' : '14px 16px',
                borderBottom: isPreview
                    ? undefined
                    : '1px solid var(--ind-divider)',
                fontFamily: 'var(--ind-font-body)',
            }}
        >
            <span
                className="mt-[6px] h-[6px] w-[6px] flex-shrink-0 rounded-full"
                style={{
                    background: notification.unread
                        ? 'var(--ind-accent-600)'
                        : 'transparent',
                }}
            />

            <Icon
                size={18}
                strokeWidth={1.5}
                className="mt-[1px] flex-shrink-0"
                style={{
                    color: notification.unread
                        ? 'var(--ind-accent-700)'
                        : 'var(--ind-neutral-600)',
                }}
            />

            <span className="min-w-0 flex-1">
                <span
                    className="block"
                    style={{
                        fontSize: isPreview ? 13 : 14,
                        fontWeight: notification.unread ? 600 : 400,
                    }}
                >
                    {notification.title}
                </span>
                <span
                    className="ind-muted mt-[2px] block"
                    style={{
                        fontSize: isPreview ? 12 : 13,
                        ...(isPreview
                            ? {
                                  whiteSpace: 'nowrap',
                                  overflow: 'hidden',
                                  textOverflow: 'ellipsis',
                              }
                            : {}),
                    }}
                >
                    {notification.message}
                </span>
                {isPreview && (
                    <span
                        className="mt-[3px] block text-[11px]"
                        style={{ color: 'var(--ind-accent-700)' }}
                    >
                        {notification.time}
                    </span>
                )}
            </span>

            {!isPreview && (
                <span className="ind-muted flex-shrink-0 text-[12px]">
                    {notification.time}
                </span>
            )}
        </button>
    );
}
