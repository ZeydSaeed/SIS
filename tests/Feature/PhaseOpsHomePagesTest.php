<?php

namespace Tests\Feature;

use App\Models\User;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class PhaseOpsHomePagesTest extends TestCase
{
    #[Test]
    public function guest_home_redirects_to_login(): void
    {
        $this->get('/')->assertRedirect('/login');
    }

    #[Test]
    public function guest_legacy_hub_redirects_to_login_then_dashboard(): void
    {
        $this->get('/hub?desktop=1')->assertRedirect('/login');
    }

    #[Test]
    public function authenticated_home_redirects_to_dashboard(): void
    {
        $user = new User;
        $user->forceFill([
            'id' => 1,
            'name' => 'Ops Home Tester',
            'email' => 'ops-home@example.test',
        ]);
        $user->exists = true;

        $this->actingAs($user)
            ->get('/')
            ->assertRedirect(route('dashboard'));
    }

    #[Test]
    public function authenticated_legacy_hub_redirects_to_dashboard(): void
    {
        $user = new User;
        $user->forceFill([
            'id' => 2,
            'name' => 'Ops Hub Tester',
            'email' => 'ops-hub@example.test',
        ]);
        $user->exists = true;

        $this->actingAs($user)
            ->get('/hub?desktop=1')
            ->assertRedirect('/dashboard?desktop=1');
    }

    #[Test]
    public function guest_cannot_open_dashboard(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }

    #[Test]
    public function guest_cannot_open_students_or_attendance(): void
    {
        $this->get('/students')->assertRedirect('/login');
        $this->get('/attendance')->assertRedirect('/login');
        $this->get('/timetable')->assertRedirect('/login');
        $this->get('/exams')->assertRedirect('/login');
    }
}
