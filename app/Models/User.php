<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\UserRole;
use App\Support\Workspace;
use Carbon\CarbonInterface;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property CarbonInterface|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property CarbonInterface|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property-read Student|null $student
 * @property-read Lecturer|null $lecturer
 */
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    private ?Workspace $activeWorkspace = null;

    /** @var Collection<int, Workspace>|null */
    private ?Collection $workspaceCache = null;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    /** @return HasOne<Student, $this> */
    public function student(): HasOne
    {
        return $this->hasOne(Student::class);
    }

    /** @return HasOne<Lecturer, $this> */
    public function lecturer(): HasOne
    {
        return $this->hasOne(Lecturer::class);
    }

    /**
     * Program studi this user administers. Only ever populated for the admin
     * role; students and lecturers reach their departments through their
     * profile, which is what keeps the two kinds of seat distinguishable.
     *
     * @return BelongsToMany<Department, $this>
     */
    public function departments(): BelongsToMany
    {
        return $this->belongsToMany(Department::class)->withTimestamps();
    }

    /**
     * Every seat this user may occupy, ordered so the most likely default comes
     * first. Roles are read through a fresh query on purpose: switching into a
     * workspace narrows the loaded roles relation, and the full list has to
     * survive that.
     *
     * @return Collection<int, Workspace>
     */
    public function workspaces(): Collection
    {
        if ($this->workspaceCache !== null) {
            return $this->workspaceCache;
        }

        $roles = $this->roles()->pluck('name')
            ->map(fn (string $name): ?UserRole => UserRole::tryFrom($name))
            ->filter()
            ->sortBy(fn (UserRole $role): int => (int) array_search($role, UserRole::cases(), true));

        return $this->workspaceCache = $roles
            ->flatMap(fn (UserRole $role): Collection => $this->workspacesFor($role))
            ->values();
    }

    /**
     * The seat this request is being served from. Falls back to the first
     * available one so a user who has never switched still sees something.
     */
    public function activeWorkspace(): ?Workspace
    {
        if ($this->activeWorkspace === null) {
            $first = $this->workspaces()->first();

            if ($first !== null) {
                $this->useWorkspace($first);
            }
        }

        return $this->activeWorkspace;
    }

    /**
     * Enter a workspace for the rest of this request. The spatie roles relation
     * is narrowed to the workspace's role, so every `can()` in the application
     * answers for the seat the user is actually occupying — without that, a
     * dosen who also administers a prodi would keep the admin's permissions
     * while working as a dosen.
     */
    public function useWorkspace(Workspace $workspace): void
    {
        $this->activeWorkspace = $workspace;

        $this->setRelation(
            'roles',
            $this->roles()->where('name', $workspace->role->value)->get(),
        );

        $this->forgetCachedPermissions();
    }

    public function workspaceFromKey(string $key): ?Workspace
    {
        return $this->workspaces()->first(fn (Workspace $workspace): bool => $workspace->key() === $key);
    }

    /**
     * The role currently in effect. Kept for the many call sites that only care
     * which kind of user is looking.
     */
    public function primaryRole(): ?UserRole
    {
        return $this->activeWorkspace()?->role;
    }

    /**
     * @return Collection<int, Workspace>
     */
    private function workspacesFor(UserRole $role): Collection
    {
        return match ($role) {
            UserRole::SuperAdmin => collect([new Workspace($role)]),
            UserRole::Student => collect($this->student?->department === null
                ? []
                : [new Workspace($role, $this->student->department)]),
            // A dosen's home prodi comes first; an admin's are simply ordered by
            // name, since none of them is primary.
            UserRole::Lecturer => ($this->lecturer?->departments()
                ->orderByPivot('is_primary', 'desc')
                ->orderBy('name')
                ->get() ?? collect())
                ->map(fn (Department $department): Workspace => new Workspace($role, $department)),
            UserRole::Admin => $this->departments()->orderBy('name')->get()
                ->map(fn (Department $department): Workspace => new Workspace($role, $department)),
        };
    }

    /**
     * Initials used by the avatar blocks throughout the interface.
     */
    public function initials(): string
    {
        return Str::of($this->name)
            ->explode(' ')
            ->filter(fn (string $part): bool => preg_match('/^\p{L}/u', $part) === 1)
            ->take(2)
            ->map(fn (string $part): string => Str::upper(Str::substr($part, 0, 1)))
            ->implode('');
    }
}
