<?php

namespace Dentro\Patcher\Console;

use Dentro\Patcher\Console\Concerns\ResolvesPatchPath;
use Dentro\Patcher\Grouping;
use Dentro\Patcher\Patcher;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Composer;
use InvalidArgumentException;

class OrganizeCommand extends Command
{
    use ResolvesPatchPath;

    protected $signature = 'patcher:organize {--dry-run : Show what would be moved without moving anything}';

    protected $description = 'Move patches into the folder layout set by patcher.grouping';

    public function __construct(
        protected Patcher $patcher,
        protected Filesystem $files,
        protected Composer $composer,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        try {
            $grouping = Grouping::fromConfig($this->laravel['config']->get('patcher.grouping', 'none'));
        } catch (InvalidArgumentException $e) {
            $this->components->error($e->getMessage());

            return 1;
        }

        $root = $this->getMigrationPath();

        // Discovery runs before anything moves, so a duplicate name aborts with nothing touched.
        $patches = $this->files->isDirectory($root) ? $this->patcher->getMigrationFiles([$root]) : [];

        if ($patches === []) {
            $this->components->info('No patches found.');

            return 0;
        }

        $dryRun = (bool) $this->option('dry-run');
        $counts = ['moved' => 0, 'in place' => 0, 'skipped' => 0, 'conflicts' => 0];

        foreach ($patches as $file) {
            $directory = $grouping->directoryFor($file);
            $from = $this->relative($root, $file);

            if ($directory === null) {
                $counts['skipped']++;
                $this->components->twoColumnDetail($from, '<fg=yellow;options=bold>SKIPPED (no date prefix)</>');

                continue;
            }

            $target = ($directory === '' ? $root : $root.'/'.$directory).'/'.basename($file);

            if ($target === $file) {
                $counts['in place']++;

                continue;
            }

            $to = $this->relative($root, $target);

            if ($this->files->exists($target)) {
                $counts['conflicts']++;
                $this->components->twoColumnDetail("$from → $to", '<fg=red;options=bold>CONFLICT</>');

                continue;
            }

            if (! $dryRun) {
                $this->files->ensureDirectoryExists(dirname($target));
                $this->files->move($file, $target);
            }

            $counts['moved']++;
            $this->components->twoColumnDetail("$from → $to", $dryRun ? '<fg=yellow;options=bold>WOULD MOVE</>' : '<fg=green;options=bold>MOVED</>');
        }

        if (! $dryRun && $counts['moved'] > 0) {
            $this->removeEmptyGroupingFolders($root);
            $this->composer->dumpAutoloads();
        }

        $this->components->info(sprintf(
            '%s: %d, already in place: %d, skipped: %d, conflicts: %d',
            $dryRun ? 'Would move' : 'Moved',
            $counts['moved'],
            $counts['in place'],
            $counts['skipped'],
            $counts['conflicts'],
        ));

        return $counts['skipped'] > 0 || $counts['conflicts'] > 0 ? 1 : 0;
    }

    /**
     * Remove "YYYY/MM" and then "YYYY" folders that the move left completely empty.
     */
    protected function removeEmptyGroupingFolders(string $root): void
    {
        foreach ($this->files->glob($root.'/[0-9][0-9][0-9][0-9]', GLOB_ONLYDIR) as $year) {
            if (! preg_match('/^\d{4}$/', basename($year))) {
                continue;
            }

            foreach ($this->files->glob($year.'/[0-9][0-9]', GLOB_ONLYDIR) as $month) {
                if (preg_match('/^(0[1-9]|1[0-2])$/', basename($month)) && $this->isEmpty($month)) {
                    $this->files->deleteDirectory($month);
                }
            }

            if ($this->isEmpty($year)) {
                $this->files->deleteDirectory($year);
            }
        }
    }

    protected function isEmpty(string $directory): bool
    {
        return count(scandir($directory)) === 2;
    }

    protected function relative(string $root, string $path): string
    {
        return ltrim(substr($path, strlen($root)), '/');
    }
}
