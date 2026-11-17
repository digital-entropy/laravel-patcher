<?php

namespace Dentro\Patcher\Tests\Command;

use Dentro\Patcher\Console\OrganizeCommand;
use Dentro\Patcher\Patcher;
use Illuminate\Config\Repository;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Database\Migrations\MigrationRepositoryInterface;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Application;
use Illuminate\Support\Composer;
use Mockery as m;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

class PatcherOrganizeCommandTest extends TestCase
{
    protected string $tmp;

    protected string $patches;

    protected Application $app;

    protected OrganizeCommand $command;

    protected $composer;

    protected BufferedOutput $output;

    protected function setUp(): void
    {
        $this->tmp = sys_get_temp_dir().'/patcher-'.uniqid();
        $this->patches = $this->tmp.'/patches';
        mkdir($this->tmp);

        $this->app = new Application($this->tmp);
        $this->setGrouping('month');

        $this->command = new OrganizeCommand(
            new Patcher(
                m::mock(MigrationRepositoryInterface::class),
                m::mock(ConnectionResolverInterface::class),
                new Filesystem
            ),
            new Filesystem,
            $this->composer = m::mock(Composer::class)
        );
        $this->command->setLaravel($this->app);

        $this->output = new BufferedOutput;
    }

    protected function tearDown(): void
    {
        (new Filesystem)->deleteDirectory($this->tmp);
        m::close();
    }

    protected function setGrouping(string $grouping): void
    {
        $this->app->instance('config', new Repository(['patcher' => ['grouping' => $grouping]]));
    }

    protected function touchPatch(string $relative): void
    {
        $path = $this->patches.'/'.$relative;
        (new Filesystem)->ensureDirectoryExists(dirname($path));
        file_put_contents($path, '<?php');
    }

    protected function runOrganize(array $input = []): int
    {
        return $this->command->run(new ArrayInput($input), $this->output);
    }

    public function testMovesRootPatchesIntoMonthFolders(): void
    {
        $this->touchPatch('2024_03_05_000000_a.php');
        $this->composer->shouldReceive('dumpAutoloads')->once();

        $this->assertSame(0, $this->runOrganize());
        $this->assertFileExists($this->patches.'/2024/03/2024_03_05_000000_a.php');
        $this->assertFileDoesNotExist($this->patches.'/2024_03_05_000000_a.php');
    }

    public function testNoneFlattensAndRemovesEmptiedFolders(): void
    {
        $this->setGrouping('none');
        $this->touchPatch('2024/03/2024_03_05_000000_a.php');
        $this->composer->shouldReceive('dumpAutoloads')->once();

        $this->assertSame(0, $this->runOrganize());
        $this->assertFileExists($this->patches.'/2024_03_05_000000_a.php');
        $this->assertDirectoryDoesNotExist($this->patches.'/2024');
    }

    public function testDryRunChangesNothing(): void
    {
        $this->touchPatch('2024_03_05_000000_a.php');
        $this->composer->shouldReceive('dumpAutoloads')->never();

        $this->assertSame(0, $this->runOrganize(['--dry-run' => true]));
        $this->assertFileExists($this->patches.'/2024_03_05_000000_a.php');
        $this->assertDirectoryDoesNotExist($this->patches.'/2024');
        $this->assertStringContainsString('WOULD MOVE', $this->output->fetch());
    }

    public function testSecondRunIsANoop(): void
    {
        $this->touchPatch('2024_03_05_000000_a.php');
        $this->composer->shouldReceive('dumpAutoloads')->once();

        $this->runOrganize();
        $this->output->fetch();

        $this->assertSame(0, $this->runOrganize());
        $this->assertStringContainsString('Moved: 0, already in place: 1, skipped: 0, conflicts: 0', $this->output->fetch());
    }

    public function testUndatedPatchIsSkippedWithNonZeroExit(): void
    {
        $this->touchPatch('fix_without_date.php');
        $this->composer->shouldReceive('dumpAutoloads')->never();

        $this->assertSame(1, $this->runOrganize());
        $this->assertFileExists($this->patches.'/fix_without_date.php');
        $this->assertStringContainsString('skipped: 1', $this->output->fetch());
    }

    public function testNonGroupingFoldersAreUntouched(): void
    {
        $this->touchPatch('support/2020_01_01_000000_helper.php');
        $this->composer->shouldReceive('dumpAutoloads')->never();

        $this->assertSame(0, $this->runOrganize());
        $this->assertFileExists($this->patches.'/support/2020_01_01_000000_helper.php');
    }

    public function testDoesNotRemoveFolderThatStillHoldsOtherFiles(): void
    {
        $this->setGrouping('none');
        $this->touchPatch('2024/03/2024_03_05_000000_a.php');
        file_put_contents($this->patches.'/2024/03/.gitkeep', '');
        $this->composer->shouldReceive('dumpAutoloads')->once();

        $this->assertSame(0, $this->runOrganize());
        $this->assertFileExists($this->patches.'/2024/03/.gitkeep');
        $this->assertFileExists($this->patches.'/2024_03_05_000000_a.php');
    }

    public function testMissingPatchesDirectory(): void
    {
        $this->composer->shouldReceive('dumpAutoloads')->never();

        $this->assertSame(0, $this->runOrganize());
        $this->assertStringContainsString('No patches found.', $this->output->fetch());
    }

    public function testInvalidGroupingFails(): void
    {
        $this->setGrouping('monthly');
        $this->touchPatch('2024_03_05_000000_a.php');
        $this->composer->shouldReceive('dumpAutoloads')->never();

        $this->assertSame(1, $this->runOrganize());
        $this->assertStringContainsString('Supported values: none, year, month.', $this->output->fetch());
        $this->assertFileExists($this->patches.'/2024_03_05_000000_a.php');
    }
}
