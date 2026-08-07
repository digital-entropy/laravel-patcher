<?php

namespace Dentro\Patcher\Console;

use Dentro\Patcher\Patcher;
use Illuminate\Database\Console\Migrations\StatusCommand as MigrationStatusCommand;
use Illuminate\Support\Collection;

class StatusCommand extends MigrationStatusCommand
{
    protected $signature = 'patcher:status
                    {--database= : The database connection to use}
                    {--pending : Only list pending patches}';

    protected $description = 'Show the status of each patches.';

    /**
     * Laravel >= 13 sets a boolean default on the "pending" option which
     * conflicts with the VALUE_NONE flag used by this command on older
     * Laravel versions. No-op to keep behavior consistent across versions.
     */
    protected function configureDefaults(): void
    {
        //
    }

    /**
     * @var Patcher
     */
    protected $migrator;

    public function handle()
    {
        return $this->migrator->usingConnection($this->option('database'), function () {
            if (! $this->migrator->repositoryExists()) {
                $this->error('Patcher table not found.');
                return;
            }

            $ran = $this->migrator->getRepository()->getRan();

            $batches = $this->migrator->getRepository()->getMigrationBatches();

            if (count($patches = $this->getStatusFor($ran, $batches)) > 0) {
                $this->newLine();

                $this->components->twoColumnDetail('<fg=gray>Patcher name</>', '<fg=gray>Batch / Status</>');

                $patches
                    ->when($this->option('pending'), fn ($collection) => $collection->filter(function ($migration) {
                        return str($migration[1])->contains('Pending');
                    }))
                    ->each(
                        fn ($migration) => $this->components->twoColumnDetail($migration[0], $migration[1])
                    );

                $this->newLine();
            } else {
                $this->components->info('No patch found');
            }
        });
    }

    protected function getStatusFor(array $ran, array $batches)
    {
        return Collection::make($this->getAllMigrationFiles())
            ->map(function ($migration) use ($ran, $batches) {
                $migrationName = $this->migrator->getMigrationName($migration);

                $defaultStatus = '<fg=yellow;options=bold>Pending</>';

                $status = $defaultStatus;

                if (in_array($migrationName, $ran)) {
                    $status = '<fg=green;options=bold>Ran</>';
                }

                if (in_array($migrationName, $ran)) {
                    $status = '['.$batches[$migrationName].'] '.$status;
                }

                if ($status === $defaultStatus) {
                    $patch = $this->migrator->getPatcherObject($migration);

                    if ($patch->isPerpetual) {
                        $status = '<fg=yellow;options=bold>Perpetual</>';
                    }
                }

                return [$migrationName, $status];
            });
    }

    /**
     * Get a migration path (either specified by '--path' option or default location).
     *
     * @return string
     */
    protected function getMigrationPath(): string
    {
        return $this->laravel->basePath().DIRECTORY_SEPARATOR.'patches';
    }
}
