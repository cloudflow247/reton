<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Note: model events must remain enabled during seeding - domain models
     * assign their UUID primary keys on the `creating` event.
     */
    public function run(): void
    {
        // The house chart of accounts must exist before any money moves.
        $this->call(SystemAccountsSeeder::class);

        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        // Provision the sign-in demo accounts when demo mode is enabled.
        if (config('reton.demo.enabled')) {
            $this->call(DemoSeeder::class);
        }
    }
}
