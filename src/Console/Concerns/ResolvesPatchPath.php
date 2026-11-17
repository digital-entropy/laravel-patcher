<?php

namespace Dentro\Patcher\Console\Concerns;

trait ResolvesPatchPath
{
    /**
     * Get the path to the patches directory.
     */
    protected function getMigrationPath(): string
    {
        return $this->laravel->basePath().DIRECTORY_SEPARATOR.'patches';
    }
}
