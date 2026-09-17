import { Form } from '@inertiajs/react';
import { Eye, EyeOff, LockKeyhole, RefreshCw } from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';
import AlertError from '@/components/alert-error';
import { Btn, Card } from '@/components/industry/ui';
import { regenerateRecoveryCodes } from '@/routes/two-factor';

type Props = {
    recoveryCodesList: string[];
    fetchRecoveryCodes: () => Promise<void>;
    errors: string[];
};

export default function TwoFactorRecoveryCodes({
    recoveryCodesList,
    fetchRecoveryCodes,
    errors,
}: Props) {
    const [codesAreVisible, setCodesAreVisible] = useState<boolean>(false);
    const codesSectionRef = useRef<HTMLDivElement | null>(null);
    const canRegenerateCodes = recoveryCodesList.length > 0 && codesAreVisible;

    const toggleCodesVisibility = useCallback(async () => {
        if (!codesAreVisible && !recoveryCodesList.length) {
            await fetchRecoveryCodes();
        }

        setCodesAreVisible(!codesAreVisible);

        if (!codesAreVisible) {
            setTimeout(() => {
                codesSectionRef.current?.scrollIntoView({
                    behavior: 'smooth',
                    block: 'nearest',
                });
            });
        }
    }, [codesAreVisible, recoveryCodesList.length, fetchRecoveryCodes]);

    useEffect(() => {
        if (!recoveryCodesList.length) {
            void fetchRecoveryCodes();
        }
    }, [recoveryCodesList.length, fetchRecoveryCodes]);

    const RecoveryCodeIconComponent = codesAreVisible ? EyeOff : Eye;

    return (
        <Card className="w-full">
            <div className="flex items-center gap-[10px]">
                <LockKeyhole size={16} strokeWidth={1.5} aria-hidden="true" />
                <span
                    className="text-[15px] font-semibold"
                    style={{ fontFamily: 'var(--ind-font-heading)' }}
                >
                    Kode Pemulihan 2FA
                </span>
            </div>
            <p className="ind-card-body m-0">
                Kode pemulihan digunakan untuk masuk apabila perangkat 2FA Anda
                hilang. Simpan di tempat yang aman, misalnya pengelola kata
                sandi.
            </p>
            <div>
                <div className="flex flex-col gap-3 select-none sm:flex-row sm:items-center sm:justify-between">
                    <Btn
                        variant="primary"
                        onClick={toggleCodesVisibility}
                        className="w-fit"
                        aria-expanded={codesAreVisible}
                        aria-controls="recovery-codes-section"
                    >
                        <RecoveryCodeIconComponent
                            className="size-4"
                            aria-hidden="true"
                        />
                        {codesAreVisible ? 'Sembunyikan' : 'Lihat'} Kode
                        Pemulihan
                    </Btn>

                    {canRegenerateCodes && (
                        <Form
                            {...regenerateRecoveryCodes.form()}
                            options={{ preserveScroll: true }}
                            onSuccess={fetchRecoveryCodes}
                        >
                            {({ processing }) => (
                                <Btn
                                    variant="secondary"
                                    type="submit"
                                    disabled={processing}
                                    aria-describedby="regenerate-warning"
                                >
                                    <RefreshCw size={16} strokeWidth={1.5} />
                                    {processing
                                        ? 'Membuat ulang...'
                                        : 'Buat Ulang Kode'}
                                </Btn>
                            )}
                        </Form>
                    )}
                </div>
                <div
                    id="recovery-codes-section"
                    className={`relative overflow-hidden transition-all duration-300 ${codesAreVisible ? 'h-auto opacity-100' : 'h-0 opacity-0'}`}
                    aria-hidden={!codesAreVisible}
                >
                    <div className="mt-3 space-y-3">
                        {errors?.length ? (
                            <AlertError errors={errors} />
                        ) : (
                            <>
                                <div
                                    ref={codesSectionRef}
                                    className="grid gap-1 p-4 font-mono text-[13px]"
                                    style={{ background: 'var(--ind-surface)' }}
                                    role="list"
                                    aria-label="Kode pemulihan"
                                >
                                    {recoveryCodesList.length ? (
                                        recoveryCodesList.map((code, index) => (
                                            <div
                                                key={index}
                                                role="listitem"
                                                className="select-text"
                                            >
                                                {code}
                                            </div>
                                        ))
                                    ) : (
                                        <div
                                            className="space-y-2"
                                            aria-label="Memuat kode pemulihan"
                                        >
                                            {Array.from(
                                                { length: 8 },
                                                (_, index) => (
                                                    <div
                                                        key={index}
                                                        className="h-4 animate-pulse"
                                                        style={{
                                                            background:
                                                                'var(--ind-neutral-300)',
                                                        }}
                                                        aria-hidden="true"
                                                    />
                                                ),
                                            )}
                                        </div>
                                    )}
                                </div>

                                <div className="ind-muted text-[12px] select-none">
                                    <p id="regenerate-warning" className="m-0">
                                        Setiap kode pemulihan hanya dapat
                                        dipakai satu kali dan otomatis hangus
                                        setelah digunakan. Apabila kode Anda
                                        menipis, tekan{' '}
                                        <span className="font-semibold">
                                            Buat Ulang Kode
                                        </span>{' '}
                                        di atas.
                                    </p>
                                </div>
                            </>
                        )}
                    </div>
                </div>
            </div>
        </Card>
    );
}
