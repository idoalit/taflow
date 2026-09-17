import { Head, setLayoutProps, useForm } from '@inertiajs/react';
import { FileDrop } from '@/components/industry/file-drop';
import { Btn, BtnLink, Field, Input, Textarea } from '@/components/industry/ui';
import { index, store } from '@/routes/research-logbooks';

export default function LogbookCreate({ today }: { today: string }) {
    setLayoutProps({ title: 'Tambah Catatan Logbook' });

    const { data, setData, post, processing, errors } = useForm({
        log_date: today,
        progress: 0,
        description: '',
        attachment: null as File | null,
    });

    return (
        <>
            <Head title="Tambah Catatan Logbook" />

            <form
                onSubmit={(event) => {
                    event.preventDefault();
                    post(store().url, { forceFormData: true });
                }}
                className="flex max-w-[640px] flex-col gap-[var(--ind-space-6)]"
            >
                <div>
                    <h3 className="mb-1">Tambah Catatan Logbook</h3>
                    <p className="ind-muted m-0">
                        Catat kegiatan penelitian hari ini agar dosen pembimbing
                        dapat memantau progres Anda.
                    </p>
                </div>

                <div className="grid [grid-template-columns:repeat(auto-fit,minmax(220px,1fr))] gap-[var(--ind-space-4)]">
                    <Field label="Tanggal" error={errors.log_date}>
                        <Input
                            type="date"
                            max={today}
                            value={data.log_date}
                            onChange={(event) =>
                                setData('log_date', event.target.value)
                            }
                        />
                    </Field>

                    <Field label="Progres Penelitian" error={errors.progress}>
                        <div className="flex items-center gap-[10px]">
                            <input
                                type="range"
                                min={0}
                                max={100}
                                step={5}
                                value={data.progress}
                                onChange={(event) =>
                                    setData(
                                        'progress',
                                        Number(event.target.value),
                                    )
                                }
                                className="flex-1"
                                style={{ accentColor: 'var(--ind-accent)' }}
                            />
                            <span
                                className="w-[42px] text-right text-[14px] font-semibold"
                                style={{
                                    fontFamily: 'var(--ind-font-heading)',
                                }}
                            >
                                {data.progress}%
                            </span>
                        </div>
                    </Field>
                </div>

                <Field label="Deskripsi Kegiatan" error={errors.description}>
                    <Textarea
                        rows={6}
                        value={data.description}
                        onChange={(event) =>
                            setData('description', event.target.value)
                        }
                        placeholder="Contoh: Melakukan preprocessing dataset ulasan, membersihkan data duplikat dan noise sebelum tahap fine-tuning model..."
                    />
                </Field>

                <div>
                    <label className="ind-label">
                        Lampiran <span className="ind-muted">(opsional)</span>
                    </label>
                    <FileDrop
                        compact
                        accept="*"
                        value={data.attachment}
                        onChange={(file) => setData('attachment', file)}
                        hint="Dokumen, gambar, atau data pendukung — maks. 5MB"
                        error={errors.attachment}
                    />
                </div>

                <div className="flex gap-[var(--ind-space-3)]">
                    <Btn type="submit" variant="primary" disabled={processing}>
                        {processing ? 'Menyimpan...' : 'Simpan Catatan'}
                    </Btn>
                    <BtnLink href={index()}>Batal</BtnLink>
                </div>
            </form>
        </>
    );
}
