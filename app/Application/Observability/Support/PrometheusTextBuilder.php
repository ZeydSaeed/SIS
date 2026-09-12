<?php

namespace App\Application\Observability\Support;

/**
 * Prometheus text exposition format (0.0.4) — no vendor client.
 */
final class PrometheusTextBuilder
{
    /** @var list<string> */
    private array $lines = [];

    public function help(string $name, string $help): self
    {
        $this->lines[] = '# HELP '.$name.' '.$this->escapeHelp($help);
        return $this;
    }

    public function type(string $name, string $type): self
    {
        $this->lines[] = '# TYPE '.$name.' '.$type;
        return $this;
    }

    /**
     * @param  array<string, scalar|null>  $labels
     */
    public function gauge(string $name, float|int $value, array $labels = []): self
    {
        $this->lines[] = $name.$this->formatLabels($labels).' '.$this->formatValue($value);

        return $this;
    }

    public function build(): string
    {
        return implode("\n", $this->lines)."\n";
    }

    /**
     * @param  array<string, scalar|null>  $labels
     */
    private function formatLabels(array $labels): string
    {
        if ($labels === []) {
            return '';
        }

        $parts = [];
        foreach ($labels as $key => $value) {
            if ($value === null) {
                continue;
            }
            $parts[] = $key.'="'.$this->escapeLabel((string) $value).'"';
        }

        return $parts === [] ? '' : '{'.implode(',', $parts).'}';
    }

    private function formatValue(float|int $value): string
    {
        if (is_int($value)) {
            return (string) $value;
        }

        if (is_nan($value)) {
            return 'Nan';
        }

        if (is_infinite($value)) {
            return $value > 0 ? '+Inf' : '-Inf';
        }

        return rtrim(rtrim(sprintf('%.6F', $value), '0'), '.') ?: '0';
    }

    private function escapeLabel(string $value): string
    {
        return str_replace(['\\', "\n", '"'], ['\\\\', '\\n', '\\"'], $value);
    }

    private function escapeHelp(string $value): string
    {
        return str_replace(["\n", '\\'], [' ', '\\\\'], $value);
    }
}
