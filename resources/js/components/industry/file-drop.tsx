import { FileText, Upload, X } from 'lucide-react';
import { useRef, useState, type DragEvent } from 'react';
import { BlueprintCorners } from '@/components/industry/blueprint';

/**
 * Drag-and-drop file field. Keeps the chosen file visible once picked, since
 * the forms it appears in are long enough that the control scrolls out of view.
 */
export function FileDrop({
    value,
    onChange,
    accept = '.pdf,.doc,.docx',
    hint = 'PDF atau DOCX, maks. 5MB',
    label = 'Seret file ke sini atau klik untuk unggah',
    error,
    compact = false,
    id,
}: {
    value: File | null;
    onChange: (file: File | null) => void;
    accept?: string;
    hint?: string;
    label?: string;
    error?: string;
    compact?: boolean;
    id?: string;
}) {
    const inputRef = useRef<HTMLInputElement>(null);
    const [dragging, setDragging] = useState(false);

    const handleDrop = (event: DragEvent<HTMLDivElement>) => {
        event.preventDefault();
        setDragging(false);
        onChange(event.dataTransfer.files[0] ?? null);
    };

    if (value) {
        return (
            <div className="ind-card ind-bp flex-row items-center gap-3">
                <BlueprintCorners />
                <FileText
                    size={18}
                    strokeWidth={1.5}
                    style={{ color: 'var(--ind-accent-700)' }}
                />
                <div className="min-w-0 flex-1">
                    <div className="truncate text-[14px] font-semibold">
                        {value.name}
                    </div>
                    <div className="ind-muted text-[12px]">
                        {(value.size / 1048576).toFixed(1)} MB
                    </div>
                </div>
                <button
                    type="button"
                    aria-label="Hapus file"
                    onClick={() => onChange(null)}
                    className="ind-btn ind-btn-ghost ind-btn-icon"
                >
                    <X size={16} strokeWidth={1.5} />
                </button>
            </div>
        );
    }

    return (
        <>
            <div
                role="button"
                tabIndex={0}
                onClick={() => inputRef.current?.click()}
                onKeyDown={(event) => {
                    if (event.key === 'Enter' || event.key === ' ') {
                        event.preventDefault();
                        inputRef.current?.click();
                    }
                }}
                onDragOver={(event) => {
                    event.preventDefault();
                    setDragging(true);
                }}
                onDragLeave={() => setDragging(false)}
                onDrop={handleDrop}
                className="ind-card ind-bp cursor-pointer items-center justify-center gap-[6px] text-center"
                style={{
                    borderStyle: 'dashed',
                    padding: compact
                        ? 'var(--ind-space-6)'
                        : 'var(--ind-space-8)',
                    borderColor: dragging
                        ? 'var(--ind-accent)'
                        : 'var(--ind-divider)',
                    background: dragging
                        ? 'var(--ind-accent-100)'
                        : 'transparent',
                }}
            >
                <BlueprintCorners />
                <Upload
                    size={18}
                    strokeWidth={1.5}
                    style={{ color: 'var(--ind-accent-700)' }}
                />
                <div className="mt-[6px] text-[13px] font-semibold">
                    {label}
                </div>
                <div className="ind-muted text-[12px]">{hint}</div>
            </div>

            <input
                ref={inputRef}
                id={id}
                type="file"
                accept={accept}
                className="hidden"
                onChange={(event) => onChange(event.target.files?.[0] ?? null)}
            />

            {error && (
                <p
                    className="mt-[5px] mb-0 text-[11.5px]"
                    style={{ color: 'var(--ind-status-error-fg)' }}
                >
                    {error}
                </p>
            )}
        </>
    );
}
