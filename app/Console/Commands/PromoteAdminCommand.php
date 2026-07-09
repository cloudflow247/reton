<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use App\Support\Admin\AdminPath;
use Illuminate\Console\Command;

class PromoteAdminCommand extends Command
{
    protected $signature = 'reton:admin {email : The user email to promote or demote} {--revoke : Remove admin access instead}';

    protected $description = 'Grant or revoke platform administrator access (never stores credentials in git)';

    public function handle(): int
    {
        $user = User::query()->where('email', $this->argument('email'))->first();

        if ($user === null) {
            $this->error('No user found with that email.');

            return self::FAILURE;
        }

        if ($this->option('revoke')) {
            $user->update(['is_admin' => false]);
            $this->info("Revoked admin access for {$user->email}.");

            return self::SUCCESS;
        }

        $user->update(['is_admin' => true]);
        $this->info("{$user->email} is now a platform administrator.");
        $this->line('Open '.AdminPath::url().' after signing in to configure ALATPay and other integrations.');

        return self::SUCCESS;
    }
}
