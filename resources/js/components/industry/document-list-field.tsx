import { FileText, X } from 'lucide-react';
import { BlueprintCorners } from '@/components/industry/blueprint';
import { Btn, Input } from '@/components/industry/ui';

export type DocumentRow = { name: string; file: File | null };

/**
 * Repeating "name + file" rows for the supporting documents an exam request
 * carries, as laid out in the design.
 */
export function DocumentListField({
    rows,
    onChange,
    errors,
}: {
    rows: DocumentRow[];
    onChange: (rows: DocumentRow[]) => void;
    errors?: Record<string, string>;
}) {
    const update = (index: number, patch: Partial<DocumentRow>) => {
        onChange(
            rows.map((row, position) =>
                position === index ? { ...row, ...patch } : row,
            ),
        );
    };

    return (
        <div>
            <label className="ind-label">Dokumen Pendukung</label>

            <div className="flex flex-col gap-[var(--ind-space-2)]">
                {rows.map((row, index) => (
                    <div key={index}>
                        <div className="ind-card ind-bp flex-row items-center gap-3">
                            <BlueprintCorners />
                            <FileText
                                size={18}
                                strokeWidth={1.5}
                                style={{ color: 'var(--ind-accent-700)' }}
                            />
                            <Input
                                className="flex-1"
                                value={row.name}
                                onChange={(event) =>
                                    update(index, { name: event.target.value })
                                }
                                placeholder="Nama dokumen, contoh: Bukti Lunas SPP"
                            />
                            <label className="ind-btn ind-btn-secondary max-w-[180px] cursor-pointer truncate">
                                {row.file?.name ?? 'Pilih File'}
                                <input
                                    type="file"
                                    className="hidden"
                                    accept=".pdf,.doc,.docx,.jpg,.png"
                                    onChange={(event) =>
                                        update(index, {
                                            file:
                                                event.target.files?.[0] ?? null,
                                        })
                                    }
                                />
                            </label>
                            {rows.length > 1 && (
                                <button
                                    type="button"
                                    aria-label="Hapus dokumen"
                                    className="ind-btn ind-btn-ghost ind-btn-icon"
                                    onClick={() =>
                                        onChange(
                                            rows.filter(
                                                (_, position) =>
                                                    position !== index,
                                            ),
                                        )
                                    }
                                >
                                    <X size={16} strokeWidth={1.5} />
                                </button>
                            )}
                        </div>

                        {(errors?.[`documents.${index}.name`] ||
                            errors?.[`documents.${index}.file`]) && (
                            <p
                                className="mt-[5px] mb-0 text-[11.5px]"
                                style={{ color: 'var(--ind-status-error-fg)' }}
                            >
                                {errors[`documents.${index}.name`] ??
                                    errors[`documents.${index}.file`]}
                            </p>
                        )}
                    </div>
                ))}
            </div>

            <Btn
                variant="ghost"
                className="mt-[var(--ind-space-2)]"
                onClick={() => onChange([...rows, { name: '', file: null }])}
            >
                + Tambah Dokumen
            </Btn>
        </div>
    );
}
