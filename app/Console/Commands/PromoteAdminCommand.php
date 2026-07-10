<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use App\Support\Admin\AdminPath;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class PromoteAdminCommand extends Command
{
    protected $signature = 'reton:admin
                            {email : The user email to promote or demote}
                            {--revoke : Remove admin access instead}
                            {--create : Create the user if not found}
                            {--name= : Display name when creating a new user}
                            {--password= : Password when creating (auto-generated if omitted)}';

    protected $description = 'Grant or revoke platform administrator access (never stores credentials in git)';

    public function handle(): int
    {
        $email = strtolower(trim((string) $this->argument('email')));
        $user = User::query()->whereRaw('lower(email) = ?', [$email])->first();

        if ($user === null && $this->option('create')) {
            $password = (string) ($this->option('password') ?: Str::password(24));
            $name = (string) ($this->option('name') ?: Str::before($email, '@'));

            $user = User::query()->create([
                'name' => $name,
                'email' => strtolower(trim($email)),
                'password' => $password,
            ]);

            $user->forceFill([
                'email_verified_at' => now(),
                'is_admin' => true,
            ])->save();

            $this->info("Created user {$email}.");
            $this->line('Temporary password: '.$password);
            $this->newLine();
            $this->warn('Save this password now — it will not be shown again.');
            $this->line('Open '.AdminPath::url().' after signing in to configure ALATPay and other integrations.');

            return self::SUCCESS;
        }

        if ($user === null) {
            $this->error('No user found with that email. Pass --create to register one.');

            return self::FAILURE;
        }

        if ($this->option('revoke')) {
            $user->update(['is_admin' => false]);
            $this->info("Revoked admin access for {$user->email}.");

            return self::SUCCESS;
        }

        $updates = ['is_admin' => true];

        if ($user->email_verified_at === null) {
            $updates['email_verified_at'] = now();
        }

        $user->update($updates);
        $this->info("{$user->email} is now a platform administrator.");
        $this->line('Open '.AdminPath::url().' after signing in to configure ALATPay and other integrations.');

        return self::SUCCESS;
    }
}
