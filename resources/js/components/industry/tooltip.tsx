import * as TooltipPrimitive from '@radix-ui/react-tooltip';
import { useState, type ReactNode } from 'react';

/**
 * Industry-styled tooltip.
 *
 * Radix opens on hover and on keyboard focus, but neither fires on a touch
 * screen, so the trigger also toggles it on tap. The trigger must stay
 * interactive for any of that to work — a genuinely `disabled` button emits no
 * pointer events at all, which is why callers mark locked controls with
 * `aria-disabled` instead.
 */
export function InfoTooltip({
    content,
    side = 'bottom',
    children,
}: {
    content: ReactNode;
    side?: 'top' | 'right' | 'bottom' | 'left';
    children: ReactNode;
}) {
    const [open, setOpen] = useState(false);

    return (
        <TooltipPrimitive.Root open={open} onOpenChange={setOpen}>
            <TooltipPrimitive.Trigger
                asChild
                onClick={() => setOpen((value) => !value)}
            >
                {children}
            </TooltipPrimitive.Trigger>

            <TooltipPrimitive.Portal>
                <TooltipPrimitive.Content
                    className="ind-tooltip"
                    side={side}
                    sideOffset={8}
                    collisionPadding={12}
                >
                    {content}
                    <TooltipPrimitive.Arrow
                        className="ind-tooltip-arrow"
                        width={11}
                        height={6}
                    />
                </TooltipPrimitive.Content>
            </TooltipPrimitive.Portal>
        </TooltipPrimitive.Root>
    );
}
