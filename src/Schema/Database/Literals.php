<?php

namespace Omnibridge\Schema\Database;

use Omnibridge\Schema\Column;

/**
 * A column's default as an SQL literal, the way DBAL writes it for MySQL and
 * SQLite - so that a database made from a description reads back the default
 * the persistence's own tool would have written: an integer, a boolean (1 or
 * 0) or a number unquoted, any other value quoted.
 *
 * No default reads the database's clock: CURRENT_TIMESTAMP, CURRENT_DATE or
 * CURRENT_TIME on a date or a time is refused. There is one time, the
 * application's, in UTC: a moment a row is created or changed at is written by
 * the application (#[Timestamp]), never by the database server.
 *
 * @internal MySqlStatements' and SqliteStatements'
 */
final class Literals
{
    private const INTEGERS = ['integer', 'smallint', 'bigint'];
    private const FLOATS = ['float', 'smallfloat'];
    private const DATABASE_CLOCK = ['CURRENT_TIMESTAMP', 'CURRENT_TIMESTAMP()', 'CURRENT_DATE', 'CURRENT_DATE()', 'CURRENT_TIME', 'CURRENT_TIME()', 'NOW()', 'UTC_TIMESTAMP()', 'LOCALTIMESTAMP'];
    private const MOMENT_TYPES = ['datetime', 'datetime_immutable', 'datetimetz', 'datetimetz_immutable', 'utc_datetime', 'utc_datetime_immutable', 'date', 'date_immutable', 'time', 'time_immutable'];

    /**
     * @param Column $column a column of portable type (ColumnTypes::resolve())
     * @param bool   $backslashes whether a backslash is escaped in a quoted literal (MySQL)
     *
     * @return string|null the literal, null for no default
     *
     * @throws \LogicException a date or a time defaulting to the database's clock
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
        if (\in_array($column->type, self::MOMENT_TYPES, true) && \is_string($default) && \in_array(strtoupper(trim($default)), self::DATABASE_CLOCK, true)) {
            throw new \LogicException(sprintf('Column "%s" defaults to the database\'s clock (%s): the application writes that moment, in UTC (#[Timestamp]).', $column->name, $default));
        }

        $text = \is_bool($default) ? (string) (int) $default : (string) $default;
        if ($backslashes) {
            $text = str_replace('\\', '\\\\', $text);
        }

        return "'".str_replace("'", "''", $text)."'";
    }
}
