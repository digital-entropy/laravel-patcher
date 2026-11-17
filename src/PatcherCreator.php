<?php

namespace Dentro\Patcher;

use Illuminate\Database\Migrations\MigrationCreator;
use Illuminate\Filesystem\Filesystem;

class PatcherCreator extends MigrationCreator
{
    /**
     * Create a new patch creator instance.
     *
     * @param \Illuminate\Filesystem\Filesystem $files
     * @param string|null $customStubPath
     * @param mixed $grouping raw "patcher.grouping" value, validated when a patch is created
     */
    public function __construct(Filesystem $files, $customStubPath, protected mixed $grouping = 'none')
    {
        parent::__construct($files, $customStubPath);
    }

    /**
     * Get the path to the stubs.
     *
     * @return string
     */
    public function stubPath(): string
    {
        return __DIR__.'/../stubs';
    }

    /**
     * Get the migration stub file.
     *
     * @param string|null $table
     * @param bool $create
     * @return string
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    protected function getStub($table, $create): string
    {
        return $this->files->get($this->files->exists($customPath = $this->customStubPath.'/blank.stub')
            ? $customPath
            : $this->stubPath().'/blank.stub');
    }

    /**
     * Get the full path to the patch, inside the folder for the configured grouping.
     *
     * @param string $name
     * @param string $path
     * @return string
     * @throws \InvalidArgumentException when the configured grouping is not supported
     */
    protected function getPath($name, $path): string
    {
        $grouping = Grouping::fromConfig($this->grouping);

        // Newer Laravel versions bump the timestamp until it is unique within
        // this directory, so point that check at the folder the patch goes in.
        if (property_exists($this, 'currentMigrationPath') && $this->currentMigrationPath !== null) {
            $this->currentMigrationPath = $this->joinPath($path, $grouping->directoryFor(date('Y_m_d_His').'_'.$name));
        }

        $prefix = $this->getDatePrefix();

        $directory = $this->joinPath($path, $grouping->directoryFor($prefix.'_'.$name));

        $this->files->ensureDirectoryExists($directory);

        return $directory.'/'.$prefix.'_'.$name.'.php';
    }

    protected function joinPath(string $path, string $directory): string
    {
        return $directory === '' ? $path : $path.'/'.$directory;
    }
}
