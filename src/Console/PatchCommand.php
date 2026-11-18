<?php

namespace Dentro\Patcher\Console;

use Dentro\Patcher\Console\Concerns\ResolvesPatchPath;
use Illuminate\Console\ConfirmableTrait;
use Illuminate\Database\Console\Migrations\MigrateCommand;

class PatchCommand extends MigrateCommand
{
    use ConfirmableTrait;
    use ResolvesPatchPath;

    protected $signature = 'patcher:run {--force : Force the operation to run when in production}';

    protected $description = 'Run the patches.';

    public function handle(): int
    {
        if (! $this->confirmToProceed()) {
            return 1;
        }

        // name connection is null because we want to run patch on default connection
        $this->migrator->usingConnection(null, function () {
            $this->prepareDatabase();

            $this->migrator->setOutput($this->output)
                ->run($this->getMigrationPaths(), [
                    'pretend' => false,
                    'step' => false,
                ]);
        });

        return 0;
    }

    protected function prepareDatabase(): void
    {
        if (! $this->migrator->repositoryExists()) {
            $this->call('patcher:install');
        }
    }

    protected function getMigrationPaths(): array
    {
        return [$this->getMigrationPath()];
    }
}
