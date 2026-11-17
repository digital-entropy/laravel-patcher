<?php

namespace Dentro\Patcher\Tests;

use Dentro\Patcher\PatcherServiceProvider;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Composer;
use Mockery as m;
use Orchestra\Testbench\TestCase;

class GroupingEndToEndTest extends TestCase
{
    protected string $runLog;

    protected function getPackageProviders($app): array
    {
        return [PatcherServiceProvider::class];
    }

    protected function setUp(): void
    {
        parent::setUp();

        (new Filesystem)->deleteDirectory(base_path('patches'));
        $this->runLog = sys_get_temp_dir().'/patcher-run-'.uniqid().'.log';

        // Never run a real `composer dump-autoload` against the testbench skeleton.
        $this->app->instance('composer', m::mock(Composer::class)->shouldIgnoreMissing());
    }

    protected function tearDown(): void
    {
        (new Filesystem)->deleteDirectory(base_path('patches'));
        @unlink($this->runLog);

        parent::tearDown();
    }

    protected function writePatch(string $relative): void
    {
        $path = base_path('patches/'.$relative);
        (new Filesystem)->ensureDirectoryExists(dirname($path));

        file_put_contents($path, sprintf(<<<'PHP'
<?php

use Dentro\Patcher\Patch;

return new class extends Patch
{
    public function patch(): void
    {
        file_put_contents(%s, basename(__FILE__, '.php').PHP_EOL, FILE_APPEND);
    }
};
PHP, var_export($this->runLog, true)));
    }

    protected function runLog(): array
    {
        return file_exists($this->runLog) ? file($this->runLog, FILE_IGNORE_NEW_LINES) : [];
    }

    public function testOrganizingRanPatchesDoesNotRerunThem(): void
    {
        $this->writePatch('2023_01_01_000000_first.php');
        $this->writePatch('2024_03_05_000000_second.php');
        $this->artisan('patcher:run')->assertExitCode(0);

        config(['patcher.grouping' => 'month']);
        $this->artisan('patcher:organize')->assertExitCode(0);
        $this->assertFileExists(base_path('patches/2023/01/2023_01_01_000000_first.php'));
        $this->assertFileExists(base_path('patches/2024/03/2024_03_05_000000_second.php'));

        $this->writePatch('2025/01/2025_01_02_000000_third.php');
        $this->artisan('patcher:run')->assertExitCode(0);

        $this->assertSame(
            ['2023_01_01_000000_first', '2024_03_05_000000_second', '2025_01_02_000000_third'],
            $this->runLog()
        );

        $this->artisan('patcher:status')
            ->expectsOutputToContain('2025_01_02_000000_third')
            ->assertExitCode(0);
    }
}
