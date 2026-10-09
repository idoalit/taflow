<?php

namespace App\Models;

use App\Policies\DepartmentPolicy;
use App\Support\SystemSettings;
use Carbon\CarbonInterface;
use Database\Factories\DepartmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property string|null $faculty
 * @property string|null $city
 * @property string $code
 * @property string|null $description
 * @property bool $is_active
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property-read int|null $students_count
 * @property-read int|null $lecturers_count
 * @property-read int|null $admins_count
 */
#[Fillable(['name', 'faculty', 'city', 'code', 'description', 'is_active'])]
#[UsePolicy(DepartmentPolicy::class)]
class Department extends Model
{
    /** @use HasFactory<DepartmentFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /** @return HasMany<Student, $this> */
    public function students(): HasMany
    {
        return $this->hasMany(Student::class);
    }

    /** @return HasMany<DepartmentThesisSetting, $this> */
    public function thesisSettings(): HasMany
    {
        return $this->hasMany(DepartmentThesisSetting::class);
    }

    /** @return BelongsToMany<Lecturer, $this> */
    public function lecturers(): BelongsToMany
    {
        return $this->belongsToMany(Lecturer::class)->withPivot('is_primary')->withTimestamps();
    }

    /** @return BelongsToMany<User, $this> */
    public function admins(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }

    /**
     * The thesis settings currently in force for this department, falling back
     * to the system-wide defaults when the department has never been
     * configured.
     */
    public function activeThesisSetting(): DepartmentThesisSetting
    {
        return $this->thesisSettings()
            ->where('is_active', true)
            ->where('effective_since', '<=', now())
            ->orderByDesc('effective_since')
            ->first() ?? new DepartmentThesisSetting([
                ...app(SystemSettings::class)->thesisDefaults(),
                'department_id' => $this->id,
            ]);
    }
}
