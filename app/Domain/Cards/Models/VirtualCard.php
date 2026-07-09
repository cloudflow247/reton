<?php

declare(strict_types=1);

namespace App\Domain\Cards\Models;

use App\Domain\Cards\Data\VirtualCardBillingAddress;
use App\Domain\Cards\Enums\VirtualCardStatus;
use App\Domain\Wallet\Models\Wallet;
use App\Models\User;
use App\Support\Concerns\HasUuidKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;

class VirtualCard extends Model
{
    use HasUuidKey;

    protected $fillable = [
        'user_id',
        'wallet_id',
        'provider',
        'card_identifier',
        'provider_card_id',
        'provider_cardholder_id',
        'status',
        'currency',
        'scheme',
        'pan_last4',
        'pan_masked',
        'pan_encrypted',
        'cvv_encrypted',
        'cvv2_encrypted',
        'expiry',
        'seq_nr',
        'customer_id',
        'name_on_card',
        'card_pin_encrypted',
        'metadata',
        'activated_at',
    ];

    protected $hidden = [
        'pan_encrypted',
        'cvv_encrypted',
        'cvv2_encrypted',
        'card_pin_encrypted',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => VirtualCardStatus::class,
            'metadata' => 'array',
            'activated_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Wallet, $this> */
    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    public function pan(): string
    {
        return Crypt::decryptString($this->pan_encrypted);
    }

    public function cvv(): string
    {
        return Crypt::decryptString($this->cvv_encrypted);
    }

    public function cvv2(): ?string
    {
        return $this->cvv2_encrypted ? Crypt::decryptString($this->cvv2_encrypted) : null;
    }

    public function cardPin(): string
    {
        return Crypt::decryptString($this->card_pin_encrypted);
    }

    public static function encryptSecret(string $value): string
    {
        return Crypt::encryptString($value);
    }

    public function expiryDisplay(): string
    {
        if (strlen($this->expiry) !== 4) {
            return '••/••';
        }

        return substr($this->expiry, 0, 2).'/'.substr($this->expiry, 2, 2);
    }

    public function formattedPan(): string
    {
        $pan = $this->pan();

        return trim(chunk_split($pan, 4, ' '));
    }

    public function providerCardId(): string
    {
        return (string) ($this->provider_card_id ?? $this->card_identifier);
    }

    /** @return array<string, string> */
    public function billingAddress(): array
    {
        $stored = $this->metadata['billing_address'] ?? null;

        if (is_array($stored) && ($stored['line1'] ?? '') !== '') {
            return VirtualCardBillingAddress::fromArray($stored)->toArray();
        }

        return VirtualCardBillingAddress::defaultFor($this->currency)->toArray();
    }
}
