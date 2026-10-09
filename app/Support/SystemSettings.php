<?php

namespace App\Support;

use App\Models\DepartmentThesisSetting;
use App\Models\SystemSetting;
use Illuminate\Container\Attributes\Scoped;
use Illuminate\Support\Facades\Storage;

/**
 * The settings a superadmin may change without a deploy, read in one place.
 *
 * Each group is one row, loaded at most once per request: the thesis defaults
 * are consulted for every program studi that has not saved rules of its own,
 * which on a dashboard is every row of a table.
 */
#[Scoped]
final class SystemSettings
{
    public const INSTITUTION = 'institution';

    public const THESIS_DEFAULTS = 'thesis_defaults';

    /**
     * The rules a program studi sets for itself, and so the ones a default can
     * be given for. The examiner rule and the grade weights have no screen yet
     * and stay with the model.
     */
    public const THESIS_RULES = [
        'requires_proposal_exam',
        'min_proposal_guidance_sessions',
        'min_research_guidance_sessions',
        'min_logbook_progress',
        'max_supervisors_per_student',
        'guidance_slot_minutes',
        'perception_alignment_mode',
    ];

    /** @var array<string, array<string, mixed>|null> */
    private array $loaded = [];

    /**
     * The letterhead of every official document. What the superadmin saved
     * wins; until then the deployment's own configuration stands.
     *
     * @return array{name: string, unit: string|null, address: string|null, phone: string|null, website: string|null, city: string|null, logo_file: string|null}
     */
    public function institution(): array
    {
        $stored = $this->get(self::INSTITUTION);

        $text = fn (string $key): ?string => $stored === null
            ? (config("institution.{$key}") ?: null)
            : ($stored[$key] ?? null);

        return [
            'name' => $text('name') ?? '',
            'unit' => $text('unit'),
            'address' => $text('address'),
            'phone' => $text('phone'),
            'website' => $text('website'),
            'city' => $text('city'),
            'logo_file' => $this->logoFile($stored),
        ];
    }

    /**
     * The rules a program studi runs under until it saves its own.
     *
     * @return array<string, mixed>
     */
    public function thesisDefaults(): array
    {
        $builtIn = (new DepartmentThesisSetting)->only(self::THESIS_RULES);

        return [
            ...array_map(fn (mixed $value): mixed => $value instanceof \BackedEnum ? $value->value : $value, $builtIn),
            ...array_intersect_key($this->get(self::THESIS_DEFAULTS) ?? [], array_flip(self::THESIS_RULES)),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function get(string $key): ?array
    {
        if (! array_key_exists($key, $this->loaded)) {
            $this->loaded[$key] = SystemSetting::query()->where('key', $key)->value('value');
        }

        return $this->loaded[$key];
    }

    /**
     * @param  array<string, mixed>  $value
     */
    public function put(string $key, array $value): SystemSetting
    {
        $setting = SystemSetting::updateOrCreate(['key' => $key], ['value' => $value]);

        $this->loaded[$key] = $value;

        return $setting;
    }

    /**
     * A logo as something a PDF or an <img> can carry inline, so neither needs
     * the storage symlink to exist.
     */
    public static function dataUri(string $file): string
    {
        $mime = match (strtolower(pathinfo($file, PATHINFO_EXTENSION))) {
            'svg' => 'image/svg+xml',
            'jpg', 'jpeg' => 'image/jpeg',
            default => 'image/png',
        };

        return "data:{$mime};base64,".base64_encode((string) file_get_contents($file));
    }

    /**
     * An uploaded logo lives on the public disk; one named in configuration is
     * a file shipped under public/.
     *
     * @param  array<string, mixed>|null  $stored
     */
    private function logoFile(?array $stored): ?string
    {
        $uploaded = $stored['logo_path'] ?? null;

        if (is_string($uploaded) && $uploaded !== '' && Storage::disk('public')->exists($uploaded)) {
            return Storage::disk('public')->path($uploaded);
        }

        $configured = config('institution.logo_path');

        return is_string($configured) && $configured !== '' && file_exists(public_path($configured))
            ? public_path($configured)
            : null;
    }
}
