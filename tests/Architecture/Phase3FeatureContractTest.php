<?php

namespace Tests\Architecture;

use App\Architecture\FeatureContractValidator;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class Phase3FeatureContractTest extends TestCase
{
    #[Test]
    public function student_bounded_context_satisfies_feature_contract(): void
    {
        $validator = new FeatureContractValidator;
        $violations = $validator->validate('Student');

        $this->assertSame(
            [],
            $violations,
            "Student feature contract violations:\n".implode("\n", $violations),
        );
    }

    #[Test]
    public function observability_health_query_satisfies_feature_contract(): void
    {
        $validator = new FeatureContractValidator;

        $this->assertFileExists(app_path('Application/Observability/Queries/GetHealthStatusHandler.php'));
        $this->assertFileExists(app_path('Application/Observability/Queries/GetHealthStatusQuery.php'));
        $this->assertFileExists(app_path('Application/Observability/DTOs/HealthStatusDTO.php'));

        $violations = $validator->validate('Observability');

        $this->assertSame(
            [],
            $violations,
            "Observability feature contract violations:\n".implode("\n", $violations),
        );
    }

    #[Test]
    public function phase3_api_controllers_delegate_to_handlers_only(): void
    {
        $controllers = [
            app_path('Http/Controllers/Api/HealthController.php'),
            app_path('Http/Controllers/Api/StudentController.php'),
        ];

        foreach ($controllers as $controller) {
            $content = file_get_contents($controller);
            $this->assertNotFalse($content);
            $this->assertDoesNotMatchRegularExpression(
                '/\buse App\\\\Intelligence\\\\Models\\\\/',
                $content,
                basename($controller).' must not import Intelligence models',
            );
            $this->assertDoesNotMatchRegularExpression(
                '/\bDB::/',
                $content,
                basename($controller).' must not use DB facade',
            );
        }
    }
}
