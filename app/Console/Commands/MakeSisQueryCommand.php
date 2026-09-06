<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

class MakeSisQueryCommand extends Command
{
    protected $signature = 'sis:make-query {context : Bounded context e.g. Student} {name : Query name e.g. GetStudentProfile}';

    protected $description = 'Scaffold a CQRS Query + Handler in Application layer';

    public function handle(): int
    {
        $context = Str::studly($this->argument('context'));
        $name = Str::studly($this->argument('name'));
        $queryClass = "{$name}Query";
        $handlerClass = "{$name}Handler";

        $base = app_path("Application/{$context}/Queries");
        if (! is_dir($base)) {
            mkdir($base, 0755, true);
        }

        $queryPath = "{$base}/{$queryClass}.php";
        $handlerPath = "{$base}/{$handlerClass}.php";

        if (file_exists($queryPath) || file_exists($handlerPath)) {
            $this->error('Query or handler already exists.');

            return self::FAILURE;
        }

        file_put_contents($queryPath, $this->queryStub($context, $queryClass));
        file_put_contents($handlerPath, $this->handlerStub($context, $queryClass, $handlerClass));

        $this->info("Created {$queryPath}");
        $this->info("Created {$handlerPath}");

        return self::SUCCESS;
    }

    private function queryStub(string $context, string $queryClass): string
    {
        return <<<PHP
<?php

namespace App\\Application\\{$context}\\Queries;

use App\\Application\\Contracts\\Query;

final readonly class {$queryClass} implements Query
{
    public function __construct(
        public int \$schoolId,
        public int \$academicYearId,
        // TODO: add query parameters
    ) {}
}

PHP;
    }

    private function handlerStub(string $context, string $queryClass, string $handlerClass): string
    {
        return <<<PHP
<?php

namespace App\\Application\\{$context}\\Queries;

use App\\Application\\Contracts\\Query;
use App\\Application\\Contracts\\QueryHandler;

final class {$handlerClass} implements QueryHandler
{
    public function handle(Query \$query): mixed
    {
        assert(\$query instanceof {$queryClass});

        // TODO: read via repository — no side effects
        return [];
    }
}

PHP;
    }
}
