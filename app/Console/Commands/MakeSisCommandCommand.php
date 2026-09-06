<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

class MakeSisCommandCommand extends Command
{
    protected $signature = 'sis:make-command {context : Bounded context e.g. Enrollment} {name : Command name e.g. EnrollStudent}';

    protected $description = 'Scaffold a CQRS Command + Handler in Application layer';

    public function handle(): int
    {
        $context = Str::studly($this->argument('context'));
        $name = Str::studly($this->argument('name'));
        $commandClass = "{$name}Command";
        $handlerClass = "{$name}Handler";

        $base = app_path("Application/{$context}/Commands");
        if (! is_dir($base)) {
            mkdir($base, 0755, true);
        }

        $commandPath = "{$base}/{$commandClass}.php";
        $handlerPath = "{$base}/{$handlerClass}.php";

        if (file_exists($commandPath) || file_exists($handlerPath)) {
            $this->error('Command or handler already exists.');

            return self::FAILURE;
        }

        file_put_contents($commandPath, $this->commandStub($context, $commandClass));
        file_put_contents($handlerPath, $this->handlerStub($context, $commandClass, $handlerClass));

        $this->info("Created {$commandPath}");
        $this->info("Created {$handlerPath}");
        $this->line('Register handler binding in ArchitectureServiceProvider if using interface binding.');

        return self::SUCCESS;
    }

    private function commandStub(string $context, string $commandClass): string
    {
        return <<<PHP
<?php

namespace App\\Application\\{$context}\\Commands;

use App\\Application\\Contracts\\Command;

final readonly class {$commandClass} implements Command
{
    public function __construct(
        public int \$schoolId,
        public int \$academicYearId,
        // TODO: add command properties
    ) {}
}

PHP;
    }

    private function handlerStub(string $context, string $commandClass, string $handlerClass): string
    {
        return <<<PHP
<?php

namespace App\\Application\\{$context}\\Commands;

use App\\Application\\Contracts\\Command;
use App\\Application\\Contracts\\CommandHandler;
use App\\Application\\Contracts\\UnitOfWork;

final class {$handlerClass} implements CommandHandler
{
    public function __construct(
        private readonly UnitOfWork \$unitOfWork,
    ) {}

    public function handle(Command \$command): mixed
    {
        assert(\$command instanceof {$commandClass});

        return \$this->unitOfWork->transaction(function () use (\$command) {
            // TODO: orchestrate domain + infrastructure
            return null;
        });
    }
}

PHP;
    }
}
