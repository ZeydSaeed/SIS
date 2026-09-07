<?php

namespace App\Optimization\Contracts;

/**
 * RCA → Recommendation contract for the self-healing safety pipeline.
 */
final readonly class IncidentReport
{
    /**
     * @param  array<string, mixed>  $evidence
     * @param  array<string, mixed>  $supportingMetrics
     * @param  list<string>  $candidateActions
     */
    public function __construct(
        public string $incidentId,
        public string $timestamp,
        public string $primaryComponent,
        public string $affectedComponent,
        public string $target,
        public string $rootCause,
        public float $confidence,
        public array $evidence,
        public array $supportingMetrics,
        public array $candidateActions,
        public string $risk,
        public string $severity,
        public ?string $schemaName = null,
        public ?string $tableName = null,
        public ?string $queryFingerprint = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'incident_id' => $this->incidentId,
            'timestamp' => $this->timestamp,
            'primary_component' => $this->primaryComponent,
            'affected_component' => $this->affectedComponent,
            'target' => $this->target,
            'root_cause' => $this->rootCause,
            'confidence' => $this->confidence,
            'evidence' => $this->evidence,
            'supporting_metrics' => $this->supportingMetrics,
            'candidate_actions' => $this->candidateActions,
            'risk' => $this->risk,
            'severity' => $this->severity,
            'schema_name' => $this->schemaName,
            'table_name' => $this->tableName,
            'query_fingerprint' => $this->queryFingerprint,
        ];
    }
}
