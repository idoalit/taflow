import { Head, router, setLayoutProps } from '@inertiajs/react';
import { Lock } from 'lucide-react';
import { useState } from 'react';
import { Btn, DataTable, PageHeader, Tabs } from '@/components/industry/ui';
import { update } from '@/routes/superadmin/roles';
import type { UserRole } from '@/types';

type Role = {
    value: UserRole;
    label: string;
    editable: boolean;
    users: number;
    permissions: string[];
    defaults: string[];
};

type Group = {
    label: string;
    permissions: { name: string; label: string; reserved: boolean }[];
};

const sameSet = (left: string[], right: string[]) =>
    left.length === right.length && left.every((item) => right.includes(item));

export default function SuperAdminRoleIndex({
    roles,
    groups,
}: {
    roles: Role[];
    groups: Group[];
}) {
    setLayoutProps({ title: 'Peran & Izin' });

    const [selected, setSelected] = useState<UserRole>(
        roles.find((role) => role.editable)?.value ?? roles[0].value,
    );
    const role = roles.find((candidate) => candidate.value === selected)!;

    // Edits are kept per role, so switching tabs to compare does not throw
    // away what was ticked on the one before.
    const [drafts, setDrafts] = useState<Partial<Record<UserRole, string[]>>>(
        {},
    );
    const [saving, setSaving] = useState(false);

    const granted = drafts[role.value] ?? role.permissions;
    const dirty = !sameSet(granted, role.permissions);
    const atDefault = sameSet(granted, role.defaults);

    const setGranted = (permissions: string[]) =>
        setDrafts((current) => ({ ...current, [role.value]: permissions }));

    const save = () =>
        router.put(
            update(role.value).url,
            { permissions: granted },
            {
                preserveScroll: true,
                onStart: () => setSaving(true),
                onFinish: () => setSaving(false),
                onSuccess: () =>
                    setDrafts((current) => {
                        const { [role.value]: _saved, ...rest } = current;

                        return rest;
                    }),
            },
        );

    return (
        <>
            <Head title="Peran & Izin" />

            <div className="ind-page max-w-[820px]">
                <PageHeader
                    title="Peran & Izin"
                    description="Empat peran aplikasi ini tetap. Yang dapat diatur adalah apa saja yang boleh dilakukan mahasiswa, dosen, dan admin prodi."
                />

                <Tabs
                    label="Peran"
                    value={selected}
                    options={roles.map((option) => ({
                        value: option.value,
                        label: option.label,
                        count: option.users,
                    }))}
                    onChange={setSelected}
                />

                <p className="ind-muted m-0 text-[12.5px]">
                    {role.editable
                        ? 'Mencabut izin langsung menutup layar terkait bagi semua pemegang peran ini. Memberi izin tidak selalu membuka layar baru: sebagian layar juga mensyaratkan data profil, misalnya hanya akun mahasiswa yang dapat mengajukan proposal.'
                        : 'Superadmin selalu memegang seluruh izin, sehingga peran ini tidak dapat diubah.'}
                </p>

                {groups.map((group) => (
                    <div key={group.label} className="ind-section">
                        <h6>{group.label}</h6>
                        <DataTable
                            head={
                                <tr>
                                    <th>Izin</th>
                                    <th className="w-[120px]">Diberikan</th>
                                </tr>
                            }
                        >
                            {group.permissions.map((permission) => {
                                const locked =
                                    !role.editable || permission.reserved;
                                const checked = role.editable
                                    ? granted.includes(permission.name)
                                    : true;

                                return (
                                    <tr key={permission.name}>
                                        <td>
                                            <label
                                                htmlFor={`permission-${permission.name}`}
                                                className="block font-semibold"
                                            >
                                                {permission.label}
                                            </label>
                                            <span className="ind-muted block text-[12px]">
                                                {permission.name}
                                                {permission.reserved &&
                                                    role.editable &&
                                                    ' · hanya superadmin'}
                                            </span>
                                        </td>
                                        <td>
                                            <span className="flex items-center gap-2">
                                                <input
                                                    id={`permission-${permission.name}`}
                                                    type="checkbox"
                                                    checked={checked}
                                                    disabled={locked}
                                                    onChange={(event) =>
                                                        setGranted(
                                                            event.target.checked
                                                                ? [
                                                                      ...granted,
                                                                      permission.name,
                                                                  ]
                                                                : granted.filter(
                                                                      (name) =>
                                                                          name !==
                                                                          permission.name,
                                                                  ),
                                                        )
                                                    }
                                                />
                                                {locked && (
                                                    <Lock
                                                        size={13}
                                                        strokeWidth={1.5}
                                                        aria-label="Terkunci"
                                                        style={{
                                                            color: 'var(--ind-neutral-600)',
                                                        }}
                                                    />
                                                )}
                                            </span>
                                        </td>
                                    </tr>
                                );
                            })}
                        </DataTable>
                    </div>
                ))}

                {role.editable && (
                    <div className="flex flex-wrap gap-[var(--ind-space-3)]">
                        <Btn
                            variant="primary"
                            disabled={saving || !dirty}
                            onClick={save}
                        >
                            {saving
                                ? 'Menyimpan...'
                                : `Simpan Izin ${role.label}`}
                        </Btn>
                        <Btn
                            disabled={saving || atDefault}
                            onClick={() => setGranted(role.defaults)}
                        >
                            Kembalikan ke Bawaan
                        </Btn>
                    </div>
                )}
            </div>
        </>
    );
}
