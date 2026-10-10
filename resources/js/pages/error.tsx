import { Head, usePage } from '@inertiajs/react';
import { ArrowLeft, Home } from 'lucide-react';
import { BlueprintCorners } from '@/components/industry/blueprint';
import { Btn, BtnLink } from '@/components/industry/ui';
import { dashboard, login } from '@/routes';

type ErrorStatus = 403 | 404 | 419 | 500 | 503;

/**
 * Copy is written for the person who hit the wall, not for a log reader: what
 * happened, why it probably happened, and the way out.
 */
const MESSAGES: Record<ErrorStatus, { title: string; description: string }> = {
    403: {
        title: 'Halaman ini tidak dapat diakses',
        description:
            'Anda tidak memiliki akses ke halaman tersebut, atau syaratnya belum terpenuhi. Periksa kembali status tugas akhir Anda, atau hubungi admin program studi jika menurut Anda ini keliru.',
    },
    404: {
        title: 'Halaman tidak ditemukan',
        description:
            'Alamat yang Anda buka tidak ada atau datanya sudah dihapus. Coba kembali ke dashboard dan ulangi dari menu.',
    },
    419: {
        title: 'Sesi Anda telah berakhir',
        description:
            'Halaman ini terbuka terlalu lama sehingga sesi keamanannya kedaluwarsa. Muat ulang halaman, lalu coba lagi.',
    },
    500: {
        title: 'Terjadi kesalahan pada sistem',
        description:
            'Ada gangguan di sisi server, bukan pada data Anda. Silakan coba beberapa saat lagi — jika terus berulang, laporkan ke admin.',
    },
    503: {
        title: 'Sistem sedang dalam pemeliharaan',
        description:
            'Aplikasi sedang diperbarui sebentar. Silakan coba kembali beberapa menit lagi.',
    },
};

export default function ErrorPage({
    status,
    reason,
}: {
    status: ErrorStatus;
    reason?: string | null;
}) {
    const { title, description } = MESSAGES[status] ?? MESSAGES[500];
    const { auth, name } = usePage().props;
    const isAuthenticated = Boolean(auth?.user);

    return (
        <div className="ind flex min-h-screen w-full items-center justify-center px-[clamp(16px,4vw,32px)] py-[clamp(24px,6vw,64px)]">
            <Head title={title} />

            <div className="w-full max-w-[560px]">
                <div
                    className="font-semibold"
                    style={{
                        fontFamily: 'var(--ind-font-heading)',
                        fontSize: 'clamp(48px, 12vw, 72px)',
                        lineHeight: 1,
                        color: 'var(--ind-accent-700)',
                    }}
                >
                    {status}
                </div>

                <h3 className="mt-[var(--ind-space-3)] mb-[var(--ind-space-2)]">
                    {title}
                </h3>
                <p className="ind-muted m-0">{description}</p>

                {reason && (
                    <div
                        className="ind-bp mt-[var(--ind-space-6)] border p-[var(--ind-space-4)]"
                        style={{ borderColor: 'var(--ind-divider)' }}
                    >
                        <BlueprintCorners />
                        <div className="ind-kicker">Penjelasan</div>
                        <p className="m-0 mt-1 text-[13.5px] leading-[1.55]">
                            {reason}
                        </p>
                    </div>
                )}

                <div className="mt-[var(--ind-space-8)] flex flex-wrap gap-[var(--ind-space-3)]">
                    {isAuthenticated ? (
                        <BtnLink variant="primary" href={dashboard()}>
                            <Home size={16} strokeWidth={1.5} />
                            Kembali ke Dashboard
                        </BtnLink>
                    ) : (
                        <BtnLink variant="primary" href={login()}>
                            {name ? `Masuk ke ${name}` : 'Masuk'}
                        </BtnLink>
                    )}

                    <Btn onClick={() => window.history.back()}>
                        <ArrowLeft size={16} strokeWidth={1.5} />
                        Halaman Sebelumnya
                    </Btn>
                </div>
            </div>
        </div>
    );
}
