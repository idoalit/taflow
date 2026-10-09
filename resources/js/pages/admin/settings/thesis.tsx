import { Head, setLayoutProps, useForm } from '@inertiajs/react';
import {
    ThesisRuleFields,
    thesisRuleForm,
} from '@/components/industry/thesis-rule-fields';
import { Btn, PageHeader } from '@/components/industry/ui';
import { update } from '@/routes/admin/thesis-settings';
import type { SelectOption, ThesisRuleValues } from '@/types';

type Props = {
    department: string;
    setting: ThesisRuleValues;
    alignmentModes: SelectOption[];
};

export default function AdminThesisSettings({
    department,
    setting,
    alignmentModes,
}: Props) {
    setLayoutProps({ title: 'Pengaturan Tugas Akhir' });

    const form = useForm(thesisRuleForm(setting));

    return (
        <>
            <Head title="Pengaturan Tugas Akhir" />

            <form
                onSubmit={(event) => {
                    event.preventDefault();
                    form.put(update().url, { preserveScroll: true });
                }}
                className="ind-page max-w-[640px]"
            >
                <PageHeader
                    title="Pengaturan Tugas Akhir"
                    description={`Konfigurasi ini hanya berlaku untuk ${department}.`}
                />

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
                    {form.processing ? 'Menyimpan...' : 'Simpan Pengaturan'}
                </Btn>
            </form>
        </>
    );
}
