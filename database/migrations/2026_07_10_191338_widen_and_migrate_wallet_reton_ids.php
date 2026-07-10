<?php

declare(strict_types=1);

use App\Domain\Wallet\Models\Wallet;
use Illuminate\Database\Migrations\Migration;

/**
 * Re-issue every legacy numeric wallet number as a RETON ID (R + 9 digits).
 * Column width stays 10+ chars — no schema change required for the new format.
 */
return new class extends Migration
{
    public function up(): void
    {
        Wallet::reissueLegacyAccountNumbers();
    }

    public function down(): void
    {
        // Irreversible: legacy numbers are preserved in metadata.legacy_account_number.
    }
};
