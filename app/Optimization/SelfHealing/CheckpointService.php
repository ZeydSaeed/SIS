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

        $checkpoint = array_merge($context, [
            'checkpoint_id' => $id,
            'created_at' => now()->toIso8601String(),
            'git_commit' => $this->gitCommit(),
        ]);

        File::put("{$path}/{$id}.json", json_encode($checkpoint, JSON_PRETTY_PRINT));

        return $id;
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
