<?php

namespace App\Models;

use App\Policies\SystemSettingPolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;

/**
 * One group of settings the superadmin may change. Read through
 * App\Support\SystemSettings rather than queried directly.
 *
 * @property int $id
 * @property string $key
 * @property array<string, mixed> $value
 */
#[Fillable(['key', 'value'])]
#[UsePolicy(SystemSettingPolicy::class)]
class SystemSetting extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['value' => 'array'];
    }
}
