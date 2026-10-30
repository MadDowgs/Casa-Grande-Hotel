<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class PromoteUserToAdmin extends Command
{
    protected $signature = 'admin:promote {email : Email address of the existing account}';

    protected $description = 'Grant administrator access to an existing user account';

    public function handle(): int
    {
        $user = User::where('email', $this->argument('email'))->first();

        if (! $user) {
            $this->components->error('No account exists with that email address.');

            return self::FAILURE;
        }

        if ($user->is_admin) {
            $this->components->info("{$user->email} is already an administrator.");

            return self::SUCCESS;
        }

        $user->is_admin = true;
        $user->save();

        $this->components->info("Administrator access granted to {$user->email}.");

        return self::SUCCESS;
    }
}
