import { Head, Link, router, setLayoutProps, useForm } from '@inertiajs/react';
import { useState } from 'react';
import {
    Btn,
    BtnLink,
    Card,
    CardKicker,
    Field,
    Input,
    PageHeader,
} from '@/components/industry/ui';
import { create, index, show, store } from '@/routes/admin/lecturers';
import { store as addToDepartment } from '@/routes/admin/lecturers/membership';

type Match = {
    id: number;
    name: string;
    lecturer_number: string;
    academic_position: string | null;
    homebase: string | null;
    is_member: boolean;
};

type Props = {
    department: string;
    /** The NIP that was looked up; empty until the first lookup. */
    nip: string;
    match: Match | null;
    defaultQuota: number;
};

/**
 * Somebody the system already knows: nothing about them is asked again, only
 * whether they should work in this prodi too.
 */
function ExistingLecturer({
    match,
    department,
}: {
    match: Match;
    department: string;
}) {
    const [adding, setAdding] = useState(false);

    return (
        <Card>
            <CardKicker>Dosen Sudah Terdaftar</CardKicker>
            <div className="ind-card-title">{match.name}</div>
            <div className="ind-card-meta">
                NIP {match.lecturer_number}
                {match.academic_position && ` · ${match.academic_position}`}
                {match.homebase && ` · Homebase ${match.homebase}`}
            </div>

            {match.is_member ? (
                <>
                    <p className="ind-muted mt-[var(--ind-space-3)] mb-[var(--ind-space-3)] text-[13px]">
                        Dosen ini sudah menjadi dosen {department}.
                    </p>
                    <BtnLink href={show(match.id)} className="self-start">
                        Buka Data Dosen
                    </BtnLink>
                </>
            ) : (
                <>
                    <p className="ind-muted mt-[var(--ind-space-3)] mb-[var(--ind-space-3)] text-[13px]">
                        Menambahkannya ke {department} membuat dosen ini dapat
                        ditugaskan sebagai pembimbing atau penguji di sini.
                        Homebase-nya tidak berubah
                        {match.homebase
                            ? `, dan admin ${match.homebase} beserta dosen yang bersangkutan diberi tahu.`
                            : ', dan dosen yang bersangkutan diberi tahu.'}
                    </p>
                    <Btn
                        variant="primary"
                        className="self-start"
                        disabled={adding}
                        onClick={() =>
                            router.post(
                                addToDepartment(match.id).url,
                                {},
                                {
                                    onStart: () => setAdding(true),
                                    onFinish: () => setAdding(false),
                                },
                            )
                        }
                    >
                        {adding
                            ? 'Menambahkan...'
                            : `Tambahkan ke ${department}`}
                    </Btn>
                </>
            )}
        </Card>
    );
}

/** Somebody new: this prodi registers them and becomes their homebase. */
function NewLecturer({
    nip,
    department,
    defaultQuota,
}: {
    nip: string;
    department: string;
    defaultQuota: number;
}) {
    const form = useForm({
        name: '',
        email: '',
        lecturer_number: nip,
        academic_position: '',
        max_supervisions: String(defaultQuota),
    });

    return (
        <form
            onSubmit={(event) => {
                event.preventDefault();
                form.post(store().url);
            }}
            className="ind-page"
        >
            <div>
                <h6 className="m-0">Dosen Baru</h6>
                <p className="ind-muted m-0 mt-1 text-[12.5px]">
                    NIP {nip} belum terdaftar. {department} akan menjadi
                    homebase dosen ini, dan tautan aktivasi dikirim ke surelnya.
                </p>
                {form.errors.lecturer_number && (
                    <p
                        className="m-0 mt-1 text-[12.5px]"
                        style={{ color: 'var(--ind-status-error-fg)' }}
                    >
                        {form.errors.lecturer_number}
                    </p>
                )}
            </div>

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

                <Field
                    label="Jabatan Akademik"
                    optional
                    error={form.errors.academic_position}
                >
                    <Input
                        value={form.data.academic_position}
                        onChange={(event) =>
                            form.setData(
                                'academic_position',
                                event.target.value,
                            )
                        }
                        placeholder="Lektor Kepala"
                    />
                </Field>

                <Field
                    label="Kuota Bimbingan"
                    error={form.errors.max_supervisions}
                >
                    <Input
                        type="number"
                        min={0}
                        max={50}
                        value={form.data.max_supervisions}
                        onChange={(event) =>
                            form.setData('max_supervisions', event.target.value)
                        }
                    />
                </Field>
            </div>

            <div className="flex flex-wrap gap-[var(--ind-space-3)]">
                <Btn type="submit" variant="primary" disabled={form.processing}>
                    {form.processing
                        ? 'Menambahkan...'
                        : 'Tambah & Kirim Tautan'}
                </Btn>
                <BtnLink href={index()}>Batal</BtnLink>
            </div>
        </form>
    );
}

/**
 * Adding a dosen starts from the NIP. What it turns up decides the rest:
 * a dosen another prodi registered is added as they are, and only a new one
 * is asked for a name and a surel.
 */
export default function AdminLecturerCreate({
    department,
    nip,
    match,
    defaultQuota,
}: Props) {
    setLayoutProps({ title: 'Tambah Dosen' });

    const [number, setNumber] = useState(nip);
    const [checking, setChecking] = useState(false);

    return (
        <>
            <Head title="Tambah Dosen" />

            <div className="ind-page max-w-[640px]">
                <div>
                    <Link href={index()} className="text-[12.5px]">
                        ← Dosen
                    </Link>
                </div>

                <PageHeader
                    title="Tambah Dosen"
                    description={`Mulai dari NIP. Dosen yang sudah terdaftar di program studi lain cukup ditambahkan ke ${department}; yang belum terdaftar dibuatkan akun baru.`}
                />

                <form
                    onSubmit={(event) => {
                        event.preventDefault();
                        router.get(
                            create().url,
                            { nip: number.trim() },
                            {
                                preserveState: true,
                                onStart: () => setChecking(true),
                                onFinish: () => setChecking(false),
                            },
                        );
                    }}
                    className="flex flex-wrap items-end gap-[var(--ind-space-3)]"
                >
                    <div className="min-w-[220px] flex-1">
                        <Field label="NIP">
                            <Input
                                value={number}
                                onChange={(event) =>
                                    setNumber(event.target.value)
                                }
                                autoComplete="off"
                                autoFocus={nip === ''}
                                required
                            />
                        </Field>
                    </div>
                    <Btn
                        type="submit"
                        variant={nip === '' ? 'primary' : 'secondary'}
                        disabled={checking || number.trim() === ''}
                    >
                        {checking ? 'Memeriksa...' : 'Periksa NIP'}
                    </Btn>
                </form>

                {nip !== '' &&
                    (match ? (
                        <ExistingLecturer
                            match={match}
                            department={department}
                        />
                    ) : (
                        // Keyed by the NIP so a second lookup starts the form
                        // clean instead of carrying the first one's typing.
                        <NewLecturer
                            key={nip}
                            nip={nip}
                            department={department}
                            defaultQuota={defaultQuota}
                        />
                    ))}
            </div>
        </>
    );
}
