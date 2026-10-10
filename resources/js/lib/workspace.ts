import { router } from '@inertiajs/react';
import { update as updateWorkspace } from '@/routes/workspace';
import type { Workspace } from '@/types';

/** Past this many seats a plain list stops being quick to scan. */
export const WORKSPACE_LIST_LIMIT = 6;

const RECENT_LIMIT = 3;

const storageKey = (userId: number) => `workspace-terakhir:${userId}`;

/**
 * Storage is a convenience here, never a requirement: a private window or a
 * full quota must not stop anybody from switching workspace.
 */
function readRecentKeys(userId: number): string[] {
    try {
        const stored: unknown = JSON.parse(
            window.localStorage.getItem(storageKey(userId)) ?? '[]',
        );

        return Array.isArray(stored)
            ? stored.filter((key): key is string => typeof key === 'string')
            : [];
    } catch {
        return [];
    }
}

function rememberWorkspace(userId: number, key: string): void {
    try {
        const keys = [
            key,
            ...readRecentKeys(userId).filter((stored) => stored !== key),
        ].slice(0, RECENT_LIMIT);

        window.localStorage.setItem(storageKey(userId), JSON.stringify(keys));
    } catch {
        // Nothing to do: the switch itself still goes ahead.
    }
}

/**
 * The seats this person last stepped into, newest first. Kept in the browser,
 * so it is theirs on this device only. A seat they no longer hold is dropped,
 * and so is the one they are sitting in — neither is somewhere to go.
 */
export function recentWorkspaces(
    userId: number,
    workspaces: Workspace[],
    currentKey: string | null,
): Workspace[] {
    return readRecentKeys(userId)
        .filter((key) => key !== currentKey)
        .map((key) => workspaces.find((workspace) => workspace.key === key))
        .filter((workspace): workspace is Workspace => workspace !== undefined);
}

/**
 * Step into another seat. Every switcher goes through here, so "terakhir
 * dibuka" is the same list whichever of them was used.
 */
export function switchWorkspace(key: string, userId: number): void {
    rememberWorkspace(userId, key);

    router.post(
        updateWorkspace().url,
        { workspace: key },
        { preserveState: false },
    );
}
