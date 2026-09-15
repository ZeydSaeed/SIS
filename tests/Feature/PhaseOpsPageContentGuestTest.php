<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class PhaseOpsPageContentGuestTest extends TestCase
{
    #[Test]
    public function guest_legacy_hub_redirects_to_login(): void
    {
        $this->get('/hub?desktop=1')->assertRedirect('/login');
    }

    #[Test]
    public function guest_cannot_open_ops_module_pages(): void
    {
        $this->get('/students')->assertRedirect('/login');
        $this->get('/attendance')->assertRedirect('/login');
        $this->get('/timetable')->assertRedirect('/login');
        $this->get('/exams')->assertRedirect('/login');
        $this->get('/enrollments')->assertRedirect('/login');
        $this->get('/teachers')->assertRedirect('/login');
        $this->get('/grades')->assertRedirect('/login');
        $this->get('/results')->assertRedirect('/login');
        $this->get('/reports')->assertRedirect('/login');
    }
}
