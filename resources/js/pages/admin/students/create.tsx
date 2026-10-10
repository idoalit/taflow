import { Head, Link, setLayoutProps, useForm } from '@inertiajs/react';
import {
    Btn,
    BtnLink,
    Field,
    Input,
    PageHeader,
} from '@/components/industry/ui';
import { index, store } from '@/routes/admin/students';

export default function AdminStudentCreate({
    department,
}: {
    department: string;
}) {
    setLayoutProps({ title: 'Tambah Mahasiswa' });

    const form = useForm({
        name: '',
        email: '',
        student_number: '',
        enrollment_year: String(new Date().getFullYear()),
    });

    return (
        <>
            <Head title="Tambah Mahasiswa" />

            <form
                onSubmit={(event) => {
                    event.preventDefault();
                    form.post(store().url);
                }}
                className="ind-page max-w-[640px]"
            >
                <div>
                    <Link href={index()} className="text-[12.5px]">
                        ← Mahasiswa
                    </Link>
                </div>

                <PageHeader
                    title="Tambah Mahasiswa"
                    description={`Mahasiswa baru ${department}. Akunnya tidak diberi kata sandi: pemiliknya menerima tautan aktivasi lewat surel dan menyetel kata sandinya sendiri.`}
                />

                <div className="grid [grid-template-columns:repeat(auto-fit,minmax(220px,1fr))] gap-[var(--ind-space-4)]">
                    <Field label="Nama" error={form.errors.name}>
                        <Input
                            value={form.data.name}
                            onChange={(event) =>
                                form.setData('name', event.target.value)
                            }
                            autoComplete="off"
                            autoFocus
                        />
                    </Field>

                    <Field label="Surel" error={form.errors.email}>
                        <Input
                            type="email"
                            value={form.data.email}
                            onChange={(event) =>
                                form.setData('email', event.target.value)
                            }
                            autoComplete="off"
                        />
                    </Field>

                    <Field label="NIM" error={form.errors.student_number}>
                        <Input
                            value={form.data.student_number}
                            onChange={(event) =>
                                form.setData(
                                    'student_number',
                                    event.target.value,
                                )
                            }
                            autoComplete="off"
                        />
                    </Field>

                    <Field label="Angkatan" error={form.errors.enrollment_year}>
                        <Input
                            type="number"
                            min={2000}
                            max={2100}
                            value={form.data.enrollment_year}
                            onChange={(event) =>
                                form.setData(
                                    'enrollment_year',
                                    event.target.value,
                                )
                            }
                        />
                    </Field>
                </div>

                <div className="flex flex-wrap gap-[var(--ind-space-3)]">
                    <Btn
                        type="submit"
                        variant="primary"
                        disabled={form.processing}
                    >
                        {form.processing
                            ? 'Menambahkan...'
                            : 'Tambah & Kirim Tautan'}
                    </Btn>
                    <BtnLink href={index()}>Batal</BtnLink>
                </div>
            </form>
        </>
    );
}
