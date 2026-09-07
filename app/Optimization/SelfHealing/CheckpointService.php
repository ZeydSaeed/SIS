<?php

namespace App\Optimization\SelfHealing;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

final class CheckpointService
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function create(array $context): string
    {
        $id = 'CHK-'.now()->format('Ymd').'-'.Str::upper(Str::random(6));
        $path = config('optimization.checkpoints_path');
        File::ensureDirectoryExists($path);

        $checkpoint = array_merge([
            'checkpoint_id' => $id,
            'execution_status' => CheckpointStatus::Created->value,
            'guard_status' => null,
            'stabilization_status' => null,
            'rollback_status' => null,
            'final_outcome' => null,
            'created_at' => now()->toIso8601String(),
            'updated_at' => now()->toIso8601String(),
            'git_commit' => $this->gitCommit(),
        ], $context);

        $this->persist($id, $checkpoint);

        return $id;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function transition(string $checkpointId, CheckpointStatus $status, array $data = []): void
    {
        $checkpoint = $this->load($checkpointId);
        if ($checkpoint === null) {
            return;
        }

        $checkpoint['execution_status'] = $status->value;
        $checkpoint['updated_at'] = now()->toIso8601String();
        $checkpoint = array_merge($checkpoint, $data);

        $this->persist($checkpointId, $checkpoint);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function load(string $checkpointId): ?array
    {
        $file = config('optimization.checkpoints_path')."/{$checkpointId}.json";
        if (! File::exists($file)) {
            return null;
        }

        $data = json_decode(File::get($file), true);

        return is_array($data) ? $data : null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function findBlockingForTarget(string $target): array
    {
        $normalized = strtolower(trim($target));
        $blocking = [];

        foreach ($this->findAll() as $checkpoint) {
            $checkpointTarget = strtolower(trim((string) ($checkpoint['target'] ?? '')));
            if ($checkpointTarget !== $normalized) {
                continue;
            }

            $outcome = (string) ($checkpoint['final_outcome'] ?? '');
            $status = (string) ($checkpoint['execution_status'] ?? '');

            if ($outcome === 'EXECUTED_BUT_NOT_FINALIZED'
                || ($status === CheckpointStatus::RecoveryRecorded->value && $outcome !== '')) {
                $blocking[] = $checkpoint;
            }
        }

        return $blocking;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function findAll(): array
    {
        $path = config('optimization.checkpoints_path');
        if (! File::isDirectory($path)) {
            return [];
        }

        $all = [];
        foreach (File::files($path) as $file) {
            $data = json_decode(File::get($file->getPathname()), true);
            if (is_array($data)) {
                $all[] = $data;
            }
        }

        return $all;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function findIncomplete(): array
    {
        $path = config('optimization.checkpoints_path');
        if (! File::isDirectory($path)) {
            return [];
        }

        $incomplete = [];
        foreach (File::files($path) as $file) {
            $data = json_decode(File::get($file->getPathname()), true);
            if (! is_array($data)) {
                continue;
            }

            $status = (string) ($data['execution_status'] ?? '');
            if (in_array($status, CheckpointStatus::incomplete(), true)) {
                $incomplete[] = $data;
            }
        }

        return $incomplete;
    }

    /**
     * @param  array<string, mixed>  $checkpoint
     */
    private function persist(string $id, array $checkpoint): void
    {
        File::put(
            config('optimization.checkpoints_path')."/{$id}.json",
            json_encode($checkpoint, JSON_PRETTY_PRINT),
        );
    }

    private function gitCommit(): ?string
    {
        $head = base_path('.git/HEAD');
        if (! File::exists($head)) {
            return null;
        }

        $ref = trim(File::get($head));
        if (! str_starts_with($ref, 'ref:')) {
            return $ref;
        }

        $refPath = base_path('.git/'.trim(substr($ref, 5)));

        return File::exists($refPath) ? trim(File::get($refPath)) : null;
    }
}
