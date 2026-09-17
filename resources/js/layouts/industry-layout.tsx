import { usePage } from '@inertiajs/react';
import { Menu } from 'lucide-react';
import { useEffect, useState, type ReactNode } from 'react';
import { toast } from 'sonner';
import { NotificationBell } from '@/components/industry/notification-bell';
import { Tag } from '@/components/industry/ui';
import { IndustrySidebar } from '@/layouts/industry/sidebar';

const MOBILE_BREAKPOINT = 900;

/**
 * The shell every FiPro screen sits in: a persistent sidebar on desktop, a
 * drawer on narrow screens, and a header carrying the page title and the
 * "viewing as" role badge.
 */
export default function IndustryLayout({
    title,
    children,
}: {
    title: string;
    children: ReactNode;
}) {
    const page = usePage();
    const user = page.props.auth.user;
    const flash = page.props.flash;
    const [isMobile, setIsMobile] = useState(false);
    const [navOpen, setNavOpen] = useState(false);

    useEffect(() => {
        const onResize = () =>
            setIsMobile(window.innerWidth < MOBILE_BREAKPOINT);

        onResize();
        window.addEventListener('resize', onResize);

        return () => window.removeEventListener('resize', onResize);
    }, []);

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success);
        }

        if (flash?.error) {
            toast.error(flash.error);
        }
    }, [flash?.success, flash?.error]);

    const showBackdrop = isMobile && navOpen;

    return (
        <div className="ind flex min-h-screen w-full">
            <aside
                className="flex flex-col overflow-y-auto p-[var(--ind-space-6)]"
                style={{
                    width: 248,
                    flexShrink: 0,
                    borderRight: '1px solid var(--ind-divider)',
                    background: 'var(--ind-bg)',
                    ...(isMobile
                        ? {
                              position: 'fixed',
                              insetBlock: 0,
                              left: 0,
                              zIndex: 60,
                              transform: navOpen
                                  ? 'translateX(0)'
                                  : 'translateX(-100%)',
                              transition: 'transform 160ms ease',
                              boxShadow: navOpen
                                  ? 'var(--ind-shadow-lg)'
                                  : 'none',
                          }
                        : { position: 'sticky', top: 0, height: '100vh' }),
                }}
            >
                <IndustrySidebar onNavigate={() => setNavOpen(false)} />
            </aside>

            {showBackdrop && (
                <button
                    type="button"
                    aria-label="Tutup menu"
                    onClick={() => setNavOpen(false)}
                    className="fixed inset-0 z-55 border-0"
                    style={{
                        background:
                            'color-mix(in srgb, var(--ind-text) 40%, transparent)',
                    }}
                />
            )}

            <div className="flex min-w-0 flex-1 flex-col">
                <header
                    className="flex h-14 flex-shrink-0 items-center justify-between gap-4 px-[clamp(16px,4vw,32px)]"
                    style={{ borderBottom: '1px solid var(--ind-divider)' }}
                >
                    <div className="flex min-w-0 items-center gap-3">
                        {isMobile && (
                            <button
                                type="button"
                                aria-label="Menu"
                                onClick={() => setNavOpen(true)}
                                className="ind-btn ind-btn-ghost ind-btn-icon"
                            >
                                <Menu size={18} strokeWidth={1.5} />
                            </button>
                        )}
                        <h6 className="m-0 truncate">{title}</h6>
                    </div>
                    <div className="flex flex-shrink-0 items-center gap-3">
                        {user.role_label && !isMobile && (
                            <Tag
                                label={`Melihat sebagai · ${user.role_label}`}
                                tone="neutral"
                            />
                        )}
                        <NotificationBell />
                    </div>
                </header>

                <div className="w-full max-w-[1180px] px-[clamp(16px,4vw,32px)] pt-[clamp(16px,4vw,32px)] pb-15">
                    {children}
                </div>
            </div>
        </div>
    );
}
