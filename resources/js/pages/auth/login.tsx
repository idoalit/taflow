import { Form, Head, Link, router, usePage } from '@inertiajs/react';
import { usePasskeyVerify } from '@laravel/passkeys/react';
import { KeyRound } from 'lucide-react';
import { BlueprintCorners } from '@/components/industry/blueprint';
import PasswordInput from '@/components/industry/password-input';
import { ThesisJourney } from '@/components/industry/thesis-journey';
import { Btn, Field, Input } from '@/components/industry/ui';
import { Spinner } from '@/components/ui/spinner';
import { store } from '@/routes/login';
import { request } from '@/routes/password';

type Institution = {
    name: string;
    unit: string | null;
    logo: string | null;
};

type Props = {
    status?: string;
    canResetPassword: boolean;
    institution: Institution;
};

/**
 * Whose application this is. The institution's logo when it has uploaded one;
 * until then the application's own mark, so the corner is never empty.
 */
function Brand({ institution }: { institution: Institution }) {
    const { name } = usePage().props;

    return (
        <div className="flex items-center gap-[var(--ind-space-4)]">
            {institution.logo ? (
                <img
                    src={institution.logo}
                    alt=""
                    className="h-11 w-11 shrink-0 object-contain"
                />
            ) : (
                <div
                    aria-hidden
                    className="ind-bp flex h-11 w-11 shrink-0 items-center justify-center border text-[19px] font-semibold"
                    style={{
                        fontFamily: 'var(--ind-font-heading)',
                        borderColor: 'var(--ind-divider)',
                        color: 'var(--ind-accent-700)',
                    }}
                >
                    <BlueprintCorners />
                    {name.slice(0, 2)}
                </div>
            )}

            <div className="min-w-0">
                <div className="ind-eyebrow truncate">{institution.name}</div>
                <div
                    className="text-[24px] leading-[1.1] font-semibold"
                    style={{
                        fontFamily: 'var(--ind-font-heading)',
                        letterSpacing: '-0.01em',
                    }}
                >
                    {name}
                </div>
            </div>
        </div>
    );
}

/**
 * Offered only where the browser can do it, and above the form: someone with a
 * passkey has no reason to type anything.
 */
function PasskeySignIn() {
    const { verify, isLoading, error, isSupported } = usePasskeyVerify({
        onSuccess: (response) => {
            router.visit(response.redirect ?? '/dashboard');
        },
    });

    if (!isSupported) {
        return null;
    }

    return (
        <>
            <Btn block onClick={verify} disabled={isLoading} className="py-2">
                {isLoading ? (
                    <Spinner />
                ) : (
                    <KeyRound size={16} strokeWidth={1.5} />
                )}
                {isLoading ? 'Memeriksa passkey…' : 'Masuk dengan passkey'}
            </Btn>
            {error && (
                <p
                    className="mt-[5px] mb-0 text-[11.5px]"
                    style={{ color: 'var(--ind-status-error-fg)' }}
                >
                    {error}
                </p>
            )}

            <div className="my-[var(--ind-space-4)] flex items-center gap-[var(--ind-space-3)]">
                <span
                    className="h-px flex-1"
                    style={{ background: 'var(--ind-divider)' }}
                />
                <span className="ind-eyebrow">atau dengan surel</span>
                <span
                    className="h-px flex-1"
                    style={{ background: 'var(--ind-divider)' }}
                />
            </div>
        </>
    );
}

/**
 * The front door: `/` and `/login` both land here. The form on one side, and
 * on the other what the application is for — hidden on a narrow screen, where
 * the form is what the person came for.
 */
