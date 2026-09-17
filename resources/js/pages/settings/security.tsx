import { Form, Head, setLayoutProps } from '@inertiajs/react';
import { useRef } from 'react';
import SecurityController from '@/actions/App/Http/Controllers/Settings/SecurityController';
import { Btn, Field } from '@/components/industry/ui';
import PasswordInput from '@/components/industry/password-input';
import type { Props as ManagePasskeysProps } from '@/components/manage-passkeys';
import ManagePasskeys from '@/components/manage-passkeys';
import type { Props as ManageTwoFactorProps } from '@/components/manage-two-factor';
import ManageTwoFactor from '@/components/manage-two-factor';

// oxfmt-ignore
type Props = {
    passwordRules: string;
} & ManagePasskeysProps &
    ManageTwoFactorProps;

export default function Security(props: Props) {
    setLayoutProps({ title: 'Pengaturan Akun' });

    const passwordInput = useRef<HTMLInputElement>(null);
    const currentPasswordInput = useRef<HTMLInputElement>(null);

    return (
        <>
            <Head title="Keamanan" />

            <section className="flex flex-col gap-[var(--ind-space-4)]">
                <div>
                    <h6 className="m-0">Ubah Kata Sandi</h6>
                    <p className="ind-muted m-0 text-[13px]">
                        Gunakan kata sandi yang panjang dan acak agar akun Anda
                        tetap aman.
                    </p>
                </div>

                <Form
                    {...SecurityController.update.form()}
                    options={{
                        preserveScroll: true,
                    }}
                    resetOnError={[
                        'password',
                        'password_confirmation',
                        'current_password',
                    ]}
                    resetOnSuccess
                    onError={(errors) => {
                        if (errors.password) {
                            passwordInput.current?.focus();
                        }

                        if (errors.current_password) {
                            currentPasswordInput.current?.focus();
                        }
                    }}
                    className="flex flex-col gap-[var(--ind-space-4)]"
                >
                    {({ errors, processing }) => (
                        <>
                            <Field
                                label="Kata Sandi Saat Ini"
                                error={errors.current_password}
                            >
                                <PasswordInput
                                    id="current_password"
                                    ref={currentPasswordInput}
                                    name="current_password"
                                    autoComplete="current-password"
                                    placeholder="Kata sandi saat ini"
                                />
                            </Field>

                            <Field
                                label="Kata Sandi Baru"
                                error={errors.password}
                            >
                                <PasswordInput
                                    id="password"
                                    ref={passwordInput}
                                    name="password"
                                    autoComplete="new-password"
                                    placeholder="Kata sandi baru"
                                    passwordrules={props.passwordRules}
                                />
                            </Field>

                            <Field
                                label="Konfirmasi Kata Sandi"
                                error={errors.password_confirmation}
                            >
                                <PasswordInput
                                    id="password_confirmation"
                                    name="password_confirmation"
                                    autoComplete="new-password"
                                    placeholder="Ulangi kata sandi baru"
                                    passwordrules={props.passwordRules}
                                />
                            </Field>

                            <div className="flex items-center gap-[var(--ind-space-4)]">
                                <Btn
                                    type="submit"
                                    variant="primary"
                                    disabled={processing}
                                    data-test="update-password-button"
                                >
                                    {processing
                                        ? 'Menyimpan...'
                                        : 'Simpan Kata Sandi'}
                                </Btn>
                            </div>
                        </>
                    )}
                </Form>
            </section>

            <ManageTwoFactor
                canManageTwoFactor={props.canManageTwoFactor}
                requiresConfirmation={props.requiresConfirmation}
                twoFactorEnabled={props.twoFactorEnabled}
            />

            <ManagePasskeys
                canManagePasskeys={props.canManagePasskeys}
                passkeys={props.passkeys}
            />
        </>
    );
}
