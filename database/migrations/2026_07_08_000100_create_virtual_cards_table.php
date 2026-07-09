<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('virtual_cards', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('wallet_id')->constrained('wallets')->restrictOnDelete();

            $table->string('provider', 32)->default('interswitch');
            $table->string('card_identifier', 100);
            $table->string('status', 16); // pending | active | blocked

            $table->char('currency', 3)->default('NGN');
            $table->string('scheme', 16)->default('verve');

            $table->string('pan_last4', 4);
            $table->string('pan_masked', 32);
            $table->text('pan_encrypted');
            $table->text('cvv_encrypted');
            $table->text('cvv2_encrypted')->nullable();
            $table->string('expiry', 4); // MMYY
            $table->string('seq_nr', 8)->nullable();
            $table->string('customer_id')->nullable();
            $table->string('name_on_card');

            $table->text('card_pin_encrypted');
            $table->json('metadata')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'card_identifier']);
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('virtual_cards');
    }
};
