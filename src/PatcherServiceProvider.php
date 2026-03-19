<?php

namespace Dentro\Patcher;

use Dentro\Patcher\Console\InstallCommand;
use Dentro\Patcher\Console\MakeCommand;
use Dentro\Patcher\Console\PatchCommand;
use Dentro\Patcher\Console\StatusCommand;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Log\Logger;
use Illuminate\Support\ServiceProvider;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger as Monolog;

class PatcherServiceProvider extends ServiceProvider
{
    public const LOG_DRIVER_NAME = 'patcher';

    public static $LOG_CHANNEL = 'patcher';

    protected $commands = [
        'PatcherPatch' => 'command.patcher',
        'PatcherInstall' => 'command.patcher.install',
        'PatcherMake' => 'command.patcher.make',
        'PatcherStatus' => 'command.patcher.status',
    ];

    /**
     * Register the patcher service.
     */
    public function register(): void
    {
        $this->registerLogger();

        $this->registerRepository();

        $this->registerPatcher();

        $this->registerCreator();

        $this->registerCommands($this->commands);
    }

    /**
     * Get the services provided by the provider.
     */
    public function provides(): array
    {
        return array_merge(
            [
                'dentro.patcher',
                'dentro.patcher.repository',
                'dentro.patcher.creator',
            ],
            array_values($this->commands),
        );
    }

    protected function registerLogger(): void
    {
        $app = $this->app;

        /**
         * @var $configRepository \Illuminate\Config\Repository
         */
        $configRepository = $app['config'];
        $key = 'logging.channels.'.self::$LOG_CHANNEL;

        // check if specified log channel declared in logging.php
        // if there is no declaration we will declare it here.
        if (! $configRepository->has($key)) {
            $configRepository->set($key, [
                'driver' => self::LOG_DRIVER_NAME,
                'path' => $app->storagePath().
                    DIRECTORY_SEPARATOR.
                    'logs'.
                    DIRECTORY_SEPARATOR.
                    'patches.log',
            ]);
        }

        $application = $app;

        $app['log']->extend(self::LOG_DRIVER_NAME, function (
            $app,
            $config,
        ) use ($application) {
            $handler = new StreamHandler(
                $config['path'] ??
                    $application->storagePath().'/logs/patches.log',
                Level::Info,
                $config['bubble'] ?? true,
                $config['permission'] ?? null,
                $config['locking'] ?? false,
            );

            return new Logger(
                new Monolog('patcher', [$handler]),
                $application['events'],
            );
        });
    }

    protected function registerPatcher(): void
    {
        $this->app->singleton('dentro.patcher', function ($app) {
            $repository = $app['dentro.patcher.repository'];

            return new Patcher(
                $repository,
                $app['db'],
                $app['files'],
                $app['events'],
            );
        });
    }

    protected function registerRepository(): void
    {
        $this->app->singleton('dentro.patcher.repository', function ($app) {
            return new PatcherRepository($app['db'], 'patches');
        });
    }

    protected function registerCreator(): void
    {
        $this->app->singleton('dentro.patcher.creator', function ($app) {
            return new PatcherCreator($app['files'], $app->basePath('stubs'));
        });
    }

    protected function registerCommands(array $commands): void
    {
        foreach (array_keys($commands) as $command) {
            call_user_func_array([$this, "register{$command}Command"], []);
        }

        $this->commands(array_values($commands));
    }

    protected function registerPatcherInstallCommand(): void
    {
        $this->app->singleton('command.patcher.install', function ($app) {
            return new InstallCommand($app['dentro.patcher.repository']);
        });
    }

    protected function registerPatcherMakeCommand(): void
    {
        $this->app->singleton('command.patcher.make', function ($app) {
            // Once we have the migration creator registered, we will create the command
            // and inject the creator. The creator is responsible for the actual file
            // creation of the migrations, and may be extended by these developers.
            $creator = $app['dentro.patcher.creator'];

            $composer = $app['composer'];

            return new MakeCommand($creator, $composer);
        });
    }

    protected function registerPatcherStatusCommand(): void
    {
        $this->app->singleton('command.patcher.status', function ($app) {
            return new StatusCommand($app['dentro.patcher']);
        });
    }

    protected function registerPatcherPatchCommand(): void
    {
        $this->app->singleton('command.patcher', function (Application $app) {
            return new PatchCommand(
                $app->make('dentro.patcher'),
                $app->make('events'),
            );
        });
    }
}
