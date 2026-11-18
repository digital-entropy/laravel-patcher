<?php

namespace Dentro\Patcher;

use InvalidArgumentException;

/**
 * Folder layout for patch files inside the patches directory.
 */
enum Grouping: string
{
    case None = 'none';
    case Year = 'year';
    case Month = 'month';

    /**
     * Matches the "YYYY_MM_DD_HHMMSS_" prefix of a patch file name.
     */
    private const DATE_PREFIX = '/^(\d{4})_(0[1-9]|1[0-2])_\d{2}_\d{6}_/';

    /**
     * Resolve the grouping from the "patcher.grouping" config value.
     *
     * @throws \InvalidArgumentException
     */
    public static function fromConfig(mixed $value): self
    {
        $grouping = is_string($value) ? self::tryFrom($value) : null;

        if ($grouping === null) {
            $given = is_scalar($value) ? (string) $value : var_export($value, true);
            $supported = implode(', ', array_map(fn (self $case) => $case->value, self::cases()));

            throw new InvalidArgumentException("Invalid patcher grouping [{$given}]. Supported values: {$supported}.");
        }

        return $grouping;
    }

    /**
     * Get the folder, relative to the patches directory, a patch belongs in.
     *
     * Returns null when the layout needs a date and the name has no valid date prefix.
     */
    public function directoryFor(string $patch): ?string
    {
        if ($this === self::None) {
            return '';
        }

        if (! preg_match(self::DATE_PREFIX, basename($patch), $matches)) {
            return null;
        }

        return $this === self::Year ? $matches[1] : $matches[1].'/'.$matches[2];
    }
}
