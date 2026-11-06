<?php

namespace Omnibridge\Schema\Database;

use Omnibridge\Schema\Description;

/**
 * omnibridge:schema:create in plain PHP (vendor/bin/omnibridge-schema-create):
 * no framework, no ORM - a description read from a file, a PDO connection,
 * SchemaCreator.
 *
 *     vendor/bin/omnibridge-schema-create --schema=schema.php [--url=mysql://user:password@host:3306/app] [--database=other] [--dump-sql]
 *     vendor/bin/omnibridge-schema-create --schema=description.json --dsn='sqlite:var/app.db'
 *
 * --schema: a PHP file returning a Description, or an array with
 * `description` (a Description) and, if needed, `types` (ColumnTypes) and
 * `options` (MySQL's charset, collation, engine); or a JSON file of
 * Description::toJson(), with `types` (ColumnTypes::toArray()) beside its
 * entities and tables if needed.
 * The connection: --dsn (PDO's, with --user and the password in the
 * OMNIBRIDGE_DATABASE_PASSWORD environment variable), or --url
 * (scheme://user:password@host:port/database), or the DATABASE_URL
 * environment variable; --database names another database on the same server.
 * --dump-sql prints the statements, one a line, and runs nothing.
 */
final class Cli
{
    private const USAGE = "Usage: omnibridge-schema-create --schema=<schema.php|description.json> [--dsn=<PDO DSN> [--user=<user>] | --url=<scheme://user:password@host:port/database>] [--database=<name>] [--dump-sql]\n"
        ."Creates the tables of the description in an empty database (no ORM). Without --dsn nor --url: the DATABASE_URL environment variable; the password of --dsn in OMNIBRIDGE_DATABASE_PASSWORD.\n";

    /**
     * @param list<string> $arguments the command line's arguments, the script's name left out
     * @param resource     $output
     * @param resource     $errors
     *
     * @return int the exit status: 0 done, 1 refused or failed, 2 a wrong command line
     */
    public static function run(array $arguments, $output = \STDOUT, $errors = \STDERR): int
    {
        $options = ['schema' => null, 'dsn' => null, 'user' => null, 'url' => null, 'database' => null, 'dump-sql' => false];
        foreach ($arguments as $argument) {
            if ('--help' === $argument || '-h' === $argument) {
                fwrite($output, self::USAGE);

                return 0;
            }
            if ('--dump-sql' === $argument) {
                $options['dump-sql'] = true;
                continue;
            }
            if (!preg_match('/^--(schema|dsn|user|url|database)=(.*)$/s', $argument, $match)) {
                fwrite($errors, 'Unknown argument: '.$argument."\n".self::USAGE);

                return 2;
            }
            $options[$match[1]] = $match[2];
        }
        if (null === $options['schema']) {
            fwrite($errors, "--schema is required.\n".self::USAGE);

            return 2;
        }

        try {
            [$description, $types, $creation] = self::schema($options['schema']);
            $pdo = self::connect($options);
            $creator = new SchemaCreator($pdo, $types, $creation);
            if ($options['dump-sql']) {
                foreach ($creator->statements($description) as $statement) {
                    fwrite($output, $statement.";\n");
                }

                return 0;
            }
            $statements = $creator->create($description);
            fwrite($output, \sprintf("%d tables created (%d statements).\n", \count($description->tables), \count($statements)));

            return 0;
        } catch (\Throwable $exception) {
            fwrite($errors, $exception->getMessage()."\n");

            return 1;
        }
    }

    /**
     * The description, its types and MySQL's options, read from a PHP or JSON file.
     *
     * @return array{Description, ColumnTypes, array<string, mixed>}
     */
    public static function schema(string $file): array
    {
        if (!is_file($file)) {
            throw new \InvalidArgumentException(\sprintf('No schema file "%s".', $file));
        }
        if (str_ends_with(strtolower($file), '.json')) {
            $data = json_decode((string) file_get_contents($file), true, 512, \JSON_THROW_ON_ERROR);

            return [Description::fromArray($data), ColumnTypes::fromArray($data['types'] ?? []), $data['options'] ?? []];
        }

        $schema = (static fn (string $file) => require $file)($file);
        if ($schema instanceof Description) {
            return [$schema, new ColumnTypes(), []];
        }
        if (\is_array($schema) && ($schema['description'] ?? null) instanceof Description) {
            $types = $schema['types'] ?? new ColumnTypes();

            return [$schema['description'], $types instanceof ColumnTypes ? $types : ColumnTypes::fromArray((array) $types), (array) ($schema['options'] ?? [])];
        }

        throw new \UnexpectedValueException(\sprintf('The schema file "%s" returns neither a Description nor an array with "description".', $file));
    }

    /** @param array<string, mixed> $options --dsn, --user, --url, --database */
    public static function connect(array $options): \PDO
    {
        if (null !== $options['dsn']) {
            $dsn = (string) $options['dsn'];
            if (null !== $options['database']) {
                $dsn = preg_match('/dbname=[^;]*/', $dsn) ? (string) preg_replace('/dbname=[^;]*/', 'dbname='.$options['database'], $dsn) : $dsn.';dbname='.$options['database'];
            }
            $password = getenv('OMNIBRIDGE_DATABASE_PASSWORD');

            return new \PDO($dsn, $options['user'], false === $password ? null : $password, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
        }

        $url = $options['url'] ?? (getenv('DATABASE_URL') ?: null) ?? throw new \InvalidArgumentException('No connection: --dsn, --url or the DATABASE_URL environment variable.');
        [$dsn, $user, $password] = self::fromUrl((string) $url, $options['database']);

        return new \PDO($dsn, $user, $password, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
    }

    /**
     * A PDO DSN, user and password from a database URL (Doctrine's and Laravel's form): mysql://user:password@host:3306/app,
     * mariadb://..., sqlite:///absolute/path.db, sqlite:///:memory:.
     *
     * @return array{string, ?string, ?string}
     */
    public static function fromUrl(string $url, ?string $database = null): array
    {
        if (preg_match('~^sqlite3?:///(.*)$~', $url, $match)) {
            $path = explode('?', $match[1], 2)[0];

            return ['sqlite:'.(':memory:' === $path ? ':memory:' : '/'.ltrim($path, '/')), null, null];
        }

        $parts = parse_url($url);
        if (false === $parts || !isset($parts['scheme'], $parts['host'])) {
            throw new \InvalidArgumentException('The database URL could not be read (scheme://user:password@host:port/database).');
        }
        $scheme = strtolower($parts['scheme']);
        if (!\in_array($scheme, ['mysql', 'mysql2', 'pdo-mysql', 'pdo_mysql', 'mariadb'], true)) {
            throw new \DomainException(\sprintf('No schema writer for "%s" URLs (mysql, mariadb and sqlite are written).', $scheme));
        }
        parse_str($parts['query'] ?? '', $query);
        $dsn = 'mysql:host='.$parts['host'].(isset($parts['port']) ? ';port='.$parts['port'] : '')
            .';dbname='.($database ?? ltrim(rawurldecode($parts['path'] ?? ''), '/'))
            .';charset='.(\is_string($query['charset'] ?? null) ? $query['charset'] : 'utf8mb4');

        return [$dsn, isset($parts['user']) ? rawurldecode($parts['user']) : null, isset($parts['pass']) ? rawurldecode($parts['pass']) : null];
    }
}
