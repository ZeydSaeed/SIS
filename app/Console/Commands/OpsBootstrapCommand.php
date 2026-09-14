<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\Ops\OpsWorkspaceBootstrap;
use Illuminate\Console\Command;

final class OpsBootstrapCommand extends Command
{
    protected $signature = 'sis:ops-bootstrap
                            {--email= : User email to bind (defaults to first user)}
                            {--user= : User id to bind}';

    protected $description = 'Seed demo school + academic year and grant ops manager roles to a user (local/ops only)';

    public function handle(OpsWorkspaceBootstrap $bootstrap): int
    {
        if (! $bootstrap->isEnabled()) {
            $this->error('Ops bootstrap is disabled. Set SECURITY_OPS_BOOTSTRAP_ENABLED=true (local only).');

            return self::FAILURE;
        }

        $user = $this->resolveUser();
        if ($user === null) {
            $this->error('No user found. Create a login account first.');

            return self::FAILURE;
        }

        $result = $bootstrap->bootstrapFor($user);

        $this->info("Bootstrapped ops workspace for {$user->email}");
        $this->line("school_id={$result['school_id']}");
        $this->line("academic_year_id={$result['academic_year_id']}");
        $this->line("roles_assigned={$result['roles_assigned']}");

        return self::SUCCESS;
    }

    private function resolveUser(): ?User
    {
        $userId = $this->option('user');
        if (is_numeric($userId)) {
            return User::query()->find((int) $userId);
        }

        $email = $this->option('email');
        if (is_string($email) && $email !== '') {
            return User::query()->where('email', $email)->first();
        }

        return User::query()->orderBy('id')->first();
    }
}
