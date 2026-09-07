<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class SecurityTestCommand extends Command
{
    protected $signature = 'security:test';

    protected $description = 'Run the security-focused PHPUnit suite';

    public function handle(): int
    {
        return Artisan::call('test', [
            '--testsuite' => 'Security',
        ], $this->output);
    }
}
