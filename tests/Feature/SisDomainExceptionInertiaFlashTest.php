<?php

namespace Tests\Feature;

use App\Domain\Shared\Exceptions\SisDomainException;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

final class SisDomainExceptionInertiaFlashTest extends TestCase
{
    public function test_domain_exception_flashes_error_for_web_requests(): void
    {
        Route::middleware('web')->get('/__sis_domain_error_probe', function () {
            throw SisDomainException::withCode('enrollment.invalid_status');
        });

        Route::middleware('web')->get('/__sis_domain_error_back', function () {
            return response('ok');
        });

        $this->withHeader('Referer', url('/__sis_domain_error_back'))
            ->get('/__sis_domain_error_probe')
            ->assertRedirect('/__sis_domain_error_back')
            ->assertSessionHas('error', 'enrollment.invalid_status');
    }

    public function test_domain_exception_returns_json_for_api_requests(): void
    {
        Route::middleware('api')->get('/api/__sis_domain_error_probe', function () {
            throw SisDomainException::withCode('enrollment.invalid_status');
        });

        $this->getJson('/api/__sis_domain_error_probe')
            ->assertStatus(422)
            ->assertJson([
                'message' => 'enrollment.invalid_status',
                'error_code' => 'enrollment.invalid_status',
            ]);
    }
}
