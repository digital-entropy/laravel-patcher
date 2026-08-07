<?php

namespace Dentro\Patcher\Tests\Command;

use Dentro\Patcher\Console\InstallCommand;
use Dentro\Patcher\Console\StatusCommand;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class PatcherCommandSignatureTest extends TestCase
{
    public function testInstallCommandDeclaresItsOwnSignature(): void
    {
        $signature = (new ReflectionClass(InstallCommand::class))->getDefaultProperties()['signature'] ?? null;

        $this->assertIsString($signature);
        $this->assertStringStartsWith('patcher:install', $signature);
    }

    public function testStatusCommandDeclaresItsOwnSignature(): void
    {
        $signature = (new ReflectionClass(StatusCommand::class))->getDefaultProperties()['signature'] ?? null;

        $this->assertIsString($signature);
        $this->assertStringStartsWith('patcher:status', $signature);
    }
}
