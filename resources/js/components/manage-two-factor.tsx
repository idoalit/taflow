import { Form, usePage } from '@inertiajs/react';
import { ShieldCheck } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import TwoFactorRecoveryCodes from '@/components/two-factor-recovery-codes';
import TwoFactorSetupModal from '@/components/two-factor-setup-modal';
import { Btn } from '@/components/industry/ui';
import { useTwoFactorAuth } from '@/hooks/use-two-factor-auth';
import { disable, enable } from '@/routes/two-factor';

export type Props = {
    canManageTwoFactor?: boolean;
    requiresConfirmation?: boolean;
    twoFactorEnabled?: boolean;
};

export default function ManageTwoFactor(props: Props) {
    const { name } = usePage().props;
    const requiresConfirmation = props.requiresConfirmation ?? false;
    const twoFactorEnabled = props.twoFactorEnabled ?? false;

    const {
        qrCodeSvg,
        hasSetupData,
        manualSetupKey,
        clearSetupData,
        clearTwoFactorAuthData,
        fetchSetupData,
        recoveryCodesList,
        fetchRecoveryCodes,
        errors,
    } = useTwoFactorAuth();
    const [showSetupModal, setShowSetupModal] = useState<boolean>(false);
    const prevTwoFactorEnabled = useRef(twoFactorEnabled);

    useEffect(() => {
        if (prevTwoFactorEnabled.current && !twoFactorEnabled) {
            clearTwoFactorAuthData();
        }

        prevTwoFactorEnabled.current = twoFactorEnabled;
    }, [twoFactorEnabled, clearTwoFactorAuthData]);

    if (!(props.canManageTwoFactor ?? false)) {
        return null;
    }

    return (
        <section className="flex flex-col gap-[var(--ind-space-4)]">
            <div>
                <h6 className="m-0">Autentikasi Dua Faktor</h6>
                <p className="ind-muted m-0 text-[13px]">
                    Kelola lapisan keamanan tambahan untuk akun {name} Anda.
                </p>
            </div>
            {twoFactorEnabled ? (
                <div className="flex flex-col items-start gap-[var(--ind-space-4)]">
                    <p className="ind-muted m-0 text-[13.5px]">
                        Saat masuk, Anda akan diminta memasukkan kode PIN acak
                        yang dapat dilihat pada aplikasi autentikator (TOTP) di
                        ponsel Anda.
                    </p>

                    <div className="relative inline">
                        <Form {...disable.form()}>
                            {({ processing }) => (
                                <Btn
                                    variant="danger"
                                    type="submit"
                                    disabled={processing}
                                >
                                    {processing
                                        ? 'Menonaktifkan...'
                                        : 'Nonaktifkan 2FA'}
                                </Btn>
                            )}
                        </Form>
                    </div>

                    <TwoFactorRecoveryCodes
                        recoveryCodesList={recoveryCodesList}
                        fetchRecoveryCodes={fetchRecoveryCodes}
                        errors={errors}
                    />
                </div>
            ) : (
                <div className="flex flex-col items-start gap-[var(--ind-space-4)]">
                    <p className="ind-muted m-0 text-[13.5px]">
                        Setelah autentikasi dua faktor aktif, Anda akan diminta
                        memasukkan kode PIN setiap kali masuk. Kode tersebut
                        dihasilkan oleh aplikasi autentikator (TOTP) di ponsel
                        Anda.
                    </p>

                    <div>
                        {hasSetupData ? (
                            <Btn
                                variant="primary"
                                onClick={() => setShowSetupModal(true)}
                            >
                                <ShieldCheck size={16} strokeWidth={1.5} />
                                Lanjutkan Pengaturan
                            </Btn>
                        ) : (
                            <Form
                                {...enable.form()}
                                onSuccess={() => setShowSetupModal(true)}
                            >
                                {({ processing }) => (
                                    <Btn
                                        variant="primary"
                                        type="submit"
                                        disabled={processing}
                                    >
                                        {processing
                                            ? 'Mengaktifkan...'
                                            : 'Aktifkan 2FA'}
                                    </Btn>
                                )}
                            </Form>
                        )}
                    </div>
                </div>
            )}

            <TwoFactorSetupModal
                isOpen={showSetupModal}
                onClose={() => setShowSetupModal(false)}
                requiresConfirmation={requiresConfirmation}
                twoFactorEnabled={twoFactorEnabled}
                qrCodeSvg={qrCodeSvg}
                manualSetupKey={manualSetupKey}
                clearSetupData={clearSetupData}
                fetchSetupData={fetchSetupData}
                errors={errors}
            />
        </section>
    );
}
