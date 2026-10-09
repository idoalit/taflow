import { usePage } from '@inertiajs/react';
import { Check, ChevronsUpDown } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { BlueprintCorners } from '@/components/industry/blueprint';
import { Tag } from '@/components/industry/ui';
import { switchWorkspace, WORKSPACE_LIST_LIMIT } from '@/lib/workspace';
import { cn } from '@/lib/utils';

/**
 * Where the person is working, always in view at the top of the page — and,
 * for anyone with more than one seat, the way to another.
 *
 * A handful of seats open as a short list, one click each. A superadmin's
 * seat in every program studi is far too many for that, so there the control
 * opens the palette instead, where they can be searched.
 */
export function WorkspaceSwitcher({
    onOpenPalette,
    compact = false,
}: {
    onOpenPalette: () => void;
    compact?: boolean;
}) {
    const user = usePage().props.auth.user;
    const [open, setOpen] = useState(false);
    const containerRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
        if (!open) {
            return;
        }

        const onMouseDown = (event: MouseEvent) => {
            if (!containerRef.current?.contains(event.target as Node)) {
                setOpen(false);
            }
        };

        const onKeyDown = (event: KeyboardEvent) => {
            if (event.key === 'Escape') {
                setOpen(false);
            }
        };

        document.addEventListener('mousedown', onMouseDown);
        document.addEventListener('keydown', onKeyDown);

        return () => {
            document.removeEventListener('mousedown', onMouseDown);
            document.removeEventListener('keydown', onKeyDown);
        };
    }, [open]);

    const current = user.workspace;

    if (current === null) {
        return null;
    }

    const width = compact ? 'max-w-[132px]' : 'max-w-[300px]';

    // One seat is nothing to switch between, so it is stated, not offered.
    if (user.workspaces.length < 2) {
        return (
            <Tag
                label={current.label}
                tone="neutral"
                className={cn('block truncate', width)}
            />
        );
    }

    const searchable = user.workspaces.length > WORKSPACE_LIST_LIMIT;

    return (
        <div className="relative min-w-0 flex-shrink" ref={containerRef}>
            <button
                type="button"
                aria-label={`Ganti workspace, sekarang ${current.label}`}
                aria-haspopup={searchable ? 'dialog' : 'menu'}
                aria-expanded={searchable ? undefined : open}
                onClick={() => {
                    if (searchable) {
                        onOpenPalette();
                    } else {
                        setOpen((value) => !value);
                    }
                }}
                className={cn(
                    'ind-btn ind-btn-secondary h-[30px] gap-[6px] px-[10px] text-[12px]',
                    width,
                )}
            >
                <span className="truncate">{current.label}</span>
                <ChevronsUpDown
                    size={13}
                    strokeWidth={1.5}
                    className="flex-shrink-0"
                />
            </button>

            {open && (
                <div
                    role="menu"
                    aria-label="Workspace"
                    className="ind-bp absolute top-[calc(100%+6px)] right-0 z-80 w-max max-w-[min(320px,calc(100vw-32px))] min-w-[220px] border p-[var(--ind-space-2)]"
                    style={{
                        background: 'var(--ind-bg)',
                        borderColor: 'var(--ind-divider)',
                        boxShadow: 'var(--ind-shadow-lg)',
                    }}
                >
                    <BlueprintCorners />
                    <div className="ind-eyebrow px-2 py-[6px]">
                        Ganti Workspace
                    </div>
                    {user.workspaces.map((workspace) => {
                        const active = workspace.key === current.key;

                        return (
                            <button
                                key={workspace.key}
                                type="button"
                                role="menuitemradio"
                                aria-checked={active}
                                className="ind-nav-item"
                                style={{
                                    background: active
                                        ? 'var(--ind-accent-100)'
                                        : undefined,
                                }}
                                onClick={() => {
                                    setOpen(false);

                                    if (!active) {
                                        switchWorkspace(workspace.key, user.id);
                                    }
                                }}
                            >
                                <span className="min-w-0 flex-1 truncate">
                                    {workspace.label}
                                </span>
                                {active && (
                                    <Check size={15} strokeWidth={1.5} />
                                )}
                            </button>
                        );
                    })}
                </div>
            )}
        </div>
    );
}
