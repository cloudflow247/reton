<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Prepare a local Reton install with demo accounts, wallets, and sample listings.
 */
class DemoInstallCommand extends Command
{
    protected $signature = 'reton:demo
                            {--fresh : Drop all tables and rebuild the database}';

    protected $description = 'Migrate and seed Ada/Bola demo accounts for local review';

    public function handle(): int
    {
        if (! config('reton.demo.enabled')) {
            $this->warn('RETON_DEMO_MODE is false — enable it in .env so the sign-in screen shows demo buttons.');
        }

        if ($this->option('fresh')) {
            $this->call('migrate:fresh', ['--force' => true]);
        } else {
            $this->call('migrate', ['--force' => true]);
        }

        $this->call('db:seed', ['--class' => \Database\Seeders\DemoSeeder::class, '--force' => true]);

        $password = (string) config('reton.demo.password', 'demo1234');
        $pin = (string) config('reton.demo.pin', '1234');

        $hotFile = public_path('hot');
        $manifest = public_path('build/manifest.json');
        if (is_file($hotFile) && ! is_file($manifest)) {
            $this->warn('public/hot points at a Vite dev server but no production build exists — run npm run build or composer dev.');
        } elseif (is_file($hotFile)) {
            $this->warn('public/hot is present — if the page is blank, another app may own that Vite port. Run npm run build or delete public/hot.');
        } elseif (! is_file($manifest)) {
            $this->warn('No frontend build found — run npm run build (or composer dev) before opening the app.');
        }

        $this->newLine();
        $this->info('Demo ready — start the stack with: composer dev');
        $this->line('  App:  '.config('app.url', 'http://127.0.0.1:8000'));
        $this->line('  Login: /login (tap a demo account or use credentials below)');
        $this->newLine();
        $this->table(
            ['Name', 'Email', 'Password', 'PIN', 'Wallet'],
            collect((array) config('reton.demo.accounts', []))->map(fn (array $a) => [
                $a['name'],
                $a['email'],
                $password,
                $pin,
                isset($a['fund']) ? '₦'.number_format($a['fund'] / 100, 2) : '—',
            ])->all(),
        );

        return self::SUCCESS;
    }
}
