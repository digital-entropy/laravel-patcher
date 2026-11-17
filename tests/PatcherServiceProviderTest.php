<?php

namespace Dentro\Patcher\Tests;

use Dentro\Patcher\PatcherServiceProvider;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Composer;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;
use Mockery as m;
use Orchestra\Testbench\TestCase;

class PatcherServiceProviderTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [PatcherServiceProvider::class];
    }

    public function testDefaultGroupingIsNone(): void
    {
        $this->assertSame('none', config('patcher.grouping'));
    }

    public function testConfigIsPublishable(): void
    {
        $paths = ServiceProvider::pathsToPublish(PatcherServiceProvider::class, 'patcher-config');

        $this->assertSame([realpath(__DIR__.'/../config/patcher.php')], array_map('realpath', array_keys($paths)));
    }

    public function testInvalidGroupingOnlyBreaksCommandsThatWrite(): void
    {
        $patches = base_path('patches');
        (new Filesystem)->deleteDirectory($patches);
        (new Filesystem)->ensureDirectoryExists($patches.'/2024/03');
        file_put_contents($patches.'/2024/03/2024_03_05_000000_probe.php', <<<'PHP'
<?php

return new class extends Dentro\Patcher\Patch
{
    public function patch(): void
    {
        $this->command->line('probe patch ran');
    }
};
PHP);

        try {
            config(['patcher.grouping' => 'monthly']);
            $this->app->instance('composer', m::mock(Composer::class)->shouldIgnoreMissing());
            $this->app->forgetInstance('dentro.patcher.creator');
            $this->app->forgetInstance('command.patcher.make');

            $this->artisan('list')->assertExitCode(0);
            $this->artisan('patcher:install')->assertExitCode(0);
            $this->artisan('patcher:status')
                ->expectsOutputToContain('2024_03_05_000000_probe')
                ->assertExitCode(0);
            $this->artisan('patcher:run')
                ->expectsOutputToContain('probe patch ran')
                ->assertExitCode(0);

            $this->expectException(InvalidArgumentException::class);
            $this->expectExceptionMessage('Supported values: none, year, month.');

            $this->artisan('make:patch', ['name' => 'x'])->run();
        } finally {
            (new Filesystem)->deleteDirectory($patches);
        }
    }
}
