import { Head, router, setLayoutProps, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { FileDrop } from '@/components/industry/file-drop';
import {
    ThesisRuleFields,
    thesisRuleForm,
} from '@/components/industry/thesis-rule-fields';
import { Btn, Field, Input, PageHeader, Tabs } from '@/components/industry/ui';
import { update as updateInstitution } from '@/routes/superadmin/settings/institution';
import { destroy as destroyLogo } from '@/routes/superadmin/settings/institution/logo';
import { update as updateThesisDefaults } from '@/routes/superadmin/settings/thesis-defaults';
import type { SelectOption, ThesisRuleValues } from '@/types';

type Institution = {
    name: string;
    unit: string | null;
    address: string | null;
    phone: string | null;
    website: string | null;
    city: string | null;
    logo: string | null;
    has_uploaded_logo: boolean;
};

type Section = 'institution' | 'thesis';

type Props = {
    institution: Institution;
    thesisDefaults: ThesisRuleValues;
    alignmentModes: SelectOption[];
};

export default function SuperAdminSettings({
    institution,
    thesisDefaults,
    alignmentModes,
}: Props) {
    setLayoutProps({ title: 'Pengaturan Sistem' });

    const [section, setSection] = useState<Section>('institution');

    return (
        <>
            <Head title="Pengaturan Sistem" />

            <div className="ind-page max-w-[640px]">
                <PageHeader
                    title="Pengaturan Sistem"
                    description="Berlaku untuk seluruh program studi."
                />

                <Tabs<Section>
                    label="Bagian pengaturan"
                    value={section}
                    options={[
                        { value: 'institution', label: 'Identitas Institusi' },
                        { value: 'thesis', label: 'Aturan TA Bawaan' },
                    ]}
                    onChange={setSection}
                />

                {section === 'institution' ? (
                    <InstitutionForm institution={institution} />
                ) : (
                    <ThesisDefaultsForm
                        thesisDefaults={thesisDefaults}
                        alignmentModes={alignmentModes}
                    />
                )}
            </div>
        </>
    );
}

function InstitutionForm({ institution }: { institution: Institution }) {
    const form = useForm<{
        name: string;
        unit: string;
        address: string;
        phone: string;
        website: string;
        city: string;
        logo: File | null;
    }>({
        name: institution.name,
        unit: institution.unit ?? '',
        address: institution.address ?? '',
        phone: institution.phone ?? '',
        website: institution.website ?? '',
        city: institution.city ?? '',
        logo: null,
    });

    return (
        <form
            onSubmit={(event) => {
                event.preventDefault();
                form.post(updateInstitution().url, {
                    preserveScroll: true,
                    onSuccess: () => form.setData('logo', null),
                });
            }}
            className="ind-page"
        >
            <p className="ind-muted m-0 text-[12.5px]">
                Tercetak pada kop keenam dokumen resmi ujian dan tampil pada
                halaman verifikasi keasliannya.
            </p>

            <Field label="Nama Institusi" error={form.errors.name}>
                <Input
                    value={form.data.name}
                    onChange={(event) =>
                        form.setData('name', event.target.value)
                    }
                    placeholder="Universitas ..."
                />
            </Field>

            <Field
                label="Unit"
                optional
                hint="Baris kedua kop, misalnya nama fakultas."
                error={form.errors.unit}
            >
                <Input
                    value={form.data.unit}
                    onChange={(event) =>
                        form.setData('unit', event.target.value)
                    }
                />
            </Field>

            <Field label="Alamat" optional error={form.errors.address}>
                <Input
                    value={form.data.address}
                    onChange={(event) =>
                        form.setData('address', event.target.value)
                    }
                />
            </Field>

            <div className="grid [grid-template-columns:repeat(auto-fit,minmax(180px,1fr))] gap-[var(--ind-space-4)]">
                <Field label="Telepon" optional error={form.errors.phone}>
                    <Input
                        value={form.data.phone}
                        onChange={(event) =>
                            form.setData('phone', event.target.value)
                        }
                    />
                </Field>

                <Field label="Situs" optional error={form.errors.website}>
                    <Input
                        value={form.data.website}
                        onChange={(event) =>
                            form.setData('website', event.target.value)
                        }
                    />
                </Field>

                <Field
                    label="Kota"
                    optional
                    hint="Tempat dokumen ditandatangani bila program studi tidak menetapkan kotanya sendiri."
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

            <div>
                <span className="ind-label">Logo Kop</span>

                {institution.logo && form.data.logo === null && (
                    <div className="mb-[var(--ind-space-3)] flex items-center gap-[var(--ind-space-4)]">
                        <img
                            src={institution.logo}
                            alt="Logo institusi saat ini"
                            className="h-[64px] w-auto"
                        />
                        {institution.has_uploaded_logo && (
                            <Btn
                                variant="ghost"
                                onClick={() =>
                                    router.delete(destroyLogo().url, {
                                        preserveScroll: true,
                                    })
                                }
                            >
                                Hapus Logo
                            </Btn>
                        )}
                    </div>
                )}

                <FileDrop
                    value={form.data.logo}
                    onChange={(file) => form.setData('logo', file)}
                    accept=".png,.jpg,.jpeg"
                    hint="PNG atau JPG, maks. 1MB"
                    label={
                        institution.logo
                            ? 'Seret logo baru ke sini atau klik untuk mengganti'
                            : 'Seret logo ke sini atau klik untuk unggah'
                    }
                    error={form.errors.logo}
                    compact
                />
            </div>

            <Btn
                type="submit"
                variant="primary"
                disabled={form.processing}
                className="self-start"
            >
                {form.processing ? 'Menyimpan...' : 'Simpan Identitas'}
            </Btn>
        </form>
    );
}

function ThesisDefaultsForm({
    thesisDefaults,
    alignmentModes,
}: {
    thesisDefaults: ThesisRuleValues;
    alignmentModes: SelectOption[];
}) {
    const form = useForm(thesisRuleForm(thesisDefaults));

    return (
        <form
            onSubmit={(event) => {
                event.preventDefault();
                form.put(updateThesisDefaults().url, { preserveScroll: true });
            }}
            className="ind-page"
        >
            <p className="ind-muted m-0 text-[12.5px]">
                Aturan yang dipakai program studi sebelum admin-nya menyimpan
                aturan sendiri. Program studi yang sudah mengatur sendiri tidak
                terpengaruh.
            </p>

            <ThesisRuleFields
                data={form.data}
                errors={form.errors}
                setData={form.setData}
                alignmentModes={alignmentModes}
            />

            <Btn
                type="submit"
                variant="primary"
                disabled={form.processing}
                className="self-start"
            >
                {form.processing ? 'Menyimpan...' : 'Simpan Aturan Bawaan'}
            </Btn>
        </form>
    );
}
