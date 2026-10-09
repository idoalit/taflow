import { Head, Link, router, setLayoutProps, useForm } from '@inertiajs/react';
import { useState } from 'react';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
} from '@/components/industry/dialog';
import {
    Btn,
    BtnLink,
    Field,
    Input,
    PageHeader,
    Textarea,
} from '@/components/industry/ui';
import { create, index, store } from '@/routes/superadmin/broadcasts';
import type { SelectOption } from '@/types';

type Audience = { role: string; department: string };

type Props = {
    roles: SelectOption[];
    departments: { id: number; name: string }[];
    audience: Audience;
    recipientCount: number;
};

export default function SuperAdminBroadcastCreate({
    roles,
    departments,
    audience,
    recipientCount,
}: Props) {
    setLayoutProps({ title: 'Kirim Siaran' });

    const [confirming, setConfirming] = useState(false);

    const form = useForm({ title: '', message: '', url: '' });
    const errors = form.errors as typeof form.errors & { audience?: string };

    const query = (next: Audience) => ({
        audience_role: next.role === 'all' ? undefined : next.role,
        department_id: next.department === 'all' ? undefined : next.department,
    });

    // The audience lives in the query string: choosing one re-asks the server
    // how many people it reaches, without touching what has been typed.
    const choose = (next: Partial<Audience>) =>
        router.get(create.url(), query({ ...audience, ...next }), {
            only: ['audience', 'recipientCount'],
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });

    const send = () => {
        form.transform((data) => ({ ...data, ...query(audience) }));
        form.post(store().url, { onFinish: () => setConfirming(false) });
    };

    const ready =
        form.data.title.trim() !== '' &&
        form.data.message.trim() !== '' &&
        recipientCount > 0;

    return (
        <>
            <Head title="Kirim Siaran" />

            <div className="ind-page max-w-[640px]">
                <div>
                    <Link href={index()} className="text-[12.5px]">
                        ← Siaran
                    </Link>
                </div>

                <PageHeader
                    title="Kirim Siaran"
                    description="Siaran muncul di lonceng notifikasi setiap penerima dan tidak dapat ditarik kembali setelah dikirim."
                />

                <div className="grid [grid-template-columns:repeat(auto-fit,minmax(220px,1fr))] gap-[var(--ind-space-4)]">
                    <Field label="Peran Sasaran">
                        <select
                            className="ind-input"
                            value={audience.role}
                            onChange={(event) =>
                                choose({ role: event.target.value })
                            }
                        >
                            <option value="all">Semua Peran</option>
                            {roles.map((role) => (
                                <option key={role.value} value={role.value}>
                                    {role.label}
                                </option>
                            ))}
                        </select>
                    </Field>

                    <Field label="Program Studi Sasaran">
                        <select
                            className="ind-input"
                            value={audience.department}
                            onChange={(event) =>
                                choose({ department: event.target.value })
                            }
                        >
                            <option value="all">Semua Program Studi</option>
                            {departments.map((department) => (
                                <option
                                    key={department.id}
                                    value={department.id}
                                >
                                    {department.name}
                                </option>
                            ))}
                        </select>
                    </Field>
                </div>

                <p
                    className="m-0 text-[13px]"
                    style={{
                        color:
                            recipientCount === 0 || errors.audience
                                ? 'var(--ind-status-error-fg)'
                                : undefined,
                    }}
                    aria-live="polite"
                >
                    {errors.audience ??
                        (recipientCount === 0
                            ? 'Tidak ada pengguna pada sasaran ini.'
                            : `Siaran ini akan diterima ${recipientCount} pengguna.`)}
                </p>

                <Field label="Judul" error={form.errors.title}>
                    <Input
                        value={form.data.title}
                        onChange={(event) =>
                            form.setData('title', event.target.value)
                        }
                        maxLength={120}
                    />
                </Field>

                <Field label="Isi Siaran" error={form.errors.message}>
                    <Textarea
                        rows={5}
                        value={form.data.message}
                        onChange={(event) =>
                            form.setData('message', event.target.value)
                        }
                        maxLength={2000}
                    />
                </Field>

                <Field
                    label="Tautan"
                    optional
                    hint="Halaman yang dibuka dari notifikasi: alamat lengkap (https://…) atau halaman aplikasi ini (/…)."
                    error={form.errors.url}
                >
                    <Input
                        value={form.data.url}
                        onChange={(event) =>
                            form.setData('url', event.target.value)
                        }
                        placeholder="/dashboard"
                    />
                </Field>

                <div className="flex flex-wrap gap-[var(--ind-space-3)]">
                    <Btn
                        variant="primary"
                        disabled={!ready || form.processing}
                        onClick={() => setConfirming(true)}
                    >
                        Kirim Siaran
                    </Btn>
                    <BtnLink href={index()}>Batal</BtnLink>
                </div>

                <Dialog open={confirming} onOpenChange={setConfirming}>
                    <DialogContent>
                        <DialogTitle>Kirim siaran ini?</DialogTitle>
                        <DialogDescription>
                            &ldquo;{form.data.title}&rdquo; akan dikirim ke{' '}
                            {recipientCount} pengguna dan tidak dapat ditarik
                            kembali.
                        </DialogDescription>

                        <DialogFooter>
                            <Btn onClick={() => setConfirming(false)}>
                                Batal
                            </Btn>
                            <Btn
                                variant="primary"
                                disabled={form.processing}
                                onClick={send}
                            >
                                {form.processing
                                    ? 'Mengirim...'
                                    : `Kirim ke ${recipientCount} Pengguna`}
                            </Btn>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
            </div>
        </>
    );
}
