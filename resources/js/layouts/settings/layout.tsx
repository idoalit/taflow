import { Link } from '@inertiajs/react';
import type { PropsWithChildren } from 'react';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { cn, toUrl } from '@/lib/utils';
import { edit as editAppearance } from '@/routes/appearance';
import { edit as editProfile } from '@/routes/profile';
import { edit as editSecurity } from '@/routes/security';

const settingsNavItems = [
    { title: 'Profil', href: editProfile() },
    { title: 'Keamanan', href: editSecurity() },
    { title: 'Tampilan', href: editAppearance() },
];

/**
 * The account section, drawn in the Industry system so it reads as part of
 * FiPro rather than a separate app. Sits inside IndustryLayout, which supplies
 * the sidebar and the header title.
 */
export default function SettingsLayout({ children }: PropsWithChildren) {
    const { isCurrentOrParentUrl } = useCurrentUrl();

    return (
        <div className="flex flex-col gap-[var(--ind-space-6)]">
            <div>
                <h3 className="mb-1">Pengaturan Akun</h3>
                <p className="ind-muted m-0">
                    Kelola profil, keamanan, dan tampilan akun FiPro Anda.
                </p>
            </div>

            <div
                className="flex gap-[var(--ind-space-4)] overflow-x-auto"
                style={{ borderBottom: '1px solid var(--ind-divider)' }}
                aria-label="Pengaturan"
            >
                {settingsNavItems.map((item) => {
                    const current = isCurrentOrParentUrl(item.href);

                    return (
                        <Link
                            key={toUrl(item.href)}
                            href={item.href}
                            className={cn('-mb-px py-[10px] whitespace-nowrap')}
                            aria-current={current ? 'page' : undefined}
                            style={{
                                fontFamily: 'var(--ind-font-heading)',
                                fontSize: 14,
                                fontWeight: current ? 600 : 400,
                                color: current
                                    ? 'var(--ind-accent-700)'
                                    : 'color-mix(in srgb, var(--ind-text) 60%, transparent)',
                                borderBottom: `2px solid ${current ? 'var(--ind-accent)' : 'transparent'}`,
                                marginRight: 20,
                                textDecoration: 'none',
                            }}
                        >
                            {item.title}
                        </Link>
                    );
                })}
            </div>

            <div className="flex max-w-[640px] flex-col gap-[var(--ind-space-8)]">
                {children}
            </div>
        </div>
    );
}
