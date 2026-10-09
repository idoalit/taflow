import { router, usePage } from '@inertiajs/react';
import * as DialogPrimitive from '@radix-ui/react-dialog';
import { Check, Search, type LucideIcon } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { BlueprintCorners } from '@/components/industry/blueprint';
import { navigationFor } from '@/layouts/industry/navigation';
import { recentWorkspaces, switchWorkspace } from '@/lib/workspace';
import type { Workspace } from '@/types';

/** Everything, or — when opened from the workspace switcher — only seats. */
export type PaletteScope = 'all' | 'workspace';

type Entry = {
    id: string;
    group: string;
    /** Whether the heading says how many rows the group currently holds. */
    counted?: boolean;
    label: string;
    hint?: string;
    icon?: LucideIcon;
    current?: boolean;
    /** What the search term is matched against, already lower-cased. */
    haystack: string;
    run: () => void;
};

/**
 * Ctrl/⌘+K: one field that reaches every screen the current role has and every
 * workspace the person holds. A superadmin has a seat in each program studi,
 * which is more than any menu can list; here it is a few keystrokes.
 */
export function CommandPalette({
    scope,
    onClose,
}: {
    scope: PaletteScope | null;
    onClose: () => void;
}) {
    return (
        <DialogPrimitive.Root
            open={scope !== null}
            onOpenChange={(open) => {
                if (!open) {
                    onClose();
                }
            }}
        >
            <DialogPrimitive.Portal>
                <DialogPrimitive.Overlay className="ind-dialog-backdrop" />
                <DialogPrimitive.Content
                    className="ind ind-palette ind-bp"
                    aria-describedby={undefined}
                >
                    <BlueprintCorners />
                    <DialogPrimitive.Title className="sr-only">
                        {scope === 'workspace'
                            ? 'Pindah workspace'
                            : 'Cari menu atau workspace'}
                    </DialogPrimitive.Title>

                    {/* Mounted only while open, so each opening starts from an
                        empty field and the first row. */}
                    {scope !== null && (
                        <PaletteBody scope={scope} onClose={onClose} />
                    )}
                </DialogPrimitive.Content>
            </DialogPrimitive.Portal>
        </DialogPrimitive.Root>
    );
}

function PaletteBody({
    scope,
    onClose,
}: {
    scope: PaletteScope;
    onClose: () => void;
}) {
    const page = usePage();
    const user = page.props.auth.user;
    const thesis = page.props.thesis;

    const [query, setQuery] = useState('');
    const [active, setActive] = useState(0);
    const listRef = useRef<HTMLDivElement>(null);

    const entries = useMemo(
        () => [
            ...(scope === 'all'
                ? menuEntries(
                      navigationFor(user.role, thesis, user.permissions),
                  )
                : []),
            ...workspaceEntries(user.id, user.workspaces, user.workspace),
        ],
        [scope, user, thesis],
    );

    const term = query.trim().toLowerCase();

    // "Terakhir dibuka" repeats seats that are also in the full list, which is
    // a shortcut while browsing and a duplicate once the person is searching.
    const matches = entries.filter((entry) =>
        term === ''
            ? true
            : !entry.id.startsWith('recent:') && entry.haystack.includes(term),
    );

    // Keep the highlighted row in view while arrowing through a long list.
    useEffect(() => {
        listRef.current
            ?.querySelector<HTMLElement>(`[data-index="${active}"]`)
            ?.scrollIntoView({ block: 'nearest' });
    }, [active]);

    // Counted from what is on screen, so a heading never claims forty seats
    // above the three a search left behind.
    const heading = (entry: Entry) =>
        entry.counted
            ? `${entry.group} (${matches.filter((match) => match.counted && match.group === entry.group).length})`
            : entry.group;

    const choose = (entry: Entry) => {
        onClose();
        entry.run();
    };

    const onKeyDown = (event: React.KeyboardEvent) => {
        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            event.preventDefault();
            setActive((index) => {
                const next = event.key === 'ArrowDown' ? index + 1 : index - 1;

                return Math.max(0, Math.min(matches.length - 1, next));
            });

            return;
        }

        if (event.key === 'Enter' && matches[active]) {
            event.preventDefault();
            choose(matches[active]);
        }
    };

    return (
        <>
            <div
                className="flex items-center gap-[10px] px-[14px]"
                style={{ borderBottom: '1px solid var(--ind-divider)' }}
            >
                <Search
                    size={16}
                    strokeWidth={1.5}
                    className="ind-muted flex-shrink-0"
                />
                <input
                    role="combobox"
                    aria-expanded
                    aria-controls="palette-list"
                    aria-activedescendant={
                        matches[active] ? `palette-option-${active}` : undefined
                    }
                    aria-label={
                        scope === 'workspace'
                            ? 'Cari workspace'
                            : 'Cari menu atau workspace'
                    }
                    value={query}
                    onChange={(event) => {
                        setQuery(event.target.value);
                        setActive(0);
                    }}
                    onKeyDown={onKeyDown}
                    placeholder={
                        scope === 'workspace'
                            ? 'Pindah ke workspace...'
                            : 'Cari menu atau workspace...'
                    }
                    className="w-full border-0 bg-transparent py-[13px] text-[15px] outline-none"
                    style={{ fontFamily: 'var(--ind-font-body)' }}
                />
            </div>

            <div
                id="palette-list"
                role="listbox"
                ref={listRef}
                className="ind-palette-list"
            >
                {matches.length === 0 ? (
                    <p className="ind-muted m-0 px-3 py-6 text-center text-[13px]">
                        {scope === 'workspace'
                            ? 'Tidak ada workspace yang cocok.'
                            : 'Tidak ada menu atau workspace yang cocok.'}
                    </p>
                ) : (
                    matches.map((entry, index) => {
                        const Icon = entry.icon;
                        const opensGroup =
                            index === 0 ||
                            matches[index - 1].group !== entry.group;

                        return (
                            <div key={entry.id}>
                                {opensGroup && (
                                    <div
                                        className="ind-eyebrow px-2 pt-3 pb-1"
                                        role="presentation"
                                    >
                                        {heading(entry)}
                                    </div>
                                )}
                                <button
                                    type="button"
                                    role="option"
                                    id={`palette-option-${index}`}
                                    aria-selected={index === active}
                                    data-index={index}
                                    data-active={index === active}
                                    // Move, not enter: a list scrolling under a
                                    // still pointer must not steal the row the
                                    // keyboard is on.
                                    onMouseMove={() => setActive(index)}
                                    onClick={() => choose(entry)}
                                    className="ind-palette-option"
                                >
                                    {Icon && (
                                        <Icon
                                            size={16}
                                            strokeWidth={1.5}
                                            style={{
                                                color: 'var(--ind-neutral-600)',
                                            }}
                                        />
                                    )}
                                    <span className="min-w-0 flex-1 truncate">
                                        {entry.label}
                                    </span>
                                    {entry.hint && (
                                        <span className="ind-muted flex-shrink-0 text-[12px]">
                                            {entry.hint}
                                        </span>
                                    )}
                                    {entry.current && (
                                        <Check
                                            size={15}
                                            strokeWidth={1.5}
                                            aria-label="Sedang dibuka"
                                        />
                                    )}
                                </button>
                            </div>
                        );
                    })
                )}
            </div>

            <div
                className="ind-muted flex flex-wrap items-center gap-x-4 gap-y-1 px-[14px] py-2 text-[11.5px]"
                style={{ borderTop: '1px solid var(--ind-divider)' }}
                aria-hidden
            >
                <span className="flex items-center gap-[6px]">
                    <kbd className="ind-kbd">↑</kbd>
                    <kbd className="ind-kbd">↓</kbd>
                    pilih
                </span>
                <span className="flex items-center gap-[6px]">
                    <kbd className="ind-kbd">Enter</kbd>
                    buka
                </span>
                <span className="flex items-center gap-[6px]">
                    <kbd className="ind-kbd">Esc</kbd>
                    tutup
                </span>
            </div>
        </>
    );
}

