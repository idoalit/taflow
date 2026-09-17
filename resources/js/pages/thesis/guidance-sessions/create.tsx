import { Head, setLayoutProps, useForm } from '@inertiajs/react';
import { FileDrop } from '@/components/industry/file-drop';
import {
    Btn,
    BtnLink,
    Field,
    Input,
    Seg,
    Textarea,
} from '@/components/industry/ui';
import { index, store } from '@/routes/guidance-sessions';
import type { GuidanceStage, GuidanceType, MeetingType } from '@/types';

type Option<T extends string> = { value: T; label: string };

type StageOption = Option<GuidanceStage> & {
    disabled: boolean;
    reason: string | null;
};

type Props = {
    options: {
        stages: StageOption[];
        targets: Option<'single' | 'both'>[];
        guidanceTypes: Option<GuidanceType>[];
        meetingTypes: Option<MeetingType>[];
    };
};

const GUIDANCE_TYPE_HINTS: Record<GuidanceType, string> = {
    face_to_face:
        'Bimbingan terjadwal, dilakukan secara langsung maupun lewat rapat daring.',
    online_async: 'Diskusi berkelanjutan lewat chat — tanpa jadwal pertemuan.',
};

const MEETING_TYPE_HINTS: Record<MeetingType, string> = {
    offline: 'Pertemuan tatap muka langsung di lokasi yang disepakati.',
    online_meeting:
        'Pertemuan daring terjadwal lewat Zoom, Google Meet, atau sejenisnya.',
};

const STAGE_HINTS: Record<GuidanceStage, string> = {
    proposal:
        'Bimbingan penyusunan proposal (Bab 1–3), syarat pengajuan ujian proposal.',
    research: 'Bimbingan pelaksanaan penelitian dan penulisan laporan akhir.',
};

