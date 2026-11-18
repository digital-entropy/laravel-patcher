<?php

namespace Dentro\Patcher\Tests;

use Dentro\Patcher\Grouping;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class GroupingTest extends TestCase
{
    #[DataProvider('directories')]
    public function testDirectoryFor(Grouping $mode, string $patch, ?string $expected): void
    {
        $this->assertSame($expected, $mode->directoryFor($patch));
    }

    public static function directories(): array
    {
        return [
            [Grouping::None, '2024_03_05_101500_fix_totals', ''],
            [Grouping::Year, '2024_03_05_101500_fix_totals', '2024'],
            [Grouping::Month, '2024_03_05_101500_fix_totals', '2024/03'],
            [Grouping::Month, '2024_03_05_101500_fix_totals.php', '2024/03'],
            [Grouping::None, 'fix_totals', ''],
            [Grouping::Year, 'fix_totals', null],
            [Grouping::Month, '2024_13_05_101500_bad_month', null],
            [Grouping::Month, '24_03_05_101500_short_year', null],
        ];
    }

    public function testFromConfigAcceptsSupportedValues(): void
    {
        $this->assertSame(Grouping::Month, Grouping::fromConfig('month'));
        $this->assertSame(Grouping::None, Grouping::fromConfig('none'));
    }

    public function testFromConfigRejectsUnknownValue(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid patcher grouping [monthly]. Supported values: none, year, month.');

        Grouping::fromConfig('monthly');
    }
}
