import * as DialogPrimitive from '@radix-ui/react-dialog';
import { X } from 'lucide-react';
import type { ComponentProps } from 'react';
import { BlueprintCorners } from '@/components/industry/blueprint';
import { cn } from '@/lib/utils';

/**
 * Industry-styled modal: square, hairline-bordered, with the system's
 * registration marks. Built on the same Radix primitives as the shadcn dialog,
 * so focus trapping, escape handling and scroll locking are unchanged.
 */
export const Dialog = DialogPrimitive.Root;
export const DialogTrigger = DialogPrimitive.Trigger;
export const DialogClose = DialogPrimitive.Close;

export function DialogContent({
    className,
    children,
    showClose = true,
    ...props
}: ComponentProps<typeof DialogPrimitive.Content> & { showClose?: boolean }) {
    return (
        <DialogPrimitive.Portal>
            <DialogPrimitive.Overlay className="ind-dialog-backdrop" />
            <DialogPrimitive.Content
                {...props}
                className={cn(
                    'ind ind-dialog ind-bp ind-dialog-centered',
                    className,
                )}
            >
                <BlueprintCorners />

                <div className="ind-dialog-body">{children}</div>

                {showClose && (
                    <DialogPrimitive.Close
                        className="ind-btn ind-btn-ghost ind-btn-icon absolute top-2 right-2"
                        aria-label="Tutup"
                    >
                        <X size={16} strokeWidth={1.5} />
                    </DialogPrimitive.Close>
                )}
            </DialogPrimitive.Content>
        </DialogPrimitive.Portal>
    );
}

export function DialogTitle({
    className,
    ...props
}: ComponentProps<typeof DialogPrimitive.Title>) {
    return (
        <DialogPrimitive.Title
            {...props}
            className={cn('ind-dialog-title', className)}
        />
    );
}

export function DialogDescription({
    className,
    ...props
}: ComponentProps<typeof DialogPrimitive.Description>) {
    return (
        <DialogPrimitive.Description
            {...props}
            className={cn('ind-muted m-0 text-[13.5px]', className)}
        />
    );
}

export function DialogFooter({ className, ...props }: ComponentProps<'div'>) {
    return (
        <div
            {...props}
            className={cn(
                'mt-[6px] flex flex-wrap justify-end gap-[8px]',
                className,
            )}
        />
    );
}