export default function GuidanceSessionCreate({ options }: Props) {
    setLayoutProps({ title: 'Buat Sesi Bimbingan' });

    const { data, setData, post, processing, errors } = useForm({
        title: '',
        stage: (options.stages.find((stage) => !stage.disabled)?.value ??
            'proposal') as GuidanceStage,
        target_type: 'single' as 'single' | 'both',
        guidance_type: 'face_to_face' as GuidanceType,
        meeting_type: 'offline' as MeetingType,
        meeting_location: '',
        meeting_link: '',
        meeting_scheduled_at: '',
        document: null as File | null,
        notes: '',
    });

    const isFaceToFace = data.guidance_type === 'face_to_face';
    const isOffline = data.meeting_type === 'offline';

    return (
        <>
            <Head title="Buat Sesi Bimbingan" />

            <form
                onSubmit={(event) => {
                    event.preventDefault();
                    post(store().url, { forceFormData: true });
                }}
                className="flex max-w-[720px] flex-col gap-[var(--ind-space-6)]"
            >
                <div>
                    <h3 className="mb-1">Buat Sesi Bimbingan Baru</h3>
                    <p className="ind-muted m-0">
                        Isi detail sesi bimbingan dengan pembimbing Anda.
                    </p>
                </div>

                <div className="ind-section">
                    <h6>Informasi Dasar</h6>
                    <div className="flex flex-col gap-[var(--ind-space-4)]">
                        <Field label="Judul Sesi" error={errors.title}>
                            <Input
                                value={data.title}
                                onChange={(event) =>
                                    setData('title', event.target.value)
                                }
                                placeholder="Contoh: Diskusi Bab 1 - Pendahuluan"
                            />
                        </Field>

                        <div className="grid [grid-template-columns:repeat(auto-fit,minmax(220px,1fr))] gap-[var(--ind-space-4)]">
                            <Field
                                label="Tipe Bimbingan"
                                hint={GUIDANCE_TYPE_HINTS[data.guidance_type]}
                                error={errors.guidance_type}
                            >
                                <Seg
                                    full
                                    name="Tipe bimbingan"
                                    value={data.guidance_type}
                                    options={options.guidanceTypes}
                                    onChange={(value) =>
                                        setData('guidance_type', value)
                                    }
                                />
                            </Field>

                            <Field
                                label="Target Pembimbing"
                                error={errors.target_type}
                            >
                                <Seg
                                    full
                                    name="Target pembimbing"
                                    value={data.target_type}
                                    options={options.targets}
                                    onChange={(value) =>
                                        setData('target_type', value)
                                    }
                                />
                            </Field>

                            <div className="[grid-column:1/-1]">
                                <Field
                                    label="Tahap Bimbingan"
                                    hint={STAGE_HINTS[data.stage]}
                                    error={errors.stage}
                                >
                                    <Seg
                                        full
                                        name="Tahap bimbingan"
                                        value={data.stage}
                                        options={options.stages}
                                        onChange={(value) =>
                                            setData('stage', value)
                                        }
                                    />
                                </Field>
                            </div>
                        </div>
                    </div>
                </div>

                {isFaceToFace && (
                    <div className="ind-section">
                        <h6>Detail Pertemuan</h6>
                        <div className="flex flex-col gap-[var(--ind-space-4)]">
                            <Field
                                label="Jenis Pertemuan"
                                hint={MEETING_TYPE_HINTS[data.meeting_type]}
                                error={errors.meeting_type}
                            >
                                <Seg
                                    full
                                    name="Jenis pertemuan"
                                    value={data.meeting_type}
                                    options={options.meetingTypes}
                                    onChange={(value) =>
                                        setData('meeting_type', value)
                                    }
                                />
                            </Field>

                            <div className="grid [grid-template-columns:repeat(auto-fit,minmax(220px,1fr))] gap-[var(--ind-space-4)]">
                                {isOffline ? (
                                    <Field
                                        label="Lokasi Pertemuan"
                                        error={errors.meeting_location}
                                    >
                                        <Input
                                            value={data.meeting_location}
                                            onChange={(event) =>
                                                setData(
                                                    'meeting_location',
                                                    event.target.value,
                                                )
                                            }
                                            placeholder="Contoh: Ruang Dosen 301"
                                        />
                                    </Field>
                                ) : (
                                    <Field
                                        label="Link Meeting"
                                        error={errors.meeting_link}
                                    >
                                        <Input
                                            type="url"
                                            value={data.meeting_link}
                                            onChange={(event) =>
                                                setData(
                                                    'meeting_link',
                                                    event.target.value,
                                                )
                                            }
                                            placeholder="https://zoom.us/j/..."
                                        />
                                    </Field>
                                )}

                                <Field
                                    label="Jadwal Pertemuan"
                                    error={errors.meeting_scheduled_at}
                                >
                                    <Input
                                        type="datetime-local"
                                        value={data.meeting_scheduled_at}
                                        onChange={(event) =>
                                            setData(
                                                'meeting_scheduled_at',
                                                event.target.value,
                                            )
                                        }
                                    />
                                </Field>
                            </div>
                        </div>
                    </div>
                )}

                <div className="ind-section">
                    <h6>Dokumen & Catatan</h6>
                    <div className="flex flex-col gap-[var(--ind-space-4)]">
                        <FileDrop
                            compact
                            value={data.document}
                            onChange={(file) => setData('document', file)}
                            label="Unggah dokumen proposal (opsional)"
                            error={errors.document}
                        />

                        <Field label="Catatan Awal" error={errors.notes}>
                            <Textarea
                                rows={4}
                                value={data.notes}
                                onChange={(event) =>
                                    setData('notes', event.target.value)
                                }
                                placeholder="Tuliskan catatan atau pertanyaan yang ingin Anda diskusikan..."
                            />
                        </Field>
                    </div>
                </div>

                <div className="flex gap-[var(--ind-space-3)]">
                    <Btn type="submit" variant="primary" disabled={processing}>
                        {processing ? 'Menyimpan...' : 'Buat Sesi Bimbingan'}
                    </Btn>
                    <BtnLink href={index()}>Batal</BtnLink>
                </div>
            </form>
        </>
    );
}
