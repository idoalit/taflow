import { Eye, EyeOff } from 'lucide-react';
import type { ComponentProps, Ref } from 'react';
import { useState } from 'react';
import { cn } from '@/lib/utils';

/**
 * Industry-styled password field with a reveal toggle. Kept separate from the
 * shadcn `password-input` the auth pages still use.
 */
export default function PasswordInput({
    className,
    ref,
    ...props
}: Omit<ComponentProps<'input'>, 'type'> & { ref?: Ref<HTMLInputElement> }) {
    const [visible, setVisible] = useState(false);

    return (
        <div className="relative">
            <input
                type={visible ? 'text' : 'password'}
                className={cn('ind-input pr-10', className)}
                ref={ref}
                {...props}
            />
            <button
                type="button"
                onClick={() => setVisible((shown) => !shown)}
                className="ind-muted absolute inset-y-0 right-0 flex items-center px-3"
                aria-label={
                    visible ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi'
                }
                tabIndex={-1}
            >
                {visible ? (
                    <EyeOff size={16} strokeWidth={1.5} />
                ) : (
                    <Eye size={16} strokeWidth={1.5} />
                )}
            </button>
        </div>
    );
}
