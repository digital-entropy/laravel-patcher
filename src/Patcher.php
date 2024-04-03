<?php

namespace Dentro\Patcher;

use Dentro\Patcher\Events\PatchEnded;
use Dentro\Patcher\Events\PatchStarted;
use Illuminate\Console\View\Components\Info;
use Illuminate\Console\View\Components\TwoColumnDetail;
use Illuminate\Database\Migrations\Migrator;
use InvalidArgumentException;

class Patcher extends Migrator
{
    /**
     * Run an array of migrations.
     *
     * @param array $migrations
     * @param array $options
     *
     * @return void
     * @throws \Throwable
     */
    public function runPending(array $migrations, array $options = []): void
    {
        if (count($migrations) === 0) {
            $this->write(Info::class, 'Nothing to patch.');

            return;
        }

        $batch = $this->repository->getNextBatchNumber();

        $step = $options['step'] ?? false;

        foreach ($migrations as $file) {
            $this->patch($file, $batch);

            if ($step) {
                $batch++;
            }
        }
    }

    /**
     * Run "patch" a migration instance.
     *
     * @param string $file
     * @param int $batch
     * @return void
     * @throws \Throwable
     */
    protected function patch(string $file, int $batch): void
    {
        $patch = $this->getPatcherObject($file);

        $name = $this->getMigrationName($file);

        $perpetualMessage = $patch->isPerpetual ? " <fg=yellow;options=bold>(Perpetual)</>" : "";

        $patch
            ->setContainer(app())
            ->setCommand(app('command.patcher'))
            ->setLogger(app('log')->driver(PatcherServiceProvider::$LOG_CHANNEL));

        $info = '<fg=yellow>Patching: </>'.$name.$perpetualMessage;

        $this->write(
            TwoColumnDetail::class,
            $info,
            '<fg=yellow;options=bold>RUNNING</>'
        );

        $startTime = microtime(true);

        if ($this->isEligible($patch)) {
            $this->runPatch($patch);

            $runTime = round(microtime(true) - $startTime, 2);

            $this->write(
                TwoColumnDetail::class,
                $info,
                "<fg=gray>$runTime ms</> <fg=green;options=bold>DONE</>"
            );
        } else {
            $this->write(
                TwoColumnDetail::class,
                "$info is not eligible to run in current condition",
                '<fg=yellow;options=bold>SKIPPED</>'
            );
        }

        if (!$patch->isPerpetual) {
            $this->repository->log($name, $batch);
        }
    }

    /**
     * Determine if patcher should run.
     *
     * @param \Dentro\Patcher\Patch $patch
     * @return bool
     */
    public function isEligible(Patch $patch): bool
    {
        if (method_exists($patch, 'eligible')) {
            return $patch->eligible();
        }

        return true;
    }

    /**
     * Run a migration inside a transaction if the database supports it.
     *
     * @param \Dentro\Patcher\Patch $patch
     * @return void
     * @throws \Throwable
     */
    protected function runPatch(Patch $patch): void
    {
        $connection = $this->resolveConnection(
            $patch->getConnection()
        );

        $dispatchEvent = function (object $event) {
            $this->events->dispatch($event);
        };

        $callback = static function () use ($patch, $dispatchEvent) {
            if (method_exists($patch, 'patch')) {
                if ($patch instanceof Patch) {
                    $dispatchEvent(new PatchStarted($patch));
                }

                $patch->patch();

                if ($patch instanceof Patch) {
                    $dispatchEvent(new PatchEnded($patch));
                }
            }
        };

        if ($patch->withinTransaction && $this->getSchemaGrammar($connection)->supportsSchemaTransactions()) {
            $connection->transaction($callback);
            return;
        }

        $callback();
    }

    public function getPatcherObject(string $path): Patch
    {
        $object = $this->resolvePath($path);

        if (! $object instanceof Patch) {
            throw new InvalidArgumentException("Patch [{$path}] must extends ".Patch::class);
        }

        return $object;
    }
}
