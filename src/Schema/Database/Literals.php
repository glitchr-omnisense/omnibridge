<?php

namespace Omnibridge\Schema\Database;

use Omnibridge\Schema\Column;

/**
 * A column's default as an SQL literal, the way DBAL writes it for MySQL and
 * SQLite - so that a database made from a description reads back the default
 * the persistence's own tool would have written: an integer, a boolean (1 or
 * 0) or a number unquoted, the current moment as its keyword on a date or a
 * time, any other value quoted.
 *
 * @internal MySqlStatements' and SqliteStatements'
 */
final class Literals
{
    private const INTEGERS = ['integer', 'smallint', 'bigint'];
    private const FLOATS = ['float', 'smallfloat'];
    private const MOMENTS = [
        'CURRENT_TIMESTAMP' => ['datetime', 'datetime_immutable', 'datetimetz', 'datetimetz_immutable', 'utc_datetime', 'utc_datetime_immutable'],
        'CURRENT_DATE' => ['date', 'date_immutable'],
        'CURRENT_TIME' => ['time', 'time_immutable'],
    ];

    /**
     * @param Column $column a column of portable type (ColumnTypes::resolve())
     * @param bool   $backslashes whether a backslash is escaped in a quoted literal (MySQL)
     *
     * @return string|null the literal, null for no default
     */
    public static function default(Column $column, bool $backslashes = false): ?string
    {
        $default = $column->default;
        if (null === $default) {
            return null;
        }

        if (\in_array($column->type, self::INTEGERS, true) || \is_int($default) || \is_float($default)) {
            return \is_bool($default) ? (string) (int) $default : (string) $default;
        }
        if ('boolean' === $column->type) {
            return \is_bool($default) ? (string) (int) $default : (string) $default;
        }
        if (\in_array($column->type, self::FLOATS, true)) {
            return (string) (float) $default;
        }
        foreach (self::MOMENTS as $keyword => $types) {
            if ($keyword === $default && \in_array($column->type, $types, true)) {
                return $keyword;
            }
        }

        $text = \is_bool($default) ? (string) (int) $default : (string) $default;
        if ($backslashes) {
            $text = str_replace('\\', '\\\\', $text);
        }

        return "'".str_replace("'", "''", $text)."'";
    }
}
