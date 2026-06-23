<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Ledger\Enums\AccountType;
use App\Domain\Ledger\Models\LedgerAccount;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<LedgerAccount>
 */
class LedgerAccountFactory extends Factory
{
    protected $model = LedgerAccount::class;

    public function definition(): array
    {
        return [
            'code' => 'acct:'.Str::lower(Str::random(12)),
            'name' => fake()->words(2, true),
            'type' => AccountType::Liability,
            'currency' => 'NGN',
            'is_system' => false,
        ];
    }

    public function system(): static
    {
        return $this->state(fn () => ['is_system' => true]);
    }

    public function asset(): static
    {
        return $this->state(fn () => ['type' => AccountType::Asset]);
    }

    public function liability(): static
    {
        return $this->state(fn () => ['type' => AccountType::Liability]);
    }

    public function revenue(): static
    {
        return $this->state(fn () => ['type' => AccountType::Revenue]);
    }
}
