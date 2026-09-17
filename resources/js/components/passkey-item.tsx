import { KeyRound, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { Btn } from '@/components/industry/ui';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/industry/dialog';
import type { Passkey } from '@/types/auth';

type Props = {
    passkey: Passkey;
    onDelete: (id: number, onError: () => void) => void;
};

export default function PasskeyItem({ passkey, onDelete }: Props) {
    const [isDeleting, setIsDeleting] = useState(false);

    const handleDelete = () => {
        setIsDeleting(true);
        onDelete(passkey.id, () => setIsDeleting(false));
    };

    return (
        <div
            className="flex items-center justify-between p-4"
            style={{ borderBottom: '1px solid var(--ind-divider)' }}
        >
            <div className="flex items-center gap-4">
                <div
                    className="flex h-10 w-10 shrink-0 items-center justify-center"
                    style={{ background: 'var(--ind-surface)' }}
                >
                    <KeyRound size={18} strokeWidth={1.5} />
                </div>
                <div className="space-y-1">
                    <div className="flex items-center gap-2.5">
                        <p className="font-medium tracking-tight">
                            {passkey.name}
                        </p>
                        {passkey.authenticator && (
                            <span className="ind-tag ind-tag-neutral uppercase">
                                {passkey.authenticator}
                            </span>
                        )}
                    </div>
                    <p className="ind-muted m-0 text-[13px]">
                        Ditambahkan {passkey.created_at_diff}
                        {passkey.last_used_at_diff && (
                            <>
                                <span className="ind-muted mx-1">/</span>
                                terakhir dipakai {passkey.last_used_at_diff}
                            </>
                        )}
                    </p>
                </div>
            </div>

            <Dialog>
                <DialogTrigger asChild>
                    <Btn
                        variant="ghost"
                        className="ind-btn-icon"
                        style={{ color: 'var(--ind-status-error-fg)' }}
                    >
                        <Trash2 size={16} strokeWidth={1.5} />
                        <span className="sr-only">Hapus passkey</span>
                    </Btn>
                </DialogTrigger>
                <DialogContent>
                    <DialogTitle>Hapus passkey</DialogTitle>
                    <DialogDescription>
                        Yakin ingin menghapus passkey "{passkey.name}"? Passkey
                        ini tidak lagi dapat digunakan untuk masuk ke FiPro.
                    </DialogDescription>
                    <DialogFooter className="gap-2">
                        <DialogClose asChild>
                            <Btn variant="secondary">Batal</Btn>
                        </DialogClose>
                        <Btn
                            variant="danger"
                            onClick={handleDelete}
                            disabled={isDeleting}
                        >
                            {isDeleting ? 'Menghapus...' : 'Hapus Passkey'}
                        </Btn>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    );
}
