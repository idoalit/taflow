import { Head, router, setLayoutProps } from '@inertiajs/react';
import { useState } from 'react';
import { BlueprintCorners } from '@/components/industry/blueprint';
import { NotificationDetail } from '@/components/industry/notification-detail';
import { NotificationRow } from '@/components/industry/notification-item';
import { Btn } from '@/components/industry/ui';
import { read, readAll } from '@/routes/notifications';
import type { AppNotification } from '@/types';

export default function NotificationIndex({
    notifications,
}: {
    notifications: AppNotification[];
}) {
    setLayoutProps({ title: 'Notifikasi' });

    const [selected, setSelected] = useState<AppNotification | null>(null);
    const unread = notifications.filter(
        (notification) => notification.unread,
    ).length;

    const openDetail = (notification: AppNotification) => {
        setSelected(notification);

        if (notification.unread) {
            router.post(
                read(notification.id).url,
                {},
                { preserveScroll: true },
            );
        }
    };

    return (
        <>
            <Head title="Notifikasi" />

            <div className="flex flex-col gap-[var(--ind-space-4)]">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <p className="ind-muted m-0 text-[14px]">
                        Semua pembaruan dan pengingat terkait aktivitas Anda.
                    </p>
                    {unread > 0 && (
                        <Btn
                            onClick={() =>
                                router.post(
                                    readAll().url,
                                    {},
                                    { preserveScroll: true },
                                )
                            }
                        >
                            Tandai Semua Dibaca
                        </Btn>
                    )}
                </div>

                {notifications.length === 0 ? (
                    <p className="ind-muted m-0 py-[var(--ind-space-8)] text-center text-[13px]">
                        Belum ada notifikasi untuk Anda.
                    </p>
                ) : (
                    <div
                        className="ind-bp border"
                        style={{ borderColor: 'var(--ind-divider)' }}
                    >
                        <BlueprintCorners />
                        {notifications.map((notification) => (
                            <NotificationRow
                                key={notification.id}
                                notification={notification}
                                onOpen={openDetail}
                                variant="full"
                            />
                        ))}
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
