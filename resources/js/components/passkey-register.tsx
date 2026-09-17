import { usePasskeyRegister } from '@laravel/passkeys/react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Btn, Input } from '@/components/industry/ui';

type Props = {
    onSuccess: () => void;
};

export default function PasskeyRegistration({ onSuccess }: Props) {
    const [name, setName] = useState(() => {
        const ua = navigator.userAgent;

        const browser = [
            { pattern: /Edg|Edge/, name: 'Edge' },
            { pattern: /OPR|Opera|OPiOS/, name: 'Opera' },
            { pattern: /Firefox|FxiOS/, name: 'Firefox' },
            { pattern: /Chrome|CriOS/, name: 'Chrome' },
            { pattern: /Safari/, name: 'Safari' },
        ].find(({ pattern }) => pattern.test(ua))?.name;

        const os = [
            { pattern: /iPhone/, name: 'iPhone' },
            { pattern: /iPad|Macintosh(?=.*Mobile)/, name: 'iPad' },
            { pattern: /Android/, name: 'Android' },
            { pattern: /Mac/, name: 'Mac' },
            { pattern: /Windows/, name: 'Windows' },
        ].find(({ pattern }) => pattern.test(ua))?.name;

        return [browser, os].filter(Boolean).join(' on ') || '';
    });

    const [showForm, setShowForm] = useState(false);
    const { register, isLoading, error, isSupported } = usePasskeyRegister({
        onSuccess: () => {
            setName('');
            setShowForm(false);
            onSuccess();
        },
    });

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();

        if (!name.trim()) {
            return;
        }

        await register(name);
    };

    const handleCancel = () => {
        setShowForm(false);
        setName('');
    };

    if (!isSupported) {
        return (
            <div className="ind-muted text-[13px]">
                Peramban ini belum mendukung passkey.
            </div>
        );
    }

    if (!showForm) {
        return (
            <Btn variant="secondary" onClick={() => setShowForm(true)}>
                Tambah Passkey
            </Btn>
        );
    }

    return (
        <form
            onSubmit={handleSubmit}
            className="flex flex-col gap-[var(--ind-space-4)] border p-4"
            style={{
                borderColor: 'var(--ind-divider)',
                background: 'var(--ind-surface)',
            }}
        >
            <div className="grid gap-2">
                <label className="ind-label" htmlFor="passkey-name">
                    Nama Passkey
                </label>
                <Input
                    id="passkey-name"
                    type="text"
                    value={name}
                    onChange={(e) => setName(e.target.value)}
                    placeholder="e.g., MacBook Pro, iPhone"
                    className="border-foreground/20 mt-1 block w-full"
                    autoFocus
                />
                <p className="text-muted-foreground text-xs">
                    A name helps you identify this passkey later.
                </p>
            </div>

            {error && <InputError message={error} />}

            <div className="flex gap-2">
                <Btn
                    variant="primary"
                    type="submit"
                    disabled={isLoading || !name.trim()}
                >
                    {isLoading ? 'Mendaftarkan...' : 'Daftarkan Passkey'}
                </Btn>
                <Btn type="button" variant="ghost" onClick={handleCancel}>
                    Batal
                </Btn>
            </div>
        </form>
    );
}
