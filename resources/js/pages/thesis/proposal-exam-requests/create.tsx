import { Head, setLayoutProps, useForm } from '@inertiajs/react';
import {
    DocumentListField,
    type DocumentRow,
} from '@/components/industry/document-list-field';
import { EligibilityCard } from '@/components/industry/exam-request';
import { Btn, BtnLink, Field, Textarea } from '@/components/industry/ui';
import { index, store } from '@/routes/proposal-exam-requests';
import type { Eligibility } from '@/types';

export default function ProposalExamRequestCreate({
    eligibility,
}: {
    eligibility: Eligibility;
}) {
    setLayoutProps({ title: 'Ajukan Ujian Proposal' });

    const { data, setData, post, processing, errors, transform } = useForm({
        student_notes: '',
        documents: [{ name: '', file: null }] as DocumentRow[],
    });

    const submit = () => {
        // Rows the student left blank are dropped rather than rejected.
        transform((payload) => ({
            ...payload,
            documents: payload.documents.filter(
                (row) => row.name !== '' && row.file !== null,
            ),
        }));

        post(store().url, { forceFormData: true });
    };

    return (
        <>
            <Head title="Ajukan Ujian Proposal" />

            <form
                onSubmit={(event) => {
                    event.preventDefault();
                    submit();
                }}
                className="flex max-w-[720px] flex-col gap-[var(--ind-space-6)]"
            >
                <div>
                    <h3 className="mb-1">Ajukan Ujian Proposal</h3>
                    <p className="ind-muted m-0">
                        Ajukan ujian proposal setelah memenuhi syarat bimbingan.
                    </p>
                </div>

                <EligibilityCard
                    eligibility={eligibility}
                    kicker="Kelayakan Ujian"
                    detailed
                />

                <div className="ind-section">
                    <h6>Informasi Pengajuan</h6>
                    <div className="flex flex-col gap-[var(--ind-space-4)]">
                        <Field
                            label="Catatan Mahasiswa"
                            optional
                            error={errors.student_notes}
                        >
                            <Textarea
                                rows={4}
                                value={data.student_notes}
                                onChange={(event) =>
                                    setData('student_notes', event.target.value)
                                }
                                placeholder="Tambahkan catatan atau informasi tambahan untuk dosen pembimbing"
                            />
                        </Field>

                        <DocumentListField
                            rows={data.documents}
                            onChange={(rows) => setData('documents', rows)}
                            errors={errors as Record<string, string>}
                        />
                    </div>
                </div>

                <div className="flex gap-[var(--ind-space-3)]">
                    <Btn
                        type="submit"
                        variant="primary"
                        disabled={processing || !eligibility.eligible}
                        title={
                            eligibility.eligible
                                ? undefined
                                : 'Lengkapi syarat kelayakan terlebih dahulu'
                        }
                    >
                        {processing ? 'Mengirim...' : 'Kirim Pengajuan'}
                    </Btn>
                    <BtnLink href={index()}>Batal</BtnLink>
                </div>
            </form>
        </>
    );
}
