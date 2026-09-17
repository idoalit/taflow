import { Form, Head, setLayoutProps, usePage } from '@inertiajs/react';
import { CheckCircle2 } from 'lucide-react';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import { Avatar, Btn, Field, Input } from '@/components/industry/ui';
import type { Auth } from '@/types';

export default function Profile() {
    setLayoutProps({ title: 'Pengaturan Akun' });

    const { auth } = usePage<{ auth: Auth }>().props;

    return (
        <>
            <Head title="Profil Saya" />

            <section className="flex flex-col gap-[var(--ind-space-6)]">
                <div className="flex items-center gap-[var(--ind-space-4)]">
                    <Avatar initials={auth.user.initials} size={48} />
                    <div className="min-w-0">
                        <div
                            className="text-[17px] font-semibold"
                            style={{ fontFamily: 'var(--ind-font-heading)' }}
                        >
                            {auth.user.name}
                        </div>
                        <div className="ind-muted text-[13px]">
                            {auth.user.role_label ?? 'Pengguna'} ·{' '}
                            {auth.user.email}
                        </div>
                    </div>
                </div>

                <div>
                    <h6 className="mb-[var(--ind-space-3)]">
                        Informasi Profil
                    </h6>

                    <Form
                        {...ProfileController.update.form()}
                        options={{ preserveScroll: true }}
                        className="flex flex-col gap-[var(--ind-space-4)]"
                    >
                        {({ processing, errors, recentlySuccessful }) => (
                            <>
                                <Field label="Nama Lengkap" error={errors.name}>
                                    <Input
                                        id="name"
                                        name="name"
                                        required
                                        autoComplete="name"
                                        defaultValue={auth.user.name}
                                        placeholder="Nama lengkap Anda"
                                    />
                                </Field>

                                <Field
                                    label="Alamat Email"
                                    error={errors.email}
                                >
                                    <Input
                                        id="email"
                                        name="email"
                                        type="email"
                                        required
                                        autoComplete="username"
                                        defaultValue={auth.user.email}
                                        placeholder="nama@kampus.ac.id"
                                    />
                                </Field>

                                <div className="flex items-center gap-[var(--ind-space-4)]">
                                    <Btn
                                        type="submit"
                                        variant="primary"
                                        disabled={processing}
                                        data-test="update-profile-button"
                                    >
                                        {processing
                                            ? 'Menyimpan...'
                                            : 'Simpan Perubahan'}
                                    </Btn>

                                    {recentlySuccessful && (
                                        <span
                                            className="flex items-center gap-[6px] text-[13px]"
                                            style={{
                                                color: 'var(--ind-status-success-fg)',
                                            }}
                                        >
                                            <CheckCircle2
                                                size={15}
                                                strokeWidth={1.5}
                                            />
                                            Tersimpan
                                        </span>
                                    )}
                                </div>
                            </>
                        )}
                    </Form>
                </div>
            </section>
        </>
    );
}
