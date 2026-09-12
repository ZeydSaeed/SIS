<?php

namespace Tests\Unit\Observability;

use App\Application\Observability\Support\PrometheusTextBuilder;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PrometheusTextBuilderTest extends TestCase
{
    #[Test]
    public function builds_prometheus_text_with_escaped_labels(): void
    {
        $body = (new PrometheusTextBuilder)
            ->help('sis_up', 'probe')
            ->type('sis_up', 'gauge')
            ->gauge('sis_up', 1)
            ->gauge('sis_http_workload_p95_ms', 12.5, ['workload' => 'finance_oltp'])
            ->build();

        $this->assertStringContainsString("# HELP sis_up probe\n", $body);
        $this->assertStringContainsString("# TYPE sis_up gauge\n", $body);
        $this->assertStringContainsString("sis_up 1\n", $body);
        $this->assertStringContainsString('sis_http_workload_p95_ms{workload="finance_oltp"} 12.5', $body);
    }
}
