<?php

namespace Aporat\ServerInfo\Support;

/**
 * @internal
 */
final class Bytes
{
    private const array UNITS = ['B', 'KiB', 'MiB', 'GiB', 'TiB', 'PiB', 'EiB'];

    /**
     * Formats a byte count with binary (IEC) units, e.g. "1.5 GiB".
     */
    public static function format(int|float $bytes, int $precision = 1): string
    {
        $bytes = max(0, $bytes);
        $unit = 0;

        while ($bytes >= 1024 && $unit < count(self::UNITS) - 1) {
            $bytes /= 1024;
            $unit++;
        }

        return $unit === 0
            ? sprintf('%d B', (int) $bytes)
            : sprintf('%.'.$precision.'f %s', $bytes, self::UNITS[$unit]);
    }
}
