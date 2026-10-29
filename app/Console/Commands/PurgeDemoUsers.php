<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

final class PurgeDemoUsers extends Command
{
    public const DEMO_EMAIL_DOMAIN = '@phumpanya.test';

    protected $signature = 'app:purge-demo-users {--dry-run : List the accounts without deleting them}';

    protected $description = 'Delete the seeded demo accounts (@phumpanya.test) that share a known password';

    public function handle(): int
    {
        $users = User::query()
            ->where('email', 'like', '%'.self::DEMO_EMAIL_DOMAIN)
            ->get(['id', 'email']);

        if ($users->isEmpty()) {
            $this->info('No demo accounts found.');

            return self::SUCCESS;
        }

        foreach ($users as $user) {
            $this->line(($this->option('dry-run') ? '[dry-run] ' : 'deleted ').$user->email);

            if (! $this->option('dry-run')) {
                $user->delete();
            }
        }

        $this->info(sprintf('%d demo account(s) %s.', $users->count(), $this->option('dry-run') ? 'found' : 'deleted'));

        return self::SUCCESS;
    }
}
