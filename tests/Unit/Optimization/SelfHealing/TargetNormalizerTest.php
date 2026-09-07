<?php

namespace Tests\Unit\Optimization\SelfHealing;

use App\Optimization\SelfHealing\OptimizationTargetLock;
use App\Optimization\SelfHealing\TargetNormalizer;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TargetNormalizerTest extends TestCase
{
    #[Test]
    public function s20_same_logical_target_produces_same_lock_key(): void
    {
        $normalizer = new TargetNormalizer(new \App\Optimization\Execution\AnalyzeTargetPolicy);
        $lock = new OptimizationTargetLock($normalizer);

        $this->assertSame(
            $normalizer->lockKey('users', 'public', 'users'),
            $normalizer->lockKey('PUBLIC.USERS'),
        );
        $this->assertSame(
            $normalizer->lockKey('"public"."users"'),
            $normalizer->lockKey('public', 'public', 'users'),
        );

        $this->assertTrue($lock->acquire('users', 'public', 'users'));
        $this->assertTrue($lock->isLocked('PUBLIC.USERS'));
        $this->assertFalse($lock->acquire('users', 'public', 'users'));
        $this->assertTrue($lock->acquire('public.attendance_records', 'public', 'attendance_records'));

        $lock->release('users', 'public', 'users');
        $this->assertFalse($lock->isLocked('public.users'));
    }
}
