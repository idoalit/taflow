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

    /** False until looked up; after that the data URI, or null for no logo. */
    private string|false|null $logo = false;

    /**
     * The letterhead of every official document. What the superadmin saved
     * wins; until then the deployment's own configuration stands.
     *
     * @return array{name: string, unit: string|null, address: string|null, phone: string|null, website: string|null, city: string|null}
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
        $this->logo = false;

        return $setting;
    }

    /**
     * The letterhead logo as a data URI, or null when there is none. Read from
     * storage as bytes rather than by path: the disk it is on may be an object
     * store, which has no path to give, and a PDF or an <img> can carry the
     * image inline either way.
     *
     * Kept apart from institution(), which every document and the public
     * verification page call for the name alone.
     */
    public function logo(): ?string
    {
        if ($this->logo !== false) {
            return $this->logo;
        }

        $uploaded = $this->uploadedLogoPath();

        if ($uploaded !== null) {
            $contents = Storage::disk('documents')->get($uploaded);

            if ($contents !== null) {
                return $this->logo = self::dataUri($contents, $uploaded);
            }
        }

        // One named in configuration is a file shipped under public/.
        $configured = config('institution.logo_path');

        return $this->logo = is_string($configured) && $configured !== '' && file_exists(public_path($configured))
            ? self::dataUri((string) file_get_contents(public_path($configured)), $configured)
            : null;
    }

    /**
     * Where the logo the superadmin uploaded is kept on the documents disk.
     */
    public function uploadedLogoPath(): ?string
    {
        $path = $this->get(self::INSTITUTION)['logo_path'] ?? null;

        return is_string($path) && $path !== '' ? $path : null;
    }

    private static function dataUri(string $contents, string $name): string
    {
        $mime = match (strtolower(pathinfo($name, PATHINFO_EXTENSION))) {
            'svg' => 'image/svg+xml',
            'jpg', 'jpeg' => 'image/jpeg',
            default => 'image/png',
        };

        return "data:{$mime};base64,".base64_encode($contents);
    }
}
