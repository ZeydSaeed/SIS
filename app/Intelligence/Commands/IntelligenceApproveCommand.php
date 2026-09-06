<?php

namespace App\Intelligence\Commands;

use App\Intelligence\Governance\ApprovalGate;
use App\Intelligence\Models\Recommendation;
use App\Intelligence\Optimization\SafeAutoExecutor;
use Illuminate\Console\Command;

class IntelligenceApproveCommand extends Command
{
    protected $signature = 'intelligence:approve {code : Recommendation code} {--user=1} {--reason=}';

    protected $description = 'Approve a pending intelligence recommendation (Tier 2+)';

    public function handle(ApprovalGate $approvalGate, SafeAutoExecutor $executor): int
    {
        $recommendation = Recommendation::query()
            ->where('recommendation_code', $this->argument('code'))
            ->first();

        if ($recommendation === null) {
            $this->error('Recommendation not found.');

            return self::FAILURE;
        }

        $approvalGate->approve(
            $recommendation,
            (int) $this->option('user'),
            $this->option('reason')
        );

        $event = $executor->executeApproved($recommendation->fresh());

        $this->info("Recommendation {$recommendation->recommendation_code} approved.");
        if ($event !== null) {
            $this->line("Optimization event: {$event->event_code}");
        }

        return self::SUCCESS;
    }
}
