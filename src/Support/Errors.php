<?php

namespace Aporat\ServerInfo\Support;

use PDOException;
use Throwable;

/**
 * @internal
 */
final class Errors
{
    /**
     * Describes an exception without its message, which for connection
     * failures can contain hosts, usernames or DSNs. Only the exception's
     * short class name and its error code (e.g. a SQLSTATE) are kept.
     */
    public static function redacted(Throwable $e): string
    {
        $class = substr(strrchr('\\'.$e::class, '\\') ?: $e::class, 1);
        $code = $e instanceof PDOException && is_array($e->errorInfo) && is_scalar($e->errorInfo[0] ?? null) && $e->errorInfo[0] !== ''
            ? (string) $e->errorInfo[0]
            : (string) $e->getCode();

        return $code === '0' || $code === '' ? $class : sprintf('%s [%s]', $class, $code);
    }
}
