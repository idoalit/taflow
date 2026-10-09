import { router } from '@inertiajs/react';
import { LogIn } from 'lucide-react';
import { Btn } from '@/components/industry/ui';
import { update as updateWorkspace } from '@/routes/workspace';

/**
 * Steps the superadmin into a program studi's Admin Prodi seat. It is the same
 * workspace switch the sidebar makes, offered where the prodi is being looked
 * at; the way back is the switcher's "Superadmin" entry.
 */
export function EnterDepartment({
    workspace,
    name,
    variant = 'secondary',
    compact = false,
}: {
    workspace: string;
    name: string;
    variant?: 'primary' | 'secondary';
    compact?: boolean;
}) {
    return (
        <Btn
            variant={variant}
            aria-label={`Masuk sebagai Admin Prodi ${name}`}
            onClick={(event) => {
                // Inside a clickable table row, entering must not also open it.
                event.stopPropagation();

                router.post(
                    updateWorkspace().url,
                    { workspace },
                    { preserveState: false },
                );
            }}
        >
            <LogIn size={15} strokeWidth={1.5} />
            {compact ? 'Masuk' : 'Masuk sebagai Admin Prodi'}
        </Btn>
    );
}
