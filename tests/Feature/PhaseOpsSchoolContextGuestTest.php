<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class PhaseOpsSchoolContextGuestTest extends TestCase
{
    #[Test]
    public function guest_legacy_hub_redirects_to_login(): void
    {
        $this->get('/hub?desktop=1')->assertRedirect('/login');
    }

    #[Test]
    public function security_config_enables_web_school_bootstrap(): void
    {
        $this->assertTrue((bool) config('security.allow_implicit_single_school'));
        $this->assertTrue((bool) config('security.bootstrap_web_school_session'));
    }

    #[Test]
    public function context_routes_require_authentication(): void
    {
        $this->post('/context/school', ['school_id' => 1])->assertRedirect();
        $this->post('/context/academic-year', ['academic_year_id' => 1])->assertRedirect();
        $this->post('/context/ops-bootstrap')->assertRedirect();
    }

    #[Test]
    public function ops_bootstrap_config_key_is_defined(): void
    {
        $this->assertIsBool(config('security.ops_bootstrap_enabled'));
    }
}
