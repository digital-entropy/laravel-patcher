<?php

namespace Dentro\Patcher;

use Illuminate\Console\Command;
use Illuminate\Container\Container;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Log\Logger;

abstract class Patch extends Migration
{
    /**
     * The console command instance.
     *
     * @var \Illuminate\Console\Command
     */
    protected Command $command;

    /**
     * The container instance.
     *
     * @var \Illuminate\Container\Container
     */
    protected Container $container;

    /**
     * Logger.
     *
     * @var \Illuminate\Log\Logger
     */
    protected Logger $logger;

    /**
     * Enables, if supported, wrapping the patch within a transaction.
     *
     * @var bool
     */
    public $withinTransaction = false;

    /**
     * Determine if patch should run perpetually.
     *
     * @var bool
     */
    public bool $isPerpetual = false;

    /**
     * Run patch script.
     *
     * @return void
     */
    abstract public function patch(): void;

    /**
     * Set command.
     *
     * @param \Illuminate\Console\Command $command
     * @return $this
     */
    public function setCommand(Command $command): Patch
    {
        $this->command = $command;

        return $this;
    }

    /**
     * Set the IoC container instance.
     *
     * @param  \Illuminate\Container\Container  $container
     * @return $this
     */
    public function setContainer(Container $container): Patch
    {
        $this->container = $container;

        return $this;
    }

    /**
     * Set Logger instance.
     *
     * @param \Illuminate\Log\Logger $logger
     * @return $this
     */
    public function setLogger(Logger $logger): Patch
    {
        $this->logger = $logger;

        return $this;
    }
}
