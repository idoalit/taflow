import { Head, setLayoutProps } from '@inertiajs/react';
import { Monitor, Moon, Sun, type LucideIcon } from 'lucide-react';
import { Field } from '@/components/industry/ui';
import type { Appearance } from '@/hooks/use-appearance';
import { useAppearance } from '@/hooks/use-appearance';
import { cn } from '@/lib/utils';

const OPTIONS: { value: Appearance; icon: LucideIcon; label: string }[] = [
    { value: 'light', icon: Sun, label: 'Terang' },
    { value: 'dark', icon: Moon, label: 'Gelap' },
    { value: 'system', icon: Monitor, label: 'Ikuti Sistem' },
];

export default function Appearance() {
    setLayoutProps({ title: 'Pengaturan Akun' });

    const { appearance, updateAppearance } = useAppearance();

    return (
        <>
            <Head title="Tampilan" />

            <section className="flex flex-col gap-[var(--ind-space-4)]">
                <h6 className="m-0">Tampilan</h6>

                <Field
                    label="Tema Antarmuka"
                    hint="Pilihan ini tersimpan di peramban Anda dan hanya berlaku di perangkat ini."
                >
                    {/* Not a Seg: each option carries an icon, and the control is
                        wider than the segmented pattern is meant for. */}
                    <div
                        className="ind-seg w-fit"
                        role="group"
                        aria-label="Tema antarmuka"
                    >
                        {OPTIONS.map(({ value, icon: Icon, label }) => (
                            <button
                                key={value}
                                type="button"
                                aria-pressed={appearance === value}
                                onClick={() => updateAppearance(value)}
                                className={cn('ind-seg-opt')}
                            >
                                <Icon size={14} strokeWidth={1.5} />
                                {label}
                            </button>
                        ))}
                    </div>
                </Field>
            </section>
        </>
    );
}
