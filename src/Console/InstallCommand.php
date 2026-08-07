<?php

namespace Dentro\Patcher\Console;

use Illuminate\Database\Console\Migrations\InstallCommand as MigrationInstallCommand;

class InstallCommand extends MigrationInstallCommand
{
    protected $signature = 'patcher:install {--database= : The database connection to use}';

    protected $description = 'Create the patches repository';

    public function handle(): void
    {
        $this->repository->setSource($this->input->getOption('database'));

        $this->repository->createRepository();

        $this->info('Patches table created successfully.');
    }
}
