<?php

namespace Tests\Unit\Workflow;

use App\Domain\Workflow\Support\ApprovalDecisionRules;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ApprovalDecisionRulesRoleTest extends TestCase
{
    #[Test]
    public function role_for_step_and_actor_validation(): void
    {
        $steps = [
            ['step' => 1, 'role' => 'transfers_manager'],
            ['step' => 2, 'role' => 'workflow_manager'],
        ];

        $this->assertSame('transfers_manager', ApprovalDecisionRules::roleForStep($steps, 1));
        $this->assertSame('workflow_manager', ApprovalDecisionRules::roleForStep($steps, 2));
        $this->assertNull(ApprovalDecisionRules::roleForStep($steps, 9));

        $this->assertSame([], ApprovalDecisionRules::validateActorRole('transfers_manager', ['transfers_manager']));
        $this->assertSame(
            ['workflow.step_role_mismatch'],
            ApprovalDecisionRules::validateActorRole('transfers_manager', ['workflow_manager']),
        );
        $this->assertSame(
            ['workflow.step_role_undefined'],
            ApprovalDecisionRules::validateActorRole(null, ['workflow_manager']),
        );
    }
}
