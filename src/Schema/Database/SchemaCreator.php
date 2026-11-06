<?php

namespace Omnibridge\Schema\Database;

use Omnibridge\Schema\Description;

/**
 * omnibridge:schema:create's work, the same on every host (a Symfony console
 * command, an artisan command, the plain-PHP bin/omnibridge-schema-create):
 * the tables of a description created in an empty database through PDO, with
 * the statements written for the connection's driver (MySqlStatements,
 * SqliteStatements) - no ORM, whatever persistence reads the tables then
 * (plan "omnibase agnostic", answer 11 and step F7a).
 *
 *     $creator = new SchemaCreator($pdo, $types);
 *     $creator->statements($description);   // what would run (--dump-sql)
 *     $creator->create($description);       // runs them; SchemaNotEmpty if a table of the description exists
 *
 * On SQLite the statements run in one transaction (all or nothing); MySQL
 * commits each one itself.
 */
final class SchemaCreator
{
    /** @param array<string, mixed> $options MySQL's charset (utf8mb4), collation (utf8mb4_unicode_ci; null: the database's, statements()) and engine (InnoDB) */
    public function __construct(
        private readonly \PDO $pdo,
        private readonly ColumnTypes $types = new ColumnTypes(),
        private readonly array $options = [],
    ) {
    }

    /** The statements for a PDO driver: mysql (MySQL, MariaDB), sqlite. @param array<string, mixed> $options */
    public static function writer(string $driver, ColumnTypes $types = new ColumnTypes(), array $options = []): Statements
    {
        return match ($driver) {
            'mysql' => new MySqlStatements($types, $options['charset'] ?? 'utf8mb4', \array_key_exists('collation', $options) ? $options['collation'] : 'utf8mb4_unicode_ci', $options['engine'] ?? 'InnoDB'),
            'sqlite' => new SqliteStatements($types),
            default => throw new \DomainException(\sprintf('No schema writer for PDO\'s "%s" driver (mysql and sqlite are written).', $driver)),
        };
    }

    /**
     * The statements create() runs, in order. On MySQL a collation given as null is the database's own default one
     * when the database's character set is the one asked (a table then reads back as the database's, as a table
     * created without either would), else the character set's default.
     *
     * @return list<string>
     */
    public function statements(Description $description): array
    {
        $options = $this->options;
        if ('mysql' === $this->driver() && \array_key_exists('collation', $options) && null === $options['collation']) {
            [$charset, $collation] = $this->pdo->query('SELECT @@character_set_database, @@collation_database')->fetch(\PDO::FETCH_NUM);
            if ($charset === ($options['charset'] ?? 'utf8mb4')) {
                $options['collation'] = $collation;
            }
        }

        return self::writer($this->driver(), $this->types, $options)->create($description);
    }

    /** @return list<string> the tables of the description the database already holds */
    public function existing(Description $description): array
    {
        $tables = match ($this->driver()) {
            'mysql' => $this->pdo->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()")->fetchAll(\PDO::FETCH_COLUMN),
            'sqlite' => $this->pdo->query("SELECT name FROM sqlite_master WHERE type = 'table'")->fetchAll(\PDO::FETCH_COLUMN),
            default => [],
        };

        return array_values(array_intersect(array_keys($description->tables), array_map('strval', $tables)));
    }

    /**
     * Creates the description's tables.
     *
     * @return list<string> the statements run
     *
     * @throws SchemaNotEmpty when a table of the description exists already (nothing is run)
     */
    public function create(Description $description): array
    {
        if ([] !== $existing = $this->existing($description)) {
            throw new SchemaNotEmpty($existing);
        }

        $statements = $this->statements($description);
        $transaction = 'sqlite' === $this->driver();
        if ($transaction) {
            $this->pdo->beginTransaction();
        }
        try {
            foreach ($statements as $statement) {
                $this->pdo->exec($statement);
            }
            if ($transaction) {
                $this->pdo->commit();
            }
        } catch (\Throwable $exception) {
            if ($transaction && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }

        return $statements;
    }

    private function driver(): string
    {
        return (string) $this->pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
    }
}
