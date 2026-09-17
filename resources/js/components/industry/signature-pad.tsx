import { router } from '@inertiajs/react';
import { Eraser } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
} from '@/components/industry/dialog';
import { FileDrop } from '@/components/industry/file-drop';
import { Btn, Seg } from '@/components/industry/ui';

/**
 * Capture a signature, drawn or uploaded.
 *
 * Drawing is the default because it is what a person does on paper; uploading
 * is there for anyone who has a scan of their own already.
 */
export function SignaturePad({
    open,
    onOpenChange,
    action,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    action: string;
}) {
    const [mode, setMode] = useState<'draw' | 'upload'>('draw');
    const [file, setFile] = useState<File | null>(null);
    const [drawn, setDrawn] = useState(false);
    const [saving, setSaving] = useState(false);
    const canvasRef = useRef<HTMLCanvasElement>(null);
    const drawing = useRef(false);

    useEffect(() => {
        if (!open) {
            setDrawn(false);
            setFile(null);
        }
    }, [open]);

    const context = () => {
        const canvas = canvasRef.current;

        if (!canvas) {
            return null;
        }

        const ctx = canvas.getContext('2d');

        if (ctx) {
            ctx.lineWidth = 2.2;
            ctx.lineCap = 'round';
            ctx.lineJoin = 'round';
            ctx.strokeStyle = '#111';
        }

        return ctx;
    };

    const pointAt = (event: React.PointerEvent<HTMLCanvasElement>) => {
        const rect = event.currentTarget.getBoundingClientRect();

        return {
            x:
                ((event.clientX - rect.left) / rect.width) *
                event.currentTarget.width,
            y:
                ((event.clientY - rect.top) / rect.height) *
                event.currentTarget.height,
        };
    };

    const clear = () => {
        const canvas = canvasRef.current;
        const ctx = context();

        if (canvas && ctx) {
            ctx.clearRect(0, 0, canvas.width, canvas.height);
        }

        setDrawn(false);
    };

    const submit = () => {
        setSaving(true);

        const payload =
            mode === 'upload' && file
                ? { file }
                : {
                      signature:
                          canvasRef.current?.toDataURL('image/png') ?? '',
                  };

        router.post(action, payload, {
            forceFormData: mode === 'upload',
            preserveScroll: true,
            onFinish: () => {
                setSaving(false);
                onOpenChange(false);
            },
        });
    };

    const ready = mode === 'upload' ? file !== null : drawn;

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="w-[min(520px,calc(100vw-32px))]">
                <DialogTitle>Tanda Tangan</DialogTitle>
                <DialogDescription>
                    Tanda tangan Anda disimpan sekali dan dipakai kembali pada
                    dokumen berikutnya.
                </DialogDescription>

                <Seg
                    name="Cara tanda tangan"
                    value={mode}
                    options={[
                        { value: 'draw', label: 'Gambar' },
                        { value: 'upload', label: 'Unggah' },
                    ]}
                    onChange={(next) => setMode(next as 'draw' | 'upload')}
                />

                {mode === 'draw' ? (
                    <div className="flex flex-col gap-[var(--ind-space-2)]">
                        <canvas
                            ref={canvasRef}
                            width={460}
                            height={180}
                            className="w-full touch-none border"
                            style={{
                                borderColor: 'var(--ind-divider)',
                                background: 'var(--ind-bg)',
                                height: 180,
                            }}
                            onPointerDown={(event) => {
                                try {
                                    event.currentTarget.setPointerCapture(
                                        event.pointerId,
                                    );
                                } catch {
                                    // Capture only keeps the stroke following a
                                    // pointer that leaves the canvas. When the
                                    // browser refuses it the stroke still works,
                                    // and letting it throw here would abort the
                                    // handler and leave the pad silently dead.
                                }

                                const ctx = context();
                                const point = pointAt(event);
                                ctx?.beginPath();
                                ctx?.moveTo(point.x, point.y);

                                // A press is already a mark: draw the dot, so a
                                // tap is not a dead end where Simpan stays
                                // disabled with nothing to explain why.
                                ctx?.lineTo(point.x, point.y);
                                ctx?.stroke();

                                drawing.current = true;
                                setDrawn(true);
                            }}
                            onPointerMove={(event) => {
                                if (!drawing.current) {
                                    return;
                                }

                                const ctx = context();
                                const point = pointAt(event);
                                ctx?.lineTo(point.x, point.y);
                                ctx?.stroke();
                                setDrawn(true);
                            }}
                            onPointerUp={() => {
                                drawing.current = false;
                            }}
                        />

                        <Btn
                            type="button"
                            className="self-start"
                            onClick={clear}
                        >
                            <Eraser size={15} strokeWidth={1.5} />
                            Bersihkan
                        </Btn>
                    </div>
                ) : (
                    <FileDrop
                        value={file}
                        onChange={setFile}
                        accept=".png,.jpg,.jpeg"
                        hint="Gambar PNG atau JPEG, maks. 2MB"
                        label="Seret gambar tanda tangan ke sini"
                    />
                )}

                <DialogFooter>
                    <Btn onClick={() => onOpenChange(false)}>Batal</Btn>
                    <Btn
                        variant="primary"
                        disabled={!ready || saving}
                        onClick={submit}
                    >
                        {saving ? 'Menyimpan...' : 'Simpan Tanda Tangan'}
                    </Btn>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
