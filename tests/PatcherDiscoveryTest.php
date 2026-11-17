<?php

namespace Dentro\Patcher\Tests;

use Dentro\Patcher\Patcher;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Database\Migrations\MigrationRepositoryInterface;
use Illuminate\Filesystem\Filesystem;
use Mockery as m;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class PatcherDiscoveryTest extends TestCase
{
    protected string $dir;

    protected Patcher $patcher;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir().'/patcher-'.uniqid();
        mkdir($this->dir);

        $this->patcher = new Patcher(
            m::mock(MigrationRepositoryInterface::class),
            m::mock(ConnectionResolverInterface::class),
            new Filesystem
        );
    }

    protected function tearDown(): void
    {
        (new Filesystem)->deleteDirectory($this->dir);
        m::close();
    }

    protected function touchPatch(string $relative): void
    {
        $path = $this->dir.'/'.$relative;
        (new Filesystem)->ensureDirectoryExists(dirname($path));
        file_put_contents($path, '<?php');
    }

    public function testReadsRootYearAndMonthFoldersSortedByName(): void
    {
        $this->touchPatch('2024_03_05_000000_c.php');
        $this->touchPatch('2023/2023_01_01_000000_a.php');
        $this->touchPatch('2023/06/2023_06_01_000000_b.php');

        $this->assertSame([
            '2023_01_01_000000_a' => $this->dir.'/2023/2023_01_01_000000_a.php',
            '2023_06_01_000000_b' => $this->dir.'/2023/06/2023_06_01_000000_b.php',
            '2024_03_05_000000_c' => $this->dir.'/2024_03_05_000000_c.php',
        ], $this->patcher->getMigrationFiles([$this->dir]));
    }

    public function testIgnoresFilesOutsideGroupingFolders(): void
    {
        foreach ([
            'support/2020_01_01_000000_x.php',
            'old/2020_01_01_000000_y.php',
            '2024/extra/2024_01_01_000000_z.php',
            '2024/13/2024_01_01_000000_w.php',
            '2024/00/2024_01_01_000000_v.php',
            '2024/03/x/2024_03_01_000000_u.php',
            '202/2020_01_01_000000_t.php',
            'nounderscore.php',
            '2024/notes.txt',
        ] as $file) {
            $this->touchPatch($file);
        }

        $this->assertSame([], $this->patcher->getMigrationFiles([$this->dir]));
    }

    public function testFilesAreFoundRegardlessOfWhetherFolderMatchesTheirDate(): void
    {
        $this->touchPatch('2023/2024_03_05_000000_misplaced.php');

        $this->assertArrayHasKey('2024_03_05_000000_misplaced', $this->patcher->getMigrationFiles([$this->dir]));
    }

    public function testDuplicateNamesAcrossFoldersThrow(): void
    {
        $this->touchPatch('2024_03_05_000000_dup.php');
        $this->touchPatch('2024/03/2024_03_05_000000_dup.php');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/Duplicate patch name \[2024_03_05_000000_dup\] found in: .+, .+/');

        $this->patcher->getMigrationFiles([$this->dir]);
    }

    public function testSamePathGivenTwiceIsNotADuplicate(): void
    {
        $this->touchPatch('2024/03/2024_03_05_000000_a.php');

        $this->assertSame(
            ['2024_03_05_000000_a' => $this->dir.'/2024/03/2024_03_05_000000_a.php'],
            $this->patcher->getMigrationFiles([$this->dir, $this->dir])
        );
    }

    public function testExplicitPhpPathIsPassedThrough(): void
    {
        $this->touchPatch('anywhere/2024_01_01_000000_one.php');
        $file = $this->dir.'/anywhere/2024_01_01_000000_one.php';

        $this->assertSame(['2024_01_01_000000_one' => $file], $this->patcher->getMigrationFiles([$file]));
    }

    public function testMissingDirectoryYieldsNoFiles(): void
    {
        $this->assertSame([], $this->patcher->getMigrationFiles([$this->dir.'/nope']));
    }
}
