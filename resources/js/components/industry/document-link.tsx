import { Download, FileText } from 'lucide-react';
import { useState } from 'react';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogTitle,
} from '@/components/industry/dialog';
import { Btn } from '@/components/industry/ui';
import { cn } from '@/lib/utils';

export type PreviewableDocument = {
    name: string;
    url: string | null;
    kind?: string;
    preview_url?: string | null;
    previewable?: boolean;
};

function usePreview(document: PreviewableDocument) {
    const [open, setOpen] = useState(false);
    const canPreview = Boolean(document.previewable && document.preview_url);

    const dialog = open && (
        <Dialog open onOpenChange={(next) => setOpen(next)}>
            <DialogContent className="w-[min(920px,calc(100vw-32px))]">
                <DialogTitle className="truncate pr-8">
                    {document.name}
                </DialogTitle>

                <div
                    className="w-full border"
                    style={{
                        borderColor: 'var(--ind-divider)',
                        background: 'var(--ind-surface)',
                        height: 'min(68dvh, 620px)',
                    }}
                >
                    <iframe
                        src={document.preview_url ?? ''}
                        title={`Pratinjau ${document.name}`}
                        className="h-full w-full"
                        style={{ border: 0 }}
                    />
                </div>

                <DialogFooter>
                    {document.url && (
                        <a
                            href={document.url}
                            className="ind-btn ind-btn-secondary"
                        >
                            <Download size={15} strokeWidth={1.5} />
                            Unduh
                        </a>
                    )}
                    <Btn onClick={() => setOpen(false)}>Tutup</Btn>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );

    return { canPreview, open: () => setOpen(true), dialog };
}

function hint(document: PreviewableDocument, canPreview: boolean): string {
    if (canPreview) {
        return 'Klik nama berkas untuk pratinjau';
    }

    return document.url === null
        ? 'Berkas tidak ditemukan'
        : `Berkas ${document.kind ?? ''} hanya dapat diunduh`.replace(
              '  ',
              ' ',
          );
}

function DownloadIcon({ document }: { document: PreviewableDocument }) {
    if (document.url === null) {
        return null;
    }

    return (
        <a
            href={document.url}
            className="ind-btn ind-btn-ghost ind-btn-icon flex-shrink-0"
            aria-label={`Unduh ${document.name}`}
            title={`Unduh ${document.name}`}
        >
            <Download size={16} strokeWidth={1.5} />
        </a>
    );
}

/**
 * A file row: the name opens a preview, the icon beside it downloads. Files we
 * cannot render inline (docx and friends) fall back to downloading on click.
 */
export function DocumentLink({
    document,
    meta,
    className,
}: {
    document: PreviewableDocument;
    meta?: string;
    className?: string;
}) {
    const preview = usePreview(document);

    return (
        <>
            <div className={cn('flex min-w-0 items-center gap-3', className)}>
                <FileText
                    size={18}
                    strokeWidth={1.5}
                    className="flex-shrink-0"
                    style={{ color: 'var(--ind-accent-700)' }}
                />

                <div className="min-w-0 flex-1">
                    {preview.canPreview ? (
                        <button
                            type="button"
                            onClick={preview.open}
                            className="ind-file-name block max-w-full truncate"
                        >
                            {document.name}
                        </button>
                    ) : (
                        <a
                            href={document.url ?? undefined}
                            className="ind-file-name block max-w-full truncate"
                        >
                            {document.name}
                        </a>
                    )}

                    <div className="ind-muted text-[12px]">
                        {meta ?? hint(document, preview.canPreview)}
                    </div>
                </div>

                <DownloadIcon document={document} />
            </div>

            {preview.dialog}
        </>
    );
}

/**
 * The same behaviour compressed to one line, for chat bubbles and table cells.
 */
export function DocumentChip({
    document,
    className,
}: {
    document: PreviewableDocument;
    className?: string;
}) {
    const preview = usePreview(document);

    return (
        <>
            <span
                className={cn(
                    'mt-1 inline-flex max-w-full items-center gap-1',
                    className,
                )}
            >
                <FileText
                    size={13}
                    strokeWidth={1.5}
                    className="flex-shrink-0"
                />
                {preview.canPreview ? (
                    <button
                        type="button"
                        onClick={preview.open}
                        className="ind-file-name truncate text-[12px]"
                    >
                        {document.name}
                    </button>
                ) : (
                    <a
                        href={document.url ?? undefined}
                        className="ind-file-name truncate text-[12px]"
                    >
                        {document.name}
                    </a>
                )}
                {document.url && (
                    <a
                        href={document.url}
                        className="ind-file-download flex-shrink-0"
                        aria-label={`Unduh ${document.name}`}
                        title={`Unduh ${document.name}`}
                    >
                        <Download size={13} strokeWidth={1.5} />
                    </a>
                )}
            </span>

            {preview.dialog}
        </>
    );
}
