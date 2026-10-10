import { Link, usePage } from '@inertiajs/react';
import { ChevronsUpDown, LogOut, UserRound } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { leaveImpersonation } from '@/components/industry/impersonation-banner';
import { Avatar } from '@/components/industry/ui';
import { brandTagline, navigationFor } from '@/layouts/industry/navigation';
import { logout } from '@/routes';
import { edit as editProfile } from '@/routes/profile';

/**
 * Highlight the current item, including when the user has drilled into a
 * detail or create screen underneath it.
 */
function isCurrent(href: string, pathname: string): boolean {
    const path = new URL(href, 'http://localhost').pathname;

    return pathname === path || pathname.startsWith(`${path}/`);
}

export function IndustrySidebar({ onNavigate }: { onNavigate?: () => void }) {
    const page = usePage();
    const user = page.props.auth.user;
    const pathname = new URL(page.url, 'http://localhost').pathname;
    const [accountOpen, setAccountOpen] = useState(false);
    const accountRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
        if (!accountOpen) {
            return;
        }

        const close = (event: MouseEvent) => {
            if (!accountRef.current?.contains(event.target as Node)) {
                setAccountOpen(false);
            }
        };

        document.addEventListener('mousedown', close);

        return () => document.removeEventListener('mousedown', close);
    }, [accountOpen]);

    return (
        <div className="flex h-full flex-col">
            <div className="mb-[var(--ind-space-8)]">
                <div
                    className="text-2xl font-semibold"
                    style={{
                        fontFamily: 'var(--ind-font-heading)',
                        letterSpacing: '-0.01em',
                    }}
                >
                    {page.props.name}
                </div>
                <div
                    className="mt-[2px] text-[11px] tracking-[0.08em] uppercase"
                    style={{ color: 'var(--ind-accent-700)' }}
                >
                    {brandTagline(user.role)}
                </div>
            </div>

            <nav className="flex-1 overflow-y-auto">
                {navigationFor(
                    user.role,
                    page.props.thesis,
                    user.permissions,
                ).map((group) => (
                    <div key={group.label}>
                        <div className="ind-eyebrow px-2 pt-4 pb-1 first:pt-2">
                            {group.label}
                        </div>
                        {group.items.map((item) => {
                            const current = isCurrent(item.href, pathname);
                            const Icon = item.icon;

                            return (
                                <Link
                                    key={item.href}
                                    href={item.href}
                                    prefetch
                                    onClick={onNavigate}
                                    className="ind-nav-item"
                                    aria-current={current ? 'page' : undefined}
                                >
                                    <Icon
                                        size={18}
                                        strokeWidth={1.5}
                                        style={{
                                            color: current
                                                ? 'var(--ind-accent-700)'
                                                : 'var(--ind-neutral-600)',
                                        }}
                                    />
                                    <span>{item.label}</span>
                                </Link>
                            );
                        })}
                    </div>
                ))}
            </nav>

            <div className="relative" ref={accountRef}>
                <button
                    type="button"
                    onClick={() => setAccountOpen((open) => !open)}
                    className="mt-[var(--ind-space-3)] flex w-full cursor-pointer items-center gap-[10px] border-0 border-t bg-transparent pt-[var(--ind-space-3)] text-left"
                    style={{
                        borderTopColor: 'var(--ind-divider)',
                        borderTopStyle: 'solid',
                        borderTopWidth: 1,
                    }}
                    aria-expanded={accountOpen}
                    aria-label="Menu akun"
                >
                    <Avatar initials={user.initials} size={32} />
                    <span className="min-w-0 flex-1">
                        <span className="block truncate text-[13px] font-semibold">
                            {user.name}
                        </span>
                        <span className="ind-muted block truncate text-[11px]">
                            {user.role_label ?? 'Pengguna'}
                        </span>
                    </span>
                    <ChevronsUpDown size={16} strokeWidth={1.5} />
                </button>

                {accountOpen && (
                    <div
                        className="ind-bp absolute right-0 bottom-[calc(100%+6px)] left-0 z-80 border p-[var(--ind-space-2)]"
                        style={{
                            background: 'var(--ind-bg)',
                            borderColor: 'var(--ind-divider)',
                            boxShadow: 'var(--ind-shadow-lg)',
                        }}
                    >
                        <i className="ind-corner tl" />
                        <i className="ind-corner tr" />
                        <i className="ind-corner bl" />
                        <i className="ind-corner br" />

                        <div className="ind-eyebrow px-2 py-[6px]">Akun</div>
                        <Link
                            href={editProfile()}
                            className="ind-nav-item"
                            onClick={onNavigate}
                        >
                            <UserRound size={16} strokeWidth={1.5} />
                            <span>Profil Saya</span>
                        </Link>
                        {page.props.impersonation === null ? (
                            <Link
                                href={logout()}
                                as="button"
                                className="ind-nav-item"
                                style={{ color: 'var(--ind-accent-900)' }}
                            >
                                <LogOut size={16} strokeWidth={1.5} />
                                <span>Keluar</span>
                            </Link>
                        ) : (
                            <button
                                type="button"
                                className="ind-nav-item"
                                style={{ color: 'var(--ind-accent-900)' }}
                                onClick={() => leaveImpersonation()}
                            >
                                <LogOut size={16} strokeWidth={1.5} />
                                <span>Kembali ke Akun Saya</span>
                            </button>
                        )}
                    </div>
                )}
            </div>
        </div>
    );
}
