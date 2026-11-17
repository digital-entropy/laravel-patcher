<?php

namespace Dentro\Patcher\Console;

use Dentro\Patcher\Console\Concerns\ResolvesPatchPath;
use Illuminate\Support\Str;
use Illuminate\Database\Console\Migrations\MigrateMakeCommand;

class MakeCommand extends MigrateMakeCommand
{
    use ResolvesPatchPath;

    protected $signature = 'make:patch {name : The name of the patch}';

    protected $description = 'Create a new patch file';

    /**
     * @return void
     * @throws \Exception
     */
    public function handle(): void
    {
        $name = Str::snake(trim($this->input->getArgument('name')));

        $this->writeMigration($name, null, false);

        $this->composer->dumpAutoloads();
    }

    /**
     * @param $name
     * @param $table
     * @param $create
     * @return string|null
     * @throws \Exception
     */
    protected function writeMigration($name, $table, $create): ?string
    {
        $file = $this->creator->create(
            $name, $this->getMigrationPath(), $table, $create
        );

        $this->line("<info>Created Patch:</info> $file");

        return $file;
    }
}
