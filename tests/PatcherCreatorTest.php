<?php

namespace Dentro\Patcher\Tests;

use Dentro\Patcher\PatcherCreator;
use Illuminate\Filesystem\Filesystem;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class PatcherCreatorTest extends TestCase
{
    protected string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir().'/patcher-'.uniqid();
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        (new Filesystem)->deleteDirectory($this->dir);
    }

    public function testNoneWritesToRoot(): void
    {
        $file = (new PatcherCreator(new Filesystem, null, 'none'))->create('fix_foo', $this->dir);

        $this->assertMatchesRegularExpression('#^'.preg_quote($this->dir, '#').'/\d{4}_\d{2}_\d{2}_\d{6}_fix_foo\.php$#', $file);
        $this->assertFileExists($file);
    }

    public function testMonthWritesToYearMonthFolderMatchingPrefix(): void
    {
        $file = (new PatcherCreator(new Filesystem, null, 'month'))->create('fix_foo', $this->dir);

        $this->assertMatchesRegularExpression('#^'.preg_quote($this->dir, '#').'/(\d{4})/(\d{2})/\1_\2_\d{2}_\d{6}_fix_foo\.php$#', $file);
        $this->assertFileExists($file);
    }

    public function testYearWritesToYearFolderMatchingPrefix(): void
    {
        $file = (new PatcherCreator(new Filesystem, null, 'year'))->create('fix_foo', $this->dir);

        $this->assertMatchesRegularExpression('#^'.preg_quote($this->dir, '#').'/(\d{4})/\1_\d{2}_\d{2}_\d{6}_fix_foo\.php$#', $file);
        $this->assertFileExists($file);
    }

    public function testInvalidModeThrowsOnCreate(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Supported values: none, year, month.');

        (new PatcherCreator(new Filesystem, null, 'monthly'))->create('fix_foo', $this->dir);
    }
}