export default function Login({
    status,
    canResetPassword,
    institution,
}: Props) {
    return (
        <div className="ind grid min-h-dvh lg:grid-cols-[minmax(440px,2fr)_3fr]">
            <Head title="Masuk" />

            <main className="flex min-h-dvh flex-col px-[clamp(20px,5vw,56px)] py-[clamp(16px,3vh,40px)]">
                <Brand institution={institution} />

                {/* On the wide layout the form keeps to the brand's left edge;
                    alone on a narrow one it sits in the middle. */}
                <div className="flex flex-1 items-center justify-center py-[var(--ind-space-6)] lg:justify-start">
                    <div className="w-full max-w-[400px]">
                        <span className="ind-kicker">Masuk</span>
                        <h2 className="mt-1">Masuk ke akun Anda</h2>
                        <p className="ind-muted text-[14px]">
                            Gunakan surel dan kata sandi akun Anda.
                        </p>

                        {status && (
                            <p
                                role="status"
                                className="mt-[var(--ind-space-4)] mb-0 px-[var(--ind-space-3)] py-[var(--ind-space-2)] text-[13px]"
                                style={{
                                    background: 'var(--ind-status-success-bg)',
                                    color: 'var(--ind-status-success-fg)',
                                }}
                            >
                                {status}
                            </p>
                        )}

                        <div className="mt-[var(--ind-space-6)]">
                            <PasskeySignIn />

                            <Form
                                {...store.form()}
                                resetOnSuccess={['password']}
                                className="flex flex-col gap-[var(--ind-space-4)]"
                            >
                                {({ processing, errors }) => (
                                    <>
                                        <Field
                                            label="Surel"
                                            error={errors.email}
                                        >
                                            <Input
                                                id="email"
                                                type="email"
                                                name="email"
                                                required
                                                autoFocus
                                                tabIndex={1}
                                                autoComplete="email"
                                                placeholder="nama@kampus.ac.id"
                                            />
                                        </Field>

                                        <div>
                                            <div className="flex items-baseline justify-between gap-3">
                                                <label
                                                    className="ind-label"
                                                    htmlFor="password"
                                                >
                                                    Kata Sandi
                                                </label>
                                                {canResetPassword && (
                                                    <Link
                                                        href={request()}
                                                        className="text-[12px]"
                                                        tabIndex={5}
                                                    >
                                                        Lupa kata sandi?
                                                    </Link>
                                                )}
                                            </div>
                                            <PasswordInput
                                                id="password"
                                                name="password"
                                                required
                                                tabIndex={2}
                                                autoComplete="current-password"
                                            />
                                            {errors.password && (
                                                <p
                                                    className="mt-[5px] mb-0 text-[11.5px]"
                                                    style={{
                                                        color: 'var(--ind-status-error-fg)',
                                                    }}
                                                >
                                                    {errors.password}
                                                </p>
                                            )}
                                        </div>

                                        <label className="ind-check">
                                            <input
                                                type="checkbox"
                                                name="remember"
                                                tabIndex={3}
                                            />
                                            Ingat saya di perangkat ini
                                        </label>

                                        <Btn
                                            type="submit"
                                            variant="primary"
                                            block
                                            className="mt-[var(--ind-space-2)] py-[10px] text-[15px]"
                                            tabIndex={4}
                                            disabled={processing}
                                            data-test="login-button"
                                        >
                                            {processing && <Spinner />}
                                            Masuk
                                        </Btn>
                                    </>
                                )}
                            </Form>
                        </div>

                        {canResetPassword && (
                            <p
                                className="ind-muted mt-[var(--ind-space-6)] mb-0 border-t pt-[var(--ind-space-4)] text-[12.5px]"
                                style={{ borderColor: 'var(--ind-divider)' }}
                            >
                                Belum pernah masuk? Akun Anda sudah dibuatkan
                                oleh program studi. Setel kata sandinya lewat{' '}
                                <Link href={request()}>Lupa kata sandi</Link>.
                            </p>
                        )}
                    </div>
                </div>

                <footer className="ind-muted text-[12px]">
                    © {new Date().getFullYear()} {institution.name}
                    {institution.unit && ` · ${institution.unit}`}
                </footer>
            </main>

            <aside className="hidden lg:block">
                <ThesisJourney />
            </aside>
        </div>
    );
}
