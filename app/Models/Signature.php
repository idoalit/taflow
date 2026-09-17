<?php

namespace App\Models;

use App\Enums\SignatureType;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * A person's signature image, with the audit of when it was captured.
 *
 * @property int $id
 * @property int $user_id
 * @property SignatureType $type
 * @property string $path
 * @property CarbonInterface $signed_at
 * @property string|null $ip_address
 * @property string|null $user_agent
 */
#[Fillable(['user_id', 'type', 'path', 'signed_at', 'ip_address', 'user_agent'])]
class Signature extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => SignatureType::class,
            'signed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The image as a data URI, which is how a PDF renderer can embed it
     * without reaching back into the filesystem.
     */
    public function toDataUri(): ?string
    {
        $disk = Storage::disk('local');

        if (! $disk->exists($this->path)) {
            return null;
        }

        return 'data:image/png;base64,'.base64_encode($disk->get($this->path));
    }

    public function absolutePath(): ?string
    {
        $disk = Storage::disk('local');

        return $disk->exists($this->path) ? $disk->path($this->path) : null;
    }
}
