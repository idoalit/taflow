import { Head, Link, setLayoutProps, useForm } from '@inertiajs/react';
import {
    Btn,
    BtnLink,
    Field,
    Input,
    PageHeader,
    Textarea,
} from '@/components/industry/ui';
import { index, show, store, update } from '@/routes/superadmin/departments';
import type { DepartmentRow } from '@/types';

/** One form for both a new program studi and an existing one. */
export default function SuperAdminDepartmentForm({
    department,
}: {
    department: DepartmentRow | null;
}) {
    const editing = department !== null;
    const title = editing ? 'Ubah Program Studi' : 'Tambah Program Studi';

    setLayoutProps({ title });

    const form = useForm({
        name: department?.name ?? '',
        code: department?.code ?? '',
        faculty: department?.faculty ?? '',
        city: department?.city ?? '',
        description: department?.description ?? '',
        is_active: department?.is_active ?? true,
    });

    return (
        <>
            <Head title={title} />

            <form
                onSubmit={(event) => {
                    event.preventDefault();

                    if (editing) {
                        form.put(update(department.id).url);
                    } else {
                        form.post(store().url);
                    }
                }}
                className="ind-page max-w-[640px]"
            >
                <div>
                    <Link
                        href={editing ? show(department.id) : index()}
                        className="text-[12.5px]"
                    >
                        ← {editing ? department.name : 'Program Studi'}
                    </Link>
                </div>

                <PageHeader
                    title={title}
                    description={
                        editing
                            ? 'Nama, fakultas, dan kota tercetak pada dokumen resmi ujian.'
                            : 'Program studi baru langsung mendapat form penilaian bimbingan baku fakultas.'
                    }
                />

                <div className="grid [grid-template-columns:repeat(auto-fit,minmax(220px,1fr))] gap-[var(--ind-space-4)]">
                    <Field label="Nama Program Studi" error={form.errors.name}>
                        <Input
                            value={form.data.name}
                            onChange={(event) =>
                                form.setData('name', event.target.value)
                            }
                            placeholder="Teknik Informatika"
                        />
                    </Field>

                    <Field
                        label="Kode"
                        hint="Singkatan unik, misalnya TI."
                        error={form.errors.code}
                    >
                        <Input
                            value={form.data.code}
                            onChange={(event) =>
                                form.setData(
                                    'code',
                                    event.target.value.toUpperCase(),
                                )
                            }
                            maxLength={20}
                            placeholder="TI"
                        />
                    </Field>

                    <Field
                        label="Fakultas"
                        optional
                        error={form.errors.faculty}
                    >
                        <Input
                            value={form.data.faculty}
                            onChange={(event) =>
                                form.setData('faculty', event.target.value)
                            }
                            placeholder="Fakultas Teknik"
                        />
                    </Field>

                    <Field
                        label="Kota"
                        optional
                        hint="Tempat dokumen ditandatangani. Kosongkan untuk memakai kota institusi."
                        error={form.errors.city}
                    >
                        <Input
                            value={form.data.city}
                            onChange={(event) =>
                                form.setData('city', event.target.value)
                            }
                        />
                    </Field>
                </div>

                <Field
                    label="Keterangan"
                    optional
                    error={form.errors.description}
                >
                    <Textarea
                        rows={3}
                        value={form.data.description}
                        onChange={(event) =>
                            form.setData('description', event.target.value)
                        }
                    />
                </Field>

                <div className="flex flex-col gap-[var(--ind-space-2)]">
                    <label className="ind-check">
                        <input
                            type="checkbox"
                            checked={form.data.is_active}
                            onChange={(event) =>
                                form.setData('is_active', event.target.checked)
                            }
                        />
                        Program studi aktif
                    </label>

                    <p className="ind-muted m-0 text-[12.5px]">
                        {form.data.is_active
                            ? 'Admin, dosen, dan mahasiswanya dapat membuka workspace program studi ini.'
                            : 'Diarsipkan: tidak ada yang dapat membuka workspace program studi ini selain superadmin. Datanya tetap tersimpan.'}
                    </p>
                </div>

                <div className="flex flex-wrap gap-[var(--ind-space-3)]">
                    <Btn
                        type="submit"
                        variant="primary"
                        disabled={form.processing}
                    >
                        {form.processing
                            ? 'Menyimpan...'
                            : editing
                              ? 'Simpan Perubahan'
                              : 'Buat Program Studi'}
                    </Btn>
                    <BtnLink href={editing ? show(department.id) : index()}>
                        Batal
                    </BtnLink>
                </div>
            </form>
        </>
    );
}
