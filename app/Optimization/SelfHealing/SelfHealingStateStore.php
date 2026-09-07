<?php

namespace App\Optimization\SelfHealing;

use Illuminate\Support\Facades\File;

final class SelfHealingStateStore
{
    private const STATE_FILE = 'engine-state.json';

    /**
     * @return array<string, mixed>
     */
    public function read(): array
    {
        $path = $this->statePath();

        if (! File::exists($path)) {
            return $this->defaultState();
        }

        $data = json_decode(File::get($path), true);

        return is_array($data) ? array_merge($this->defaultState(), $data) : $this->defaultState();
    }

    /**
     * @param  array<string, mixed>  $state
     */
    public function write(array $state): void
    {
        File::ensureDirectoryExists(config('optimization.state_path'));
        File::put($this->statePath(), json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    /**
     * @param  callable(array<string, mixed>): array<string, mixed>  $mutator
     */
    public function mutate(callable $mutator): array
    {
        $state = $this->read();
        $state = $mutator($state);
        $this->write($state);

        return $state;
    }

    public function isSafeMode(): bool
    {
        return ($this->read()['safe_mode'] ?? false) === true;
    }

    public function enterSafeMode(string $reason): void
    {
        $this->mutate(function (array $state) use ($reason) {
            $state['safe_mode'] = true;
            $state['safe_mode_reason'] = $reason;
            $state['safe_mode_entered_at'] = now()->toIso8601String();

            return $state;
        });
    }

    public function exitSafeMode(): void
    {
        $this->mutate(function (array $state) {
            $state['safe_mode'] = false;
            $state['safe_mode_reason'] = null;
            $state['consecutive_failures'] = 0;

            return $state;
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function defaultState(): array
    {
        return [
            'safe_mode' => false,
            'safe_mode_reason' => null,
            'safe_mode_entered_at' => null,
            'consecutive_failures' => 0,
            'last_cycle_at' => null,
            'last_cycle_outcome' => null,
            'degradation_streaks' => [],
            'cooldowns' => [],
            'active_stabilizations' => [],
            'active_optimization_id' => null,
        ];
    }

    private function statePath(): string
    {
        return config('optimization.state_path').'/'.self::STATE_FILE;
    }
}
