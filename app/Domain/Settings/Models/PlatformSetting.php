<?php

declare(strict_types=1);

namespace App\Domain\Settings\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;

class PlatformSetting extends Model
{
    public $incrementing = false;

    protected $primaryKey = 'group';

    protected $keyType = 'string';

    protected $fillable = [
        'group',
        'payload_encrypted',
        'updated_by',
    ];

    /** @return BelongsTo<User, $this> */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /** @return array<string, mixed> */
    public function decryptPayload(): array
    {
        $json = Crypt::decryptString($this->payload_encrypted);

        return json_decode($json, true, 512, JSON_THROW_ON_ERROR);
    }

    /** @param  array<string, mixed>  $payload */
    public static function encryptPayload(array $payload): string
    {
        return Crypt::encryptString(json_encode($payload, JSON_THROW_ON_ERROR));
    }
}
