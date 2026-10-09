import { router, usePage } from '@inertiajs/react';
import { Eye } from 'lucide-react';
import { useState } from 'react';
import { Btn } from '@/components/industry/ui';
import { destroy as stopImpersonating } from '@/routes/impersonation';

/** Leave the account being looked at and return to the superadmin's own. */
export function leaveImpersonation(options: { onFinish?: () => void } = {}) {
    router.delete(stopImpersonating().url, options);
}

/**
 * Shown on every page while a superadmin is looking through somebody else's
 * account. It says whose screen this is and that nothing here can be changed —
 * every button still looks live, because the point is to see what the owner
 * sees — and it is the way back out.
 */
export function ImpersonationBanner() {
    const { impersonation, auth } = usePage().props;
    const [leaving, setLeaving] = useState(false);

    if (impersonation === null) {
        return null;
    }

    return (
        <div
            role="status"
            className="flex flex-shrink-0 flex-wrap items-center justify-between gap-x-4 gap-y-2 px-[clamp(16px,4vw,32px)] py-2 text-[13px]"
            style={{
                background: 'var(--ind-status-warning-bg)',
                color: 'var(--ind-status-warning-fg)',
                borderBottom: '1px solid var(--ind-divider)',
            }}
        >
            <span className="flex min-w-0 items-center gap-[8px]">
                <Eye size={16} strokeWidth={1.5} className="flex-shrink-0" />
                <span>
                    Anda melihat sebagai <strong>{auth.user.name}</strong>. Mode
                    lihat saja: tidak ada yang dapat diubah.
                </span>
            </span>
            <Btn
                disabled={leaving}
                onClick={() => {
                    setLeaving(true);
                    leaveImpersonation({ onFinish: () => setLeaving(false) });
                }}
            >
                {leaving ? 'Kembali...' : 'Kembali ke Akun Saya'}
            </Btn>
        </div>
    );
}
