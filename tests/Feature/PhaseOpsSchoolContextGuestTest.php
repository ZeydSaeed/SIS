<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class PhaseOpsSchoolContextGuestTest extends TestCase
{
    #[Test]
    public function guest_hub_still_loads_without_school_context(): void
    {
        $this->get('/hub?desktop=1')
            ->assertSuccessful()
            ->assertInertia(fn ($page) => $page
                ->component('hub')
                ->where('schoolContext.schoolId', null));
    }

    #[Test]
    public function security_config_enables_web_school_bootstrap(): void
    {
        $this->assertTrue((bool) config('security.allow_implicit_single_school'));
        $this->assertTrue((bool) config('security.bootstrap_web_school_session'));
    }
}
