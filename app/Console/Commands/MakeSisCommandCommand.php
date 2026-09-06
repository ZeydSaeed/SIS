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
        file_put_contents($handlerPath, $this->handlerStub($context, $name, $commandClass, $handlerClass));

        $resultPath = app_path("Application/{$context}/Results/{$name}Result.php");
        if (! is_dir(dirname($resultPath))) {
            mkdir(dirname($resultPath), 0755, true);
        }
        if (! file_exists($resultPath)) {
            file_put_contents($resultPath, $this->resultStub($context, $name));
        }

        $this->info("Created {$commandPath}");
        $this->info("Created {$handlerPath}");
        $this->info("Created {$resultPath}");
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
        public ?int \$enrolledBy = null,
        public ?string \$idempotencyKey = null,
    ) {}
}

PHP;
    }

    private function handlerStub(string $context, string $name, string $commandClass, string $handlerClass): string
    {
        return <<<PHP
<?php

namespace App\\Application\\{$context}\\Commands;

use App\\Application\\Contracts\\Command;
use App\\Application\\Contracts\\CommandHandler;
use App\\Application\\Contracts\\OutboxRepository;
use App\\Application\\Contracts\\UnitOfWork;
use App\\Application\\{$context}\\Results\\{$name}Result;

final class {$handlerClass} implements CommandHandler
{
    public function __construct(
        private readonly UnitOfWork \$unitOfWork,
        private readonly OutboxRepository \$outbox,
    ) {}

    public function handle(Command \$command): {$name}Result
    {
        assert(\$command instanceof {$commandClass});

        return \$this->unitOfWork->transaction(function () use (\$command) {
            // TODO: orchestrate domain + infrastructure + outbox->stage()
            return {$name}Result::success(/* ... */);
        });
    }
}

PHP;
    }

    private function resultStub(string $context, string $name): string
    {
        return <<<PHP
<?php

namespace App\\Application\\{$context}\\Results;

use App\\Application\\Shared\\Results\\ApplicationResult;

final readonly class {$name}Result extends ApplicationResult
{
    private function __construct(
        bool \$success,
        array \$errors = [],
        array \$warnings = [],
        bool \$fromIdempotencyCache = false,
    ) {
        parent::__construct(\$success, \$errors, \$warnings, \$fromIdempotencyCache);
    }

    public static function success(): self
    {
        return new self(true);
    }

    /**
     * @param  list<string>  \$errors
     */
    public static function failure(array \$errors): self
    {
        return new self(false, errors: \$errors);
    }
}

PHP;
    }
}
