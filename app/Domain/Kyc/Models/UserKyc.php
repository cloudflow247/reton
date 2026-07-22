<?php

declare(strict_types=1);

namespace App\Domain\Kyc\Models;

use App\Domain\Kyc\Enums\KycTier;
use App\Domain\Payments\Enums\StaticWalletType;
use App\Models\User;
use App\Support\Concerns\HasUuidKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;

class UserKyc extends Model
{
    use HasUuidKey;

    protected $table = 'user_kyc';

    protected $fillable = [
        'user_id',
        'tier',
        'bvn_encrypted',
        'bvn_hash',
        'bvn_last4',
        'nin_encrypted',
        'nin_hash',
        'nin_last4',
        'date_of_birth',
        'address_line1',
        'city',
        'state',
        'bvn_verified_at',
        'nin_verified_at',
    ];

    protected $casts = [
        'tier' => KycTier::class,
        'date_of_birth' => 'date',
        'bvn_verified_at' => 'datetime',
        'nin_verified_at' => 'datetime',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function staticWalletType(): StaticWalletType
    {
        return $this->tier->value >= KycTier::Tier2->value
            ? StaticWalletType::Individual
            : StaticWalletType::Collection;
    }

    public function decryptedBvn(): ?string
    {
        if ($this->bvn_encrypted === null) {
            return null;
        }

        return Crypt::decryptString($this->bvn_encrypted);
    }

    public function storeBvn(string $bvn): void
    {
        $this->bvn_encrypted = Crypt::encryptString($bvn);
        $this->bvn_hash = hash('sha256', $bvn);
        $this->bvn_last4 = substr($bvn, -4);
        $this->bvn_verified_at = now();
    }

    /**
     * Clear BVN identity so another user may verify the same BVN (support only).
     * Does not delete deposit accounts - review those separately.
     */
    public function clearBvn(): void
    {
        $this->bvn_encrypted = null;
        $this->bvn_hash = null;
        $this->bvn_last4 = null;
        $this->bvn_verified_at = null;

        if ($this->tier === KycTier::Tier2) {
            $this->tier = KycTier::Tier1;
        }

        $this->save();
    }

    public function storeNin(string $nin): void
    {
        $this->nin_encrypted = Crypt::encryptString($nin);
        $this->nin_hash = hash('sha256', $nin);
        $this->nin_last4 = substr($nin, -4);
        $this->nin_verified_at = now();
    }
}
