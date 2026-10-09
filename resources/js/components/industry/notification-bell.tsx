import { router, usePage } from '@inertiajs/react';
import { Bell } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { BlueprintCorners } from '@/components/industry/blueprint';
import { NotificationDetail } from '@/components/industry/notification-detail';
import { NotificationRow } from '@/components/industry/notification-item';
import { index as notificationsIndex, readAll } from '@/routes/notifications';
import { read } from '@/routes/notifications';
import type { AppNotification } from '@/types';

/**
 * The header bell: unread badge, a preview dropdown, and the detail dialog.
 * Opening a notification marks it read, which is what the badge counts down.
 */
export function NotificationBell() {
    const { notificationFeed: feed, impersonation } = usePage().props;
    // Marking as read is the owner's to do. A superadmin looking through the
    // account reads without leaving that trace, and is not offered the button.
    const canMarkRead = impersonation === null;
    const [open, setOpen] = useState(false);
    const [selected, setSelected] = useState<AppNotification | null>(null);
    const containerRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
        if (!open) {
            return;
        }

        const close = (event: MouseEvent) => {
            if (!containerRef.current?.contains(event.target as Node)) {
                setOpen(false);
            }
        };

        document.addEventListener('mousedown', close);

        return () => document.removeEventListener('mousedown', close);
    }, [open]);

    if (feed === null) {
        return null;
    }

    const openDetail = (notification: AppNotification) => {
        setSelected(notification);
        setOpen(false);

        if (notification.unread && canMarkRead) {
            router.post(
                read(notification.id).url,
                {},
                { preserveScroll: true, preserveState: true },
            );
        }
    };

    return (
        <>
            <div className="relative flex-shrink-0" ref={containerRef}>
                <button
                    type="button"
                    aria-label="Notifikasi"
                    aria-expanded={open}
                    onClick={() => setOpen((value) => !value)}
                    className="ind-btn ind-btn-ghost ind-btn-icon relative"
                >
                    <Bell size={18} strokeWidth={1.5} />
                    {feed.unread > 0 && (
                        <span
                            className="absolute top-[2px] right-[2px] flex h-4 min-w-4 items-center justify-center rounded-lg px-[3px] text-[10px] leading-none font-semibold"
                            style={{
                                background: 'var(--ind-accent-600)',
                                color: '#fff',
                            }}
                        >
                            {feed.unread > 99 ? '99+' : feed.unread}
                        </span>
                    )}
                </button>

                {open && (
                    <div
                        className="ind-bp absolute top-[calc(100%+8px)] right-0 z-90 w-[340px] max-w-[90vw] border p-[var(--ind-space-2)]"
                        style={{
                            background: 'var(--ind-bg)',
                            borderColor: 'var(--ind-divider)',
                            boxShadow: 'var(--ind-shadow-lg)',
                        }}
                    >
                        <BlueprintCorners />

                        <div className="flex items-center justify-between px-2 py-[6px]">
                            <span className="ind-eyebrow">Notifikasi</span>
                            {feed.unread > 0 && canMarkRead && (
                                <button
                                    type="button"
                                    onClick={() =>
                                        router.post(
                                            readAll().url,
                                            {},
                                            {
                                                preserveScroll: true,
                                                preserveState: true,
                                            },
                                        )
                                    }
                                    className="cursor-pointer border-0 bg-transparent p-0 text-[11px]"
                                    style={{ color: 'var(--ind-accent-700)' }}
                                >
                                    Tandai semua dibaca
                                </button>
                            )}
                        </div>

                        <div className="flex max-h-[360px] flex-col overflow-x-hidden overflow-y-auto">
                            {feed.recent.length === 0 ? (
                                <p className="ind-muted m-0 px-2 py-6 text-center text-[13px]">
                                    Belum ada notifikasi.
                                </p>
                            ) : (
                                feed.recent.map((notification) => (
                                    <NotificationRow
                                        key={notification.id}
                                        notification={notification}
                                        onOpen={openDetail}
                                        variant="preview"
                                    />
                                ))
                            )}
                        </div>

                        <button
                            type="button"
                            onClick={() => {
                                setOpen(false);
                                router.visit(notificationsIndex().url);
                            }}
                            className="mt-1 w-full cursor-pointer border-0 bg-transparent px-2 py-[10px] text-center text-[13px]"
                            style={{
                                borderTop: '1px solid var(--ind-divider)',
                                color: 'var(--ind-accent-700)',
                            }}
                        >
                            Lihat Semua Notifikasi
                        </button>
                    </div>
                )}
            </div>

            <NotificationDetail
                notification={selected}
                onClose={() => setSelected(null)}
            />
        </>
    );
}