/**
 * The sidebar of the current role, flattened. It is the same list the sidebar
 * draws, so an item the role is not permitted has already been left out.
 */
function menuEntries(groups: ReturnType<typeof navigationFor>): Entry[] {
    return groups.flatMap((group) =>
        group.items.map((item) => ({
            id: `menu:${item.href}`,
            group: 'Menu',
            label: item.label,
            hint: group.label,
            icon: item.icon,
            haystack: `${item.label} ${group.label}`.toLowerCase(),
            run: () => router.visit(item.href),
        })),
    );
}

/**
 * Seats in the order somebody looks for them: the one that spans every prodi,
 * the ones just used, then all of them by role. Nothing is listed for a person
 * with a single seat — there is nowhere to switch to.
 */
function workspaceEntries(
    userId: number,
    workspaces: Workspace[],
    current: Workspace | null,
): Entry[] {
    if (workspaces.length < 2) {
        return [];
    }

    const entry = (
        workspace: Workspace,
        group: string,
        id: string,
        counted = false,
    ): Entry => ({
        id,
        group,
        counted,
        // A seat is named by its prodi; the role is the heading it sits under.
        // The one seat with no prodi has only its role to be named by.
        label: workspace.department ?? workspace.role_label,
        hint:
            group === 'Terakhir Dibuka'
                ? workspace.role_label
                : (workspace.department_code ?? undefined),
        current: workspace.key === current?.key,
        haystack: [
            workspace.department,
            workspace.department_code,
            workspace.role_label,
        ]
            .filter(Boolean)
            .join(' ')
            .toLowerCase(),
        run: () => {
            if (workspace.key !== current?.key) {
                switchWorkspace(workspace.key, userId);
            }
        },
    });

    const spanning = workspaces.filter(
        (workspace) => workspace.department === null,
    );
    const seated = workspaces.filter(
        (workspace) => workspace.department !== null,
    );

    return [
        ...spanning.map((workspace) =>
            entry(workspace, 'Workspace', `workspace:${workspace.key}`),
        ),
        ...recentWorkspaces(userId, seated, current?.key ?? null).map(
            (workspace) =>
                entry(workspace, 'Terakhir Dibuka', `recent:${workspace.key}`),
        ),
        ...seated.map((workspace) =>
            entry(
                workspace,
                workspace.role_label,
                `workspace:${workspace.key}`,
                true,
            ),
        ),
    ];
}
