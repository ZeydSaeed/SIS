<?php

namespace App\Providers;

use App\Database\ProtectedDatabaseGuard;
use Carbon\CarbonImmutable;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * @var list<string>
     */
    private const DESTRUCTIVE_ARTISAN_COMMANDS = [
        'migrate:fresh',
        'migrate:refresh',
        'migrate:reset',
        'db:wipe',
        'schema:drop',
    ];

    public function register(): void
    {
        $this->app->singleton(ProtectedDatabaseGuard::class);
    }

    public function boot(): void
    {
        $this->configureDefaults();
        $this->protectDestructiveArtisanCommands();
    }

    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        $guard = $this->app->make(ProtectedDatabaseGuard::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction() || $guard->isProtected()
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }

    protected function protectDestructiveArtisanCommands(): void
    {
        Event::listen(CommandStarting::class, function (CommandStarting $event): void {
            $command = (string) $event->command;

            if (! in_array($command, self::DESTRUCTIVE_ARTISAN_COMMANDS, true)) {
                return;
            }

            $this->app->make(ProtectedDatabaseGuard::class)->assertSafeForDestructiveOperations();
        });
    }
}
