import { router } from '@inertiajs/react';
import { KeyRound } from 'lucide-react';
import { destroy } from '@/actions/Laravel/Passkeys/Http/Controllers/PasskeyRegistrationController';
import PasskeyItem from '@/components/passkey-item';
import PasskeyRegistration from '@/components/passkey-register';
import type { Passkey } from '@/types/auth';

export type Props = {
    canManagePasskeys?: boolean;
    passkeys?: Passkey[];
};

const EmptyState = () => {
    return (
        <div className="p-8 text-center">
            <div
                className="mx-auto mb-4 flex h-14 w-14 items-center justify-center"
                style={{ background: 'var(--ind-surface)' }}
            >
                <KeyRound size={24} strokeWidth={1.5} />
            </div>
            <p className="m-0 font-semibold">Belum ada passkey</p>
            <p className="ind-muted m-0 mt-1 text-[13px]">
                Tambahkan passkey untuk masuk tanpa kata sandi.
            </p>
        </div>
    );
};

export default function ManagePasskeys(props: Props) {
    const passkeys = props.passkeys ?? [];

    const handleDelete = (id: number, onError: () => void) => {
        router.delete(destroy.url(id), {
            preserveScroll: true,
            onError,
        });
    };

    const handleRegisterSuccess = () => {
        router.reload();
    };

    if (!(props.canManagePasskeys ?? false)) {
        return null;
    }

    return (
        <section className="flex flex-col gap-[var(--ind-space-4)]">
            <div>
                <h6 className="m-0">Passkey</h6>
                <p className="ind-muted m-0 text-[13px]">
                    Kelola passkey untuk masuk tanpa kata sandi.
                </p>
            </div>

            <div
                className="overflow-hidden border"
                style={{ borderColor: 'var(--ind-divider)' }}
            >
                {passkeys.length > 0 ? (
                    passkeys.map((passkey) => (
                        <PasskeyItem
                            key={passkey.id}
                            passkey={passkey}
                            onDelete={handleDelete}
                        />
                    ))
                ) : (
                    <EmptyState />
                )}
            </div>

            <PasskeyRegistration onSuccess={handleRegisterSuccess} />
        </section>
    );
}
