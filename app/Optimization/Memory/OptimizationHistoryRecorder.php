<?php

namespace App\Optimization\Memory;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

final class OptimizationHistoryRecorder
{
    /**
     * @param  array<string, mixed>  $record
     */
    public function record(array $record): string
    {
        $path = config('optimization.history_path');
        File::ensureDirectoryExists($path);

        $id = 'OPT-'.now()->format('Ymd').'-'.Str::upper(Str::random(6));
        $record['id'] = $id;
        $record['recorded_at'] = now()->toIso8601String();

        File::put("{$path}/{$id}.json", json_encode($record, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return $id;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function recent(int $limit = 20): array
    {
        $path = config('optimization.history_path');
        if (! File::isDirectory($path)) {
            return [];
        }

        $files = collect(File::files($path))
            ->sortByDesc(fn ($f) => $f->getMTime())
            ->take($limit);

        return $files->map(function ($file) {
            $content = json_decode(File::get($file->getPathname()), true);

            return is_array($content) ? $content : [];
        })->filter()->values()->all();
    }
}
