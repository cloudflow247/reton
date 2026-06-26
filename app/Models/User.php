<?php

namespace App\Models;

use App\Domain\Auth\Models\Device;
use App\Domain\Wallet\Models\Wallet;
use App\Support\Concerns\HasUuidKey;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens;

    /** @use HasFactory<UserFactory> */
    use HasFactory;

    use HasUuidKey;
    use Notifiable;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'country',
        'password',
        'status',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'transaction_pin',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'pin_locked_until' => 'datetime',
            'pin_attempts' => 'integer',
            'password' => 'hashed',
        ];
    }

    /** @return MorphMany<Wallet, $this> */
    public function wallets(): MorphMany
    {
        return $this->morphMany(Wallet::class, 'owner');
    }

    /** @return HasMany<Device, $this> */
    public function devices(): HasMany
    {
        return $this->hasMany(Device::class);
    }

    public function hasTransactionPin(): bool
    {
        return $this->transaction_pin !== null;
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
