<?php

namespace App\Support;

use App\Enums\UserRole;
use App\Models\Department;

/**
 * One seat a user can occupy: a role, and the program studi it applies to.
 *
 * A person is often more than one thing at once — a dosen in two prodi who also
 * administers a third — so the pair, not the role alone, decides what they see
 * and may do. Superadmin is the only role that spans every prodi, and so is the
 * only one whose department is null.
 */
final readonly class Workspace
{
    public function __construct(
        public UserRole $role,
        public ?Department $department = null,
    ) {}

    /**
     * Stable identifier, used in the session and in the switcher's form.
     */
    public function key(): string
    {
        return $this->role->value.':'.($this->department === null ? 'all' : $this->department->id);
    }

    public function label(): string
    {
        return $this->department === null
            ? $this->role->label()
            : $this->role->label().' · '.$this->department->name;
    }

    public function is(Workspace $other): bool
    {
        return $this->key() === $other->key();
    }

    /**
     * @return array{key: string, role: string, role_label: string, department: string|null, label: string}
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key(),
            'role' => $this->role->value,
            'role_label' => $this->role->label(),
            'department' => $this->department?->name,
            'label' => $this->label(),
        ];
    }
}
